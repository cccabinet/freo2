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
model('field_sets.php');

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'fields;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'field_sets;');

// 正常データ（管理画面のフィールド登録フォームからの送信を想定）
$data_field = [
    'id'          => '',
    'type_id'     => 1,
    'code'        => 'test1',
    'name'        => 'テスト1',
    'kind'        => 'text',
    'validation'  => 'none',
    'choices'     => '',
    'initial'     => '',
    'explanation' => '',
    'memo'        => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_field = model('default_fields');

    // 結果
    test_equals('default field id', $default_field['id'], null);
    test_equals('default field type_id', $default_field['type_id'], 0);
    test_equals('default field code', $default_field['code'], null);
    test_equals('default field name', $default_field['name'], '');
    test_equals('default field kind', $default_field['kind'], '');
    test_equals('default field validation', $default_field['validation'], null);
    test_equals('default field choices', $default_field['choices'], null);
    test_equals('default field sort', $default_field['sort'], 0);
    test_regexp('default field created', $default_field['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_field = $data_field;
    $test_field['sort'] = '１２';

    // 確認
    $test_field = model('normalize_fields', $test_field);

    // 結果
    test_equals('normalize field sort', $test_field['sort'], '12');
}

// 正常登録テスト
{
    // データ
    $test_field = $data_field;

    // 登録
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate field', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_field_insert($test_field);
    } else {
        debug($warnings);
    }

    // 結果
    $fields = model('select_fields', [
        'select' => 'type_id, code, name, kind, validation, choices, sort',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert field', count($fields), 1);
    test_equals('insert field code', $fields[0]['code'], 'test1');
    test_equals('insert field name', $fields[0]['name'], 'テスト1');
    test_equals('insert field kind', $fields[0]['kind'], 'text');
    test_equals('insert field validation', $fields[0]['validation'], 'none');

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert field choices', $fields[0]['choices'], null);

    // 結果（並び順は自動で採番されること）
    test_equals('insert field sort', intval($fields[0]['sort']), 1);
}

// 並び順の正規化（自動採番）テスト
{
    // データ（並び順を持たない管理画面からの入力を想定）
    $test_field = $data_field;
    $test_field['code'] = 'test2';

    // 確認
    $test_field = model('normalize_fields', $test_field);

    // 結果（登録済みの最大値 1 に 1 を加えた値になること）
    test_equals('normalize field sort auto', $test_field['sort'], 2);
}

// 型の必須テスト
{
    // データ
    $test_field = $data_field;
    $test_field['type_id'] = '';
    $test_field['code']    = 'test2';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate required field type_id', count($warnings), 1);
}

// コードの未入力テスト
{
    // データ（コードは任意項目のため未入力でも警告は出ない）
    $test_field = $data_field;
    $test_field['code'] = '';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate empty field code', count($warnings), 0);
}

// コードの書式テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'あいうえお';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate alpha_dash field code', count($warnings), 1);
}

// コードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code'] = str_repeat('a', 2);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate between field code (min boundary)', count($warnings), 0);
}

// コードの長さ（最小）テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = str_repeat('a', 1);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate between field code (min)', count($warnings), 1);
}

// コードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code'] = str_repeat('a', 80);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate between field code (max boundary)', count($warnings), 0);
}

// コードの長さ（最大）テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = str_repeat('a', 81);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate between field code (max)', count($warnings), 1);
}

// コードの重複テスト
{
    // データ（登録済みのコードと同じコードで新規登録。型が違っても重複と判定される）
    $test_field = $data_field;
    $test_field['type_id'] = 2;

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate duplicate field code', count($warnings), 1);
}

// コードの重複（自分自身は除外）テスト
{
    // データ（登録済みのフィールド自身を編集）
    $test_field = $data_field;
    $test_field['id'] = $inserted_id;

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate duplicate field code (self)', count($warnings), 0);
}

