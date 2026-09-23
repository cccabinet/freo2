<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
// insert_entries() は関連データ（フィールド・カテゴリー・属性）のひも付けも更新するため、そのモデルも必要になる
model('comments.php');
model('entries.php');
model('fields.php');
model('field_sets.php');
model('category_sets.php');
model('attribute_sets.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'comments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'entries;');

// 正常データ（公開側のコメント投稿を想定）
$data_comment = [
    'approved' => 1,
    'name'     => 'テスト太郎',
    'url'      => '',
    'message'  => 'コメントの内容です。',
    'memo'     => '',
];

// トランザクションを開始
db_transaction();

// 前提データを登録（コメントを付けるエントリー）
model('insert_entries', [
    'values' => [
        'type_id'   => 1,
        'approved'  => 1,
        'public'    => 'all',
        'datetime'  => '2026-01-01 10:00:00',
        'code'      => 'test_entry',
        'title'     => 'テストのエントリー',
        'text'      => '本文',
        'text_type' => 'html',
        'comment'   => 'opened',
        'sort'      => 1,
    ],
]);
$entries = model('select_entries', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$entry_id = intval($entries[0]['id']);

// 初期値テスト
{
    // 確認
    $default_comment = model('default_comments');

    // 結果
    test_equals('default comment id', $default_comment['id'], null);
    test_equals('default comment user_id', $default_comment['user_id'], null);
    test_equals('default comment entry_id', $default_comment['entry_id'], null);
    test_equals('default comment contact_id', $default_comment['contact_id'], null);
    test_equals('default comment approved', $default_comment['approved'], 1);
    test_equals('default comment name', $default_comment['name'], '');
    test_equals('default comment message', $default_comment['message'], '');
    test_equals('default comment memo', $default_comment['memo'], null);
    test_regexp('default comment created', $default_comment['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['entry_id'] = $entry_id;

    // 登録
    $warnings = model('validate_comments', $test_comment);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate comment', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_comments', [
            'values' => $test_comment,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $comments = model('select_comments', [
        'select'   => 'entry_id, approved, name, url, message, memo',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert comment', count($comments), 1);
    test_equals('insert comment entry_id', intval($comments[0]['entry_id']), $entry_id);
    test_equals('insert comment name', $comments[0]['name'], 'テスト太郎');
    test_equals('insert comment message', $comments[0]['message'], 'コメントの内容です。');

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert comment url', $comments[0]['url'], null);
    test_equals('insert comment memo', $comments[0]['memo'], null);
}

// 登録したコメントのIDを取得
$comments = model('select_comments', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($comments[0]['id']);

// 承認の書式テスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['approved'] = 'あ';

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate boolean comment approved', count($warnings), 1);
}

// お名前の必須テスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['name'] = '';

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate required comment name', count($warnings), 1);
}

// お名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_comment = $data_comment;
    $test_comment['name'] = str_repeat('あ', 50);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment name (boundary)', count($warnings), 0);
}

// お名前の長さテスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['name'] = str_repeat('あ', 51);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment name', count($warnings), 1);
}

// URLの未入力テスト
{
    // データ（URLは任意項目のため未入力でも警告は出ない）
    $test_comment = $data_comment;
    $test_comment['url'] = '';

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate empty comment url', count($warnings), 0);
}

// URLの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。URLは書式を検証していない）
    $test_comment = $data_comment;
    $test_comment['url'] = str_repeat('a', 200);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment url (boundary)', count($warnings), 0);
}

// URLの長さテスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['url'] = str_repeat('a', 201);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment url', count($warnings), 1);
}

// コメント内容の必須テスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['message'] = '';

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate required comment message', count($warnings), 1);
}

// コメント内容の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_comment = $data_comment;
    $test_comment['message'] = str_repeat('あ', 5000);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment message (boundary)', count($warnings), 0);
}

// コメント内容の長さテスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['message'] = str_repeat('あ', 5001);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment message', count($warnings), 1);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_comment = $data_comment;
    $test_comment['memo'] = str_repeat('あ', 5000);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['memo'] = str_repeat('あ', 5001);

    // 確認
    $warnings = model('validate_comments', $test_comment);

    // 結果
    test_equals('validate max_length comment memo', count($warnings), 1);
}

