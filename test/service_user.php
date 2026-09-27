<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('users.php');
model('sessions.php');
model('attributes.php');
model('attribute_sets.php');
model('logs.php');
service('user.php');
import('libs/modules/hash.php');

// リクエスト情報を用意（cookie_set() は SERVER_NAME と SCRIPT_NAME を使う）
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';
$_SERVER['SERVER_NAME']     = 'localhost';
$_SERVER['SCRIPT_NAME']     = '/index.php';

// 既存データ削除
// users は admin などの前提データ（シナリオテストが使う）があるため、テスト用のユーザーだけを削除する
// sessions はテストの中で登録したものだけを扱うため、削除しない（トランザクションで元に戻る）
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（テスト用ユーザーは最小権限で作る）
$data_user = [
    'username'     => 'testuser1',
    'password'     => 'abcd1234',
    'authority_id' => 3,
    'enabled'      => 1,
    'name'         => 'テスト太郎',
    'email'        => 'testuser1@example.com',
];

// トランザクションを開始
db_transaction();

// ユーザーの登録テスト
{
    // データ
    $password_salt = hash_salt();

    // 登録
    service_user_insert([
        'values' => [
            'username'       => $data_user['username'],
            'password'       => hash_crypt($data_user['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            'password_salt'  => $password_salt,
            'authority_id'   => $data_user['authority_id'],
            'enabled'        => $data_user['enabled'],
            'name'           => $data_user['name'],
            'email'          => $data_user['email'],
            'email_verified' => 1,
        ],
    ]);

    // 結果
    $users = model('select_users', [
        'select' => 'username, email',
        'where'  => 'username = ' . db_escape('testuser1'),
    ]);

    test_equals('insert user', count($users), 1);
    test_equals('insert user email', $users[0]['email'], 'testuser1@example.com');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert user log', count($logs), 1);
    test_equals('insert user log model', $logs[0]['model'], 'users');
    test_equals('insert user log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したユーザーのIDを取得
$users = model('select_users', [
    'select' => 'id',
    'where'  => 'username = ' . db_escape('testuser1'),
]);
$inserted_id = intval($users[0]['id']);

// パスワード認証テスト
{
    // 確認（auth/index.php と同じ方法でソルトを取得し、ハッシュで照合する）
    $users = model('select_users', [
        'select' => 'password_salt',
        'where'  => [
            'username = :username',
            [
                'username' => 'testuser1',
            ],
        ],
    ]);
    $password_salt = $users[0]['password_salt'];

    $users_valid = model('select_users', [
        'select' => 'id, enabled',
        'where'  => [
            'username = :username AND password = :password',
            [
                'username' => 'testuser1',
                'password' => hash_crypt('abcd1234', $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            ],
        ],
    ]);
    $users_invalid = model('select_users', [
        'select' => 'id, enabled',
        'where'  => [
            'username = :username AND password = :password',
            [
                'username' => 'testuser1',
                'password' => hash_crypt('abcd12345', $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            ],
        ],
    ]);

    // 結果
    test_equals('authenticate user', count($users_valid), 1);
    test_equals('authenticate user id', intval($users_valid[0]['id']), $inserted_id);
    test_equals('authenticate user enabled', intval($users_valid[0]['enabled']), 1);

    // 結果（パスワードが違えば認証できないこと）
    test_equals('authenticate user (invalid password)', count($users_invalid), 0);
}

// ユーザーの更新テスト
{
    // 更新
    service_user_update([
        'set'   => [
            'name' => 'テスト次郎',
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

    // 結果
    $users = model('select_users', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update user', $users[0]['name'], 'テスト次郎');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update user log', count($logs), 1);
    test_equals('update user log model', $logs[0]['model'], 'users');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_user_update([
        'set'   => [
            'name' => 'テスト三郎',
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
    $users = model('select_users', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update user (modified check)', $users[0]['name'], 'テスト三郎');
}

// 認証失敗回数の記録テスト
{
    // 更新（auth/index.php が認証失敗のたびに行う更新を、10回繰り返す）
    for ($i = 0; $i < 10; $i++) {
        $users = model('select_users', [
            'select' => 'failed',
            'where'  => 'id = ' . $inserted_id,
        ]);

        service_user_update([
            'set'   => [
                'failed'      => $users[0]['failed'] + 1,
                'failed_last' => localdate('Y-m-d H:i:s'),
            ],
            'where' => [
                'enabled = 1 AND username = :username',
                [
                    'username' => 'testuser1',
                ],
            ],
        ]);
    }

    // 結果（凍結の判定に使う失敗回数と日時が記録されること。判定そのものは auth/index.php にある）
    $users = model('select_users', [
        'select' => 'failed, failed_last',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('record user failed', intval($users[0]['failed']), 10);
    test_regexp('record user failed_last', $users[0]['failed_last'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 認証失敗回数のリセットテスト
{
    // 更新（auth/index.php が認証成功時に行う更新）
    service_user_update([
        'set'   => [
            'loggedin'    => localdate('Y-m-d H:i:s'),
            'failed'      => null,
            'failed_last' => null,
        ],
        'where' => [
            'enabled = 1 AND username = :username',
            [
                'username' => 'testuser1',
            ],
        ],
    ]);

    // 結果（凍結が解除されること）
    $users = model('select_users', [
        'select' => 'loggedin, failed, failed_last',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('reset user failed', $users[0]['failed'], null);
    test_equals('reset user failed_last', $users[0]['failed_last'], null);
    test_regexp('reset user loggedin', $users[0]['loggedin'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// ここから先はログイン処理が cookie_set() を呼ぶ。テストランナーはテストの前に画面へ出力しているため、
// setcookie() が「headers already sent」の警告を出す。この警告だけを無視する
set_error_handler('test_user_header_error');

// セッションの登録テスト
{
    // データ（ログイン時に新しいセッションIDを発行する）
    $session_id = rand_string();

    // 登録（ログイン状態を保持しない）
    service_user_duration(null, $session_id, $inserted_id, 0);

    // 結果
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($session_id),
    ]);

    test_equals('duration user session', count($sessions), 1);
    test_equals('duration user session user_id', intval($sessions[0]['user_id']), $inserted_id);
    test_equals('duration user session keep', intval($sessions[0]['keep']), 0);
    test_equals('duration user session agent', $sessions[0]['agent'], 'freo/2');

    // 結果（有効期限は login_expire の分だけ先になること）
    test_equals('duration user session expire', $sessions[0]['expire'], localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['login_expire']));
}

// セッションの更新テスト
{
    // データ（ログイン済みの状態でもう一度ログインし、ログイン状態を保持する）
    $old_session_id = $session_id;
    $session_id     = rand_string();

    // 更新
    service_user_duration($old_session_id, $session_id, $inserted_id, 1);

    // 結果（古いセッションは残らず、新しいセッションになること）
    $sessions_old = model('select_sessions', [
        'where' => 'id = ' . db_escape($old_session_id),
    ]);
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($session_id),
    ]);

    test_equals('duration user session (old)', count($sessions_old), 0);
    test_equals('duration user session (new)', count($sessions), 1);
    test_equals('duration user session (keep)', intval($sessions[0]['keep']), 1);

    // 結果（有効期限は cookie_expire の分だけ先になること）
    test_equals('duration user session (expire)', $sessions[0]['expire'], localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['cookie_expire']));
}

// 期限切れセッションの削除テスト
{
    // データ（期限切れのセッションを登録する）
    $expired_session_id = rand_string();

    model('insert_sessions', [
        'values' => [
            'id'      => $expired_session_id,
            'user_id' => $inserted_id,
            'agent'   => 'freo/2',
            'keep'    => 1,
            'expire'  => localdate('Y-m-d H:i:s', time() - 60),
        ],
    ]);

    // 更新（セッションの保持時に、期限切れのセッションを削除する）
    $old_session_id = $session_id;
    $session_id     = rand_string();

    service_user_duration($old_session_id, $session_id, $inserted_id, 1);

    // 結果
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($expired_session_id),
    ]);

    test_equals('duration user session (expired)', count($sessions), 0);
}

// 自動ログインテスト
{
    // 確認（ログイン状態を保持しているセッションから復元する）
    list($login_session, $login_user_id) = service_user_login($session_id);

    // 結果
    test_equals('login user session', $login_session, true);
    test_equals('login user id', intval($login_user_id), $inserted_id);

    // 結果（セッションIDが変わること）
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($session_id),
    ]);

    test_equals('login user session (old)', count($sessions), 0);

    // 結果（最終ログイン日時が更新されること）
    $users = model('select_users', [
        'select' => 'loggedin',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_regexp('login user loggedin', $users[0]['loggedin'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 更新後のセッションIDを取得
$sessions = model('select_sessions', [
    'select'   => 'id',
    'where'    => 'user_id = ' . $inserted_id,
    'order_by' => 'modified DESC',
    'limit'    => 1,
]);
$session_id = $sessions[0]['id'];

// 自動ログイン（保持しない場合）テスト
{
    // データ（ログイン状態を保持しないセッションにする）
    model('update_sessions', [
        'set'   => [
            'keep' => 0,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $session_id,
            ],
        ],
    ]);

    // 確認
    list($login_session, $login_user_id) = service_user_login($session_id);

    // 結果（ログイン状態は復元されないこと）
    test_equals('login user session (not keep)', $login_session, false);
    test_equals('login user id (not keep)', $login_user_id, null);

    // 結果（セッション自体は更新されること）
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($session_id),
    ]);

    test_equals('login user session (not keep old)', count($sessions), 0);
}

// 警告の処理を元に戻す（以降は cookie_set() を呼ばない）
restore_error_handler();

// 自動ログイン（期限切れ）テスト
{
    // データ（期限切れのセッション）
    $expired_session_id = rand_string();

    model('insert_sessions', [
        'values' => [
            'id'      => $expired_session_id,
            'user_id' => $inserted_id,
            'agent'   => 'freo/2',
            'keep'    => 1,
            'expire'  => localdate('Y-m-d H:i:s', time() - 60),
        ],
    ]);

    // 確認
    list($login_session, $login_user_id) = service_user_login($expired_session_id);

    // 結果（ログイン状態は復元されないこと）
    test_equals('login user session (expired)', $login_session, false);
    test_equals('login user id (expired)', $login_user_id, null);

    // 結果（セッションはそのまま残ること）
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($expired_session_id),
    ]);

    test_equals('login user session (expired record)', count($sessions), 1);
}

// ログアウトテスト
{
    // データ（ログイン状態を保持しているセッション）
    $logout_session_id = rand_string();

    model('insert_sessions', [
        'values' => [
            'id'      => $logout_session_id,
            'user_id' => $inserted_id,
            'agent'   => 'freo/2',
            'keep'    => 1,
            'expire'  => localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['cookie_expire']),
        ],
    ]);

    // ログアウト
    service_user_logout($logout_session_id, $inserted_id);

    // 結果（ログイン状態の保持だけが外れること）
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($logout_session_id),
    ]);

    test_equals('logout user session', count($sessions), 1);
    test_equals('logout user session keep', intval($sessions[0]['keep']), 0);
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record user log once', count($logs), 1);
}

// ユーザーの削除テスト
{
    // 削除
    service_user_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $users = model('select_users', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete user', count($users), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete user log', count($logs), 1);
    test_equals('delete user log model', $logs[0]['model'], 'users');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/user.php',
    ]);
}

/**
 * ヘッダー送信後の警告を無視
 *
 * ログイン処理は cookie_set() でクッキーを発行するが、テストの実行中は
 * すでに画面へ出力しているため setcookie() が警告を出す。テストでは避けられないので、
 * この警告だけを無視する（ほかの警告は false を返して通常どおり表示する）。
 *
 * @param int    $errno
 * @param string $errstr
 *
 * @return bool
 */
function test_user_header_error($errno, $errstr)
{
    if (strpos($errstr, 'Cannot modify header information') !== false) {
        return true;
    }

    return false;
}
