<?php

import('app/services/user.php');

// 投稿データを確認（確認画面を通っていなければ、入力画面に戻す）
if (empty($_SESSION['post']['user']) || empty($_SESSION['twostep_verify']) || $_SESSION['twostep_verify']['email'] !== (string)$_SESSION['post']['user']['twostep_email']) {
    // リダイレクト
    redirect('/auth/modify');
}

// コードを送るか（画面を最初に開いたときと、再送信のとき）
$send = $_SESSION['twostep_verify']['code'] === null;

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
        if (isset($_POST['exec']) && $_POST['exec'] === 'resend') {
            // 2段階認証用コードを再送信
            $send = true;
        } else {
            // 2段階認証用コードを確認
            $twostep_code = isset($_POST['twostep_code']) ? trim($_POST['twostep_code']) : '';

            if ($twostep_code === '') {
                $warnings[] = '2段階認証用コードが入力されていません。';
            } elseif (!is_string($_SESSION['twostep_verify']['code'])) {
                // 続けて間違えて無効にした（false）。null はまだ送っていないことを表す
                $warnings[] = '2段階認証用コードが無効になっています。コードを再送信してください。';
            } elseif (!hash_equals($_SESSION['twostep_verify']['code'], $twostep_code)) {
                // 失敗した回数を記録する（上限に達したらコードを無効にする。このコードはセッションにあるので、回数もセッションに持つ）
                $_SESSION['twostep_verify']['failed'] = ($_SESSION['twostep_verify']['failed'] ?? 0) + 1;

                if ($_SESSION['twostep_verify']['failed'] >= $GLOBALS['config']['code_failed_limit']) {
                    $_SESSION['twostep_verify']['code']   = false;
                    $_SESSION['twostep_verify']['failed'] = 0;

                    $warnings[] = '2段階認証用コードを続けて間違えたため、このコードを無効にしました。コードを再送信してください。';
                } else {
                    $warnings[] = '2段階認証用コードが違います。';
                }
            } elseif ($_SESSION['twostep_verify']['expire'] < localdate()) {
                $warnings[] = '2段階認証用コードの有効期限が終了しています。コードを再送信してください。';
            } else {
                $_SESSION['twostep_verify']['verified'] = true;

                // フォワード
                forward('/auth/modify_post');
            }
        }
    }

    if (!empty($warnings)) {
        $_view['warnings'] = $warnings;
    }
}

// 2段階認証用コードを送信
if ($send) {
    $_SESSION['twostep_verify']['code']   = service_user_code();
    $_SESSION['twostep_verify']['expire'] = localdate() + 60 * 10;
    $_SESSION['twostep_verify']['failed'] = 0;

    // メールを送信
    service_user_twostep_mail($_SESSION['twostep_verify']['email'], $_SESSION['twostep_verify']['code']);
}

$_view['user'] = $_SESSION['post']['user'];

// タイトル
$_view['title'] = '2段階認証用メールアドレスの確認';
