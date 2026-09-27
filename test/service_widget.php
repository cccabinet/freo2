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
model('logs.php');
service('widget.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
// widgets はマイグレーションで登録される前提データ（public_home など）があるため、テスト用のウィジェットだけを削除する
db_query('DELETE FROM ' . DATABASE_PREFIX . 'widgets WHERE code LIKE ' . db_escape('%test%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面のウィジェット登録フォームからの送信を想定）
$data_widget = [
    'code'  => 'test1',
    'title' => 'テスト1',
    'text'  => '<p>ウィジェットの内容です。</p>',
    'memo'  => '',
    'sort'  => 1,
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_widget = $data_widget;

    // 登録
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);
    if (empty($warnings)) {
        service_widget_insert([
            'values' => $test_widget,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $widgets = model('select_widgets', [
        'select' => 'code, title, text',
        'where'  => 'code = ' . db_escape('test1'),
    ]);

    test_equals('insert widget', count($widgets), 1);
    test_equals('insert widget title', $widgets[0]['title'], 'テスト1');
    test_equals('insert widget text', $widgets[0]['text'], '<p>ウィジェットの内容です。</p>');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert widget log', count($logs), 1);
    test_equals('insert widget log model', $logs[0]['model'], 'widgets');
    test_equals('insert widget log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したウィジェットのIDを取得
$widgets = model('select_widgets', [
    'select' => 'id',
    'where'  => 'code = ' . db_escape('test1'),
]);
$inserted_id = intval($widgets[0]['id']);

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める。id が無いとコードの重複チェックが自分自身を検出してしまう）
    $test_widget = $data_widget;
    $test_widget['id']    = $inserted_id;
    $test_widget['title'] = 'テスト1改';
    $test_widget['text']  = '<p>更新後の内容です。</p>';

    // 更新
    $test_widget = model('normalize_widgets', $test_widget);
    $warnings    = model('validate_widgets', $test_widget);
    if (empty($warnings)) {
        service_widget_update([
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
        ], [
            'id' => $inserted_id,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $widgets = model('select_widgets', [
        'select' => 'title, text',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update widget title', $widgets[0]['title'], 'テスト1改');
    test_equals('update widget text', $widgets[0]['text'], '<p>更新後の内容です。</p>');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update widget log', count($logs), 1);
    test_equals('update widget log model', $logs[0]['model'], 'widgets');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_widget_update([
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
    $widgets = model('select_widgets', [
        'select' => 'title',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update widget (modified check)', $widgets[0]['title'], 'テスト1改改');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record widget log once', count($logs), 1);
}

// 削除テスト
{
    // 削除
    service_widget_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $widgets = model('select_widgets', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete widget', count($widgets), 0);

    // 結果（前提データのウィジェットは消えないこと）
    $widgets = model('select_widgets', [
        'where' => 'code = ' . db_escape('public_home'),
    ]);

    test_equals('delete widget (initial data)', count($widgets), 1);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete widget log', count($logs), 1);
    test_equals('delete widget log model', $logs[0]['model'], 'widgets');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'widgets WHERE code LIKE ' . db_escape('%test%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/widget.php',
    ]);
}
