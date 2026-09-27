<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('authorities.php');

// 既存データ削除
// authorities はマイグレーションで登録される前提データ（管理者・投稿者・閲覧者・ゲスト）があるため、テスト用のものだけを削除する
db_query('DELETE FROM ' . DATABASE_PREFIX . 'authorities WHERE name LIKE ' . db_escape('%テスト権限%') . ';');

// 正常データ（権限には管理画面が無いので、マイグレーションと同じ形を想定）
$data_authority = [
    'id'    => '',
    'name'  => 'テスト権限1',
    'power' => 2,
    'memo'  => '',
];

// トランザクションを開始
db_transaction();

// 前提データテスト
{
    // 取得
    $authorities = model('select_authorities', [
        'select'   => 'name, power',
        'order_by' => 'power DESC',
    ]);

    // 結果（権限の強さが 3=管理者 / 2=投稿者 / 1=閲覧者 / 0=ゲスト であること。before.php の判定がこの値を前提にしている）
    test_equals('select authorities', count($authorities), 4);
    test_equals('select authorities power', array_column($authorities, 'power'), [3, 2, 1, 0]);
    test_equals('select authorities name', $authorities[0]['name'], '管理者');
    test_equals('select authorities name (guest)', $authorities[3]['name'], 'ゲスト');
}

// 初期値テスト
{
    // 確認
    $default_authority = model('default_authorities');

    // 結果
    test_equals('default authority id', $default_authority['id'], null);
    test_equals('default authority name', $default_authority['name'], '');
    test_equals('default authority power', $default_authority['power'], 0);
    test_equals('default authority memo', $default_authority['memo'], null);
    test_equals('default authority deleted', $default_authority['deleted'], null);
    test_regexp('default authority created', $default_authority['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 権力の正規化（全角数字）テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['power'] = '２';

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);

    // 結果
    test_equals('normalize authority power', $test_authority['power'], '2');
}

// 権力の正規化（未入力）テスト
{
    // データ（権力は権限の強さなので、並び順のような自動採番はしない）
    $test_authority = $data_authority;
    unset($test_authority['power']);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);

    // 結果（値を補わないこと）
    test_array_not_haskey('normalize authority power (empty)', $test_authority, 'power');
}

// 正常登録テスト
{
    // データ
    $test_authority = $data_authority;

    // 登録
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate authority', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_authority_insert($test_authority);
    } else {
        debug($warnings);
    }

    // 結果
    $authorities = model('select_authorities', [
        'select' => 'name, power, memo',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert authority', count($authorities), 1);
    test_equals('insert authority name', $authorities[0]['name'], 'テスト権限1');
    test_equals('insert authority power', intval($authorities[0]['power']), 2);

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert authority memo', $authorities[0]['memo'], null);
}

// 名前の必須テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['name'] = '';

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate required authority name', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_authority = $data_authority;
    $test_authority['name'] = str_repeat('あ', 20);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['name'] = str_repeat('あ', 21);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority name', count($warnings), 1);
}

// 権力の必須テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['power'] = '';

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate required authority power', count($warnings), 1);
}

// 権力の書式テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['power'] = 'あ';

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate numeric authority power', count($warnings), 1);
}

// 権力の桁数（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_authority = $data_authority;
    $test_authority['power'] = str_repeat('9', 1);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority power (boundary)', count($warnings), 0);
}

// 権力の桁数テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['power'] = str_repeat('9', 2);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority power', count($warnings), 1);
}

// メモの未入力テスト
{
    // データ（メモは任意項目のため未入力でも警告は出ない）
    $test_authority = $data_authority;
    $test_authority['memo'] = '';

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate empty authority memo', count($warnings), 0);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_authority = $data_authority;
    $test_authority['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);

    // 結果
    test_equals('validate max_length authority memo', count($warnings), 1);
}

// 更新テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['id']    = $inserted_id;
    $test_authority['name']  = 'テスト権限1改';
    $test_authority['power'] = 1;

    // 更新
    $test_authority = model('normalize_authorities', $test_authority);
    $warnings       = model('validate_authorities', $test_authority);
    if (empty($warnings)) {
        model('update_authorities', [
            'set'   => [
                'name'  => $test_authority['name'],
                'power' => $test_authority['power'],
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
    $authorities = model('select_authorities', [
        'select' => 'name, power',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update authorities name', $authorities[0]['name'], 'テスト権限1改');
    test_equals('update authorities power', intval($authorities[0]['power']), 1);
}

// 削除テスト
{
    // 削除
    model('delete_authorities', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $authorities = model('select_authorities', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete authorities', count($authorities), 0);

    // 結果（レコード自体は残り、削除日時が入ること。名前は書き換えないこと）
    $authorities = db_select([
        'select' => 'name, deleted',
        'from'   => DATABASE_PREFIX . 'authorities',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete authorities (record)', count($authorities), 1);
    test_not_equals('delete authorities (deleted)', $authorities[0]['deleted'], null);
    test_equals('delete authorities (name)', $authorities[0]['name'], 'テスト権限1改');
}

// 物理削除テスト
{
    // データ
    $test_authority = $data_authority;
    $test_authority['name'] = 'テスト権限2';

    // 登録
    $test_authority = model('normalize_authorities', $test_authority);
    $deleted_id     = test_authority_insert($test_authority);

    // 削除
    model('delete_authorities', [
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
    $authorities = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'authorities',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete authorities (physical)', count($authorities), 0);
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'authorities WHERE name LIKE ' . db_escape('%テスト権限%') . ';');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/authorities.php',
    ]);
}

/**
 * 権限を登録してIDを取得
 *
 * 正規化した配列には id などカラム以外のキーが含まれるため、値を組み立て直す。
 *
 * @param array $authority
 *
 * @return int|null
 */
function test_authority_insert($authority)
{
    $resource = model('insert_authorities', [
        'values' => [
            'name'  => $authority['name'],
            'power' => $authority['power'],
            'memo'  => $authority['memo'],
        ],
    ]);
    if (!$resource) {
        return null;
    }

    $authorities = model('select_authorities', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($authorities[0]['id']);
}
