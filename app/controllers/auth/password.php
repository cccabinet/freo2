<?php

import('app/services/user.php');

// 権限を確認
if (isset($GLOBALS['authority'])) {
    // リダイレクト
    redirect('/auth/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ワンタイムトークン
    if (!token('check')) {
        error('不正な操作が検出されました。送信内容を確認して再度実行してください。');
    }

    // アクセス元
    if (empty($_SERVER['HTTP_REFERER']) || !preg_match('/^' . preg_quote($GLOBALS['config']['http_url'], '/') . '/', $_SERVER['HTTP_REFERER'])) {
        error('不正なアクセスです。');
    }

    // メールアドレスを文字列に限定する
    $_POST['email'] = (isset($_POST['email']) && is_string($_POST['email'])) ? $_POST['email'] : '';

    // メールアドレスを検証
    $users = model('select_users', [
        'where' => [
            'email = :email',
            [
                'email' => $_POST['email'],
            ],
        ],
    ]);
    if (empty($users)) {
        $warnings = ['email' => '指定されたメールアドレスが見つかりません。'];
    } else {
        $warnings = [];
    }

    // 入力データを検証＆登録
    if (isset($_POST['_type']) && $_POST['_type'] === 'json') {
        if (empty($warnings)) {
            ok();
        } else {
            warning($warnings);
        }
    } else {
        if (empty($warnings)) {
            // トランザクションを開始
            db_transaction();

            // パスワード再発行用のトークンと暗証コードを発行
            $user = service_user_password_issue($_POST['email']);
            if ($user === null) {
                error('指定されたメールアドレスが見つかりません。');
            }

            // 暗証コードを通知
            service_user_password_mail($user['email'], $user['token_code'], $user['token_expire']);

            // トランザクションを終了
            db_commit();

            // リダイレクト
            redirect('/auth/password_form?key=' . rawurlencode($user['email']) . '&token=' . $user['token']);
        } else {
            $_view['user'] = $_POST;

            $_view['warnings'] = $warnings;
        }
    }
} else {
    $_view['user'] = [
        'email' => '',
    ];
}

// タイトル
$_view['title'] = 'パスワード再発行';
