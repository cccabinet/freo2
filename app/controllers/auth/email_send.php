<?php

import('app/services/user.php');

// トランザクションを開始
db_transaction();

// 認証用のトークンを発行（送るたびに作り直す。パスワード再設定と共用しているので、前のトークンは消えていることがあるため）
$user = service_user_email_issue($_SESSION['auth']['user']['id']);

// トランザクションを終了
db_commit();

// メールアドレス認証用URLを通知
service_user_email_mail($user['email'], $user['token']);

// リダイレクト
redirect('/auth/home?verify=send');
