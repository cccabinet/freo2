<?php

import('app/services/user.php');

// トランザクションを開始
db_transaction();

// 認証用URLを確認して、メールアドレスを確認済みにする
if (!service_user_email_verify(isset($_GET['key']) ? $_GET['key'] : '', isset($_GET['token']) ? $_GET['token'] : '')) {
    error('URLの有効期限が終了しているか、URLが正しくありません。確認のメールを送信し直してください。');
}

// トランザクションを終了
db_commit();

// リダイレクト
redirect('/auth/email_verified');
