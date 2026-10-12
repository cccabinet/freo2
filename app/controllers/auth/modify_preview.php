<?php

import('app/services/user.php');

// 投稿データを確認
if (empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/auth/modify');
}

// 2段階認証用メールアドレスに、コードが届くかの確認が必要か
$twostep_verify = service_user_twostep_verify_required($_SESSION['auth']['user']['id'], $_SESSION['post']['user']['twostep'], $_SESSION['post']['user']['twostep_email']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $warnings = [];

    // ワンタイムトークン
    if (!token('check')) {
        $warnings[] = '不正な操作が検出されました。送信内容を確認して再度実行してください。';
    }

    // アクセス元
    if (empty($_SERVER['HTTP_REFERER']) || !preg_match('/^' . preg_quote($GLOBALS['config']['http_url'], '/') . '/', $_SERVER['HTTP_REFERER'])) {
        $warnings[] = '不正なアクセスです。';
    }

    if (empty($warnings)) {
        if ($twostep_verify) {
            // コードの入力画面へ(コードはその画面を開いたときに送る)
            $_SESSION['twostep_verify'] = [
                'email'    => (string)$_SESSION['post']['user']['twostep_email'],
                'code'     => null,
                'expire'   => null,
                'failed'   => 0,
                'verified' => false,
            ];

            // リダイレクト
            redirect('/auth/modify_verify');
        } else {
            unset($_SESSION['twostep_verify']);

            // フォワード
            forward('/auth/modify_post');
        }
    } else {
        $_view['warnings'] = $warnings;
    }
}

$_view['user']           = $_SESSION['post']['user'];
$_view['twostep_verify'] = $twostep_verify;

// タイトル
$_view['title'] = 'ユーザー情報編集確認';
