<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('attributes.php');
model('attribute_sets.php');
model('logs.php');
service('attribute.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面の属性登録フォームからの送信を想定）
$data_attribute = [
    'name'       => 'テスト属性1',
    'filterable' => 0,
    'memo'       => '',
    'sort'       => 1,
];
$data_attributes = [
    [
        'name'       => '属性1',
        'filterable' => 0,
        'sort'       => 1,
    ],
    [
        'name'       => '属性2',
        'filterable' => 0,
        'sort'       => 2,
    ],
    [
        'name'       => '属性3',
        'filterable' => 0,
        'sort'       => 3,
    ],
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_attribute = $data_attribute;

    // 登録
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);
    if (empty($warnings)) {
        service_attribute_insert([
            'values' => $test_attribute,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $attributes = model('select_attributes', [
        'select'   => 'name, memo',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert attribute', count($attributes), 1);
    test_equals('insert attribute name', $attributes[0]['name'], 'テスト属性1');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert attribute log', count($logs), 1);
    test_equals('insert attribute log model', $logs[0]['model'], 'attributes');
    test_equals('insert attribute log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録した属性のIDを取得
$attributes = model('select_attributes', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($attributes[0]['id']);

// 更新テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['name'] = 'テスト属性1改';
    $test_attribute['memo'] = '有料会員向け。';

    // 更新
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);
    if (empty($warnings)) {
        service_attribute_update([
            'set'   => [
                'name' => $test_attribute['name'],
                'memo' => $test_attribute['memo'],
            ],
            'where' => [
                'id = :id',
                [
                    'id' => $inserted_id,
                ],
            ],
        ], [
            'id' => $inserted_id,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $attributes = model('select_attributes', [
        'select' => 'name, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update attribute name', $attributes[0]['name'], 'テスト属性1改');
    test_equals('update attribute memo', $attributes[0]['memo'], '有料会員向け。');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update attribute log', count($logs), 1);
    test_equals('update attribute log model', $logs[0]['model'], 'attributes');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_attribute_update([
        'set'   => [
            'name' => 'テスト属性1改改',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'     => $inserted_id,
        'update' => localdate('Y-m-d H:i:s'),
    ]);

    // 結果
    $attributes = model('select_attributes', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update attribute (modified check)', $attributes[0]['name'], 'テスト属性1改改');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record attribute log once', count($logs), 1);
}

// 削除テスト
{
    // データ（ユーザーとエントリーにひも付けておく）
    model('insert_attribute_sets', [
        'values' => [
            'attribute_id' => $inserted_id,
            'user_id'      => 1,
        ],
    ]);
    model('insert_attribute_sets', [
        'values' => [
            'attribute_id' => $inserted_id,
            'entry_id'     => 1,
        ],
    ]);

    // 削除（管理画面の attribute_delete.php と同じく、ひも付けも消す）
    service_attribute_delete([
        'where' => [
            'attributes.id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'associate' => true,
    ]);

    // 結果
    $attributes = model('select_attributes', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete attribute', count($attributes), 0);

    // 結果（ひも付けも消えること）
    $attribute_sets = model('select_attribute_sets', [
        'where' => 'attribute_id = ' . $inserted_id,
    ]);

    test_equals('delete attribute (associate)', count($attribute_sets), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete attribute log', count($logs), 1);
    test_equals('delete attribute log model', $logs[0]['model'], 'attributes');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録
    foreach ($data_attributes as $attribute) {
        $attribute = model('normalize_attributes', $attribute);
        $warnings  = model('validate_attributes', $attribute);
        if (empty($warnings)) {
            service_attribute_insert([
                'values' => $attribute,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $attributes = model('select_attributes', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort attributes (before)', array_column($attributes, 'sort'), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($attributes, 'id');

    service_attribute_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $attributes = model('select_attributes', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort attributes (after)', array_column($attributes, 'sort'), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_attribute_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $attributes = model('select_attributes', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort attributes (invalid)', array_column($attributes, 'sort'), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// フィルター用の属性を登録（一般はフィルター対象外、ほかはフィルター対象。並び順は登録順と逆にしておく）
$filter_ids = [];
foreach ([
    'general' => ['name' => '一般', 'filterable' => 0, 'sort' => 5],
    'r15'     => ['name' => 'R-15', 'filterable' => 1, 'sort' => 4],
    'r18'     => ['name' => 'R-18', 'filterable' => 1, 'sort' => 3],
    'other'   => ['name' => '他の会員用', 'filterable' => 1, 'sort' => 2],
    'deleted' => ['name' => '削除済み', 'filterable' => 1, 'sort' => 1],
] as $key => $attribute) {
    model('insert_attributes', [
        'values' => $attribute,
    ]);

    $attributes = model('select_attributes', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $filter_ids[$key] = intval($attributes[0]['id']);
}
model('delete_attributes', [
    'where' => 'id = ' . $filter_ids['deleted'],
]);

// フィルターで選べる属性の取得テスト
{
    // 確認（与えられた属性が無ければ、何も返さないこと）
    test_equals('filterable attributes (empty)', service_attribute_filterable([]), []);

    // 確認（与えられた属性のうち、フィルター対象のものだけを並び順で返すこと。与えられていない属性・削除済みの属性は返さないこと）
    $attributes = service_attribute_filterable([$filter_ids['general'], $filter_ids['r15'], $filter_ids['r18'], $filter_ids['deleted']]);

    test_equals('filterable attributes', array_map('intval', array_column($attributes, 'id')), [$filter_ids['r18'], $filter_ids['r15']]);
    test_equals('filterable attributes (name)', array_column($attributes, 'name'), ['R-18', 'R-15']);
}

// フィルターの適用テスト
{
    // データ（会員に与えられた属性）
    $attribute_ids  = [$filter_ids['general'], $filter_ids['r15'], $filter_ids['r18']];
    $filterable_ids = [$filter_ids['r15'], $filter_ids['r18']];

    // 結果（表示を選んでいなければ、フィルター対象の属性はすべて除かれること。初期状態は表示しない）
    test_equals('filter attributes (none shown)', service_attribute_filter($attribute_ids, $filterable_ids, []), [$filter_ids['general']]);

    // 結果（表示を選んだ属性だけが残ること）
    test_equals('filter attributes (r15 shown)', service_attribute_filter($attribute_ids, $filterable_ids, [$filter_ids['r15']]), [$filter_ids['general'], $filter_ids['r15']]);

    // 結果（すべて選べば、与えられた属性がそのまま残ること）
    test_equals('filter attributes (all shown)', service_attribute_filter($attribute_ids, $filterable_ids, [$filter_ids['r15'], $filter_ids['r18']]), $attribute_ids);

    // 結果（与えられていない属性を選んでも、見える範囲は広がらないこと）
    test_equals('filter attributes (not granted)', service_attribute_filter($attribute_ids, $filterable_ids, [$filter_ids['other']]), [$filter_ids['general']]);

    // 結果（フィルター対象外の属性は、選ばなくても残ること）
    test_equals('filter attributes (not filterable)', service_attribute_filter([$filter_ids['general']], [], []), [$filter_ids['general']]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// フィルターで表示を選んだ属性の取得テスト（クッキーを差し替えて確認し、最後に元へ戻す）
{
    $cookie = $_COOKIE;

    // 結果（クッキーが無ければ、何も選んでいないこと）
    unset($_COOKIE['attribute_filter']);
    test_equals('get attribute filter (no cookie)', service_attribute_filter_get(5), []);

    // 結果（数字だけを取り出すこと）
    $_COOKIE['attribute_filter'] = [5 => '3,abc,4,,1 OR 1=1'];
    test_equals('get attribute filter', service_attribute_filter_get(5), [3, 4]);

    // 結果（ほかのユーザーの選択は使わないこと）
    test_equals('get attribute filter (other user)', service_attribute_filter_get(6), []);

    // 結果（形が不正なら、何も選んでいないこと）
    $_COOKIE['attribute_filter'] = '3,4';
    test_equals('get attribute filter (not array)', service_attribute_filter_get(5), []);

    $_COOKIE['attribute_filter'] = [5 => ['3', '4']];
    test_equals('get attribute filter (nested)', service_attribute_filter_get(5), []);

    $_COOKIE = $cookie;
}

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/attribute.php',
    ]);
}
