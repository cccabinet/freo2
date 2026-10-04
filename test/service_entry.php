<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('entries.php');
model('fields.php');
model('field_sets.php');
model('categories.php');
model('category_sets.php');
model('attributes.php');
model('attribute_sets.php');
model('logs.php');
service('entry.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// パスワード認証時の表示を固定する（設定によって結果が変わらないようにする）
$setting_title = isset($GLOBALS['setting']['restricted_password_title']) ? $GLOBALS['setting']['restricted_password_title'] : null;
$setting_text  = isset($GLOBALS['setting']['restricted_password_text'])  ? $GLOBALS['setting']['restricted_password_text']  : null;

$GLOBALS['setting']['restricted_password_title'] = '要認証: ';
$GLOBALS['setting']['restricted_password_text']  = '<p>パスワード認証により公開されます。</p>';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'entries;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 公開範囲の確認用データ（登録順に取得されるため、この順序が取得結果の順序になる）
$data_entries = [
    [
        'code'      => 'pub_all',
        'public'    => 'all',
        'text'      => "1行目\n2行目",
        'text_type' => 'textarea',
        'pictures'  => "a.jpg\nb.jpg",
        'thumbnail' => 'thumb.jpg',
    ],
    [
        'code'   => 'pub_user',
        'public' => 'user',
    ],
    [
        'code'   => 'pub_attribute',
        'public' => 'attribute',
    ],
    [
        'code'   => 'pub_attribute_other',
        'public' => 'attribute',
    ],
    [
        'code'      => 'pub_password',
        'public'    => 'password',
        'password'  => 'abcd',
        'text'      => '<p>認証後の本文です。</p>',
        'pictures'  => "a.jpg\nb.jpg",
        'thumbnail' => 'thumb.jpg',
    ],
    [
        'code'   => 'pub_none',
        'public' => 'none',
    ],
    [
        'code'     => 'pub_unapproved',
        'approved' => 0,
    ],
    [
        'code'         => 'pub_future',
        'public_begin' => '2099-01-01 00:00:00',
    ],
    [
        'code'       => 'pub_past',
        'public_end' => '2000-01-01 00:00:00',
    ],
    [
        'code'         => 'pub_period',
        'public_begin' => '2000-01-01 00:00:00',
        'public_end'   => '2099-01-01 00:00:00',
    ],
    [
        'code'      => 'pub_text_none',
        'text'      => '表示されない本文',
        'text_type' => 'none',
    ],
    [
        'code'    => 'pub_page',
        'type_id' => 2,
    ],
];

// トランザクションを開始
db_transaction();

// 前提データを登録（エントリー・属性）
$entry_ids = [];
foreach ($data_entries as $data_entry) {
    model('insert_entries', [
        'values' => test_entry_values($data_entry),
    ]);

    $entries = model('select_entries', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $entry_ids[$data_entry['code']] = intval($entries[0]['id']);
}

$attribute_ids = [];
foreach (['テスト属性1', 'テスト属性2'] as $index => $name) {
    model('insert_attributes', [
        'values' => [
            'name'       => $name,
            'filterable' => 0,
            'sort'       => $index + 1,
        ],
    ]);

    $attributes = model('select_attributes', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $attribute_ids[] = intval($attributes[0]['id']);
}

model('insert_attribute_sets', [
    'values' => [
        'attribute_id' => $attribute_ids[0],
        'entry_id'     => $entry_ids['pub_attribute'],
    ],
]);
model('insert_attribute_sets', [
    'values' => [
        'attribute_id' => $attribute_ids[1],
        'entry_id'     => $entry_ids['pub_attribute_other'],
    ],
]);

// 公開エントリーの取得（ユーザー登録なし）テスト
{
    // 取得（$GLOBALS['authority'] を用意しない状態が「ユーザー登録なし」にあたる）
    $entries = service_entry_select_published('entry', [
        'order_by' => 'entries.id',
    ]);

    // 結果（「全体に公開」と「パスワード認証で公開」だけが取得されること）
    test_equals('select published entry', array_column($entries, 'code'), ['pub_all', 'pub_password', 'pub_period', 'pub_text_none']);
}

// 公開エントリーの取得（型の絞り込み）テスト
{
    // 取得
    $entries = service_entry_select_published('page', [
        'order_by' => 'entries.id',
    ]);

    // 結果（指定した型のエントリーだけが取得されること）
    test_equals('select published entry (type)', array_column($entries, 'code'), ['pub_page']);
}

// 公開エントリーの取得（条件の追加）テスト
{
    // 取得
    $entries = service_entry_select_published('entry', [
        'where' => [
            'entries.code = :code',
            [
                'code' => 'pub_all',
            ],
        ],
    ]);

    // 結果（指定した条件で絞り込めること）
    test_equals('select published entry (where)', count($entries), 1);
    test_equals('select published entry (where code)', $entries[0]['code'], 'pub_all');
}

// 公開エントリーの取得（条件を追加しても非公開は取得しない）テスト
{
    // 取得
    $entries = service_entry_select_published('entry', [
        'where' => [
            'entries.code = :code',
            [
                'code' => 'pub_none',
            ],
        ],
    ]);

    // 結果（条件を追加しても公開範囲の判定は外れないこと）
    test_equals('select published entry (where none)', count($entries), 0);
}

// パスワード認証エントリーの表示制限テスト
{
    // 取得（パスワードを入力していない状態）
    unset($_SESSION['entry_passwords']);

    $entries = service_entry_select_published('entry', [
        'where' => 'entries.code = ' . db_escape('pub_password'),
    ]);

    // 結果（タイトルに文言が付き、本文・画像・サムネイルが伏せられること）
    test_equals('select published entry (password)', count($entries), 1);
    test_equals('select published entry (password title)', $entries[0]['title'], '要認証: タイトル');
    test_equals('select published entry (password text)', $entries[0]['text'], '<p>パスワード認証により公開されます。</p>');
    test_equals('select published entry (password pictures)', $entries[0]['pictures'], null);
    test_equals('select published entry (password thumbnail)', $entries[0]['thumbnail'], null);
}

// パスワード認証エントリーの表示（認証後）テスト
{
    // 取得（パスワードを入力した状態）
    $_SESSION['entry_passwords'][$entry_ids['pub_password']] = true;

    $entries = service_entry_select_published('entry', [
        'where' => 'entries.code = ' . db_escape('pub_password'),
    ]);

    unset($_SESSION['entry_passwords']);

    // 結果（本来の内容が表示されること）
    test_equals('select published entry (authorized title)', $entries[0]['title'], 'タイトル');
    test_equals('select published entry (authorized text)', $entries[0]['text'], '<p>認証後の本文です。</p>');
    test_equals('select published entry (authorized pictures)', $entries[0]['pictures'], ['a.jpg', 'b.jpg']);
    test_equals('select published entry (authorized thumbnail)', $entries[0]['thumbnail'], 'thumb.jpg');
}

// 本文形式の変換テスト
{
    // 取得
    $entries = service_entry_select_published('entry', [
        'where'    => 'entries.code IN(' . db_escape('pub_all') . ', ' . db_escape('pub_period') . ', ' . db_escape('pub_text_none') . ')',
        'order_by' => 'entries.id',
    ]);

    // 結果（複数行入力はエスケープして段落と改行に変換されること）
    test_equals('select published entry (textarea)', $entries[0]['text'], "<p>1行目<br>\n2行目</p>");

    // 結果（HTML直接入力はそのまま出力されること）
    test_equals('select published entry (html)', $entries[1]['text'], '本文');

    // 結果（本文形式が「なし」なら本文を持たないこと）
    test_equals('select published entry (none)', $entries[2]['text'], null);
}

// 画像の変換テスト
{
    // 取得
    $entries = service_entry_select_published('entry', [
        'where' => 'entries.code = ' . db_escape('pub_all'),
    ]);

    // 結果（改行区切りの画像が配列になること）
    test_equals('select published entry (pictures)', $entries[0]['pictures'], ['a.jpg', 'b.jpg']);
}

// 公開エントリーの取得（ゲスト）テスト
{
    // データ（会員登録したユーザーとしてログインしている状態。属性は1つ目だけを持つ）
    $GLOBALS['authority']['power'] = 0;
    $GLOBALS['attributes']         = [$attribute_ids[0]];

    // 取得
    $entries = service_entry_select_published('entry', [
        'order_by' => 'entries.id',
    ]);

    // 結果（「登録ユーザーに公開」と、自分が持つ属性の「指定の属性に公開」が増えること）
    test_equals('select published entry (guest)', array_column($entries, 'code'), ['pub_all', 'pub_user', 'pub_attribute', 'pub_password', 'pub_period', 'pub_text_none']);
}

// 公開エントリーの取得（閲覧者以上）テスト
{
    // データ（管理画面に入れる権限でログインしている状態）
    $GLOBALS['authority']['power'] = 1;

    // 取得
    $entries = service_entry_select_published('entry', [
        'order_by' => 'entries.id',
    ]);

    // 結果（「非公開」以外がすべて取得されること。属性を持たないエントリーも取得できること）
    test_equals('select published entry (authority)', array_column($entries, 'code'), ['pub_all', 'pub_user', 'pub_attribute', 'pub_attribute_other', 'pub_password', 'pub_period', 'pub_text_none']);
}

// 公開エントリーの取得（権限の変化）テスト
{
    // データ（ログアウトした状態に戻す）
    unset($GLOBALS['authority']['power']);
    unset($GLOBALS['attributes']);

    // 取得
    $entries = service_entry_select_published('entry', [
        'order_by' => 'entries.id',
    ]);

    // 結果（最初に判定した権限を保持せず、そのときの権限で判定されること）
    test_equals('select published entry (authority changed)', array_column($entries, 'code'), ['pub_all', 'pub_password', 'pub_period', 'pub_text_none']);
}

// エントリーの登録テスト
{
    // データ
    $test_entry = test_entry_values([
        'code'  => 'test1',
        'title' => 'テスト1',
    ]);

    // 登録
    service_entry_insert([
        'values' => $test_entry,
    ]);

    // 結果
    $entries = model('select_entries', [
        'select' => 'code, title',
        'where'  => 'code = ' . db_escape('test1'),
    ]);

    test_equals('insert entry', count($entries), 1);
    test_equals('insert entry title', $entries[0]['title'], 'テスト1');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert entry log', count($logs), 1);
    test_equals('insert entry log model', $logs[0]['model'], 'entries');
    test_equals('insert entry log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したエントリーのIDを取得
$entries = model('select_entries', [
    'select' => 'id',
    'where'  => 'code = ' . db_escape('test1'),
]);
$inserted_id = intval($entries[0]['id']);

// エントリーの更新テスト
{
    // 更新
    service_entry_update([
        'set'   => [
            'title' => 'テスト1改',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id' => $inserted_id,
    ]);

    // 結果
    $entries = model('select_entries', [
        'select' => 'title',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update entry', $entries[0]['title'], 'テスト1改');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update entry log', count($logs), 1);
    test_equals('update entry log model', $logs[0]['model'], 'entries');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_entry_update([
        'set'   => [
            'title' => 'テスト1改改',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'     => $inserted_id,
        'update' => localdate('Y-m-d H:i:s'),
    ]);

    // 結果
    $entries = model('select_entries', [
        'select' => 'title',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update entry (modified check)', $entries[0]['title'], 'テスト1改改');
}

// 並び順の一括変更テスト
{
    // データ
    $sort_ids = [
        $entry_ids['pub_all'],
        $entry_ids['pub_user'],
        $entry_ids['pub_none'],
    ];

    // 並び順を更新
    service_entry_sort([
        $sort_ids[0] => 3,
        $sort_ids[1] => 2,
        $sort_ids[2] => 1,
    ]);

    // 結果
    $entries = model('select_entries', [
        'select'   => 'sort',
        'where'    => 'id IN(' . implode(',', $sort_ids) . ')',
        'order_by' => 'id',
    ]);

    test_equals('sort entries', array_column($entries, 'sort'), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_entry_sort([
        $sort_ids[0] => 'あ', // 並び順が数字でない
        'あ'         => 1,    // IDが不正
    ]);

    $entries = model('select_entries', [
        'select'   => 'sort',
        'where'    => 'id IN(' . implode(',', $sort_ids) . ')',
        'order_by' => 'id',
    ]);

    test_equals('sort entries (invalid)', array_column($entries, 'sort'), [3, 2, 1]);
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record entry log once', count($logs), 1);
}

// エントリーの削除テスト
{
    // 削除（ファイルの削除は実際のストレージを操作するため行わない）
    service_entry_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'file' => false,
    ]);

    // 結果
    $entries = model('select_entries', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete entry', count($entries), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete entry log', count($logs), 1);
    test_equals('delete entry log model', $logs[0]['model'], 'entries');
}

// トランザクションを終了
db_rollback();

// 権限と設定を元に戻す
unset($GLOBALS['authority']['power']);
unset($GLOBALS['attributes']);

if ($setting_title === null) {
    unset($GLOBALS['setting']['restricted_password_title']);
} else {
    $GLOBALS['setting']['restricted_password_title'] = $setting_title;
}
if ($setting_text === null) {
    unset($GLOBALS['setting']['restricted_password_text']);
} else {
    $GLOBALS['setting']['restricted_password_text'] = $setting_text;
}

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'entries;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/entry.php',
    ]);
}

/**
 * エントリーの登録内容を作成
 *
 * 指定しなかった項目は、公開中のエントリーの値で補う。
 *
 * @param array $values
 *
 * @return array
 */
function test_entry_values($values)
{
    return array_merge([
        'type_id'      => 1,
        'approved'     => 1,
        'public'       => 'all',
        'public_begin' => '',
        'public_end'   => '',
        'password'     => '',
        'datetime'     => '2026-01-01 10:00:00',
        'code'         => '',
        'title'        => 'タイトル',
        'text'         => '本文',
        'text_type'    => 'html',
        'pictures'     => '',
        'thumbnail'    => '',
        'comment'      => 'closed',
        'sort'         => 1,
    ], $values);
}
