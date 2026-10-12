<?php

import('app/services/session.php');
import('app/services/log.php');
import('app/services/mail.php');
import('libs/modules/cookie.php');
import('libs/modules/hash.php');

/**
 * ユーザーの登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_user_insert($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'users', 'insert');

    // ユーザーを登録
    $resource = model('insert_users', $queries, $options);
    if (!$resource) {
        error('データを登録できません。');
    }

    return $resource;
}

/**
 * ユーザーの編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_user_update($queries, $options = [])
{
    $options = [
        'id'             => isset($options['id'])             ? $options['id']             : null,
        'update'         => isset($options['update'])         ? $options['update']         : null,
        'attribute_sets' => isset($options['attribute_sets']) ? $options['attribute_sets'] : [],
    ];

    // 最終編集日時を確認
    if (isset($options['id']) && isset($options['update']) && (!isset($queries['set']['modified']) || $queries['set']['modified'] !== false)) {
        $users = model('select_users', [
            'where' => [
                'id = :id AND modified > :update',
                [
                    'id'     => $options['id'],
                    'update' => $options['update'],
                ],
            ],
        ]);
        if (!empty($users)) {
            error('編集開始後にデータが更新されています。');
        }
    }

    // 操作ログの記録
    service_log_record(null, 'users', 'update');

    // ユーザーを編集
    $resource = update_users($queries, $options);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $resource;
}

/**
 * ユーザーの削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_user_delete($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'users', 'delete');

    // ユーザーを削除
    $resource = model('delete_users', $queries, $options);
    if (!$resource) {
        error('データを削除できません。');
    }

    return $resource;
}

/**
 * ユーザーのログイン
 *
 * @param string $session_id
 *
 * @return array
 */
function service_user_login($session_id)
{
    // セッションを取得
    $users = model('select_sessions', [
        'select' => 'user_id, keep',
        'where'  => [
            'id = :id AND expire > :expire',
            [
                'id'     => $session_id,
                'expire' => localdate('Y-m-d H:i:s'),
            ],
        ],
    ]);

    $session = false;
    $user_id = null;
    if (!empty($users)) {
        // セッションの有効期限を取得
        if ($users[0]['keep']) {
            $expire = $GLOBALS['config']['cookie_expire'];
        } else {
            $expire = $GLOBALS['config']['login_expire'];
        }

        // セッションを更新
        $new_session_id = rand_string();

        $resource = service_session_update([
            'set'   => [
                'id'     => $new_session_id,
                'agent'  => $_SERVER['HTTP_USER_AGENT'],
                'expire' => localdate('Y-m-d H:i:s', time() + $expire),
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $session_id,
                ],
            ],
        ]);
        if ($resource) {
            cookie_set('auth[session]', $new_session_id, time() + $GLOBALS['config']['cookie_expire'], $GLOBALS['config']['cookie_path'], $GLOBALS['config']['cookie_domain'], $GLOBALS['config']['cookie_secure']);
        } else {
            error('データを編集できません。');
        }

        if ($users[0]['keep']) {
            // ユーザーを更新
            $resource = service_user_update([
                'set'   => [
                    'loggedin' => localdate('Y-m-d H:i:s'),
                ],
                'where' => [
                    'id = :id AND enabled = 1',
                    [
                        'id' => $users[0]['user_id'],
                    ],
                ],
            ]);
            if (!$resource) {
                error('データを編集できません。');
            }

            $session = true;
            $user_id = $users[0]['user_id'];
        }
    }

    return [$session, $user_id];
}

/**
 * ユーザーのセッション保持
 *
 * @param string $old_session
 * @param string $new_session
 * @param string $user_id
 * @param int $keep
 * @param int $twostep
 */
function service_user_duration($old_session, $new_session, $user_id, $keep, $twostep)
{
    // セッションを取得
    $flag = false;
    if ($old_session) {
        $users = model('select_sessions', [
            'select' => 'user_id',
            'where'  => [
                'id = :id',
                [
                    'id' => $old_session,
                ],
            ],
        ]);
        if (!empty($users)) {
            $flag = true;
        }
    }

    // セッションの有効期限を取得
    if ($keep) {
        $expire = $GLOBALS['config']['cookie_expire'];
    } else {
        $expire = $GLOBALS['config']['login_expire'];
    }

    // セッションを更新
    if ($flag === true) {
        $resource = service_session_update([
            'set'   => [
                'id'      => $new_session,
                'user_id' => $user_id,
                'agent'   => $_SERVER['HTTP_USER_AGENT'],
                'keep'    => $keep,
                'twostep' => $twostep,
                'expire'  => localdate('Y-m-d H:i:s', time() + $expire),
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $old_session,
                ],
            ],
        ]);
        if (!$resource) {
            error('データを編集できません。');
        }
    } else {
        $resource = service_session_insert([
            'values' => [
                'id'      => $new_session,
                'user_id' => $user_id,
                'agent'   => $_SERVER['HTTP_USER_AGENT'],
                'keep'    => $keep,
                'twostep' => $twostep,
                'expire'  => localdate('Y-m-d H:i:s', time() + $expire),
            ],
        ]);
        if (!$resource) {
            error('データを登録できません。');
        }
    }

    cookie_set('auth[session]', $new_session, localdate() + $GLOBALS['config']['cookie_expire'], $GLOBALS['config']['cookie_path'], $GLOBALS['config']['cookie_domain'], $GLOBALS['config']['cookie_secure']);

    // 古いセッションを削除
    $resource = service_session_delete([
        'where' => [
            'expire < :expire',
            [
                'expire' => localdate('Y-m-d H:i:s'),
            ],
        ],
    ]);
    if (!$resource) {
        error('データを削除できません。');
    }
}

