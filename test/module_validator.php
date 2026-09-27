<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
// 検証はデータベースを使わないので、トランザクションもテーブルの初期化も行わない
import('libs/modules/validator.php');

// 必須テスト
{
    // 確認
    $value = validator_required('あ');
    $zero  = validator_required('0');
    $empty = validator_required('');
    $space = validator_required(' ');

    // 結果（空文字だけが不可。'0' や空白は入力されたものとして扱う）
    test_equals('validator_required', $value, true);
    test_equals('validator_required (zero)', $zero, true);
    test_equals('validator_required (empty)', $empty, false);
    test_equals('validator_required (space)', $space, true);
}

// 最小長テスト
{
    // 確認（文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $boundary = validator_min_length(str_repeat('あ', 3), 3);
    $short    = validator_min_length(str_repeat('あ', 2), 3);

    // 結果
    test_equals('validator_min_length (boundary)', $boundary, true);
    test_equals('validator_min_length', $short, false);
}

// 最大長テスト
{
    // 確認
    $boundary = validator_max_length(str_repeat('あ', 3), 3);
    $long     = validator_max_length(str_repeat('あ', 4), 3);

    // 結果
    test_equals('validator_max_length (boundary)', $boundary, true);
    test_equals('validator_max_length', $long, false);
}

// 範囲の長さテスト
{
    // 確認
    $min   = validator_between(str_repeat('あ', 2), 2, 4);
    $max   = validator_between(str_repeat('あ', 4), 2, 4);
    $short = validator_between(str_repeat('あ', 1), 2, 4);
    $long  = validator_between(str_repeat('あ', 5), 2, 4);

    // 結果
    test_equals('validator_between (min boundary)', $min, true);
    test_equals('validator_between (max boundary)', $max, true);
    test_equals('validator_between (short)', $short, false);
    test_equals('validator_between (long)', $long, false);
}

// 英字テスト
{
    // 確認
    $alpha     = validator_alpha('abcABC');
    $underbar  = validator_alpha('abc_ABC');
    $number    = validator_alpha('abc1');
    $multibyte = validator_alpha('あ');

    // 結果（名前は英字だが、アンダーバーも許可している）
    test_equals('validator_alpha', $alpha, true);
    test_equals('validator_alpha (underbar)', $underbar, true);
    test_equals('validator_alpha (number)', $number, false);
    test_equals('validator_alpha (multibyte)', $multibyte, false);
}

// 数字テスト
{
    // 確認
    $number   = validator_numeric('0123');
    $minus    = validator_numeric('-1');
    $decimal  = validator_numeric('1.5');
    $fullsize = validator_numeric('１');

    // 結果（半角数字だけを許可する）
    test_equals('validator_numeric', $number, true);
    test_equals('validator_numeric (minus)', $minus, false);
    test_equals('validator_numeric (decimal)', $decimal, false);
    test_equals('validator_numeric (fullsize)', $fullsize, false);
}

// 数値テスト
{
    // 確認
    $number     = validator_decimal('123');
    $minus      = validator_decimal('-1.5');
    $exponent   = validator_decimal('1e3');
    $multibyte  = validator_decimal('あ');
    $empty      = validator_decimal('');

    // 結果（マイナスや小数、指数表記も数値として扱う）
    test_equals('validator_decimal', $number, true);
    test_equals('validator_decimal (minus)', $minus, true);
    test_equals('validator_decimal (exponent)', $exponent, true);
    test_equals('validator_decimal (multibyte)', $multibyte, false);
    test_equals('validator_decimal (empty)', $empty, false);
}

// 英数字テスト
{
    // 確認
    $alpha_numeric = validator_alpha_numeric('abc123');
    $underbar      = validator_alpha_numeric('abc_123');
    $dash          = validator_alpha_numeric('abc-123');

    // 結果（\w なのでアンダーバーは通り、ダッシュは通らない）
    test_equals('validator_alpha_numeric', $alpha_numeric, true);
    test_equals('validator_alpha_numeric (underbar)', $underbar, true);
    test_equals('validator_alpha_numeric (dash)', $dash, false);
}

// 英数字・アンダーバー・ダッシュテスト
{
    // 確認
    $alpha_dash = validator_alpha_dash('abc-123_ABC');
    $slash      = validator_alpha_dash('abc/123');
    $multibyte  = validator_alpha_dash('あ');

    // 結果
    test_equals('validator_alpha_dash', $alpha_dash, true);
    test_equals('validator_alpha_dash (slash)', $slash, false);
    test_equals('validator_alpha_dash (multibyte)', $multibyte, false);
}

