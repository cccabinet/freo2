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
    redirect('/admin/user_form');
}

// パスワードのソルトを作成
$password_salt = hash_salt();

// トランザクションを開始
db_transaction();

if (empty($_SESSION['post']['user']['id'])) {
    // ユーザーを登録
    $resource = service_user_insert([
        'values' => [
            'username'        => $_SESSION['post']['user']['username'],
            'password'        => hash_crypt($_SESSION['post']['user']['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            'password_salt'   => $password_salt,
            'authority_id'    => $_SESSION['post']['user']['authority_id'],
            'attribute_begin' => $_SESSION['post']['user']['attribute_begin'],
            'attribute_end'   => $_SESSION['post']['user']['attribute_end'],
            'enabled'         => $_SESSION['post']['user']['enabled'],
            'name'            => $_SESSION['post']['user']['name'],
            'email'           => $_SESSION['post']['user']['email'],
            'email_verified'  => 1,
            'url'             => $_SESSION['post']['user']['url'],
            'text'            => $_SESSION['post']['user']['text'],
            'memo'            => $_SESSION['post']['user']['memo'],
            'twostep'         => $_SESSION['post']['user']['twostep'],
            'twostep_email'   => $_SESSION['post']['user']['twostep_email'],
        ],
    ], [
        'attribute_sets' => $_SESSION['post']['user']['attribute_sets'],
    ]);
    if (!$resource) {
        error('データを登録できません。');
    }
} else {
    // 変更前の2段階認証の設定を取得
    $users = model('select_users', [
        'select' => 'twostep, twostep_email',
        'where'  => [
            'id = :id',
            [
                'id' => $_SESSION['post']['user']['id'],
            ],
        ],
    ]);
    if (empty($users)) {
        error('編集データが見つかりません。');
    }

    // ユーザーを編集
    $sets = [
        'username'        => $_SESSION['post']['user']['username'],
        'authority_id'    => $_SESSION['post']['user']['authority_id'],
        'attribute_begin' => $_SESSION['post']['user']['attribute_begin'],
        'attribute_end'   => $_SESSION['post']['user']['attribute_end'],
        'enabled'         => $_SESSION['post']['user']['enabled'],
        'name'            => $_SESSION['post']['user']['name'],
        'email'           => $_SESSION['post']['user']['email'],
        'url'             => $_SESSION['post']['user']['url'],
        'text'            => $_SESSION['post']['user']['text'],
        'memo'            => $_SESSION['post']['user']['memo'],
        'twostep'         => $_SESSION['post']['user']['twostep'],
        'twostep_email'   => $_SESSION['post']['user']['twostep_email'],
    ];
    if (!empty($_SESSION['post']['user']['password'])) {
        $sets['password']      = hash_crypt($_SESSION['post']['user']['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']);
        $sets['password_salt'] = $password_salt;
    }
    $resource = service_user_update([
        'set'   => $sets,
        'where' => [
            'id = :id',
            [
                'id' => $_SESSION['post']['user']['id'],
            ],
        ],
    ], [
        'id'             => intval($_SESSION['post']['user']['id']),
        'update'         => $_SESSION['update']['user'],
        'attribute_sets' => $_SESSION['post']['user']['attribute_sets'],
    ]);
    if (!$resource) {
        error('データを編集できません。');
    }

    // 2段階認証を無効にしたか、2段階認証用メールアドレスを変えたら、コードの入力を省略しているブラウザを解除
    if ((string)$_SESSION['post']['user']['twostep'] !== '1' || (string)$users[0]['twostep_email'] !== (string)$_SESSION['post']['user']['twostep_email']) {
        service_user_twostep_reset($_SESSION['post']['user']['id']);
    }
}

// トランザクションを終了
db_commit();

// 投稿セッションを初期化
unset($_SESSION['post']);
unset($_SESSION['update']);

// リダイレクト
redirect('/admin/user?ok=post');
