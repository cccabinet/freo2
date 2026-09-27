<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('comments.php');
model('logs.php');
service('comment.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'comments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

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

// 正常登録テスト
{
    // データ
    $test_comment = $data_comment;

    // 登録
    $warnings = model('validate_comments', $test_comment);
    if (empty($warnings)) {
        service_comment_insert([
            'values' => $test_comment,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $comments = model('select_comments', [
        'select'   => 'name, message',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert comment', count($comments), 1);
    test_equals('insert comment message', $comments[0]['message'], 'コメントの内容です。');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert comment log', count($logs), 1);
    test_equals('insert comment log model', $logs[0]['model'], 'comments');
    test_equals('insert comment log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したコメントのIDを取得
$comments = model('select_comments', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($comments[0]['id']);

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
        service_comment_update([
            'set'   => $test_comment,
            'where' => [
                'id = :id',
                [
                    'id' => $inserted_id,
                ],
            ],
        ], [
            'id' => $inserted_id,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $comments = model('select_comments', [
        'select' => 'approved, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update comment approved', intval($comments[0]['approved']), 0);
    test_equals('update comment memo', $comments[0]['memo'], '確認しました。');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update comment log', count($logs), 1);
    test_equals('update comment log model', $logs[0]['model'], 'comments');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_comment_update([
        'set'   => [
            'memo' => '再確認しました。',
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
    $comments = model('select_comments', [
        'select' => 'memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update comment (modified check)', $comments[0]['memo'], '再確認しました。');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record comment log once', count($logs), 1);
}

// 削除テスト
{
    // 削除
    service_comment_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $comments = model('select_comments', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete comment', count($comments), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete comment log', count($logs), 1);
    test_equals('delete comment log model', $logs[0]['model'], 'comments');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'comments;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/comment.php',
    ]);
}
