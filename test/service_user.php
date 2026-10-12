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
            'twostep'        => 0,
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

    // 登録（ログイン状態を保持しない。2段階認証のコードの入力も省略しない）
    service_user_duration(null, $session_id, $inserted_id, 0, 0);

    // 結果
    $sessions = model('select_sessions', [
        'where' => 'id = ' . db_escape($session_id),
    ]);

    test_equals('duration user session', count($sessions), 1);
    test_equals('duration user session user_id', intval($sessions[0]['user_id']), $inserted_id);
    test_equals('duration user session keep', intval($sessions[0]['keep']), 0);
    test_equals('duration user session twostep', intval($sessions[0]['twostep']), 0);
    test_equals('duration user session agent', $sessions[0]['agent'], 'freo/2');

    // 結果（有効期限は login_expire の分だけ先になること）
    test_equals('duration user session expire', $sessions[0]['expire'], localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['login_expire']));
}

// セッションの更新テスト
{
    // データ（ログイン済みの状態でもう一度ログインし、ログイン状態を保持する。このブラウザでは2段階認証のコードの入力を省略する）
    $old_session_id = $session_id;
    $session_id     = rand_string();

    // 更新
    service_user_duration($old_session_id, $session_id, $inserted_id, 1, 1);

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
    test_equals('duration user session (twostep)', intval($sessions[0]['twostep']), 1);

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
            'twostep' => 0,
            'expire'  => localdate('Y-m-d H:i:s', time() - 60),
        ],
    ]);

    // 更新（セッションの保持時に、期限切れのセッションを削除する）
    $old_session_id = $session_id;
    $session_id     = rand_string();

    service_user_duration($old_session_id, $session_id, $inserted_id, 1, 0);

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
            'twostep' => 0,
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
            'twostep' => 0,
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

// 2段階認証の省略の解除テスト
{
    // データ（このユーザーのブラウザ2つと、ほかのユーザーのブラウザ1つで、コードの入力を省略している）
    $twostep_session_ids = [rand_string(), rand_string(), rand_string()];
    $twostep_user_ids    = [$inserted_id, $inserted_id, $inserted_id + 1000];

    foreach ($twostep_session_ids as $index => $twostep_session_id) {
        model('insert_sessions', [
            'values' => [
                'id'      => $twostep_session_id,
                'user_id' => $twostep_user_ids[$index],
                'agent'   => 'freo/2',
                'keep'    => 1,
                'twostep' => 1,
                'expire'  => localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['cookie_expire']),
            ],
        ]);
    }

    // 解除
    service_user_twostep_reset($inserted_id);

    // 結果（このユーザーのブラウザだけが解除され、ほかのユーザーのものは残ること）
    $sessions = model('select_sessions', [
        'select' => 'id, twostep',
        'where'  => 'id IN(' . implode(',', array_map('db_escape', $twostep_session_ids)) . ')',
    ]);
    $twosteps = array_column($sessions, 'twostep', 'id');

    test_equals('twostep reset user session', intval($twosteps[$twostep_session_ids[0]]), 0);
    test_equals('twostep reset user session (another browser)', intval($twosteps[$twostep_session_ids[1]]), 0);
    test_equals('twostep reset user session (another user)', intval($twosteps[$twostep_session_ids[2]]), 1);
}

// 2段階認証用メールアドレスの確認が必要かのテスト
{
    // 確認（2段階認証を使っていないユーザー。フォームの値は文字列）
    $required_off    = service_user_twostep_verify_required($inserted_id, '0', '');
    $required_enable = service_user_twostep_verify_required($inserted_id, '1', 'testuser1-twostep@example.com');

    // データ（2段階認証を使っている状態にする）
    model('update_users', [
        'set'   => [
            'twostep'       => 1,
            'twostep_email' => 'testuser1-twostep@example.com',
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

    // 確認（2段階認証を使っているユーザー）
    $required_same    = service_user_twostep_verify_required($inserted_id, '1', 'testuser1-twostep@example.com');
    $required_change  = service_user_twostep_verify_required($inserted_id, '1', 'testuser1-twostep2@example.com');
    $required_disable = service_user_twostep_verify_required($inserted_id, '0', 'testuser1-twostep@example.com');
    $required_unknown = service_user_twostep_verify_required($inserted_id + 1000, '1', 'testuser1-twostep@example.com');

    // 結果（有効にするときと、有効のままメールアドレスを変えるときだけ確認する）
    test_equals('twostep verify required (off)', $required_off, false);
    test_equals('twostep verify required (enable)', $required_enable, true);
    test_equals('twostep verify required (same email)', $required_same, false);
    test_equals('twostep verify required (change email)', $required_change, true);
    test_equals('twostep verify required (disable)', $required_disable, false);
    test_equals('twostep verify required (unknown user)', $required_unknown, true);
}

// コードの照合の失敗回数のテスト
{
    // データ（上限は設定で変わるので、3回に固定する）
    $code_failed_limit = $GLOBALS['config']['code_failed_limit'];
    $GLOBALS['config']['code_failed_limit'] = 3;

    model('update_users', [
        'set'   => [
            'token_code'   => '12345678',
            'twostep_code' => 'abcd1234',
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

    // 確認（上限の手前までは、回数だけが増えること）
    $invalidated_1 = service_user_code_failed($inserted_id, 'twostep');
    $invalidated_2 = service_user_code_failed($inserted_id, 'twostep');

    $users = model('select_users', [
        'select' => 'twostep_code, twostep_failed, token_code, token_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('code failed twostep (1)', $invalidated_1, false);
    test_equals('code failed twostep (2)', $invalidated_2, false);
    test_equals('code failed twostep count', intval($users[0]['twostep_failed']), 2);
    test_equals('code failed twostep code kept', $users[0]['twostep_code'], 'abcd1234');
    test_equals('code failed twostep (other type untouched)', $users[0]['token_failed'], null);

    // 確認（上限に達すると、コードを無効にして回数も消すこと）
    $invalidated_3 = service_user_code_failed($inserted_id, 'twostep');

    $users = model('select_users', [
        'select' => 'twostep_code, twostep_failed, token_code',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('code failed twostep (limit)', $invalidated_3, true);
    test_equals('code failed twostep code invalidated', $users[0]['twostep_code'], null);
    test_equals('code failed twostep count cleared', $users[0]['twostep_failed'], null);
    test_equals('code failed twostep (token code untouched)', $users[0]['token_code'], '12345678');

    // 確認（パスワード再設定の暗証コードも、同じように数えること）
    service_user_code_failed($inserted_id, 'token');
    service_user_code_failed($inserted_id, 'token');
    $invalidated_token = service_user_code_failed($inserted_id, 'token');

    $users = model('select_users', [
        'select' => 'token_code, token_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('code failed token (limit)', $invalidated_token, true);
    test_equals('code failed token code invalidated', $users[0]['token_code'], null);
    test_equals('code failed token count cleared', $users[0]['token_failed'], null);

    // 確認（ユーザーが見つからなければ、何もしないこと）
    $invalidated_unknown = service_user_code_failed($inserted_id + 1000, 'twostep');

    // 結果
    test_equals('code failed unknown user', $invalidated_unknown, false);

    // 設定を戻す
    $GLOBALS['config']['code_failed_limit'] = $code_failed_limit;
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
