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
model('logs.php');
service('menu.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'menus;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面のメニュー登録フォームからの送信を想定）
$data_menu = [
    'enabled' => 1,
    'title'   => 'テスト1',
    'url'     => '/test1',
    'memo'    => '',
    'sort'    => 1,
];
$data_menus = [
    [
        'enabled' => 1,
        'title'   => 'メニュー1',
        'url'     => '/sort1',
        'sort'    => 1,
    ],
    [
        'enabled' => 1,
        'title'   => 'メニュー2',
        'url'     => '/sort2',
        'sort'    => 2,
    ],
    [
        'enabled' => 1,
        'title'   => 'メニュー3',
        'url'     => '/sort3',
        'sort'    => 3,
    ],
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_menu = $data_menu;

    // 登録
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);
    if (empty($warnings)) {
        service_menu_insert([
            'values' => $test_menu,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $menus = model('select_menus', [
        'select'   => 'enabled, title, url',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert menu', count($menus), 1);
    test_equals('insert menu title', $menus[0]['title'], 'テスト1');
    test_equals('insert menu url', $menus[0]['url'], '/test1');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert menu log', count($logs), 1);
    test_equals('insert menu log model', $logs[0]['model'], 'menus');
    test_equals('insert menu log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したメニューのIDを取得
$menus = model('select_menus', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($menus[0]['id']);

// 更新テスト
{
    // データ
    $test_menu = $data_menu;
    $test_menu['enabled'] = 0;
    $test_menu['title']   = 'テスト1改';

    // 更新
    $test_menu = model('normalize_menus', $test_menu);
    $warnings  = model('validate_menus', $test_menu);
    if (empty($warnings)) {
        service_menu_update([
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
        ], [
            'id' => $inserted_id,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $menus = model('select_menus', [
        'select' => 'enabled, title',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update menu enabled', intval($menus[0]['enabled']), 0);
    test_equals('update menu title', $menus[0]['title'], 'テスト1改');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update menu log', count($logs), 1);
    test_equals('update menu log model', $logs[0]['model'], 'menus');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_menu_update([
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
    $menus = model('select_menus', [
        'select' => 'title',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update menu (modified check)', $menus[0]['title'], 'テスト1改改');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record menu log once', count($logs), 1);
}

// 削除テスト
{
    // 削除
    service_menu_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $menus = model('select_menus', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete menu', count($menus), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete menu log', count($logs), 1);
    test_equals('delete menu log model', $logs[0]['model'], 'menus');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'menus;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録
    foreach ($data_menus as $menu) {
        $menu     = model('normalize_menus', $menu);
        $warnings = model('validate_menus', $menu);
        if (empty($warnings)) {
            service_menu_insert([
                'values' => $menu,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $menus = model('select_menus', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort menus (before)', array_column($menus, 'sort'), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($menus, 'id');

    service_menu_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $menus = model('select_menus', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort menus (after)', array_column($menus, 'sort'), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_menu_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $menus = model('select_menus', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort menus (invalid)', array_column($menus, 'sort'), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'menus;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/menu.php',
    ]);
}
