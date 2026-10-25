<?php

import('app/services/log.php');
import('app/services/storage.php');

/**
 * 公開エントリーの取得
 *
 * @param string $type
 * @param array  $queries
 * @param array  $options
 *
 * @return array
 */
function service_entry_select_published($type, $queries, $options = [])
{
    $authority_power = isset($GLOBALS['authority']['power']) ? $GLOBALS['authority']['power'] : null;
    $attributes      = isset($GLOBALS['attributes'])         ? $GLOBALS['attributes']         : null;

    // エントリーの絞り込み
    if (empty($queries['where'])) {
        $where1 = 'TRUE';
        $where2 = [];
    } elseif (!is_array($queries['where'])) {
        $where1 = $queries['where'];
        $where2 = [];
    } else {
        $where1 = $queries['where'][0];
        $where2 = $queries['where'][1];
    }

    if ($authority_power >= 1) {
        // 閲覧者以上: 「非公開」以外のエントリーを表示
        $public = ' AND entries.public != ' . db_escape('none');
    } elseif ($authority_power === 0) {
        // ゲスト: 「全体に公開」のエントリー、もしくは「登録ユーザーに公開」のエントリー、もしくは「指定の属性に公開」で指定の属性を持つエントリーを表示
        $public = ' AND (entries.public = ' . db_escape('all') . ' OR entries.public = ' . db_escape('user') . ' OR (entries.public = ' . db_escape('attribute') . ' AND attribute_sets.attribute_id IN(' . ($attributes ? implode(',', $attributes) : 0) . ')) OR entries.public = ' . db_escape('password') . ')';
    } else {
        // ユーザー登録なし: 「全体に公開」のエントリーを表示
        $public = ' AND (entries.public = ' . db_escape('all') . ' OR entries.public = ' . db_escape('password') . ')';
    }

    $where1 .= ' AND types.code = :type_code' . $public . ' AND entries.approved = 1 AND (entries.public_begin IS NULL OR entries.public_begin <= :now) AND (entries.public_end IS NULL OR entries.public_end >= :now)';
    $where2['type_code'] = $type;
    $where2['now']       = localdate('Y-m-d H:i:s');

    $queries['where'] = [$where1, $where2];

    // エントリーを取得
    $entries = model('select_entries', $queries, [
        'associate' => true,
    ]);

    // エントリーの表示制限
    $temps = [];
    foreach ($entries as $entry) {
        if (isset($entry['id'])) {
            if ($entry['public'] === 'password' && empty($_SESSION['entry_passwords'][$entry['id']])) {
                $entry['title']     = $GLOBALS['setting']['restricted_password_title'] . $entry['title'];
                $entry['text']      = $GLOBALS['setting']['restricted_password_text'];
                $entry['pictures']  = null;
                $entry['thumbnail'] = null;
            } else {
                if ($entry['text_type'] === 'none') {
                    $entry['text'] = null;
                } elseif ($entry['text_type'] === 'textarea') {
                    $entry['text'] = '<p>' . h($entry['text'], true) . '</p>';
                }

                if (!empty($entry['pictures'])) {
                    $entry['pictures'] = explode("\n", $entry['pictures']);
                }
            }
        }
        $temps[] = $entry;
    }
    $entries = $temps;

    return $entries;
}

/**
 * 公開側の詳細ページのURLを取得
 *
 * ページは /page/<コード>、それ以外の型は /<型コード>/detail/<コード>
 *
 * @param array $entry
 *
 * @return string
 */
function service_entry_url($entry)
{
    if ($entry['type_code'] === 'page') {
        return '/page/' . $entry['code'];
    }

    return '/' . $entry['type_code'] . '/detail/' . $entry['code'];
}

/**
 * パスワード認証
 *
 * 認証に成功したら、セッションに記録する
 *
 * @param array  $entry
 * @param string $password
 *
 * @return bool
 */
function service_entry_password($entry, $password)
{
    if ($password !== $entry['password']) {
        return false;
    }

    $_SESSION['entry_passwords'][$entry['id']] = true;

    return true;
}

/**
 * エントリーの登録
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_entry_insert($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'entries', 'insert');

    // ユーザー
    if (!empty($_SESSION['auth']['user']['id'])) {
        $queries['values']['user_id'] = $_SESSION['auth']['user']['id'];
    }

    // エントリーを登録
    $resource = model('insert_entries', $queries, $options);
    if (!$resource) {
        error('データを登録できません。');
    }

    return $resource;
}

/**
 * エントリーの編集
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_entry_update($queries, $options = [])
{
    $options = [
        'id'             => isset($options['id'])             ? $options['id']             : null,
        'field_sets'     => isset($options['field_sets'])     ? $options['field_sets']     : [],
        'category_sets'  => isset($options['category_sets'])  ? $options['category_sets']  : [],
        'attribute_sets' => isset($options['attribute_sets']) ? $options['attribute_sets'] : [],
        'files'          => isset($options['files'])          ? $options['files']          : [],
        'picture_files'  => isset($options['picture_files'])  ? $options['picture_files']  : [],
        'update'         => isset($options['update'])         ? $options['update']         : null,
    ];

    // 最終編集日時を確認
    if (isset($options['id']) && isset($options['update']) && (!isset($queries['set']['modified']) || $queries['set']['modified'] !== false)) {
        $entries = model('select_entries', [
            'where' => [
                'id = :id AND modified > :update',
                [
                    'id'     => $options['id'],
                    'update' => $options['update'],
                ],
            ],
        ]);
        if (!empty($entries)) {
            error('編集開始後にデータが更新されています。');
        }
    }

    // 操作ログの記録
    service_log_record(null, 'entries', 'update');

    // エントリーを編集
    $resource = model('update_entries', $queries, $options);
    if (!$resource) {
        error('データを編集できません。');
    }

    return $resource;
}

/**
 * エントリーの削除
 *
 * @param array $queries
 * @param array $options
 *
 * @return resource
 */
