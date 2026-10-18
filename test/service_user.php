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
    // 登録
    service_user_insert([
        'values' => [
            'username'       => $data_user['username'],
            'password'       => service_user_password_hash($data_user['password']),
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
    // 確認（保存されているハッシュを取得して照合する）
    $users = model('select_users', [
        'select' => 'id, enabled, password',
        'where'  => [
            'username = :username',
            [
                'username' => 'testuser1',
            ],
        ],
    ]);

    // 結果
    test_equals('authenticate user', count($users), 1);
    test_equals('authenticate user id', intval($users[0]['id']), $inserted_id);
    test_equals('authenticate user enabled', intval($users[0]['enabled']), 1);
    test_equals('authenticate user password', service_user_password_verify('abcd1234', $users[0]['password']), true);

    // 結果（パスワードが違えば認証できないこと）
    test_equals('authenticate user (invalid password)', service_user_password_verify('abcd12345', $users[0]['password']), false);
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

// 信頼済みのブラウザの解除が必要かのテスト
{
    // 結果（無効にするときと、メールアドレスを変えるときだけ解除する。フォームの値は文字列）
    test_equals('twostep reset required (same)', service_user_twostep_reset_required('1', 'testuser1-twostep@example.com', 'testuser1-twostep@example.com'), false);
    test_equals('twostep reset required (change email)', service_user_twostep_reset_required('1', 'testuser1-twostep2@example.com', 'testuser1-twostep@example.com'), true);
    test_equals('twostep reset required (disable)', service_user_twostep_reset_required('0', 'testuser1-twostep@example.com', 'testuser1-twostep@example.com'), true);
    test_equals('twostep reset required (enable)', service_user_twostep_reset_required('1', 'testuser1-twostep@example.com', null), true);
    test_equals('twostep reset required (null and empty)', service_user_twostep_reset_required(1, '', null), false);
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

// アカウントの凍結の判定テスト
{
    // 確認（10回失敗して、最後の失敗から5分以内なら凍結）
    test_user_set($inserted_id, ['failed' => 10, 'failed_last' => localdate('Y-m-d H:i:s')]);
    $frozen = service_user_frozen($data_user['username']);

    test_user_set($inserted_id, ['failed' => 9, 'failed_last' => localdate('Y-m-d H:i:s')]);
    $frozen_9 = service_user_frozen($data_user['username']);

    test_user_set($inserted_id, ['failed' => 10, 'failed_last' => localdate('Y-m-d H:i:s', time() - 60 * 6)]);
    $frozen_old = service_user_frozen($data_user['username']);

    $frozen_unknown = service_user_frozen('testuser_unknown');

    // 結果
    test_equals('frozen user', $frozen, true);
    test_equals('frozen user (9 times)', $frozen_9, false);
    test_equals('frozen user (after 5 minutes)', $frozen_old, false);
    test_equals('frozen user (unknown)', $frozen_unknown, false);
}

// パスワード認証テスト
{
    // データ
    test_user_set($inserted_id, ['failed' => null, 'failed_last' => null]);

    // 確認（正しいパスワード）
    $user = service_user_authenticate($data_user['username'], $data_user['password']);

    // 結果
    test_equals('authenticate user', intval($user['id']), $inserted_id);
    test_array_haskey('authenticate user twostep', $user, 'twostep');
    test_array_haskey('authenticate user twostep_email', $user, 'twostep_email');

    // 確認（誤ったパスワードは null で、失敗した回数が増えること）
    $user_wrong = service_user_authenticate($data_user['username'], 'wrongpass1');

    $users = model('select_users', [
        'select' => 'failed, failed_last',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('authenticate user (wrong password)', $user_wrong, null);
    test_equals('authenticate user (wrong password) failed', intval($users[0]['failed']), 1);
    test_not_equals('authenticate user (wrong password) failed_last', $users[0]['failed_last'], null);

    // 確認（存在しないユーザー名）
    $user_unknown = service_user_authenticate('testuser_unknown', $data_user['password']);

    // 結果
    test_equals('authenticate user (unknown)', $user_unknown, null);
}

// 信頼済みのブラウザの判定テスト
{
    // データ（コードの入力を省略するブラウザと、しないブラウザ）
    $trusted_session_id   = rand_string();
    $untrusted_session_id = rand_string();

    foreach ([$trusted_session_id => 1, $untrusted_session_id => 0] as $session_id => $twostep) {
        model('insert_sessions', [
            'values' => [
                'id'      => $session_id,
                'user_id' => $inserted_id,
                'agent'   => 'freo/2',
                'keep'    => 1,
                'twostep' => $twostep,
                'expire'  => localdate('Y-m-d H:i:s', time() + $GLOBALS['config']['cookie_expire']),
            ],
        ]);
    }

    // 結果
    test_equals('twostep trusted', service_user_twostep_trusted($inserted_id, $trusted_session_id), true);
    test_equals('twostep trusted (not trusted)', service_user_twostep_trusted($inserted_id, $untrusted_session_id), false);
    test_equals('twostep trusted (another user)', service_user_twostep_trusted($inserted_id + 1000, $trusted_session_id), false);
    test_equals('twostep trusted (no session)', service_user_twostep_trusted($inserted_id, null), false);
}

// メールで送るコードの作成テスト
{
    // 確認
    $code = service_user_code();

    // 結果（数字8桁の文字列。hash_equals() で照合するので文字列で返す）
    test_regexp('user code', $code, '^[0-9]{8}$');
    test_equals('user code type', is_string($code), true);
}

// 2段階認証用コードの発行テスト
{
    // データ（前のコードで3回間違えている）
    test_user_set($inserted_id, ['twostep_failed' => 3]);

    // 発行
    $twostep_code = service_user_twostep_issue($inserted_id);

    $users = model('select_users', [
        'select' => 'twostep_code, twostep_expire, twostep_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果（数字8桁で、期限は10分後、失敗した回数は数え直す）
    test_regexp('twostep issue code', $twostep_code, '^[0-9]{8}$');
    test_equals('twostep issue code saved', $users[0]['twostep_code'], $twostep_code);
    test_equals('twostep issue expire', $users[0]['twostep_expire'], localdate('Y-m-d H:i:s', time() + 60 * 10));
    test_equals('twostep issue failed cleared', $users[0]['twostep_failed'], null);
}

// 2段階認証用コードの照合テスト
{
    // データ（上限は設定で変わるので、3回に固定する）
    $code_failed_limit = $GLOBALS['config']['code_failed_limit'];
    $GLOBALS['config']['code_failed_limit'] = 3;

    $twostep_code = service_user_twostep_issue($inserted_id);

    // 確認
    $message_empty   = service_user_twostep_verify($inserted_id, '');
    $message_wrong   = service_user_twostep_verify($inserted_id, 'zzzzzzzz');
    $message_correct = service_user_twostep_verify($inserted_id, ' ' . $twostep_code . ' ');

    // 結果（前後の空白は除いて照合する。空欄は失敗に数えない）
    test_contains('twostep verify (empty)', $message_empty, '入力されていません');
    test_contains('twostep verify (wrong)', $message_wrong, '違います');
    test_equals('twostep verify (correct)', $message_correct, null);

    // 確認（期限切れ。コードは合っている）
    test_user_set($inserted_id, ['twostep_expire' => localdate('Y-m-d H:i:s', time() - 60)]);
    $message_expired = service_user_twostep_verify($inserted_id, $twostep_code);

    // 結果
    test_contains('twostep verify (expired)', $message_expired, '有効期限が終了');

    // 確認（続けて間違えると、上限でコードを無効にし、その後は無効と伝えること）
    service_user_twostep_issue($inserted_id);
    service_user_twostep_verify($inserted_id, 'zzzzzzzz');
    service_user_twostep_verify($inserted_id, 'zzzzzzzz');
    $message_limit   = service_user_twostep_verify($inserted_id, 'zzzzzzzz');
    $message_invalid = service_user_twostep_verify($inserted_id, 'zzzzzzzz');

    // 結果
    test_contains('twostep verify (limit)', $message_limit, '無効にしました');
    test_contains('twostep verify (invalidated)', $message_invalid, '無効になっています');

    // 設定を戻す
    $GLOBALS['config']['code_failed_limit'] = $code_failed_limit;
}

// 2段階認証用コードのメールテスト
{
    // データ（実際にメールを送信しないよう「送信なし・記録あり」に固定する。件名などの設定も固定する）
    $mail_config  = [$GLOBALS['config']['mail_send'], $GLOBALS['config']['mail_log']];
    $mail_setting = $GLOBALS['setting'] ?? null;

    $GLOBALS['config']['mail_send'] = false;
    $GLOBALS['config']['mail_log']  = true;
    $GLOBALS['setting'] = [
        'mail_twostep_subject' => '2段階認証のテスト',
        'mail_from'            => 'testuser1-from@example.com',
        'mail_body_begin'      => '',
        'mail_body_end'        => '',
    ];

    $mail_to   = 'testuser1-twostep-mail@example.com';
    $mail_glob = MAIN_APPLICATION_PATH . 'mail/' . localdate('Ymd') . '/*_' . $mail_to . '.txt';

    foreach (glob($mail_glob) as $file) {
        unlink($file);
    }

    // 送信
    service_user_twostep_mail($mail_to, 'abcd1234');

    // 結果
    $records = glob($mail_glob);

    test_equals('twostep mail record', count($records), 1);
    test_contains('twostep mail subject', file_get_contents($records[0]), 'subject: 2段階認証のテスト');
    test_contains('twostep mail code', file_get_contents($records[0]), '2段階認証用コードは abcd1234 です。');
    test_array_not_haskey('twostep mail view cleared', $_view, 'code');

    // 後始末
    foreach ($records as $file) {
        unlink($file);
    }

    list($GLOBALS['config']['mail_send'], $GLOBALS['config']['mail_log']) = $mail_config;
    $GLOBALS['setting'] = $mail_setting;
}

// ログインの完了テスト
{
    // データ（失敗した回数と、使い終わったコードが残っている）
    test_user_set($inserted_id, ['failed' => 3, 'failed_last' => localdate('Y-m-d H:i:s'), 'twostep_code' => 'abcd1234', 'twostep_expire' => localdate('Y-m-d H:i:s'), 'twostep_failed' => 2]);

    // 新しく作られるセッションを見分けるため、このユーザーのセッションを消しておく（トランザクションで元に戻る）
    db_query('DELETE FROM ' . DATABASE_PREFIX . 'sessions WHERE user_id = ' . db_escape($inserted_id) . ';');

    // ブラウザから実行したときに本物のログイン状態を変えないよう、セッションを退避しておく
    $session_auth = $_SESSION['auth'] ?? null;

    $_SESSION['auth']['twostep'] = ['id' => $inserted_id, 'keep' => 0];

    // ログイン処理は cookie_set() を呼ぶので、ヘッダー送信済みの警告だけを無視する
    set_error_handler('test_user_header_error');

    service_user_login_complete($inserted_id, 1, 1);

    restore_error_handler();

    $login_session = $_SESSION['auth'];

    if ($session_auth === null) {
        unset($_SESSION['auth']);
    } else {
        $_SESSION['auth'] = $session_auth;
    }

    $users = model('select_users', [
        'select' => 'loggedin, failed, failed_last, twostep_code, twostep_expire, twostep_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);
    $sessions = model('select_sessions', [
        'select' => 'keep, twostep',
        'where'  => 'user_id = ' . db_escape($inserted_id),
    ]);

    // 結果（セッションに記録し、認証待ちの状態を消すこと）
    test_equals('login complete session user', intval($login_session['user']['id']), $inserted_id);
    test_array_not_haskey('login complete session twostep', $login_session, 'twostep');

    // 結果（失敗した回数と使い終わったコードを消すこと）
    test_not_equals('login complete loggedin', $users[0]['loggedin'], null);
    test_equals('login complete failed', $users[0]['failed'], null);
    test_equals('login complete failed_last', $users[0]['failed_last'], null);
    test_equals('login complete twostep_code', $users[0]['twostep_code'], null);
    test_equals('login complete twostep_expire', $users[0]['twostep_expire'], null);
    test_equals('login complete twostep_failed', $users[0]['twostep_failed'], null);

    // 結果（ログイン状態を記憶し、このブラウザでコードの入力を省略すること）
    test_equals('login complete session', count($sessions), 1);
    test_equals('login complete keep', intval($sessions[0]['keep']), 1);
    test_equals('login complete twostep', intval($sessions[0]['twostep']), 1);
}

// パスワード再設定の暗証コードの発行テスト
{
    // データ（前の暗証コードで3回間違えている）
    test_user_set($inserted_id, ['token_failed' => 3]);

    $users = model('select_users', [
        'select' => 'email',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);
    $password_email = $users[0]['email'];

    // 発行
    $password_user    = service_user_password_issue($password_email);
    $password_unknown = service_user_password_issue('testuser-unknown@example.com');

    $users = model('select_users', [
        'select' => 'token, token_code, token_expire, token_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果（暗証コードは8桁の数字で、期限は10分後、失敗した回数は数え直す）
    test_equals('password issue user', intval($password_user['id']), $inserted_id);
    test_equals('password issue email', $password_user['email'], $password_email);
    test_not_equals('password issue token', $password_user['token'], null);
    test_equals('password issue token saved', $users[0]['token'], $password_user['token']);
    test_regexp('password issue code', (string)$password_user['token_code'], '^[0-9]{8}$');
    test_equals('password issue expire', $users[0]['token_expire'], localdate('Y-m-d H:i:s', time() + 60 * 10));
    test_equals('password issue failed cleared', $users[0]['token_failed'], null);
    test_equals('password issue (unknown email)', $password_unknown, null);
}

// パスワード再設定の暗証コードの失敗回数のテスト
{
    // データ（上限は設定で変わるので、3回に固定する）
    $code_failed_limit = $GLOBALS['config']['code_failed_limit'];
    $GLOBALS['config']['code_failed_limit'] = 3;

    test_user_set($inserted_id, ['token_code' => '12345678', 'token_expire' => localdate('Y-m-d H:i:s', time() + 60 * 10), 'token_failed' => null]);

    // 確認（暗証コードが合っているときは数えず、メッセージも差し替えない。有効期限が終了しているときなど）
    $message_correct = service_user_password_failed($password_email, '12345678');

    $users = model('select_users', [
        'select' => 'token_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('password failed (correct code)', $message_correct, null);
    test_equals('password failed (correct code) not counted', $users[0]['token_failed'], null);

    // 確認（上限の手前までは数えるだけで、メッセージは差し替えない）
    $message_wrong_1 = service_user_password_failed($password_email, '00000000');
    $message_wrong_2 = service_user_password_failed($password_email, '00000000');

    // 結果
    test_equals('password failed (1)', $message_wrong_1, null);
    test_equals('password failed (2)', $message_wrong_2, null);

    // 確認（上限で暗証コードを無効にし、その後は無効と伝えること）
    $message_limit   = service_user_password_failed($password_email, '00000000');
    $message_invalid = service_user_password_failed($password_email, '00000000');

    $users = model('select_users', [
        'select' => 'token_code, token_failed',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_contains('password failed (limit)', $message_limit, '無効にしました');
    test_contains('password failed (invalidated)', $message_invalid, '無効になっています');
    test_equals('password failed code invalidated', $users[0]['token_code'], null);
    test_equals('password failed count cleared', $users[0]['token_failed'], null);

    // 確認（メールアドレスが見つからなければ、何もしないこと）
    test_equals('password failed (unknown email)', service_user_password_failed('testuser-unknown@example.com', '00000000'), null);

    // 設定を戻す
    $GLOBALS['config']['code_failed_limit'] = $code_failed_limit;
}

// メールアドレスの存在確認のテスト
{
    // データ（未確認の状態にする）
    test_user_set($inserted_id, ['email_verified' => 0, 'token' => null, 'token_expire' => null]);

    // 発行
    $email_user = service_user_email_issue($inserted_id);

    $users = model('select_users', [
        'select' => 'token, token_expire',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果（期限は24時間後）
    test_equals('email issue email', $email_user['email'], $password_email);
    test_equals('email issue token saved', $users[0]['token'], $email_user['token']);
    test_equals('email issue expire', $users[0]['token_expire'], localdate('Y-m-d H:i:s', time() + 60 * 60 * 24));

    // 確認（誤ったトークン・誤ったメールアドレスでは確認済みにしないこと）
    $verified_wrong_token = service_user_email_verify($password_email, 'wrongtoken');
    $verified_wrong_email = service_user_email_verify('testuser-unknown@example.com', $email_user['token']);

    // 確認（期限切れでは確認済みにしないこと）
    test_user_set($inserted_id, ['token_expire' => localdate('Y-m-d H:i:s', time() - 60)]);
    $verified_expired = service_user_email_verify($password_email, $email_user['token']);

    $users = model('select_users', [
        'select' => 'email_verified',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('email verify (wrong token)', $verified_wrong_token, false);
    test_equals('email verify (wrong email)', $verified_wrong_email, false);
    test_equals('email verify (expired)', $verified_expired, false);
    test_equals('email verify (not verified)', intval($users[0]['email_verified']), 0);

    // 確認（正しいURLなら確認済みにして、トークンを消すこと）
    test_user_set($inserted_id, ['token_expire' => localdate('Y-m-d H:i:s', time() + 60)]);
    $verified = service_user_email_verify($password_email, $email_user['token']);

    $users = model('select_users', [
        'select' => 'email_verified, token, token_expire',
        'where'  => 'id = ' . db_escape($inserted_id),
    ]);

    // 結果
    test_equals('email verify', $verified, true);
    test_equals('email verify verified', intval($users[0]['email_verified']), 1);
    test_equals('email verify token cleared', $users[0]['token'], null);
    test_equals('email verify token_expire cleared', $users[0]['token_expire'], null);

    // 確認（使ったURLは2回目には使えないこと）
    test_equals('email verify (used)', service_user_email_verify($password_email, $email_user['token']), false);
}

// パスワード再設定・メールアドレスの存在確認のメールテスト
{
    // データ（実際にメールを送信しないよう「送信なし・記録あり」に固定する。件名などの設定も固定する）
    $mail_config  = [$GLOBALS['config']['mail_send'], $GLOBALS['config']['mail_log']];
    $mail_setting = $GLOBALS['setting'] ?? null;

    $GLOBALS['config']['mail_send'] = false;
    $GLOBALS['config']['mail_log']  = true;
    $GLOBALS['setting'] = [
        'mail_password_subject' => 'パスワード再設定のテスト',
        'mail_verify_subject'   => '存在確認のテスト',
        'mail_from'             => 'testuser1-from@example.com',
        'mail_body_begin'       => '',
        'mail_body_end'         => '',
    ];

    $mail_password_to   = 'testuser1-password-mail@example.com';
    $mail_verify_to     = 'testuser1-verify-mail@example.com';
    $mail_password_glob = MAIN_APPLICATION_PATH . 'mail/' . localdate('Ymd') . '/*_' . $mail_password_to . '.txt';
    $mail_verify_glob   = MAIN_APPLICATION_PATH . 'mail/' . localdate('Ymd') . '/*_' . $mail_verify_to . '.txt';

    foreach (array_merge(glob($mail_password_glob), glob($mail_verify_glob)) as $file) {
        unlink($file);
    }

    // 送信
    service_user_password_mail($mail_password_to, '12345678', '2026-01-02 03:04:05');
    service_user_email_mail($mail_verify_to, 'abcdtoken');

    // 結果（パスワード再設定は暗証コードと有効期限、存在確認はURLが入ること）
    $password_records = glob($mail_password_glob);
    $verify_records   = glob($mail_verify_glob);

    test_equals('password mail record', count($password_records), 1);
    test_contains('password mail subject', file_get_contents($password_records[0]), 'subject: パスワード再設定のテスト');
    test_contains('password mail code', file_get_contents($password_records[0]), '12345678');
    test_contains('password mail expire', file_get_contents($password_records[0]), '2026/01/02 03:04');
    test_array_not_haskey('password mail view cleared', $_view, 'token_code');

    test_equals('verify mail record', count($verify_records), 1);
    test_contains('verify mail subject', file_get_contents($verify_records[0]), 'subject: 存在確認のテスト');
    test_contains('verify mail url', file_get_contents($verify_records[0]), '/auth/email_verify?key=' . rawurlencode($mail_verify_to) . '&token=abcdtoken');
    test_array_not_haskey('verify mail view cleared', $_view, 'url');

    // 後始末
    foreach (array_merge($password_records, $verify_records) as $file) {
        unlink($file);
    }

    list($GLOBALS['config']['mail_send'], $GLOBALS['config']['mail_log']) = $mail_config;
    $GLOBALS['setting'] = $mail_setting;
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

/**
 * テスト用ユーザーの列を書き換える（前提の状態を作るため。サービスを通さないので操作ログは残らない）
 *
 * @param int   $id
 * @param array $sets
 */
function test_user_set($id, $sets)
{
    model('update_users', [
        'set'   => $sets,
        'where' => [
            'id = :id',
            [
                'id' => $id,
            ],
        ],
    ], [
        'id' => $id,
    ]);
}
