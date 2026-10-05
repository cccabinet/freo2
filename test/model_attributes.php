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

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');

// 正常データ（管理画面の属性登録フォームからの送信を想定）
$data_attribute = [
    'id'         => '',
    'name'       => 'テスト属性1',
    'filterable' => '0',
    'memo'       => '',
];

// トランザクションを開始
db_transaction();

// 初期値テスト
{
    // 確認
    $default_attribute = model('default_attributes');

    // 結果
    test_equals('default attribute id', $default_attribute['id'], null);
    test_equals('default attribute name', $default_attribute['name'], '');
    test_equals('default attribute filterable', $default_attribute['filterable'], 0);
    test_equals('default attribute memo', $default_attribute['memo'], null);
    test_equals('default attribute sort', $default_attribute['sort'], 0);
    test_equals('default attribute deleted', $default_attribute['deleted'], null);
    test_regexp('default attribute created', $default_attribute['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 並び順の正規化（全角数字）テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['sort'] = '１２';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);

    // 結果
    test_equals('normalize attribute sort', $test_attribute['sort'], '12');
}

// 正常登録テスト
{
    // データ
    $test_attribute = $data_attribute;

    // 登録
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate attribute', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_attribute_insert($test_attribute);
    } else {
        debug($warnings);
    }

    // 結果
    $attributes = model('select_attributes', [
        'select' => 'name, filterable, memo, sort',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert attribute', count($attributes), 1);
    test_equals('insert attribute name', $attributes[0]['name'], 'テスト属性1');
    test_equals('insert attribute filterable', intval($attributes[0]['filterable']), 0);

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert attribute memo', $attributes[0]['memo'], null);

    // 結果（並び順は自動で採番されること）
    test_equals('insert attribute sort', intval($attributes[0]['sort']), 1);
}

// 並び順の正規化（自動採番）テスト
{
    // データ（並び順を持たない管理画面からの入力を想定）
    $test_attribute = $data_attribute;

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);

    // 結果（登録済みの最大値 1 に 1 を加えた値になること）
    test_equals('normalize attribute sort auto', $test_attribute['sort'], 2);
}

// 名前の必須テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['name'] = '';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate required attribute name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_attribute = $data_attribute;
    $test_attribute['name'] = str_repeat('あ', 20);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['name'] = str_repeat('あ', 21);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute name', count($warnings), 1);
}

// 名前の重複テスト
{
    // データ（登録済みの名前と同じ名前。属性は重複を確認していないため警告は出ない）
    $test_attribute = $data_attribute;

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate duplicate attribute name', count($warnings), 0);
}

// フィルター対象の必須テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['filterable'] = '';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate required attribute filterable', count($warnings), 1);
    test_array_haskey('validate required attribute filterable (key)', $warnings, 'filterable');
}

// フィルター対象の値テスト
{
    // データ（選択肢は 1 と 0 だけ）
    $test_attribute = $data_attribute;
    $test_attribute['filterable'] = '2';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate list attribute filterable', count($warnings), 1);
    test_array_haskey('validate list attribute filterable (key)', $warnings, 'filterable');
}

// フィルター対象の選択肢テスト
{
    // データ（対象にする）
    $test_attribute = $data_attribute;
    $test_attribute['filterable'] = '1';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate attribute filterable', count($warnings), 0);
}

// メモの未入力テスト
{
    // データ（メモは任意項目のため未入力でも警告は出ない）
    $test_attribute = $data_attribute;
    $test_attribute['memo'] = '';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate empty attribute memo', count($warnings), 0);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_attribute = $data_attribute;
    $test_attribute['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute memo', count($warnings), 1);
}

// 並び順の必須テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['sort'] = '';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate required attribute sort', count($warnings), 1);
}

// 並び順の書式テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['sort'] = 'あ';

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate numeric attribute sort', count($warnings), 1);
}

// 並び順の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_attribute = $data_attribute;
    $test_attribute['sort'] = str_repeat('1', 5);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute sort (boundary)', count($warnings), 0);
}

