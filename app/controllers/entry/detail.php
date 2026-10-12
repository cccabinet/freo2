<?php

import('app/services/entry.php');
import('app/services/comment.php');
import('app/services/recaptcha.php');

// 表示対象を取得
if (isset($_params[2])) {
    $_GET['code'] = $_params[2];
}
if (!isset($_GET['code']) || !is_string($_GET['code'])) {
    error('不正なアクセスです。');
}

// エントリーを取得
$entries = service_entry_select_published('entry', [
    'where' => [
        'entries.code = :code',
        [
            'code' => $_GET['code'],
        ],
    ],
]);
if (empty($entries)) {
    warning('エントリーが見つかりません。');
} else {
    $_view['entry'] = $entries[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ワンタイムトークン
    if (!token('check')) {
        error('不正なアクセスです。');
    }

    if (isset($_POST['exec']) && $_POST['exec'] === 'password') {
        // パスワード認証
        if (service_entry_password($entries[0], isset($_POST['password']) ? $_POST['password'] : '')) {
            // リダイレクト
            redirect(service_entry_url($entries[0]));
        } else {
            warning('パスワードが違います。');
        }
    } elseif (isset($_POST['exec']) && $_POST['exec'] === 'comment') {
        // コメントの受付を確認
        if (!service_comment_acceptable($entries[0])) {
            error('不正なアクセスです。');
        }

        // reCAPTCHA
        if (!service_recaptcha_verify()) {
            error('reCAPTCHAでの認証に失敗しました。');
        }

        // 入力データを整理
        $post = [
            'comment' => service_comment_normalize($_POST, $entries[0]),
        ];

        // 入力データを検証＆登録
        $warnings = model('validate_comments', $post['comment']);
        if (isset($_POST['_type']) && $_POST['_type'] === 'json') {
            if (empty($warnings)) {
                ok();
            } else {
                warning($warnings);
            }
        } else {
            if (empty($warnings)) {
                $_SESSION['post']['comment'] = $post['comment'];

                // リダイレクト
                redirect('/comment/preview');
            } else {
                $_view['comment'] = $post['comment'];

                $_view['warnings'] = $warnings;
            }
        }
    }
} elseif (isset($_GET['referer']) && $_GET['referer'] === 'preview') {
    // 入力データを復元
    $_view['comment'] = $_SESSION['post']['comment'];
} else {
    // 初期データを取得
    $_view['comment'] = service_comment_default();
}

// フィールドを取得
$_view['fields'] = model('select_fields', [
    'where'    => 'type_id = ' . intval($entries[0]['type_id']),
    'order_by' => 'sort, id',
]);

// コメントを取得
$_view['comments'] = service_comment_select_approved($entries[0]['id']);

// タイトル
if (isset($_view['entry']['title'])) {
    $_view['title'] = $_view['entry']['title'];
} else {
    $_view['title'] = 'エントリー';
}
