<?php

import('app/services/user.php');

// 認証用URLを確認(一致しなくても更新は0件で成功するので、先に確認する)
$users = model('select_users', [
    'select' => 'id',
    'where'  => [
        'email = :email AND token = :token AND token_expire > :token_expire',
        [
            'email'        => isset($_GET['key'])   ? $_GET['key']   : '',
            'token'        => isset($_GET['token']) ? $_GET['token'] : '',
            'token_expire' => localdate('Y-m-d H:i:s'),
        ],
    ],
]);
if (empty($users)) {
    error('URLの有効期限が終了しているか、URLが正しくありません。確認のメールを送信し直してください。');
}

// トランザクションを開始
db_transaction();

// ユーザーを編集
$resource = service_user_update([
    'set'   => [
        'email_verified' => 1,
        'token'          => null,
        'token_expire'   => null,
    ],
    'where' => [
        'id = :id',
        [
            'id' => $users[0]['id'],
        ],
    ],
]);
if (!$resource) {
    error('データを編集できません。');
}

// トランザクションを終了
db_commit();

// リダイレクト
redirect('/auth/email_verified');
