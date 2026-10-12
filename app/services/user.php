<?php

import('app/services/session.php');
import('app/services/log.php');
import('libs/modules/cookie.php');

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
 * ユーザーのコードの照合に失敗した回数を記録し、上限に達したらそのコードを無効にする
 * (パスワード再設定の暗証コードは token、2段階認証用コードは twostep)
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
        // 上限に達したら、コードを無効にする(回数は、次にコードを発行したときに数え直す)
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
 * (2段階認証を有効にするときと、有効のまま2段階認証用メールアドレスを変えるとき)
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
