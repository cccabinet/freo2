<?php

// コマンドラインからの実行を考慮（app_test() が _mode で単体テストを判定できるよう、ルーティングより前に入れる）
if (php_sapi_name() === 'cli' && isset($_SERVER['argv'][1])) {
    $_REQUEST['_mode'] = $_SERVER['argv'][1];
}

// テスト中ならテスト用データベースに接続
if (app_test()) {
    db_connect([
        'test' => [
            'host'     => defined('APP_TEST_DATABASE_HOST') ? APP_TEST_DATABASE_HOST : DATABASE_HOST,
            'username' => defined('APP_TEST_DATABASE_USERNAME') ? APP_TEST_DATABASE_USERNAME : DATABASE_USERNAME,
            'password' => defined('APP_TEST_DATABASE_PASSWORD') ? APP_TEST_DATABASE_PASSWORD : DATABASE_PASSWORD,
            'name'     => defined('APP_TEST_DATABASE_NAME') ? APP_TEST_DATABASE_NAME : DATABASE_NAME . '-test',
        ],
    ]);
}
