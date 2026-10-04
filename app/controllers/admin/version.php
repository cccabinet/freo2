<?php

// タイトル
$_view['title'] = 'バージョン情報';

// Webサーバー
$server_version = $_SERVER['SERVER_SOFTWARE'];

if (preg_match('/^(\S+)/', $server_version, $matches)) {
    $server_version = str_replace('/', ' ', $matches[1]);
}

// PHP
$php_version = 'PHP ' . PHP_VERSION;

// 画像処理（メディアのサムネイルを作成できる形式を確認する）
$gd_formats = [];

foreach ([
    'GIF'  => ['imagecreatefromgif', 'imagegif'],
    'JPEG' => ['imagecreatefromjpeg', 'imagejpeg'],
    'PNG'  => ['imagecreatefrompng', 'imagepng'],
] as $format => $functions) {
    $available = true;

    foreach (array_merge(['imagecreatetruecolor', 'imagecopyresampled'], $functions) as $function) {
        if (!function_exists($function)) {
            $available = false;

            break;
        }
    }

    if ($available) {
        $gd_formats[] = $format;
    }
}

if (empty($gd_formats)) {
    $gd_version = '利用できません（メディアのサムネイルは作成されません）';
} else {
    $gd_info = [];
    if (function_exists('gd_info')) {
        $gd_info = gd_info();
    }

    if (empty($gd_info['GD Version'])) {
        $gd_version = 'GD';
    } else {
        $gd_version = 'GD ' . $gd_info['GD Version'];
    }

    $gd_version .= ' / ' . implode('・', $gd_formats);
}

// データベース
$resource = db_query('SELECT VERSION() AS version;');
$results  = db_result($resource);

$database_version = $results[0]['version'];

if (preg_match('/^([^\-]+)\-MariaDB/', $database_version, $matches)) {
    $database_version = 'MariaDB ' . $matches[1];
} else {
    $database_version = 'MySQL ' . $database_version;
}

// バージョン情報
$_view['version_info'] = [
    'server_version'   => $server_version,
    'php_version'      => $php_version,
    'gd_version'       => $gd_version,
    'database_version' => $database_version,
];
