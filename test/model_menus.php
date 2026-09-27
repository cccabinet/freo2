<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('menus.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'menus;');

// 正常データ（管理画面のメニュー登録フォームからの送信を想定）
$data_menu = [
    'id'      => '',
    'enabled' => 1,
    'title'   => 'テスト1',
    'url'     => '/test1',
    'memo'    => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_menu = model('default_menus');

    // 結果
    test_equals('default menu id', $default_menu['id'], null);
    test_equals('default menu enabled', $default_menu['enabled'], 1);
    test_equals('default menu title', $default_menu['title'], '');
    test_equals('default menu url', $default_menu['url'], '');
    test_equals('default menu memo', $default_menu['memo'], null);
    test_equals('default menu sort', $default_menu['sort'], 0);
    test_regexp('default menu created', $default_menu['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['sort'] = '１２';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);

    // 結果
    test_equals('normalize menu sort', $test_menu['sort'], '12');
}

// 正常登録テスト
{
    // データ
    $test_menu = $data_menu;

    // 登録
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate menu', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_menu_insert($test_menu);
    } else {
        debug($warnings);
    }

    // 結果
    $menus = model('select_menus', [
        'select' => 'enabled, title, url, memo, sort',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert menu', count($menus), 1);
    test_equals('insert menu enabled', intval($menus[0]['enabled']), 1);
    test_equals('insert menu title', $menus[0]['title'], 'テスト1');
    test_equals('insert menu url', $menus[0]['url'], '/test1');

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert menu memo', $menus[0]['memo'], null);

    // 結果（並び順は自動で採番されること）
    test_equals('insert menu sort', intval($menus[0]['sort']), 1);
}

// 並び順の正規化（自動採番）テスト
{
    // データ（並び順を持たない管理画面からの入力を想定）
    $test_menu = $data_menu;

    // 確認
    $test_menu = model('normalize_menus', $test_menu);

    // 結果（登録済みの最大値 1 に 1 を加えた値になること）
    test_equals('normalize menu sort auto', $test_menu['sort'], 2);
}

// 有効の書式テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['enabled'] = 'あ';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate boolean menu enabled', count($warnings), 1);
}

// タイトルの必須テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['title'] = '';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate required menu title', count($warnings), 1);
}

// タイトルの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_menu = $data_menu;
    $test_menu['title'] = str_repeat('あ', 20);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu title (boundary)', count($warnings), 0);
}

// タイトルの長さテスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['title'] = str_repeat('あ', 21);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu title', count($warnings), 1);
}

// URLの必須テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['url'] = '';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate required menu url', count($warnings), 1);
}

// URLの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。URLは書式を検証していない）
    $test_menu = $data_menu;
    $test_menu['url'] = str_repeat('a', 200);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu url (boundary)', count($warnings), 0);
}

// URLの長さテスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['url'] = str_repeat('a', 201);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu url', count($warnings), 1);
}

// メモの未入力テスト
{
    // データ（メモは任意項目のため未入力でも警告は出ない）
    $test_menu = $data_menu;
    $test_menu['memo'] = '';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate empty menu memo', count($warnings), 0);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_menu = $data_menu;
    $test_menu['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['sort'] = '';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate required menu sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['sort'] = 'あ';

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate numeric menu sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_menu = $data_menu;
    $test_menu['sort'] = str_repeat('1', 5);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['sort'] = str_repeat('1', 6);

    // 確認
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);

    // 結果
    test_equals('validate max_length menu sort', count($warnings), 1);
}

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める）
    $test_menu = $data_menu;
    $test_menu['id']      = $inserted_id;
    $test_menu['enabled'] = 0;
    $test_menu['title']   = 'テスト1改';

    // 更新
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);
    if (empty($warnings)) {
        model('update_menus', [
            'set'   => [
                'enabled' => $test_menu['enabled'],
                'title'   => $test_menu['title'],
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
    $menus = model('select_menus', [
        'select' => 'enabled, title',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update menus enabled', intval($menus[0]['enabled']), 0);
    test_equals('update menus title', $menus[0]['title'], 'テスト1改');
}

// 削除テスト
{
    // 削除
    model('delete_menus', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $menus = model('select_menus', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete menus', count($menus), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $menus = db_select([
        'select' => 'title, deleted',
        'from'   => DATABASE_PREFIX . 'menus',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete menus (record)', count($menus), 1);
    test_not_equals('delete menus (deleted)', $menus[0]['deleted'], null);
    test_equals('delete menus (title)', $menus[0]['title'], 'テスト1改');
}

// 物理削除テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['title'] = 'テスト2';
    $test_menu['url']   = '/test2';

    // 登録
    $test_menu = model('normalize_menus', $test_menu);
    $deleted_id = test_menu_insert($test_menu);

    // 削除
    model('delete_menus', [
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
    $menus = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'menus',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete menus (physical)', count($menus), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'menus;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/menus.php',
    ]);
}

/**
 * メニューを登録してIDを取得
 *
 * 正規化した配列には id などカラム以外のキーが含まれるため、
 * 管理画面の menu_post.php と同じように values を組み立てる。
 *
 * @param array $menu
 *
 * @return int|null
 */
function test_menu_insert($menu)
{
    $resource = model('insert_menus', [
        'values' => [
            'enabled' => $menu['enabled'],
            'title'   => $menu['title'],
            'url'     => $menu['url'],
            'memo'    => $menu['memo'],
            'sort'    => $menu['sort'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $menus = model('select_menus', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($menus[0]['id']);
}
