<?php

import('app/services/user.php');
import('libs/modules/hash.php');

// フォワードを確認
if (forward() === null) {
    error('不正なアクセスです。');
}

// 投稿データを確認
if (empty($_SESSION['post'])) {
    // リダイレクト
    redirect('/auth/modify');
}

// パスワードのソルトを作成
$password_salt = hash_salt();

// 属性を取得
$attribute_sets = model('select_attribute_sets', [
    'where' => [
        'user_id = :id',
        [
            'id' => $_SESSION['auth']['user']['id'],
        ],
    ],
]);
$attribute_sets = array_column($attribute_sets, 'attribute_id');

// 変更前の2段階認証の設定を取得
$users = model('select_users', [
    'select' => 'twostep_email',
    'where'  => [
        'id = :id AND enabled = 1',
        [
            'id' => $_SESSION['auth']['user']['id'],
        ],
    ],
]);
if (empty($users)) {
    error('編集データが見つかりません。');
}

// 2段階認証用メールアドレスに、コードが届くことを確認したか（確認画面の後のコード入力画面で確認する）
if (service_user_twostep_verify_required($_SESSION['auth']['user']['id'], $_SESSION['post']['user']['twostep'], $_SESSION['post']['user']['twostep_email'])) {
    if (empty($_SESSION['twostep_verify']['verified']) || $_SESSION['twostep_verify']['email'] !== (string)$_SESSION['post']['user']['twostep_email']) {
        error('2段階認証用メールアドレスの確認が済んでいません。');
    }
}

// トランザクションを開始
db_transaction();

// ユーザーを編集
$sets = [
    'username'      => $_SESSION['post']['user']['username'],
    'name'          => $_SESSION['post']['user']['name'],
    'email'         => $_SESSION['post']['user']['email'],
    'url'           => $_SESSION['post']['user']['url'],
    'text'          => $_SESSION['post']['user']['text'],
    'twostep'       => $_SESSION['post']['user']['twostep'],
    'twostep_email' => $_SESSION['post']['user']['twostep_email'],
];
if (!empty($_SESSION['post']['user']['password'])) {
    $sets['password']      = hash_crypt($_SESSION['post']['user']['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']);
    $sets['password_salt'] = $password_salt;
}
$resource = service_user_update([
    'set'   => $sets,
    'where' => [
        'id = :id AND enabled = 1',
        [
            'id' => $_SESSION['auth']['user']['id'],
        ],
    ],
], [
    'id'             => intval($_SESSION['auth']['user']['id']),
    'update'         => $_SESSION['update']['user'],
    'attribute_sets' => $attribute_sets,
]);
if (!$resource) {
    error('データを編集できません。');
}

// 2段階認証を無効にしたか、2段階認証用メールアドレスを変えたら、コードの入力を省略しているブラウザを解除
if (service_user_twostep_reset_required($_SESSION['post']['user']['twostep'], $_SESSION['post']['user']['twostep_email'], $users[0]['twostep_email'])) {
    service_user_twostep_reset($_SESSION['auth']['user']['id']);
}

// トランザクションを終了
db_commit();

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['update']);
unset($_SESSION['twostep_verify']);

// リダイレクト
redirect('/auth/modify_complete');
