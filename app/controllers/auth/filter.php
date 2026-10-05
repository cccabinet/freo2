<?php

// フィルターで選択できる属性（app/controllers/before.php で取得済み）
$filterable_ids = array_column($GLOBALS['attribute_filterables'] ?? [], 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ワンタイムトークン
    if (!token('check')) {
        error('不正な操作が検出されました。送信内容を確認して再度実行してください。');
    }

    // アクセス元
    if (empty($_SERVER['HTTP_REFERER']) || !preg_match('/^' . preg_quote($GLOBALS['config']['http_url'], '/') . '/', $_SERVER['HTTP_REFERER'])) {
        error('不正なアクセスです。');
    }

    // 表示を選択した属性（フィルターで選択できる属性だけを残す）
    $shown_ids = [];
    if (isset($_POST['attribute_filters']) && is_array($_POST['attribute_filters'])) {
        foreach ($_POST['attribute_filters'] as $attribute_id) {
            if (is_string($attribute_id) && preg_match('/^\d+$/', $attribute_id) && in_array(intval($attribute_id), $filterable_ids)) {
                $shown_ids[] = intval($attribute_id);
            }
        }
    }

    // 選択内容を保存
    service_attribute_filter_set($_SESSION['auth']['user']['id'], $shown_ids);

    // リダイレクト
    redirect('/auth/filter?ok=post');
}

// 属性を取得
$_view['attributes']        = $GLOBALS['attribute_filterables'] ?? [];
$_view['attribute_filters'] = service_attribute_filter_get($_SESSION['auth']['user']['id']);

// タイトル
$_view['title'] = 'フィルター';
