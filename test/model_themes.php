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

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'themes;');

// 正常データ（管理画面からテーマをインストールしたときの登録内容を想定）
$data_theme = [
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
    $default_theme = model('default_themes');

    // 結果
    test_equals('default theme id', $default_theme['id'], null);
    test_equals('default theme code', $default_theme['code'], '');
    test_equals('default theme version', $default_theme['version'], '');
    test_equals('default theme enabled', $default_theme['enabled'], 0);
    test_equals('default theme setting', $default_theme['setting'], null);
    test_regexp('default theme created', $default_theme['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_theme = $data_theme;

    // 登録
    $warnings = model('validate_themes', $test_theme);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate theme', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_theme_insert($test_theme);
    } else {
        debug($warnings);
    }

    // 結果
    $themes = model('select_themes', [
        'select' => 'code, version, enabled, setting',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert theme', count($themes), 1);
    test_equals('insert theme code', $themes[0]['code'], 'test1');
    test_equals('insert theme version', $themes[0]['version'], '1.0.0');
    test_equals('insert theme enabled', intval($themes[0]['enabled']), 0);

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert theme setting', $themes[0]['setting'], null);
}

// コードの必須テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code'] = '';

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate required theme code', count($warnings), 1);
}

// コードの書式テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code'] = 'あいうえお';

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate alpha_dash theme code', count($warnings), 1);
}

// コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_theme = $data_theme;
    $test_theme['code'] = str_repeat('a', 2);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate between theme code (min boundary)', count($warnings), 0);
}

// コードの長さ（最小）テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code'] = str_repeat('a', 1);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate between theme code (min)', count($warnings), 1);
}

// コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_theme = $data_theme;
    $test_theme['code'] = str_repeat('a', 80);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate between theme code (max boundary)', count($warnings), 0);
}

// コードの長さ（最大）テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code'] = str_repeat('a', 81);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate between theme code (max)', count($warnings), 1);
}

// コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録）
    $test_theme = $data_theme;

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate duplicate theme code', count($warnings), 1);
}

// コードの重複（自分自身は除外）テスト
{
    // データ（登録済みのテーマ自身を編集）
    $test_theme = $data_theme;
    $test_theme['id'] = $inserted_id;

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate duplicate theme code (self)', count($warnings), 0);
}

// コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_theme = $data_theme;

    // 確認
    $warnings = model('validate_themes', $test_theme, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate theme code (disabled)', count($warnings), 0);
}

// バージョンの必須テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code']    = 'test2';
    $test_theme['version'] = '';

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate required theme version', count($warnings), 1);
}

// バージョンの書式テスト
{
    // データ（数字もドットも含まない）
    $test_theme = $data_theme;
    $test_theme['code']    = 'test2';
    $test_theme['version'] = 'あいうえお';

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate regexp theme version', count($warnings), 1);
}

// バージョンの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_theme = $data_theme;
    $test_theme['code']    = 'test2';
    $test_theme['version'] = str_repeat('1', 20);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate max_length theme version (boundary)', count($warnings), 0);
}

// バージョンの長さテスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code']    = 'test2';
    $test_theme['version'] = str_repeat('1', 21);

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate max_length theme version', count($warnings), 1);
}

// 有効の書式テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code']    = 'test2';
    $test_theme['enabled'] = 'あ';

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate boolean theme enabled', count($warnings), 1);
}

// 更新テスト
{
    // データ（管理画面では有効・無効の切り替えと設定の保存を行う）
    $test_theme = $data_theme;
    $test_theme['id']      = $inserted_id;
    $test_theme['enabled'] = 1;
    $test_theme['setting'] = '{"color":"blue"}';

    // 更新
    $warnings = model('validate_themes', $test_theme);
    if (empty($warnings)) {
        model('update_themes', [
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
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $themes = model('select_themes', [
        'select' => 'enabled, setting',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update themes enabled', intval($themes[0]['enabled']), 1);
    test_equals('update themes setting', $themes[0]['setting'], '{"color":"blue"}');
}

// 削除テスト
{
    // 削除
    model('delete_themes', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $themes = model('select_themes', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete themes', count($themes), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $themes = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'themes',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete themes (record)', count($themes), 1);
    test_not_equals('delete themes (deleted)', $themes[0]['deleted'], null);
    test_regexp('delete themes (code)', $themes[0]['code'], '^DELETED \d{14} test1$');
}

// 再インストールテスト
{
    // データ（削除したテーマと同じコード。削除時にコードが書き換わるため、入れ直せること）
    $test_theme = $data_theme;

    // 確認
    $warnings = model('validate_themes', $test_theme);

    // 結果
    test_equals('validate duplicate theme code (deleted)', count($warnings), 0);
}

// 物理削除テスト
{
    // データ
    $test_theme = $data_theme;
    $test_theme['code'] = 'test3';

    // 登録
    $deleted_id = test_theme_insert($test_theme);

    // 削除
    model('delete_themes', [
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
    $themes = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'themes',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete themes (physical)', count($themes), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'themes;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/themes.php',
    ]);
}

/**
 * テーマを登録してIDを取得
 *
 * @param array $theme
 *
 * @return int|null
 */
function test_theme_insert($theme)
{
    $resource = model('insert_themes', [
        'values' => [
            'code'    => $theme['code'],
            'version' => $theme['version'],
            'enabled' => $theme['enabled'],
            'setting' => $theme['setting'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $themes = model('select_themes', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($themes[0]['id']);
}