function service_entry_delete($queries, $options = [])
{
    // 操作ログの記録
    service_log_record(null, 'entries', 'delete');

    // エントリーを削除
    $resource = model('delete_entries', $queries, $options);
    if (!$resource) {
        error('データを削除できません。');
    }

    return $resource;
}

/**
 * エントリーの並び順を一括変更
 *
 * @param array $data
 *
 * @return void
 */
function service_entry_sort($data)
{
    // 並び順を更新
    foreach ($data as $id => $sort) {
        if (!preg_match('/^[\w\-\/]+$/', $id)) {
            continue;
        }
        if (!preg_match('/^\d+$/', $sort)) {
            continue;
        }

        $resource = service_entry_update([
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
 * 親のコードを取得
 *
 * コードをスラッシュで区切った階層を末尾から遡り、$codes に含まれる最も近い祖先を親とする
 * （about/staff が無ければ、about/staff/yamada の親は about）
 *
 * @param string $code
 * @param array  $codes
 *
 * @return string|null
 */
function service_entry_parent($code, $codes)
{
    $position = strrpos($code, '/');
    while ($position !== false) {
        $code = substr($code, 0, $position);
        if (in_array($code, $codes, true)) {
            return $code;
        }
        $position = strrpos($code, '/');
    }

    return null;
}

/**
 * ページを一括取得
 *
 * @param string $select
 *
 * @return array
 */
function service_entry_select_pages($select)
{
    $types = model('select_types', [
        'select' => 'id',
        'where'  => 'code = ' . db_escape('page'),
    ]);
    if (empty($types)) {
        return [];
    }

    return model('select_entries', [
        'select' => $select,
        'where'  => [
            'type_id = :type_id',
            [
                'type_id' => $types[0]['id'],
            ],
        ],
    ]);
}

/**
 * ページのコードを一括取得
 *
 * @return array
 */
function service_entry_page_codes()
{
    return array_column(service_entry_select_pages('code'), 'code');
}

/**
 * 親ページを階層順に取得
 *
 * $parent と、その祖先のページを一覧のトップに近い順に返す（パンくずに使う）
 *
 * @param string|null $parent
 *
 * @return array
 */
function service_entry_page_parents($parent)
{
    $parents = [];
    if ($parent === null) {
        return $parents;
    }

    $entries = service_entry_select_pages('code, title');
    $codes   = array_column($entries, 'code');
    $titles  = array_column($entries, 'title', 'code');

    for ($code = $parent; $code !== null; $code = service_entry_parent($code, $codes)) {
        if (!isset($titles[$code])) {
            break;
        }
        array_unshift($parents, [
            'code'  => $code,
            'title' => $titles[$code],
        ]);
    }

    return $parents;
}

/**
 * ページ一覧のURLを取得
 *
 * @param string      $query
 * @param string|null $parent
 *
 * @return string
 */
function service_entry_page_list($query, $parent)
{
    $url = '/admin/page?' . $query;
    if ($parent !== null) {
        $url .= '&parent=' . rawurlencode($parent);
    }

    return $url;
}

/**
 * アップロードされたファイルを一時ファイルに保存（セッションには、返した名前と一時ファイルの場所だけを入れる）
 *
 * @param string $name     ファイル名
 * @param string $tmp_name アップロードされたファイルの場所
 *
 * @return array|false ['name' => ファイル名, 'temp' => 一時ファイルの場所]、保存できなければ false
 */
function service_entry_file_upload($name, $tmp_name)
{
    // 古い一時ファイルを削除
    service_storage_temp_clean();

    // ファイル名は利用者の入力なので、一時ファイルの名前には使わない
    $temp = $GLOBALS['config']['file_target']['temp'] . session_id() . '_entry_' . bin2hex(random_bytes(16));

    if (service_storage_put($temp, file_get_contents($tmp_name)) === false) {
        return false;
    }

    return [
        'name' => $name,
        'temp' => $temp,
    ];
}

/**
 * アップロードされたファイルの内容を取得（一時ファイルから読む。以前の形でセッションに内容があれば、それを返す）
 *
 * @param array $file
 *
 * @return string|null
 */
function service_entry_file_data($file)
{
    if (isset($file['temp'])) {
        return service_storage_exist($file['temp']) ? service_storage_get($file['temp']) : null;
    }

    return isset($file['data']) ? $file['data'] : null;
}

/**
 * アップロードされたファイルの情報を破棄（一時ファイルも削除する）
 *
 * @return void
 */
function service_entry_file_clear()
{
    if (isset($_SESSION['file'])) {
        array_walk_recursive($_SESSION['file'], function ($value, $key) {
            if ($key === 'temp' && service_storage_exist($value)) {
                service_storage_remove($value);
            }
        });
    }

    unset($_SESSION['file']);
}