// 等しい値テスト
{
    // 確認
    $same      = validator_equals('abc', 'abc');
    $different = validator_equals('abc', 'abd');
    $type      = validator_equals(1, '1');

    // 結果（型も含めて比較する）
    test_equals('validator_equals', $same, true);
    test_equals('validator_equals (different)', $different, false);
    test_equals('validator_equals (type)', $type, false);
}

// 以上テスト
{
    // 確認
    $boundary = validator_min(10, 10);
    $over     = validator_min(11, 10);
    $under    = validator_min(9, 10);

    // 結果
    test_equals('validator_min (boundary)', $boundary, true);
    test_equals('validator_min (over)', $over, true);
    test_equals('validator_min', $under, false);
}

// 以下テスト
{
    // 確認
    $boundary = validator_max(10, 10);
    $under    = validator_max(9, 10);
    $over     = validator_max(11, 10);

    // 結果
    test_equals('validator_max (boundary)', $boundary, true);
    test_equals('validator_max (under)', $under, true);
    test_equals('validator_max', $over, false);
}

// 範囲の数値テスト
{
    // 確認
    $min   = validator_range(1, 1, 10);
    $max   = validator_range(10, 1, 10);
    $under = validator_range(0, 1, 10);
    $over  = validator_range(11, 1, 10);

    // 結果
    test_equals('validator_range (min boundary)', $min, true);
    test_equals('validator_range (max boundary)', $max, true);
    test_equals('validator_range (under)', $under, false);
    test_equals('validator_range (over)', $over, false);
}

// 未入力もしくはホワイトスペーステスト
{
    // 確認
    $empty   = validator_blank('');
    $space   = validator_blank('   ');
    $newline = validator_blank("\n");
    $value   = validator_blank('あ');

    // 結果
    test_equals('validator_blank (empty)', $empty, true);
    test_equals('validator_blank (space)', $space, true);
    test_equals('validator_blank (newline)', $newline, true);
    test_equals('validator_blank', $value, false);
}

// ブール値テスト
{
    // 確認
    $true      = validator_boolean(true);
    $false     = validator_boolean(false);
    $one       = validator_boolean('1');
    $zero      = validator_boolean('0');
    $two       = validator_boolean('2');
    $empty     = validator_boolean('');
    $multibyte = validator_boolean('あ');

    // 結果
    test_equals('validator_boolean (true)', $true, true);
    test_equals('validator_boolean (false)', $false, true);
    test_equals('validator_boolean (one)', $one, true);
    test_equals('validator_boolean (zero)', $zero, true);
    test_equals('validator_boolean (two)', $two, false);
    test_equals('validator_boolean (empty)', $empty, false);
    test_equals('validator_boolean (multibyte)', $multibyte, false);
}

// リストテスト
{
    // データ（選択肢は「キー => ラベル」の形で持つ）
    $list = [
        'all'  => '公開',
        'none' => '非公開',
    ];

    // 確認
    $value    = validator_list('all', $list);
    $multiple = validator_list(['all', 'none'], $list);
    $unknown  = validator_list('unknown', $list);
    $partial  = validator_list(['all', 'unknown'], $list);
    $label    = validator_list('公開', $list);

    // 結果（比較するのはキー。配列を渡すとすべてが含まれるかを確認する）
    test_equals('validator_list', $value, true);
    test_equals('validator_list (multiple)', $multiple, true);
    test_equals('validator_list (unknown)', $unknown, false);
    test_equals('validator_list (partial)', $partial, false);
    test_equals('validator_list (label)', $label, false);
}

// リスト（空の配列）テスト
{
    // データ
    $list = [
        'all' => '公開',
    ];

    // 確認
    $empty = validator_list([], $list);

    // 結果（確認する値が無いので通る。チェックボックスが未選択の場合にあたる）
    test_equals('validator_list (empty)', $empty, true);
}

// カスタム正規表現テスト
{
    // 確認（デリミタ無しのパターンを渡す）
    $match    = validator_regexp('abc123', '^[a-z]+\d+$');
    $unmatch  = validator_regexp('ABC', '^[a-z]+$');
    $partial  = validator_regexp('abc123', '\d');

    // 結果（アンカーを書かないと、一部が一致するだけで通る）
    test_equals('validator_regexp', $match, true);
    test_equals('validator_regexp (unmatch)', $unmatch, false);
    test_equals('validator_regexp (partial)', $partial, true);
}