// コードの重複チェック無効テスト
{
    // データ（登録済みのコードと同じコード）
    $test_field = $data_field;

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field, [
        'duplicate' => false,
    ]);

    // 結果
    test_equals('validate duplicate field code (disabled)', count($warnings), 0);
}

// 名前の必須テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['name'] = '';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate required field name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['name'] = str_repeat('あ', 20);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['name'] = str_repeat('あ', 21);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field name', count($warnings), 1);
}

// 種類の必須テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['kind'] = '';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate required field kind', count($warnings), 1);
}

// 種類の値テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['kind'] = 'unknown';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate list field kind', count($warnings), 1);
}

// バリデーションの値テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code']       = 'test2';
    $test_field['validation'] = 'unknown';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate list field validation', count($warnings), 1);
}

// バリデーションの未入力テスト
{
    // データ（バリデーションは任意項目のため未入力でも警告は出ない）
    $test_field = $data_field;
    $test_field['code']       = 'test2';
    $test_field['validation'] = '';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate empty field validation', count($warnings), 0);
}

// 選択肢の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code']    = 'test2';
    $test_field['choices'] = str_repeat('あ', 1000);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field choices (boundary)', count($warnings), 0);
}

// 選択肢の長さテスト
{
    // データ
    $test_field = $data_field;
    $test_field['code']    = 'test2';
    $test_field['choices'] = str_repeat('あ', 1001);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field choices', count($warnings), 1);
}

// 初期値の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code']    = 'test2';
    $test_field['initial'] = str_repeat('あ', 2000);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field initial (boundary)', count($warnings), 0);
}

// 初期値の長さテスト
{
    // データ
    $test_field = $data_field;
    $test_field['code']    = 'test2';
    $test_field['initial'] = str_repeat('あ', 2001);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field initial', count($warnings), 1);
}

// 説明の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code']        = 'test2';
    $test_field['explanation'] = str_repeat('あ', 200);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field explanation (boundary)', count($warnings), 0);
}

// 説明の長さテスト
{
    // データ
    $test_field = $data_field;
    $test_field['code']        = 'test2';
    $test_field['explanation'] = str_repeat('あ', 201);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field explanation', count($warnings), 1);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['sort'] = '';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate required field sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['sort'] = 'あ';

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate numeric field sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['sort'] = str_repeat('1', 5);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test2';
    $test_field['sort'] = str_repeat('1', 6);

    // 確認
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);

    // 結果
    test_equals('validate max_length field sort', count($warnings), 1);
}

