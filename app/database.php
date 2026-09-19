<?php

// コマンドラインからの実行を考慮
if (php_sapi_name() === 'cli' && isset($_SERVER['argv'][1])) {
    $_REQUEST['_mode'] = $_SERVER['argv'][1];
}

// テスト中か否かを判定
$test_mode = false;
if (isset($_REQUEST['_mode']) && preg_match('/^(test_index|test_exec)$/', $_REQUEST['_mode'])) {
    $test_mode = true;
} elseif (isset($_GET['test']) || isset($_SESSION['test'])) {
    $test_mode = true;
}

// テスト中ならテスト用データベースに接続
if ($test_mode) {
    db_connect([
        'test' => [
            'host'     => defined('APP_TEST_DATABASE_HOST') ? APP_TEST_DATABASE_HOST : DATABASE_HOST,
            'username' => defined('APP_TEST_DATABASE_USERNAME') ? APP_TEST_DATABASE_USERNAME : DATABASE_USERNAME,
            'password' => defined('APP_TEST_DATABASE_PASSWORD') ? APP_TEST_DATABASE_PASSWORD : DATABASE_PASSWORD,
            'name'     => defined('APP_TEST_DATABASE_NAME') ? APP_TEST_DATABASE_NAME : DATABASE_NAME . '-test',
        ],
    ]);
}