/**
 * ユーザーのログアウト
 *
 * @param string $session_id
 * @param string $user_id
 */
function service_user_logout($session_id, $user_id)
{
    $resource = service_session_update([
        'set'   => [
            'keep' => 0
        ],
        'where' => [
            'id = :session AND user_id = :user_id',
            [
                'session' => $session_id,
                'user_id' => $user_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }
}

/**
 * ユーザーのアカウントが凍結されているか（ログインに10回続けて失敗すると、最後の失敗から5分間）
 *
 * @param string $username
 *
 * @return bool
 */
function service_user_frozen($username)
{
    $users = model('select_users', [
        'select' => 'failed, failed_last',
        'where'  => [
            'username = :username',
            [
                'username' => $username,
            ],
        ],
    ]);
    if (empty($users)) {
        return false;
    }

    return localdate(null, $users[0]['failed_last']) + 60 * 5 > localdate() && $users[0]['failed'] >= 10;
}

/**
 * ユーザーのパスワード認証（失敗したら、失敗した回数を記録する）
 *
 * @param string $username
 * @param string $password
 *
 * @return array|null 成功したらユーザー（id, enabled, twostep, twostep_email）、失敗したら null
 */
function service_user_authenticate($username, $password)
{
    // ログイン失敗回数とパスワードのソルトを取得
    $users = model('select_users', [
        'select' => 'password_salt, failed',
        'where'  => [
            'username = :username',
            [
                'username' => $username,
            ],
        ],
    ]);
    if (empty($users)) {
        $password_salt = null;
        $failed        = null;
    } else {
        $password_salt = $users[0]['password_salt'];
        $failed        = $users[0]['failed'];
    }

    // パスワード認証
    $users = model('select_users', [
        'select' => 'id, enabled, twostep, twostep_email',
        'where'  => [
            'username = :username AND password = :password',
            [
                'username' => $username,
                'password' => hash_crypt($password, $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            ],
        ],
    ]);
    if (!empty($users)) {
        return $users[0];
    }

    // 認証失敗回数を記録（有効なユーザーだけ。存在しないユーザー名なら何も更新しない）
    $resource = service_user_update([
        'set'   => [
            'failed'      => $failed + 1,
            'failed_last' => localdate('Y-m-d H:i:s'),
        ],
        'where' => [
            'enabled = 1 AND username = :username',
            [
                'username' => $username,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    return null;
}

/**
 * ユーザーの2段階認証で、このブラウザのコードの入力を省略するか（ブラウザを信頼しているか）
 *
 * @param string $user_id
 * @param string|null $session_id
 *
 * @return bool
 */
function service_user_twostep_trusted($user_id, $session_id)
{
    if ($session_id === null || $session_id === '') {
        return false;
    }

    $sessions = model('select_sessions', [
        'select' => 'twostep',
        'where'  => [
            'id = :session AND user_id = :user_id',
            [
                'session' => $session_id,
                'user_id' => $user_id,
            ],
        ],
    ]);

    return !empty($sessions) && intval($sessions[0]['twostep']) === 1;
}

/**
 * ユーザーの2段階認証用コードを発行する（有効期限は10分。失敗した回数は数え直す）
 *
 * @param string $user_id
 *
 * @return string
 */
function service_user_twostep_issue($user_id)
{
    $twostep_code = rand_string(8);

    $resource = service_user_update([
        'set'   => [
            'twostep_code'   => $twostep_code,
            'twostep_expire' => localdate('Y-m-d H:i:s', time() + 60 * 10),
            'twostep_failed' => null,
        ],
        'where' => [
            'id = :id',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $twostep_code;
}

/**
 * 2段階認証用コードをメールで送る（ログインと、本人が2段階認証用メールアドレスを確認するときに使う）
 *
 * @param string $email
 * @param string $twostep_code
 */
function service_user_twostep_mail($email, $twostep_code)
{
    // メール送信内容を作成（本文のテンプレートは $_view['code'] を読む）
    $GLOBALS['_view']['code'] = $twostep_code;

    $subject = $GLOBALS['setting']['mail_twostep_subject'];
    $message = view('mail/email/twostep.php', true);
    $headers = [
        'From' => $GLOBALS['setting']['mail_from'],
    ];

    unset($GLOBALS['_view']['code']);

    // メールを送信
    if (service_mail_send($email, $subject, $message, $headers) === false) {
        error('メールを送信できません。');
    }
}

/**
 * ユーザーの2段階認証用コードを照合する（間違えたら失敗した回数を記録し、上限に達したらコードを無効にする）
 *
 * @param string $user_id
 * @param string $twostep_code
 *
 * @return string|null 成功したら null、失敗したらメッセージ
 */
function service_user_twostep_verify($user_id, $twostep_code)
{
    $twostep_code = trim((string)$twostep_code);

    if ($twostep_code === '') {
        return '2段階認証用コードが入力されていません。';
    }

    $users = model('select_users', [
        'select' => 'twostep_code, twostep_expire',
        'where'  => [
            'id = :id',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (empty($users)) {
        return '2段階認証用コードが違います。';
    }

    if ($users[0]['twostep_code'] === null) {
        return '2段階認証用コードが無効になっています。コードを再送信してください。';
    }

    if (!hash_equals($users[0]['twostep_code'], $twostep_code)) {
        if (service_user_code_failed($user_id, 'twostep')) {
            return '2段階認証用コードを続けて間違えたため、このコードを無効にしました。コードを再送信してください。';
        } else {
            return '2段階認証用コードが違います。';
        }
    }

    if (empty($users[0]['twostep_expire']) || localdate(null, $users[0]['twostep_expire']) < localdate()) {
        return '2段階認証用コードの有効期限が終了しています。';
    }

    return null;
}

/**
 * ユーザーのログインを完了する（セッションへの記録、失敗回数と使い終わったコードの消去、ログイン状態の保持、操作ログ）
 *
 * @param string $user_id
 * @param int $keep    ログイン状態を記憶するか
 * @param int $twostep このブラウザで2段階認証のコードの入力を省略するか
 */
function service_user_login_complete($user_id, $keep, $twostep)
{
    // 2段階認証の認証待ちの状態を破棄
    unset($_SESSION['auth']['twostep']);

    $_SESSION['auth']['user'] = [
        'id'   => $user_id,
        'time' => localdate(),
    ];

    // 認証失敗回数をリセットし、使い終わった2段階認証用コードを削除
    $resource = service_user_update([
        'set'   => [
            'loggedin'       => localdate('Y-m-d H:i:s'),
            'failed'         => null,
            'failed_last'    => null,
            'twostep_code'   => null,
            'twostep_expire' => null,
            'twostep_failed' => null,
        ],
        'where' => [
            'id = :id AND enabled = 1',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    // ログイン状態を保持
    $old_session = isset($_COOKIE['auth']['session']) ? $_COOKIE['auth']['session'] : null;

    service_user_duration($old_session, rand_string(), $user_id, $keep, $twostep);

    // 操作ログの記録
    service_log_record('管理者用ページにログインしました。');
}

/**
 * ユーザーのコードの照合に失敗した回数を記録し、上限に達したらそのコードを無効にする
 * （パスワード再設定の暗証コードは token、2段階認証用コードは twostep）
 *
 * @param string $user_id
 * @param string $type
 *
 * @return bool 上限に達してコードを無効にしたら true
 */
function service_user_code_failed($user_id, $type)
{
    // 列名に使うので、決まったもの以外は受け付けない
    if ($type !== 'token' && $type !== 'twostep') {
        error('不正な操作です。');
    }

    $users = model('select_users', [
        'select' => $type . '_failed AS failed',
        'where'  => [
            'id = :id',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (empty($users)) {
        return false;
    }

    $failed = intval($users[0]['failed']) + 1;

    if ($failed >= $GLOBALS['config']['code_failed_limit']) {
        // 上限に達したら、コードを無効にする（回数は、次にコードを発行したときに数え直す）
        $sets = [
            $type . '_code'   => null,
            $type . '_failed' => null,
        ];
    } else {
        $sets = [
            $type . '_failed' => $failed,
        ];
    }

    $resource = service_user_update([
        'set'   => $sets,
        'where' => [
            'id = :id',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $failed >= $GLOBALS['config']['code_failed_limit'];
}

/**
 * ユーザーの2段階認証用メールアドレスに、コードが届くかの確認が必要か
 * （2段階認証を有効にするときと、有効のまま2段階認証用メールアドレスを変えるとき）
 *
 * @param string $user_id
 * @param string $twostep
 * @param string $twostep_email
 *
 * @return bool
 */
function service_user_twostep_verify_required($user_id, $twostep, $twostep_email)
{
    if ((string)$twostep !== '1') {
        return false;
    }

    $users = model('select_users', [
        'select' => 'twostep, twostep_email',
        'where'  => [
            'id = :id',
            [
                'id' => $user_id,
            ],
        ],
    ]);
    if (empty($users)) {
        return true;
    }

    return (string)$users[0]['twostep'] !== '1' || (string)$users[0]['twostep_email'] !== (string)$twostep_email;
}

/**
 * ユーザーの2段階認証で、コードの入力を省略しているブラウザをすべて解除
 *
 * @param string $user_id
 */
function service_user_twostep_reset($user_id)
{
    $resource = service_session_update([
        'set'   => [
            'twostep' => 0,
        ],
        'where' => [
            'user_id = :user_id',
            [
                'user_id' => $user_id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }
}