// 関連データの取得テスト
{
    // 取得
    $fields = model('select_fields', [
        'where' => 'fields.id = ' . intval($inserted_id),
    ], [
        'associate' => true,
    ]);

    // 結果（型の情報が付くこと）
    test_equals('select associate field', count($fields), 1);
    test_array_haskey('select associate field type_code', $fields[0], 'type_code');
    test_array_haskey('select associate field type_name', $fields[0], 'type_name');
    test_equals('select associate field type_code (value)', $fields[0]['type_code'], 'entry');
}

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める）
    $test_field = $data_field;
    $test_field['id']   = $inserted_id;
    $test_field['name'] = 'テスト1改';
    $test_field['kind'] = 'textarea';

    // 更新
    $test_field = model('normalize_fields', $test_field);
    $warnings   = model('validate_fields', $test_field);
    if (empty($warnings)) {
        model('update_fields', [
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
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $fields = model('select_fields', [
        'select' => 'name, kind',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update fields name', $fields[0]['name'], 'テスト1改');
    test_equals('update fields kind', $fields[0]['kind'], 'textarea');
}

// 削除テスト
{
    // 削除
    model('delete_fields', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $fields = model('select_fields', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete fields', count($fields), 0);

    // 結果（レコード自体は残り、削除日時とコードが書き換わること）
    $fields = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'fields',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete fields (record)', count($fields), 1);
    test_not_equals('delete fields (deleted)', $fields[0]['deleted'], null);
    test_regexp('delete fields (code)', $fields[0]['code'], '^DELETED \d{14} test1$');
}

// 削除（コードなし）テスト
{
    // データ（コードは任意項目なので、持たないフィールドもある）
    $test_field = $data_field;
    $test_field['code'] = '';
    $test_field['name'] = 'コードなし';

    // 登録
    $test_field = model('normalize_fields', $test_field);
    $nocode_id  = test_field_insert($test_field);

    // 削除
    model('delete_fields', [
        'where' => [
            'id = :id',
            [
                'id' => $nocode_id,
            ],
        ],
    ]);

    // 結果（コードは NULL のまま、削除日時だけが入ること）
    $fields = db_select([
        'select' => 'code, deleted',
        'from'   => DATABASE_PREFIX . 'fields',
        'where'  => 'id = ' . intval($nocode_id),
    ]);

    test_equals('delete fields (code null)', $fields[0]['code'], null);
    test_not_equals('delete fields (code null deleted)', $fields[0]['deleted'], null);
}

// 物理削除テスト
{
    // データ
    $test_field = $data_field;
    $test_field['code'] = 'test3';
    $test_field['name'] = 'テスト3';

    // 登録
    $test_field = model('normalize_fields', $test_field);
    $deleted_id = test_field_insert($test_field);

    // 削除
    model('delete_fields', [
        'where' => [
            'id = :id',
            [
                'id' => $deleted_id,
            ],
        ],
    ], [
        'softdelete' => false,
    ]);

    // 結果（レコード自体が消えること）
    $fields = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'fields',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete fields (physical)', count($fields), 0);
}

// 関連データの削除（複数件）テスト
{
    // データ（2件を登録し、それぞれにエントリーをひも付ける）
    $associate_ids = [];
    foreach (['test4', 'test5'] as $index => $code) {
        $test_field = $data_field;
        $test_field['code'] = $code;
        $test_field['name'] = 'テスト' . ($index + 4);

        $test_field      = model('normalize_fields', $test_field);
        $associate_ids[] = test_field_insert($test_field);
    }

    // 無関係なひも付け（2件のIDを区切り文字なしで連結した値。区切り文字が無いと IN(45) のようになり、これを削除してしまう）
    $unrelated_id = intval(implode('', $associate_ids));

    foreach (array_merge($associate_ids, [$unrelated_id]) as $field_id) {
        model('insert_field_sets', [
            'values' => [
                'field_id' => $field_id,
                'entry_id' => 1,
                'text'     => 'テキスト',
            ],
        ]);
    }

    // 削除
    model('delete_fields', [
        'where' => 'code IN(' . db_escape('test4') . ', ' . db_escape('test5') . ')',
    ], [
        'associate' => true,
    ]);

    // 結果（削除したフィールドのひも付けは消えること）
    $field_sets = model('select_field_sets', [
        'where' => 'field_id IN(' . implode(',', $associate_ids) . ')',
    ]);

    test_equals('delete fields (associate)', count($field_sets), 0);

    // 結果（無関係なひも付けは残ること）
    $field_sets = model('select_field_sets', [
        'where' => 'field_id = ' . $unrelated_id,
    ]);

    test_equals('delete fields (associate unrelated)', count($field_sets), 1);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'fields;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'field_sets;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/fields.php',
    ]);
}

/**
 * フィールドを登録してIDを取得
 *
 * 正規化した配列には id などカラム以外のキーが含まれるため、
 * 管理画面の field_post.php と同じように values を組み立てる。
 *
 * @param array $field
 *
 * @return int|null
 */
function test_field_insert($field)
{
    $resource = model('insert_fields', [
        'values' => [
            'type_id'     => $field['type_id'],
            'code'        => $field['code'],
            'name'        => $field['name'],
            'kind'        => $field['kind'],
            'validation'  => $field['validation'],
            'choices'     => $field['choices'],
            'initial'     => $field['initial'],
            'explanation' => $field['explanation'],
            'memo'        => $field['memo'],
            'sort'        => $field['sort'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $fields = model('select_fields', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($fields[0]['id']);
}
