<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('widgets.php');

// 既存データ削除
// widgets はマイグレーションで登録される前提データ（public_home など）があるため、テスト用のウィジェットだけを削除する
db_query('DELETE FROM ' . DATABASE_PREFIX . 'widgets WHERE code LIKE ' . db_escape('%test%') . ';');

// 正常データ（管理画面のウィジェット登録フォームからの送信を想定）
$data_widget = [
    'id'    => '',
    'code'  => 'test1',
    'title' => 'テスト1',
    'text'  => '<p>ウィジェットの内容です。</p>',
    'memo'  => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_widget = model('default_widgets');

    // 結果
    test_equals('default widget id', $default_widget['id'], null);
    test_equals('default widget code', $default_widget['code'], '');
    test_equals('default widget title', $default_widget['title'], '');
    test_equals('default widget text', $default_widget['text'], null);
    test_equals('default widget memo', $default_widget['memo'], null);
    test_equals('default widget sort', $default_widget['sort'], 0);
    test_regexp('default widget created', $default_widget['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['sort'] = '１２';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);

    // 結果
    test_equals('normalize widget sort', $test_widget['sort'], '12');
}

// 正常登録テスト
{
    // データ
    $test_widget = $data_widget;

    // 登録
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate widget', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_widget_insert($test_widget);
    } else {
        debug($warnings);
    }

    // 結果
    $widgets = model('select_widgets', [
        'select' => 'code, title, text, memo',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert widget', count($widgets), 1);
    test_equals('insert widget code', $widgets[0]['code'], 'test1');
    test_equals('insert widget title', $widgets[0]['title'], 'テスト1');
    test_equals('insert widget text', $widgets[0]['text'], '<p>ウィジェットの内容です。</p>');

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert widget memo', $widgets[0]['memo'], null);
}

// コードの必須テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = '';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate required widget code', count($warnings), 1);
}

// コードの書式テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'あいうえお';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate alpha_dash widget code', count($warnings), 1);
}

// コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = str_repeat('a', 2);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate between widget code (min boundary)', count($warnings), 0);
}

// コードの長さ（最小）テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = str_repeat('a', 1);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate between widget code (min)', count($warnings), 1);
}

// コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = str_repeat('a', 80);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate between widget code (max boundary)', count($warnings), 0);
}

// コードの長さ（最大）テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = str_repeat('a', 81);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate between widget code (max)', count($warnings), 1);
}

// コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録）
    $test_widget = $data_widget;

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate duplicate widget code', count($warnings), 1);
}

// コードの重複（自分自身は除外）テスト
{
    // データ（登録済みのウィジェット自身を編集）
    $test_widget = $data_widget;
    $test_widget['id'] = $inserted_id;

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate duplicate widget code (self)', count($warnings), 0);
}

// コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_widget = $data_widget;

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate widget code (disabled)', count($warnings), 0);
}

// タイトルの必須テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code']  = 'test2';
    $test_widget['title'] = '';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate required widget title', count($warnings), 1);
}

// タイトルの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_widget = $data_widget;
    $test_widget['code']  = 'test2';
    $test_widget['title'] = str_repeat('あ', 20);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget title (boundary)', count($warnings), 0);
}

// タイトルの長さテスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code']  = 'test2';
    $test_widget['title'] = str_repeat('あ', 21);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget title', count($warnings), 1);
}

// テキストの未入力テスト
{
    // データ（テキストは任意項目のため未入力でも警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['text'] = '';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate empty widget text', count($warnings), 0);
}

// テキストの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['text'] = str_repeat('あ', 5000);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget text (boundary)', count($warnings), 0);
}

// テキストの長さテスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['text'] = str_repeat('あ', 5001);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget text', count($warnings), 1);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['sort'] = '';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate required widget sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['sort'] = 'あ';

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate numeric widget sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['sort'] = str_repeat('1', 5);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code'] = 'test2';
    $test_widget['sort'] = str_repeat('1', 6);

    // 確認
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);

    // 結果
    test_equals('validate max_length widget sort', count($warnings), 1);
}

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める）
    $test_widget = $data_widget;
    $test_widget['id']    = $inserted_id;
    $test_widget['title'] = 'テスト1改';
    $test_widget['text']  = '<p>更新後の内容です。</p>';

    // 更新
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);
    if (empty($warnings)) {
        model('update_widgets', [
            'set'   => [
                'title' => $test_widget['title'],
                'text'  => $test_widget['text'],
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $inserted_id,
                ],
            ],
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $widgets = model('select_widgets', [
        'select' => 'title, text',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update widgets title', $widgets[0]['title'], 'テスト1改');
    test_equals('update widgets text', $widgets[0]['text'], '<p>更新後の内容です。</p>');
}

// 削除テスト
{
    // 削除
    model('delete_widgets', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $widgets = model('select_widgets', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete widgets', count($widgets), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $widgets = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'widgets',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete widgets (record)', count($widgets), 1);
    test_not_equals('delete widgets (deleted)', $widgets[0]['deleted'], null);
    test_regexp('delete widgets (code)', $widgets[0]['code'], '^DELETED \d{14} test1$');
}

// 物理削除テスト
{
    // データ
    $test_widget = $data_widget;
    $test_widget['code']  = 'test3';
    $test_widget['title'] = 'テスト3';

    // 登録
    $test_widget = model('normalize_widgets', $test_widget);
    $deleted_id  = test_widget_insert($test_widget);

    // 削除
    model('delete_widgets', [
        'where' => [
            'id = :id',
            [
                'id' => $deleted_id,
            ],
        ],
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $widgets = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'widgets',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete widgets (physical)', count($widgets), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'widgets WHERE code LIKE ' . db_escape('%test%') . ';');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/widgets.php',
    ]);
}

/**
 * ウィジェットを登録してIDを取得
 *
 * 正規化した配列には id などカラム以外のキーが含まれるため、
 * 管理画面の widget_post.php と同じように values を組み立てる。
 *
 * @param array $widget
 *
 * @return int|null
 */
function test_widget_insert($widget)
{
    $resource = model('insert_widgets', [
        'values' => [
            'code'  => $widget['code'],
            'title' => $widget['title'],
            'text'  => $widget['text'],
            'memo'  => $widget['memo'],
            'sort'  => $widget['sort'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $widgets = model('select_widgets', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($widgets[0]['id']);
}
