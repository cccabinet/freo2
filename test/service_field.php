<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('fields.php');
model('logs.php');
service('field.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'fields;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（管理画面のフィールド登録フォームからの送信を想定）
$data_field = [
    'type_id'     => 1,
    'code'        => 'test1',
    'name'        => 'テスト1',
    'kind'        => 'text',
    'validation'  => 'none',
    'choices'     => '',
    'initial'     => '',
    'explanation' => '',
    'memo'        => '',
    'sort'        => 1,
];
$data_fields = [
    [
        'type_id' => 1,
        'code'    => 'sort1',
        'name'    => '項目1',
        'kind'    => 'text',
        'sort'    => 1,
    ],
    [
        'type_id' => 1,
        'code'    => 'sort2',
        'name'    => '項目2',
        'kind'    => 'text',
        'sort'    => 2,
    ],
    [
        'type_id' => 1,
        'code'    => 'sort3',
        'name'    => '項目3',
        'kind'    => 'text',
        'sort'    => 3,
    ],
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_field = $data_field;

    // 登録
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);
    if (empty($warnings)) {
        service_field_insert([
            'values' => $test_field,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $fields = model('select_fields', [
        'select'   => 'code, name, kind',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert field', count($fields), 1);
    test_equals('insert field code', $fields[0]['code'], 'test1');
    test_equals('insert field kind', $fields[0]['kind'], 'text');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert field log', count($logs), 1);
    test_equals('insert field log model', $logs[0]['model'], 'fields');
    test_equals('insert field log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したフィールドのIDを取得
$fields = model('select_fields', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($fields[0]['id']);

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める。id が無いとコードの重複チェックが自分自身を検出してしまう）
    $test_field = $data_field;
    $test_field['id']   = $inserted_id;
    $test_field['name'] = 'テスト1改';
    $test_field['kind'] = 'textarea';

    // 更新
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);
    if (empty($warnings)) {
        service_field_update([
            'set'   => [
                'name' => $test_field['name'],
                'kind' => $test_field['kind'],
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
    $fields = model('select_fields', [
        'select' => 'name, kind',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update field name', $fields[0]['name'], 'テスト1改');
    test_equals('update field kind', $fields[0]['kind'], 'textarea');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update field log', count($logs), 1);
    test_equals('update field log model', $logs[0]['model'], 'fields');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_field_update([
        'set'   => [
            'name' => 'テスト1改改',
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
    $fields = model('select_fields', [
        'select' => 'name',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update field (modified check)', $fields[0]['name'], 'テスト1改改');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('record field log once', count($logs), 1);
}

// 削除テスト
{
    // 削除
    service_field_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $fields = model('select_fields', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete field', count($fields), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete field log', count($logs), 1);
    test_equals('delete field log model', $logs[0]['model'], 'fields');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'fields;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// トランザクションを開始
db_transaction();

// 並び順の一括変更テスト
{
    // 登録
    foreach ($data_fields as $field) {
        $field    = model('normalize_fields', $field);
        $warnings = model('validate_fields', $field);
        if (empty($warnings)) {
            service_field_insert([
                'values' => $field,
            ]);
        } else {
            debug($warnings);
        }
    }

    // 結果
    $fields = model('select_fields', [
        'select'   => 'id, sort',
        'order_by' => 'id',
    ]);

    test_equals('sort fields (before)', array_column($fields, 'sort'), [1, 2, 3]);

    // 並び順を更新（IDは決め打ちにせず、登録済みのものを使う）
    $ids = array_column($fields, 'id');

    service_field_sort([
        $ids[0] => 3,
        $ids[1] => 2,
        $ids[2] => 1,
    ]);

    // 結果
    $fields = model('select_fields', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort fields (after)', array_column($fields, 'sort'), [3, 2, 1]);

    // 結果（不正な値は無視されること）
    service_field_sort([
        $ids[0] => 'あ', // 並び順が数字でない
        'あ'    => 1,    // IDが不正
    ]);

    $fields = model('select_fields', [
        'select'   => 'sort',
        'order_by' => 'id',
    ]);

    test_equals('sort fields (invalid)', array_column($fields, 'sort'), [3, 2, 1]);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'fields;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/field.php',
    ]);
}
