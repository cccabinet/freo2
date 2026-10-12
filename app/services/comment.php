<?php

import('app/services/log.php');
import('libs/modules/recaptcha.php');

/**
 * コメントを受け付けるか
 *
 * @param array $entry
 *
 * @return bool
 */
function service_comment_acceptable($entry)
{
    if ($entry['comment'] === 'opened') {
        return true;
    }
    if ($entry['comment'] === 'user' && !empty($_SESSION['auth']['user']['id'])) {
        return true;
    }

    return false;
}

/**
 * reCAPTCHAの確認
 *
 * reCAPTCHAが無効なら確認しない。一度通ったら、同じセッションでは確認しない
 *
 * @return bool
 */
function service_comment_recaptcha()
{
    if ($GLOBALS['config']['recaptcha_enable'] != true || !empty($_SESSION['recaptcha'])) {
        return true;
    }

    if (!recaptcha_verify($GLOBALS['config']['recaptcha_secret_key'])) {
        return false;
    }

    $_SESSION['recaptcha'] = true;

    return true;
}

/**
 * 公開側の投稿データを整理
 *
 * コメント先は、送信された値ではなく表示しているエントリーにする
 *
 * @param array $data
 * @param array $entry
 *
 * @return array
 */
function service_comment_normalize($data, $entry)
{
    return model('normalize_comments', [
        'entry_id' => $entry['id'],
        'name'     => isset($data['name'])    ? $data['name']    : '',
        'url'      => isset($data['url'])     ? $data['url']     : '',
        'message'  => isset($data['message']) ? $data['message'] : '',
    ]);
}

/**
 * 公開側の入力フォームの初期値を取得
 *
 * ログインしていれば、お名前とURLにユーザーの情報を入れる
 *
 * @return array
 */
function service_comment_default()
{
    $comment = model('default_comments');

    if (!empty($_SESSION['auth']['user']['id'])) {
        $users = model('select_users', [
            'where' => [
                'id = :id AND enabled = 1',
                [
                    'id' => $_SESSION['auth']['user']['id'],
                ],
            ],
        ]);
        if (!empty($users)) {
            $comment['name'] = $users[0]['name'];
            $comment['url']  = $users[0]['url'];
        }
    }

    return $comment;
}

/**
 * エントリーの承認済みコメントを取得
 *
 * @param int $entry_id
 *
 * @return array
 */
function service_comment_select_approved($entry_id)
{
    return model('select_comments', [
        'where'    => [
            'comments.approved = 1 AND comments.entry_id = :entry_id',
            [
                'entry_id' => $entry_id,
            ],
        ],
        'order_by' => 'comments.id',
    ], [
        'associate' => true,
    ]);
}

/**
 * コメントの登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_comment_insert($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'comments', 'insert');

    // ユーザー
    if (!empty($_SESSION['auth']['user']['id'])) {
        $queries['values']['user_id'] = $_SESSION['auth']['user']['id'];
    }

    // コメントを登録
    $resource = model('insert_comments', $queries, $options);
    if (!$resource) {
        error('データを登録できません。');
    }

    return $resource;
}

/**
 * コメントの編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_comment_update($queries, $options = [])
{
    $options = [
        'id'     => isset($options['id'])     ? $options['id']     : null,
        'update' => isset($options['update']) ? $options['update'] : null,
    ];

    // 最終編集日時を確認
    if (isset($options['id']) && isset($options['update']) && (!isset($queries['set']['modified']) || $queries['set']['modified'] !== false)) {
        $comments = model('select_comments', [
            'where' => [
                'id = :id AND modified > :update',
                [
                    'id'     => $options['id'],
                    'update' => $options['update'],
                ],
            ],
        ]);
        if (!empty($comments)) {
            error('編集開始後にデータが更新されています。');
        }
    }

    // 操作ログの記録
    service_log_record(null, 'comments', 'update');

    // コメントを編集
    $resource = model('update_comments', $queries, $options);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $resource;
}

/**
 * コメントの削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_comment_delete($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'comments', 'delete');

    // コメントを削除
    $resource = model('delete_comments', $queries, $options);
    if (!$resource) {
        error('データを削除できません。');
    }

    return $resource;
}