// 関連データの取得テスト
{
    // 取得
    $comments = model('select_comments', [
        'where' => 'comments.id = ' . intval($inserted_id),
    ], [
        'associate' => true,
    ]);

    // 結果（エントリーの情報が付くこと）
    test_equals('select associate comment', count($comments), 1);
    test_equals('select associate comment entry_code', $comments[0]['entry_code'], 'test_entry');
    test_equals('select associate comment entry_title', $comments[0]['entry_title'], 'テストのエントリー');
    test_equals('select associate comment type_code', $comments[0]['type_code'], 'entry');

    // 結果（ひも付けの無い項目もキーは返ること）
    test_array_haskey('select associate comment contact_subject', $comments[0], 'contact_subject');
    test_array_haskey('select associate comment user_username', $comments[0], 'user_username');
    test_equals('select associate comment user_username (value)', $comments[0]['user_username'], null);
}

// 絞り込みテスト
{
    // 確認
    $filter = model('filter_comments', [
        'approved' => '1',
        'name'     => 'テスト',
        'keyword'  => 'コメント',
    ], [
        'associate' => true,
    ]);

    // 結果（条件が AND で連結されること）
    test_contains('filter comments approved', $filter['where'], 'comments.approved = 1');
    test_contains('filter comments name', $filter['where'], 'comments.name LIKE ' . db_escape('%テスト%'));
    test_contains('filter comments keyword', $filter['where'], 'comments.message LIKE ' . db_escape('%コメント%'));
    test_contains('filter comments and', $filter['where'], ' AND ');

    // 結果（ページャー用のクエリ文字列が作られること）
    test_contains('filter comments pager', $filter['pager'], 'approved=1');
}

// 絞り込み（未入力）テスト
{
    // 確認
    $filter = model('filter_comments', [
        'approved' => '',
        'name'     => '',
    ], [
        'associate' => true,
    ]);

    // 結果（未入力の項目は条件に含めないこと）
    test_equals('filter comments (empty)', $filter['where'], '');
    test_equals('filter comments pager (empty)', $filter['pager'], '');
}

// 絞り込み（関連データなし）テスト
{
    // 確認
    $filter = model('filter_comments', [
        'approved' => '1',
    ]);

    // 結果
    test_equals('filter comments (not associate)', $filter['where'], null);
    test_equals('filter comments pager (not associate)', $filter['pager'], null);
}

// 更新テスト
{
    // データ（管理画面では承認とメモを変更する）
    $test_comment = [
        'approved' => 0,
        'memo'     => '確認しました。',
    ];

    // 更新
    $warnings = model('validate_comments', $test_comment);
    if (empty($warnings)) {
        model('update_comments', [
            'set'   => $test_comment,
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
    $comments = model('select_comments', [
        'select' => 'approved, memo',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update comments approved', intval($comments[0]['approved']), 0);
    test_equals('update comments memo', $comments[0]['memo'], '確認しました。');
}

// 削除テスト
{
    // 削除
    model('delete_comments', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $comments = model('select_comments', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete comments', count($comments), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $comments = db_select([
        'select' => 'message, deleted',
        'from'   => DATABASE_PREFIX . 'comments',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete comments (record)', count($comments), 1);
    test_not_equals('delete comments (deleted)', $comments[0]['deleted'], null);

    // 結果（コメント内容は書き換えないこと）
    test_equals('delete comments (message)', $comments[0]['message'], 'コメントの内容です。');
}

// 物理削除テスト
{
    // データ
    $test_comment = $data_comment;
    $test_comment['entry_id'] = $entry_id;
    $test_comment['message']  = '物理削除のコメントです。';

    // 登録
    model('insert_comments', [
        'values' => $test_comment,
    ]);

    $comments = model('select_comments', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $deleted_id = intval($comments[0]['id']);

    // 削除
    model('delete_comments', [
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
    $comments = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'comments',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete comments (physical)', count($comments), 0);
}

// 表示用データ作成テスト
{
    // 確認（コメントは変換せずにそのまま返す）
    $view_comment = model('view_comments', [
        'name'    => 'テスト太郎',
        'message' => "1行目\n2行目",
    ]);

    // 結果
    test_equals('view comments name', $view_comment['name'], 'テスト太郎');
    test_equals('view comments message', $view_comment['message'], "1行目\n2行目");
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'comments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'entries;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/comments.php',
    ]);
}