// 日付テスト
{
    // 確認
    $date     = validator_date('2026-01-01');
    $leap     = validator_date('2024-02-29');
    $noleap   = validator_date('2026-02-29');
    $overflow = validator_date('2026-13-01');
    $short    = validator_date('2026-1-1');
    $slash    = validator_date('2026/01/01');

    // 結果（存在しない日付は通らない。0埋めした YYYY-MM-DD だけを許可する）
    test_equals('validator_date', $date, true);
    test_equals('validator_date (leap year)', $leap, true);
    test_equals('validator_date (not leap year)', $noleap, false);
    test_equals('validator_date (overflow)', $overflow, false);
    test_equals('validator_date (short)', $short, false);
    test_equals('validator_date (slash)', $slash, false);
}

// 時間テスト
{
    // 確認
    $time     = validator_time('00:00:00');
    $boundary = validator_time('23:59:59');
    $hour     = validator_time('24:00:00');
    $minute   = validator_time('23:60:00');
    $short    = validator_time('9:00:00');
    $second   = validator_time('23:59');

    // 結果（24時以降は通らない。秒まで必要）
    test_equals('validator_time', $time, true);
    test_equals('validator_time (boundary)', $boundary, true);
    test_equals('validator_time (hour)', $hour, false);
    test_equals('validator_time (minute)', $minute, false);
    test_equals('validator_time (short)', $short, false);
    test_equals('validator_time (second)', $second, false);
}

// 日時テスト
{
    // 確認
    $datetime = validator_datetime('2026-01-01 10:00:00');
    $date     = validator_datetime('2026-01-01');
    $time     = validator_datetime('2026-01-01 25:00:00');
    $spaces   = validator_datetime('2026-01-01  10:00:00');
    $empty    = validator_datetime('');

    // 結果（日付と時間を半角スペース1つで区切る）
    test_equals('validator_datetime', $datetime, true);
    test_equals('validator_datetime (date only)', $date, false);
    test_equals('validator_datetime (time)', $time, false);
    test_equals('validator_datetime (spaces)', $spaces, false);
    test_equals('validator_datetime (empty)', $empty, false);
}

// メールアドレステスト
{
    // 確認
    $email    = validator_email('info@example.com');
    $atmark   = validator_email('info.example.com');
    $double   = validator_email('info@@example.com');
    $space    = validator_email('info @example.com');
    $boundary = validator_email(str_repeat('a', 244) . '@example.com');
    $long     = validator_email(str_repeat('a', 245) . '@example.com');

    // 結果（@ で区切られていることと、256文字以内であることだけを見る）
    test_equals('validator_email', $email, true);
    test_equals('validator_email (atmark)', $atmark, false);
    test_equals('validator_email (double)', $double, false);
    test_equals('validator_email (space)', $space, false);
    test_equals('validator_email (boundary)', $boundary, true);
    test_equals('validator_email (long)', $long, false);
}

// URLテスト
{
    // 確認
    $http     = validator_url('http://example.com/');
    $https    = validator_url('https://example.com/');
    $scheme   = validator_url('ftp://example.com/');
    $nothing  = validator_url('example.com');
    $only     = validator_url('https://');

    // 結果（先頭が http:// もしくは https:// かどうかだけを見る）
    test_equals('validator_url (http)', $http, true);
    test_equals('validator_url (https)', $https, true);
    test_equals('validator_url (scheme)', $scheme, false);
    test_equals('validator_url (nothing)', $nothing, false);
    test_equals('validator_url (scheme only)', $only, true);
}

// ひらがなテスト
{
    // 確認
    $hiragana = validator_hiragana('あいうえおん');
    $small    = validator_hiragana('ぁぃゃっ');
    $katakana = validator_hiragana('アイウエオ');
    $mixed    = validator_hiragana('あa');
    $mark     = validator_hiragana('あー');

    // 結果（長音符（ー）は通らない）
    test_equals('validator_hiragana', $hiragana, true);
    test_equals('validator_hiragana (small)', $small, true);
    test_equals('validator_hiragana (katakana)', $katakana, false);
    test_equals('validator_hiragana (mixed)', $mixed, false);
    test_equals('validator_hiragana (mark)', $mark, false);
}

// カタカナテスト
{
    // 確認
    $katakana = validator_katakana('アイウエオヶ');
    $mark     = validator_katakana('ラーメン');
    $space    = validator_katakana('テスト　タロウ');
    $hiragana = validator_katakana('あいうえお');
    $halfsize = validator_katakana('ｱｲｳ');

    // 結果（長音符と全角スペースは通る。半角カナは通らない）
    test_equals('validator_katakana', $katakana, true);
    test_equals('validator_katakana (mark)', $mark, true);
    test_equals('validator_katakana (space)', $space, true);
    test_equals('validator_katakana (hiragana)', $hiragana, false);
    test_equals('validator_katakana (halfsize)', $halfsize, false);
}

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'libs/modules/validator.php',
    ]);
}