// 並び順の桁数テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['sort'] = str_repeat('1', 6);

    // 確認
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);

    // 結果
    test_equals('validate max_length attribute sort', count($warnings), 1);
}

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める）
    $test_attribute = $data_attribute;
    $test_attribute['id']   = $inserted_id;
    $test_attribute['name']       = 'テスト属性1改';
    $test_attribute['filterable'] = '1';
    $test_attribute['memo']       = '有料会員向け。';

    // 更新
    $test_attribute = model('normalize_attributes', $test_attribute);
    $warnings       = model('validate_attributes', $test_attribute);
    if (empty($warnings)) {
        model('update_attributes', [
            'set'   => [
                'name'       => $test_attribute['name'],
                'filterable' => $test_attribute['filterable'],
                'memo'       => $test_attribute['memo'],
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
    $attributes = model('select_attributes', [
        'select' => 'name, filterable, memo',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update attributes name', $attributes[0]['name'], 'テスト属性1改');
    test_equals('update attributes filterable', intval($attributes[0]['filterable']), 1);
    test_equals('update attributes memo', $attributes[0]['memo'], '有料会員向け。');
}

// 削除テスト
{
    // 削除
    model('delete_attributes', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $attributes = model('select_attributes', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete attributes', count($attributes), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $attributes = db_select([
        'select' => 'name, deleted',
        'from'   => DATABASE_PREFIX . 'attributes',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete attributes (record)', count($attributes), 1);
    test_not_equals('delete attributes (deleted)', $attributes[0]['deleted'], null);
    test_equals('delete attributes (name)', $attributes[0]['name'], 'テスト属性1改');
}

// 物理削除テスト
{
    // データ
    $test_attribute = $data_attribute;
    $test_attribute['name'] = 'テスト属性2';

    // 登録
    $test_attribute = model('normalize_attributes', $test_attribute);
    $deleted_id     = test_attribute_insert($test_attribute);

    // 削除
    model('delete_attributes', [
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
    $attributes = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'attributes',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete attributes (physical)', count($attributes), 0);
}

// 関連データの削除（複数件）テスト
{
    // データ（2件を登録し、それぞれにユーザーとエントリーをひも付ける）
    $associate_ids = [];
    foreach (['テスト属性4', 'テスト属性5'] as $name) {
        $test_attribute = $data_attribute;
        $test_attribute['name'] = $name;

        $test_attribute  = model('normalize_attributes', $test_attribute);
        $associate_ids[] = test_attribute_insert($test_attribute);
    }

    // 無関係なひも付け（2件のIDを区切り文字なしで連結した値。区切り文字が無いと IN(45) のようになり、これを削除してしまう）
    $unrelated_id = intval(implode('', $associate_ids));

    foreach (array_merge($associate_ids, [$unrelated_id]) as $attribute_id) {
        model('insert_attribute_sets', [
            'values' => [
                'attribute_id' => $attribute_id,
                'user_id'      => 1,
            ],
        ]);
        model('insert_attribute_sets', [
            'values' => [
                'attribute_id' => $attribute_id,
                'entry_id'     => 1,
            ],
        ]);
    }

    // 削除
    model('delete_attributes', [
        'where' => 'name IN(' . db_escape('テスト属性4') . ', ' . db_escape('テスト属性5') . ')',
    ], [
        'associate' => true,
    ]);

    // 結果（削除した属性のひも付けは、ユーザー側もエントリー側も消えること）
    $attribute_sets = model('select_attribute_sets', [
        'where' => 'attribute_id IN(' . implode(',', $associate_ids) . ')',
    ]);

    test_equals('delete attributes (associate)', count($attribute_sets), 0);

    // 結果（無関係なひも付けは残ること）
    $attribute_sets = model('select_attribute_sets', [
        'where' => 'attribute_id = ' . $unrelated_id,
    ]);

    test_equals('delete attributes (associate unrelated)', count($attribute_sets), 2);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/attributes.php',
    ]);
}

/**
 * 属性を登録してIDを取得
 *
 * 正規化した配列には id などカラム以外のキーが含まれるため、
 * 管理画面の attribute_post.php と同じように values を組み立てる。
 *
 * @param array $attribute
 *
 * @return int|null
 */
function test_attribute_insert($attribute)
{
    $resource = model('insert_attributes', [
        'values' => [
            'name'       => $attribute['name'],
            'filterable' => $attribute['filterable'],
            'memo'       => $attribute['memo'],
            'sort'       => $attribute['sort'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $attributes = model('select_attributes', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($attributes[0]['id']);
}
