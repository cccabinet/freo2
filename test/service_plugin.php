<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('plugins.php');
model('logs.php');
service('plugin.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除（テスト用データベースにインストールしたプラグインを消さないよう、plugins はテスト用のものだけを消す）
db_query('DELETE FROM ' . DATABASE_PREFIX . 'plugins WHERE code LIKE \'%test%\';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面からプラグインをインストールしたときの登録内容を想定）
$data_plugin = [
    'code'    => 'test1',
    'version' => '1.0.0',
    'enabled' => 0,
    'setting' => '',
];

// トランザクションを開始
db_transaction();

// 正常登録（インストール）テスト
{
    // データ
    $test_plugin = $data_plugin;

    // 登録
    $warnings = model('validate_plugins', $test_plugin);
    if (empty($warnings)) {
        service_plugin_insert([
            'values' => $test_plugin,
        ]);
    } else {
        debug($warnings);
    }

    // 結果（インストール済みのプラグインが他にあってもよいように、コードで絞る）
    $plugins = model('select_plugins', [
        'select' => 'code, version, enabled',
        'where'  => 'code = ' . db_escape('test1'),
    ]);

    test_equals('insert plugin', count($plugins), 1);
    test_equals('insert plugin code', $plugins[0]['code'], 'test1');
    test_equals('insert plugin version', $plugins[0]['version'], '1.0.0');
    test_equals('insert plugin enabled', intval($plugins[0]['enabled']), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert plugin log', count($logs), 1);
    test_equals('insert plugin log model', $logs[0]['model'], 'plugins');
    test_equals('insert plugin log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したプラグインのIDを取得
$plugins = model('select_plugins', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($plugins[0]['id']);

// 更新（有効化・設定の保存）テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['id']      = $inserted_id;
    $test_plugin['enabled'] = 1;
    $test_plugin['setting'] = '{"button_order":"注文する"}';

    // 更新
    $warnings = model('validate_plugins', $test_plugin);
    if (empty($warnings)) {
        service_plugin_update([
            'set'   => [
                'enabled' => $test_plugin['enabled'],
                'setting' => $test_plugin['setting'],
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
    $plugins = model('select_plugins', [
        'select' => 'enabled, setting',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update plugin enabled', intval($plugins[0]['enabled']), 1);
    test_equals('update plugin setting', $plugins[0]['setting'], '{"button_order":"注文する"}');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update plugin log', count($logs), 1);
    test_equals('update plugin log model', $logs[0]['model'], 'plugins');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない。アップグレードを想定）
    service_plugin_update([
        'set'   => [
            'version' => '1.1.0',
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
    $plugins = model('select_plugins', [
        'select' => 'version',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update plugin (modified check)', $plugins[0]['version'], '1.1.0');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record plugin log once', count($logs), 1);
}

// 削除（アンインストール）テスト
{
    // 削除
    service_plugin_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $plugins = model('select_plugins', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete plugin', count($plugins), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete plugin log', count($logs), 1);
    test_equals('delete plugin log model', $logs[0]['model'], 'plugins');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'plugins WHERE code LIKE \'%test%\';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/plugin.php',
    ]);
}
