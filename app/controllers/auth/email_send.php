<?php

import('app/services/user.php');
import('app/services/mail.php');

// トランザクションを開始
db_transaction();

// 認証用のトークンを発行(送るたびに作り直す。登録時のトークンは期限切れや、パスワード再設定で消えていることがあるため)
$resource = service_user_update([
    'set'   => [
        'token'        => rand_string(),
        'token_expire' => localdate('Y-m-d H:i:s', time() + 60 * 60 * 24),
    ],
    'where' => [
        'id = :id',
        [
            'id' => $_SESSION['auth']['user']['id'],
        ],
    ],
]);
if (!$resource) {
    error('データを編集できません。');
}

// トランザクションを終了
db_commit();

// メールアドレス認証用URLを通知
$users = model('select_users', [
    'select' => 'email, token',
    'where'  => [
        'id = :id',
        [
            'id' => $_SESSION['auth']['user']['id'],
        ],
    ],
]);

// 認証用URLを作成
$_view['url'] = $GLOBALS['config']['http_url'] . '/auth/email_verify?key=' . rawurlencode($users[0]['email']) . '&token=' . $users[0]['token'];

$to      = $users[0]['email'];
$subject = $GLOBALS['setting']['mail_verify_subject'];
$message = view('mail/email/verify.php', true);
$headers = [
    'From' => $GLOBALS['setting']['mail_from'],
];

// メールを送信
if (service_mail_send($to, $subject, $message, $headers) === false) {
    error('メールを送信できません。');
}

// リダイレクト
redirect('/auth/home?verify=send');
