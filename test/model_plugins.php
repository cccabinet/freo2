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

// 既存データ削除（テスト用データベースにインストールしたプラグインを消さないよう、テスト用のものだけを消す。論理削除でコードの先頭に DELETED が付いた行も含む）
db_query('DELETE FROM ' . DATABASE_PREFIX . 'plugins WHERE code LIKE \'%test%\';');

// 正常データ（管理画面からプラグインをインストールしたときの登録内容を想定）
$data_plugin = [
    'id'      => '',
    'code'    => 'test1',
    'version' => '1.0.0',
    'enabled' => 0,
    'setting' => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_plugin = model('default_plugins');

    // 結果
    test_equals('default plugin id', $default_plugin['id'], null);
    test_equals('default plugin code', $default_plugin['code'], '');
    test_equals('default plugin version', $default_plugin['version'], '');
    test_equals('default plugin enabled', $default_plugin['enabled'], 0);
    test_equals('default plugin setting', $default_plugin['setting'], null);
    test_regexp('default plugin created', $default_plugin['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_plugin = $data_plugin;

    // 登録
    $warnings = model('validate_plugins', $test_plugin);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate plugin', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_plugin_insert($test_plugin);
    } else {
        debug($warnings);
    }

    // 結果
    $plugins = model('select_plugins', [
        'select' => 'code, version, enabled, setting',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert plugin', count($plugins), 1);
    test_equals('insert plugin code', $plugins[0]['code'], 'test1');
    test_equals('insert plugin version', $plugins[0]['version'], '1.0.0');
    test_equals('insert plugin enabled', intval($plugins[0]['enabled']), 0);

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert plugin setting', $plugins[0]['setting'], null);
}

// コードの必須テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code'] = '';

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate required plugin code', count($warnings), 1);
}

// コードの書式テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code'] = 'あいうえお';

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate alpha_dash plugin code', count($warnings), 1);
}

// コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_plugin = $data_plugin;
    $test_plugin['code'] = str_repeat('a', 2);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate between plugin code (min boundary)', count($warnings), 0);
}

// コードの長さ（最小）テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code'] = str_repeat('a', 1);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate between plugin code (min)', count($warnings), 1);
}

// コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_plugin = $data_plugin;
    $test_plugin['code'] = str_repeat('a', 80);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate between plugin code (max boundary)', count($warnings), 0);
}

// コードの長さ（最大）テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code'] = str_repeat('a', 81);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate between plugin code (max)', count($warnings), 1);
}

// コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録）
    $test_plugin = $data_plugin;

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate duplicate plugin code', count($warnings), 1);
}

// コードの重複（自分自身は除外）テスト
{
    // データ（登録済みのプラグイン自身を編集）
    $test_plugin = $data_plugin;
    $test_plugin['id'] = $inserted_id;

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate duplicate plugin code (self)', count($warnings), 0);
}

// コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_plugin = $data_plugin;

    // 確認
    $warnings = model('validate_plugins', $test_plugin, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate plugin code (disabled)', count($warnings), 0);
}

// バージョンの必須テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code']    = 'test2';
    $test_plugin['version'] = '';

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate required plugin version', count($warnings), 1);
}

// バージョンの書式テスト
{
    // データ（数字もドットも含まない）
    $test_plugin = $data_plugin;
    $test_plugin['code']    = 'test2';
    $test_plugin['version'] = 'あいうえお';

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate regexp plugin version', count($warnings), 1);
}

// バージョンの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_plugin = $data_plugin;
    $test_plugin['code']    = 'test2';
    $test_plugin['version'] = str_repeat('1', 20);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate max_length plugin version (boundary)', count($warnings), 0);
}

// バージョンの長さテスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code']    = 'test2';
    $test_plugin['version'] = str_repeat('1', 21);

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate max_length plugin version', count($warnings), 1);
}

// 有効の書式テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code']    = 'test2';
    $test_plugin['enabled'] = 'あ';

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate boolean plugin enabled', count($warnings), 1);
}

// 更新（アップグレード）テスト
{
    // データ（プラグイン詳細画面からのアップグレードと有効化を想定）
    $test_plugin = $data_plugin;
    $test_plugin['id']      = $inserted_id;
    $test_plugin['version'] = '1.1.0';
    $test_plugin['enabled'] = 1;
    $test_plugin['setting'] = '{"button_order":"注文する"}';

    // 更新
    $warnings = model('validate_plugins', $test_plugin);
    if (empty($warnings)) {
        model('update_plugins', [
            'set'   => [
                'version' => $test_plugin['version'],
                'enabled' => $test_plugin['enabled'],
                'setting' => $test_plugin['setting'],
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
    $plugins = model('select_plugins', [
        'select' => 'version, enabled, setting',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update plugins version', $plugins[0]['version'], '1.1.0');
    test_equals('update plugins enabled', intval($plugins[0]['enabled']), 1);
    test_equals('update plugins setting', $plugins[0]['setting'], '{"button_order":"注文する"}');
}

// 削除（アンインストール）テスト
{
    // 削除
    model('delete_plugins', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $plugins = model('select_plugins', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete plugins', count($plugins), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $plugins = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'plugins',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete plugins (record)', count($plugins), 1);
    test_not_equals('delete plugins (deleted)', $plugins[0]['deleted'], null);
    test_regexp('delete plugins (code)', $plugins[0]['code'], '^DELETED \d{14} test1$');
}

// 再インストールテスト
{
    // データ（アンインストールしたプラグインと同じコード。削除時にコードが書き換わるため、入れ直せること）
    $test_plugin = $data_plugin;

    // 確認
    $warnings = model('validate_plugins', $test_plugin);

    // 結果
    test_equals('validate duplicate plugin code (deleted)', count($warnings), 0);
}

// 物理削除テスト
{
    // データ
    $test_plugin = $data_plugin;
    $test_plugin['code'] = 'test3';

    // 登録
    $deleted_id = test_plugin_insert($test_plugin);

    // 削除
    model('delete_plugins', [
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
    $plugins = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'plugins',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete plugins (physical)', count($plugins), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'plugins WHERE code LIKE \'%test%\';');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/plugins.php',
    ]);
}

/**
 * プラグインを登録してIDを取得
 *
 * @param array $plugin
 *
 * @return int|null
 */
function test_plugin_insert($plugin)
{
    $resource = model('insert_plugins', [
        'values' => [
            'code'    => $plugin['code'],
            'version' => $plugin['version'],
            'enabled' => $plugin['enabled'],
            'setting' => $plugin['setting'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $plugins = model('select_plugins', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($plugins[0]['id']);
}
