<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('settings.php');
model('logs.php');
service('setting.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
// settings はマイグレーションで登録される前提データ（85件）で、項目を増やすのもマイグレーションの役目なので削除しない。
// テストでの変更は、末尾の db_rollback() で元に戻る
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面の「設定」からの保存を想定）
$data_setting = [
    'admin_title' => 'テスト管理ページ',
    'description' => 'テストのサイト概要。',
];

// トランザクションを開始
db_transaction();

// 保存前の状態を取得
$settings = model('select_settings', [
    'select' => 'COUNT(*) AS count',
]);
$setting_count = intval($settings[0]['count']);

$settings = model('select_settings', [
    'select' => 'value',
    'where'  => 'id = ' . db_escape('entry_text_type'),
]);
$setting_other = $settings[0]['value'];

// 設定の保存テスト
{
    // 保存
    service_setting_save($data_setting);

    // 結果（指定した項目がすべて更新されること）
    $settings = model('select_settings', [
        'select'   => 'id, value',
        'where'    => 'id IN(' . db_escape('admin_title') . ', ' . db_escape('description') . ')',
        'order_by' => 'id',
    ]);

    test_equals('save setting', count($settings), 2);
    test_equals('save setting admin_title', $settings[0]['value'], 'テスト管理ページ');
    test_equals('save setting description', $settings[1]['value'], 'テストのサイト概要。');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('save setting log', count($logs), 1);
    test_equals('save setting log model', $logs[0]['model'], 'settings');
    test_equals('save setting log ip', $logs[0]['ip'], '127.0.0.1');
}

// 指定しなかった項目テスト
{
    // 結果（渡さなかった項目は変わらないこと）
    $settings = model('select_settings', [
        'select' => 'value',
        'where'  => 'id = ' . db_escape('entry_text_type'),
    ]);

    test_equals('save setting (other)', $settings[0]['value'], $setting_other);
}

// 空の値の保存テスト
{
    // 保存（入力欄を空にして送信した場合）
    service_setting_save([
        'admin_title' => '',
    ]);

    // 結果（空文字は NULL として保存されること）
    $settings = model('select_settings', [
        'select' => 'value',
        'where'  => 'id = ' . db_escape('admin_title'),
    ]);

    test_equals('save setting (empty)', $settings[0]['value'], null);
}

// 存在しない項目テスト
{
    // 保存（設定の項目はマイグレーションで追加するため、サービスは登録を行わない）
    service_setting_save([
        'test_unknown' => 'テストの値',
    ]);

    // 結果（項目が増えないこと）
    $settings = model('select_settings', [
        'select' => 'COUNT(*) AS count',
    ]);

    test_equals('save setting (unknown count)', intval($settings[0]['count']), $setting_count);

    // 結果（登録もされないこと）
    $settings = model('select_settings', [
        'where' => 'id = ' . db_escape('test_unknown'),
    ]);

    test_equals('save setting (unknown)', count($settings), 0);
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、保存を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record setting log once', count($logs), 1);
}

// トランザクションを終了
db_rollback();

// 設定が元に戻ることのテスト
{
    // 結果（テストで変更した設定が、ロールバックで元に戻ること）
    $settings = model('select_settings', [
        'select' => 'value',
        'where'  => 'id = ' . db_escape('admin_title'),
    ]);

    test_not_equals('rollback setting', $settings[0]['value'], null);
}

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/setting.php',
    ]);
}
