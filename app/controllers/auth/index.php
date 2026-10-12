<?php

import('app/services/user.php');
import('app/services/session.php');
import('app/services/mail.php');
import('app/services/log.php');
import('libs/modules/hash.php');

// ログイン処理の結果
$success         = false;
$id              = null;
$keep            = 0;
$session_twostep = 0;
$twostep_send    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exec']) && ($_POST['exec'] === 'twostep' || $_POST['exec'] === 'twostep_resend')) {
    // 2段階認証（パスワード認証が済み、コードの入力を待っている状態）
    if (empty($_SESSION['auth']['twostep']['id'])) {
        $users = [];
    } else {
        $users = model('select_users', [
            'select' => 'id, twostep_email, twostep_code, twostep_expire',
            'where'  => [
                'id = :id AND enabled = 1 AND twostep = 1',
                [
                    'id' => $_SESSION['auth']['twostep']['id'],
                ],
            ],
        ]);
    }
    if (empty($users)) {
        // 認証待ちの状態が無い（セッション切れなど）
        unset($_SESSION['auth']['twostep']);

        $_view['user'] = [
            'username' => '',
            'password' => '',
        ];

        $_view['warnings'] = ['もう一度ログインしてください。'];
    } else {
        $id            = $users[0]['id'];
        $keep          = $_SESSION['auth']['twostep']['keep'];
        $twostep_email = $users[0]['twostep_email'];

        $_view['user']    = $_POST;
        $_view['twostep'] = true;

        if ($_POST['exec'] === 'twostep_resend') {
            // 2段階認証用コードを再送信
            $twostep_send = true;
        } else {
            // 2段階認証用コードを確認
            $twostep_code = isset($_POST['twostep_code']) ? trim($_POST['twostep_code']) : '';

            if ($twostep_code === '') {
                $_view['warnings'] = ['2段階認証用コードが入力されていません。'];
            } elseif ($users[0]['twostep_code'] === null || !hash_equals($users[0]['twostep_code'], $twostep_code)) {
                $_view['warnings'] = ['2段階認証用コードが違います。'];
            } elseif (empty($users[0]['twostep_expire']) || localdate(null, $users[0]['twostep_expire']) < localdate()) {
                $_view['warnings'] = ['2段階認証用コードの有効期限が終了しています。'];
            } else {
                $success = true;
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 前回の認証待ちの状態を破棄
    unset($_SESSION['auth']['twostep']);

    // ログイン失敗回数とパスワードのソルトを取得
    $users = model('select_users', [
        'select' => 'password_salt, failed, failed_last',
        'where'  => [
            'username = :username',
            [
                'username' => $_POST['username'],
            ],
        ],
    ]);
    if (empty($users)) {
        $password_salt = null;
        $failed        = null;
        $failed_last   = null;
    } else {
        $password_salt = $users[0]['password_salt'];
        $failed        = $users[0]['failed'];
        $failed_last   = $users[0]['failed_last'];

        if (localdate(null, $failed_last) + 60 * 5 > localdate() && $failed >= 10) {
            error('パスワードを連続して間違えたため、このアカウントは凍結されています。5分後以降にお試しください。');
        }
    }

    // パスワード認証
    $users = model('select_users', [
        'select' => 'id, enabled, twostep, twostep_email',
        'where'  => [
            'username = :username AND password = :password',
            [
                'username' => $_POST['username'],
                'password' => hash_crypt($_POST['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            ],
        ],
    ]);
    if (empty($users)) {
        // パスワード認証失敗
        $_view['user'] = $_POST;
        $_view['user']['password'] = '';

        $_view['warnings'] = ['ユーザー名もしくはパスワードが違います。'];

        // トランザクションを開始
        db_transaction();

        // 認証失敗回数を記録
        $resource = service_user_update([
            'set'   => [
                'failed'      => $failed + 1,
                'failed_last' => localdate('Y-m-d H:i:s'),
            ],
            'where' => [
                'enabled = 1 AND username = :username',
                [
                    'username' => $_POST['username'],
                ],
            ],
        ]);
        if (!$resource) {
            error('データを編集できません。');
        }

        // トランザクションを終了
        db_commit();
    } else {
        // パスワード認証成功
        $id            = $users[0]['id'];
        $enabled       = $users[0]['enabled'];
        $twostep       = $users[0]['twostep'];
        $twostep_email = $users[0]['twostep_email'];

        if (isset($_POST['session']) && $_POST['session'] === 'keep') {
            $keep = 1;
        } else {
            $keep = 0;
        }

        // 2段階認証の状態を取得（コードの入力を省略するブラウザか）
        if ($twostep == 1 && isset($_COOKIE['auth']['session'])) {
            $sessions = model('select_sessions', [
                'select' => 'twostep',
                'where'  => [
                    'id = :session AND user_id = :user_id',
                    [
                        'session' => $_COOKIE['auth']['session'],
                        'user_id' => $id,
                    ],
                ],
            ]);
            if (!empty($sessions)) {
                $session_twostep = $sessions[0]['twostep'];
            }
        }

        if (!$enabled) {
            // アカウント有効化前
            $_view['user'] = $_POST;
            $_view['user']['password'] = '';

            $_view['warnings'] = ['アカウントが有効化されていません。'];
        } elseif ($twostep == 1 && $session_twostep == 0) {
            // 2段階認証が必要
            $_view['user'] = $_POST;
            $_view['user']['password'] = '';

            if (empty($twostep_email)) {
                $_view['warnings'] = ['2段階認証用メールアドレスが登録されていません。'];
            } else {
                // パスワード認証が済んだことをセッションに記録し、コードの入力を待つ
                // (コードの画面ではユーザー名・パスワードを受け渡さない)
                $_SESSION['auth']['twostep'] = [
                    'id'   => $id,
                    'keep' => $keep,
                ];

                $_view['user']    = [];
                $_view['twostep'] = true;

                $twostep_send = true;
            }
        } else {
            $success = true;
        }
    }
} else {
    $_view['user'] = [
        'username' => '',
        'password' => '',
        'session'  => null,
    ];
}

// 2段階認証用コードを送信
if ($twostep_send) {
    $twostep_code = rand_string(8);

    // トランザクションを開始
    db_transaction();

    // 2段階認証用コードを記録
    $resource = service_user_update([
        'set'   => [
            'twostep_code'   => $twostep_code,
            'twostep_expire' => localdate('Y-m-d H:i:s', time() + 60 * 10),
        ],
        'where' => [
            'id = :id',
            [
                'id' => $id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    // メール送信内容を作成
    $_view['code'] = $twostep_code;

    $to      = $twostep_email;
    $subject = $GLOBALS['setting']['mail_twostep_subject'];
    $message = view('mail/email/twostep.php', true);
    $headers = [
        'From' => $GLOBALS['setting']['mail_from'],
    ];

    // メールを送信
    if (service_mail_send($to, $subject, $message, $headers) === false) {
        error('メールを送信できません。');
    }

    // トランザクションを終了
    db_commit();
}

// 認証成功
if ($success) {
    // 2段階認証の認証待ちの状態を破棄
    unset($_SESSION['auth']['twostep']);

    $_SESSION['auth']['user'] = [
        'id'   => $id,
        'time' => localdate(),
    ];

    // トランザクションを開始
    db_transaction();

    // 認証失敗回数をリセットし、使い終わった2段階認証用コードを削除
    $resource = service_user_update([
        'set'   => [
            'loggedin'       => localdate('Y-m-d H:i:s'),
            'failed'         => null,
            'failed_last'    => null,
            'twostep_code'   => null,
            'twostep_expire' => null,
        ],
        'where' => [
            'id = :id AND enabled = 1',
            [
                'id' => $id,
            ],
        ],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    // ログイン状態を保持
    if (isset($_COOKIE['auth']['session'])) {
        $old_session = $_COOKIE['auth']['session'];
    } else {
        $old_session = null;
    }
    if ($session_twostep == 1 || (isset($_POST['twostep_session']) && $_POST['twostep_session'] === 'keep')) {
        $twostep = 1;
    } else {
        $twostep = 0;
    }
    service_user_duration($old_session, rand_string(), $_SESSION['auth']['user']['id'], $keep, $twostep);

    // 操作ログの記録
    service_log_record('管理者用ページにログインしました。');

    // トランザクションを終了
    db_commit();
}

// ログイン確認
if (!empty($_SESSION['auth']['user']['id'])) {
    if ($_REQUEST['_work'] === 'index') {
        if (isset($_GET['referer']) && regexp_match('^\/', $_GET['referer'])) {
            $url = $_GET['referer'];
        } else {
            $url = '/auth/home';
        }

        // セッションを再作成
        session_regenerate_id(true);

        // リダイレクト
        redirect($url);
    } else {
        error('不正なアクセスです。');
    }
}

// タイトル
$_view['title'] = $GLOBALS['string']['heading_auth'];
