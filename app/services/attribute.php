<?php

import('app/services/log.php');
import('libs/modules/cookie.php');

/**
 * 属性の登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_attribute_insert($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'attributes', 'insert');

    // 属性を登録
    $resource = model('insert_attributes', $queries, $options);
    if (!$resource) {
        error('データを登録できません。');
    }

    return $resource;
}

/**
 * 属性の編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_attribute_update($queries, $options = [])
{
    $options = [
        'id'     => isset($options['id'])     ? $options['id']     : null,
        'update' => isset($options['update']) ? $options['update'] : null,
    ];

    // 最終編集日時を確認
    if (isset($options['id']) && isset($options['update']) && (!isset($queries['set']['modified']) || $queries['set']['modified'] !== false)) {
        $attributes = model('select_attributes', [
            'where' => [
                'id = :id AND modified > :update',
                [
                    'id'     => $options['id'],
                    'update' => $options['update'],
                ],
            ],
        ]);
        if (!empty($attributes)) {
            error('編集開始後にデータが更新されています。');
        }
    }

    // 操作ログの記録
    service_log_record(null, 'attributes', 'update');

    // 属性を編集
    $resource = model('update_attributes', $queries, $options);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $resource;
}

/**
 * 属性の削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_attribute_delete($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'attributes', 'delete');

    // 属性を削除
    $resource = model('delete_attributes', $queries, $options);
    if (!$resource) {
        error('データを削除できません。');
    }

    return $resource;
}

/**
 * 属性の並び順を一括変更
 *
 * @param array $data
 *
 * @return void
 */
function service_attribute_sort($data)
{
    // 並び順を更新
    foreach ($data as $id => $sort) {
        if (!preg_match('/^[\w\-\/]+$/', $id)) {
            continue;
        }
        if (!preg_match('/^\d+$/', $sort)) {
            continue;
        }

        $resource = service_attribute_update([
            'set'   => [
                'sort' => $sort,
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $id,
                ],
            ],
        ]);
        if (!$resource) {
            error('データを編集できません。');
        }
    }

    return;
}

/**
 * フィルターで選択できる属性の取得
 *
 * 与えられた属性のうち、フィルター対象のものを返す。
 *
 * @param array $attribute_ids
 *
 * @return array
 */
function service_attribute_filterable($attribute_ids)
{
    if (empty($attribute_ids)) {
        return [];
    }

    // 属性を取得
    $attributes = model('select_attributes', [
        'where'    => 'filterable = 1 AND id IN(' . implode(',', array_map('intval', $attribute_ids)) . ')',
        'order_by' => 'sort, id',
    ]);

    return $attributes;
}

/**
 * フィルターの適用
 *
 * 与えられた属性から、フィルター対象で表示を選択していないものを除く。
 * 戻り値は与えられた属性の一部だけなので、表示を選択した属性に何が入っていても、見える範囲は広がらない。
 *
 * @param array $attribute_ids
 * @param array $filterable_ids
 * @param array $shown_ids
 *
 * @return array
 */
function service_attribute_filter($attribute_ids, $filterable_ids, $shown_ids)
{
    $results = [];
    foreach ($attribute_ids as $attribute_id) {
        if (in_array($attribute_id, $filterable_ids) && !in_array($attribute_id, $shown_ids)) {
            continue;
        }

        $results[] = $attribute_id;
    }

    return $results;
}

/**
 * フィルターで表示を選択した属性の取得
 *
 * クッキーはユーザーごとに分けて持つ（1つのブラウザを複数のユーザーで使う場合に、ほかのユーザーの選択が効かないようにするため）。
 *
 * @param int $user_id
 *
 * @return array
 */
function service_attribute_filter_get($user_id)
{
    if (!isset($_COOKIE['attribute_filter']) || !is_array($_COOKIE['attribute_filter'])) {
        return [];
    }
    if (!isset($_COOKIE['attribute_filter'][$user_id]) || !is_string($_COOKIE['attribute_filter'][$user_id])) {
        return [];
    }

    $attribute_ids = [];
    foreach (explode(',', $_COOKIE['attribute_filter'][$user_id]) as $attribute_id) {
        if (preg_match('/^\d+$/', $attribute_id)) {
            $attribute_ids[] = intval($attribute_id);
        }
    }

    return $attribute_ids;
}

/**
 * フィルターで表示を選択した属性の保存
 *
 * @param int   $user_id
 * @param array $attribute_ids
 *
 * @return void
 */
function service_attribute_filter_set($user_id, $attribute_ids)
{
    cookie_set('attribute_filter[' . intval($user_id) . ']', implode(',', array_map('intval', $attribute_ids)), localdate() + $GLOBALS['config']['cookie_expire'], $GLOBALS['config']['cookie_path'], $GLOBALS['config']['cookie_domain'], $GLOBALS['config']['cookie_secure']);

    return;
}
