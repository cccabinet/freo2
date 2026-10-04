<?php

import('app/services/entry.php');

// エントリーを取得
$entries = model('select_entries', [
    'where'    => 'types.code = ' . db_escape('page'),
    'order_by' => 'entries.code, entries.id',
], [
    'associate' => true,
]);

// 親ページを確認
$codes  = array_column($entries, 'code');
$parent = (isset($_GET['parent']) && $_GET['parent'] !== '') ? $_GET['parent'] : null;
if ($parent !== null && !in_array($parent, $codes, true)) {
    error('親ページが見つかりません。');
}

// 表示する階層のページと、子ページの数を取得
$_view['entries']  = [];
$_view['children'] = [];
foreach ($entries as $entry) {
    $entry_parent = service_entry_parent($entry['code'], $codes);

    if ($entry_parent === $parent) {
        $_view['entries'][] = $entry;
    }
    if ($entry_parent !== null) {
        $_view['children'][$entry_parent] = isset($_view['children'][$entry_parent]) ? $_view['children'][$entry_parent] + 1 : 1;
    }
}

// パンくずに表示する親ページを取得
$_view['parent']  = $parent;
$_view['parents'] = service_entry_page_parents($parent);

// カテゴリーを取得
$_view['categories'] = model('select_categories', [
    'where'    => 'types.code = ' .  db_escape('page'),
    'order_by' => 'categories.sort, categories.id',
], [
    'associate' => true,
]);

// タイトル
$_view['title'] = 'ページ管理';
