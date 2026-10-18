<?php

import('app/services/user.php');

// 権限を確認
if (isset($GLOBALS['authority'])) {
    // リダイレクト
    redirect('/auth/');
}

// フォワードを確認
if (forward() === null) {
    error('不正なアクセスです。');
}

// 投稿データを確認
if (empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/auth/password');
}

// トランザクションを開始
db_transaction();

// ユーザーを編集
$resource = service_user_update([
    'set'   => [
        'password'      => service_user_password_hash($_SESSION['post']['user']['password']),
        'token'         => null,
        'token_code'    => null,
        'token_expire'  => null,
        'token_failed'  => null,
    ],
    'where' => [
        'email = :email',
        [
            'email' => $_SESSION['post']['user']['key'],
        ],
    ],
], [
    'id'     => intval($_SESSION['post']['user']['id']),
    'update' => $_SESSION['update']['user'],
]);
if (!$resource) {
    error('データを編集できません。');
}

// トランザクションを終了
db_commit();

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['update']);

// リダイレクト
redirect('/auth/password_complete');
