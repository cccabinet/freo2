<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('themes.php');
model('logs.php');
service('theme.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'themes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面からテーマをインストールしたときの登録内容を想定）
$data_theme = [
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
    $test_theme = $data_theme;

    // 登録
    $warnings = model('validate_themes', $test_theme);
    if (empty($warnings)) {
        service_theme_insert([
            'values' => $test_theme,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $themes = model('select_themes', [
        'select'   => 'code, version, enabled',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert theme', count($themes), 1);
    test_equals('insert theme code', $themes[0]['code'], 'test1');
    test_equals('insert theme version', $themes[0]['version'], '1.0.0');
    test_equals('insert theme enabled', intval($themes[0]['enabled']), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert theme log', count($logs), 1);
    test_equals('insert theme log model', $logs[0]['model'], 'themes');
    test_equals('insert theme log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したテーマのIDを取得
$themes = model('select_themes', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($themes[0]['id']);

// 更新（有効化・設定の保存）テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['id']      = $inserted_id;
    $test_theme['enabled'] = 1;
    $test_theme['setting'] = '{"color":"blue"}';

    // 更新
    $warnings = model('validate_themes', $test_theme);
    if (empty($warnings)) {
        service_theme_update([
            'set'   => [
                'enabled' => $test_theme['enabled'],
                'setting' => $test_theme['setting'],
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
    $themes = model('select_themes', [
        'select' => 'enabled, setting',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update theme enabled', intval($themes[0]['enabled']), 1);
    test_equals('update theme setting', $themes[0]['setting'], '{"color":"blue"}');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update theme log', count($logs), 1);
    test_equals('update theme log model', $logs[0]['model'], 'themes');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_theme_update([
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
    $themes = model('select_themes', [
        'select' => 'version',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update theme (modified check)', $themes[0]['version'], '1.1.0');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record theme log once', count($logs), 1);
}

// 削除（アンインストール）テスト
{
    // 削除
    service_theme_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $themes = model('select_themes', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete theme', count($themes), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete theme log', count($logs), 1);
    test_equals('delete theme log model', $logs[0]['model'], 'themes');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'themes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/theme.php',
    ]);
}
