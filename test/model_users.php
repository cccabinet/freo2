<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('users.php');
model('attributes.php');
model('attribute_sets.php');
import('libs/modules/hash.php');

// 既存データ削除
// users は admin などの前提データ（シナリオテストが使う）があるため、テスト用のユーザーだけを削除する
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');

// 正常データ（管理画面のユーザー登録フォームからの送信を想定。テスト用ユーザーは最小権限で作る）
$data_user = [
    'id'               => '',
    'username'         => 'testuser1',
    'password'         => 'abcd1234',
    'password_confirm' => 'abcd1234',
    'authority_id'     => 3,
    'attribute_begin'  => '',
    'attribute_end'    => '',
    'enabled'          => 1,
    'name'             => 'テスト太郎',
    'email'            => 'testuser1@example.com',
    'url'              => '',
    'text'             => '',
    'memo'             => '',
    'attribute_sets'   => [],
];

// 検証テスト用のデータ（登録済みのユーザーと重複しない値にする）
$data_validate = $data_user;
$data_validate['username'] = 'testuser2';
$data_validate['email']    = 'testuser2@example.com';

// トランザクションを開始
db_transaction();

// 前提データを登録（属性）
$attribute_ids = [];
foreach (['テスト属性1', 'テスト属性2'] as $index => $name) {
    model('insert_attributes', [
        'values' => [
            'name' => $name,
            'sort' => $index + 1,
        ],
    ]);

    $attributes = model('select_attributes', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $attribute_ids[] = intval($attributes[0]['id']);
}

// 初期値テスト
{
    // 確認
    $default_user = model('default_users');

    // 結果
    test_equals('default user id', $default_user['id'], null);
    test_equals('default user enabled', $default_user['enabled'], 1);
    test_equals('default user username', $default_user['username'], '');
    test_equals('default user authority_id', $default_user['authority_id'], 0);
    test_equals('default user email', $default_user['email'], '');
    test_equals('default user email_verified', $default_user['email_verified'], 0);
    test_equals('default user attribute_begin', $default_user['attribute_begin'], null);
    test_equals('default user failed', $default_user['failed'], null);
    test_equals('default user attribute_sets', $default_user['attribute_sets'], []);
    test_regexp('default user created', $default_user['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 属性適用日時の正規化テスト
{
    // データ（入力欄は分までなので秒を補う。全角数字でも入力できること）
    $test_user = $data_user;
    $test_user['attribute_begin'] = '2026-02-01 09:30';
    $test_user['attribute_end']   = '２０２６-０３-０１ １０:３０';

    // 確認
    $test_user = model('normalize_users', $test_user);

    // 結果
    test_equals('normalize user attribute_begin', $test_user['attribute_begin'], '2026-02-01 09:30:00');
    test_equals('normalize user attribute_end', $test_user['attribute_end'], '2026-03-01 10:30:00');
}

// 属性適用日時の正規化（未入力）テスト
{
    // データ
    $test_user = $data_user;

    // 確認
    $test_user = model('normalize_users', $test_user);

    // 結果（未入力のときは秒を補わないこと）
    test_equals('normalize user attribute_begin (empty)', $test_user['attribute_begin'], '');
    test_equals('normalize user attribute_end (empty)', $test_user['attribute_end'], '');
}

// 正常登録テスト
{
    // データ
    $test_user = $data_user;

    // 登録
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate user', count($warnings), 0);

    if (empty($warnings)) {
        $inserted_id = test_user_insert($test_user);
    } else {
        debug($warnings);
    }

    // 結果
    $users = model('select_users', [
        'select' => 'username, authority_id, enabled, name, email, email_verified, url, attribute_begin',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('insert user', count($users), 1);
    test_equals('insert user username', $users[0]['username'], 'testuser1');
    test_equals('insert user authority_id', intval($users[0]['authority_id']), 3);
    test_equals('insert user enabled', intval($users[0]['enabled']), 1);
    test_equals('insert user email', $users[0]['email'], 'testuser1@example.com');
    test_equals('insert user email_verified', intval($users[0]['email_verified']), 1);

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert user url', $users[0]['url'], null);
    test_equals('insert user attribute_begin', $users[0]['attribute_begin'], null);
}

// パスワードの保存テスト
{
    // 確認（認証と同じ方法でハッシュを作り、登録した値と突き合わせる）
    $users = model('select_users', [
        'select' => 'password, password_salt',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    // 結果（平文では保存されていないこと）
    test_not_equals('insert user password (raw)', $users[0]['password'], 'abcd1234');
    test_not_equals('insert user password_salt', $users[0]['password_salt'], '');

    // 結果（ソルトと合わせたハッシュが一致すること）
    test_equals('insert user password', $users[0]['password'], hash_crypt('abcd1234', $users[0]['password_salt'] . ':' . $GLOBALS['config']['hash_salt']));
}

// 有効の書式テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['enabled'] = 'あ';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate boolean user enabled', count($warnings), 1);
}

// ユーザー名の必須テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['username'] = '';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate required user username', count($warnings), 1);
}

// ユーザー名の書式テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['username'] = 'テスト';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate alpha_dash user username', count($warnings), 1);
}

// ユーザー名の長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['username'] = str_repeat('a', 4);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user username (min boundary)', count($warnings), 0);
}

// ユーザー名の長さ（最小）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['username'] = str_repeat('a', 3);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user username (min)', count($warnings), 1);
}

// ユーザー名の長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['username'] = str_repeat('a', 20);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user username (max boundary)', count($warnings), 0);
}

// ユーザー名の長さ（最大）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['username'] = str_repeat('a', 21);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user username (max)', count($warnings), 1);
}

// ユーザー名の重複テスト
{
    // データ（登録済みのユーザー名と同じユーザー名で新規登録）
    $test_user = $data_validate;
    $test_user['username'] = 'testuser1';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate duplicate user username', count($warnings), 1);
}

// ユーザー名の重複（自分自身は除外）テスト
{
    // データ（登録済みのユーザー自身を編集）
    $test_user = $data_user;
    $test_user['id'] = $inserted_id;

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate duplicate user username (self)', count($warnings), 0);
}

// ユーザー名の重複チェック無効テスト
{
    // データ（登録済みのユーザー名と同じユーザー名）
    $test_user = $data_validate;
    $test_user['username'] = 'testuser1';
    $test_user['email']    = 'testuser1@example.com';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user, [
        'duplicate' => false,
    ]);

    // 結果（ユーザー名・メールアドレスとも重複を確認しないこと）
    test_equals('validate duplicate user (disabled)', count($warnings), 0);
}

// パスワードの必須テスト
{
    // データ（新規登録ではパスワードが必須）
    $test_user = $data_validate;
    $test_user['password']         = '';
    $test_user['password_confirm'] = '';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate required user password', count($warnings), 1);
}

// パスワードの書式テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = 'あいうえお１２３４';
    $test_user['password_confirm'] = 'あいうえお１２３４';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate regexp user password', count($warnings), 1);
}

// パスワードの書式（記号）テスト
{
    // データ（半角記号は使えること）
    $test_user = $data_validate;
    $test_user['password']         = 'abcd1234!#$%';
    $test_user['password_confirm'] = 'abcd1234!#$%';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate regexp user password (symbol)', count($warnings), 0);
}

// パスワードの混在（英字のみ）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('a', 8);
    $test_user['password_confirm'] = str_repeat('a', 8);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate mixed user password (alphabet)', count($warnings), 1);
}

// パスワードの混在（数字のみ）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('1', 8);
    $test_user['password_confirm'] = str_repeat('1', 8);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate mixed user password (number)', count($warnings), 1);
}

// パスワードの長さ（最小・境界値）テスト
{
    // データ（下限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('a', 4) . str_repeat('1', 4);
    $test_user['password_confirm'] = $test_user['password'];

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user password (min boundary)', count($warnings), 0);
}

// パスワードの長さ（最小）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('a', 4) . str_repeat('1', 3);
    $test_user['password_confirm'] = $test_user['password'];

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user password (min)', count($warnings), 1);
}

// パスワードの長さ（最大・境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('a', 36) . str_repeat('1', 4);
    $test_user['password_confirm'] = $test_user['password'];

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user password (max boundary)', count($warnings), 0);
}

// パスワードの長さ（最大）テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = str_repeat('a', 37) . str_repeat('1', 4);
    $test_user['password_confirm'] = $test_user['password'];

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate between user password (max)', count($warnings), 1);
}

// パスワードとユーザー名が同じテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['username']         = 'test1234';
    $test_user['password']         = 'test1234';
    $test_user['password_confirm'] = 'test1234';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate equals user password (username)', count($warnings), 1);
}

// 確認パスワードの不一致テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['password']         = 'abcd1234';
    $test_user['password_confirm'] = 'abcd12345';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate equals user password (confirm)', count($warnings), 1);
}

// パスワードの未入力（編集）テスト
{
    // データ（編集では空欄にすると変更しないため、警告は出ない）
    $test_user = $data_user;
    $test_user['id']               = $inserted_id;
    $test_user['password']         = '';
    $test_user['password_confirm'] = '';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate empty user password (edit)', count($warnings), 0);
}

// 権限の必須テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['authority_id'] = '';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate required user authority_id', count($warnings), 1);
}

// 属性適用開始日時の値テスト
{
    // データ（存在しない日付）
    $test_user = $data_validate;
    $test_user['attribute_begin'] = '2026-01-32 10:00';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate datetime user attribute_begin', count($warnings), 1);
}

// 属性適用終了日時の値テスト
{
    // データ（存在しない時刻）
    $test_user = $data_validate;
    $test_user['attribute_end'] = '2026-01-01 25:00';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate datetime user attribute_end', count($warnings), 1);
}

// 名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_user = $data_validate;
    $test_user['name'] = str_repeat('あ', 20);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user name (boundary)', count($warnings), 0);
}

// 名前の長さテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['name'] = str_repeat('あ', 21);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user name', count($warnings), 1);
}

// メールアドレスの必須テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['email'] = '';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate required user email', count($warnings), 1);
}

// メールアドレスの書式テスト
{
    // データ
    $test_user = $data_validate;
    $test_user['email'] = 'testuser2.example.com';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate email user email', count($warnings), 1);
}

// メールアドレスの長さ（境界値）テスト
{
    // データ（@example.com が12文字なので、上限ちょうどの80文字になる）
    $test_user = $data_validate;
    $test_user['email'] = str_repeat('a', 68) . '@example.com';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user email (boundary)', count($warnings), 0);
}

// メールアドレスの長さテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['email'] = str_repeat('a', 69) . '@example.com';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user email', count($warnings), 1);
}

// メールアドレスの重複テスト
{
    // データ（登録済みのメールアドレスと同じメールアドレスで新規登録）
    $test_user = $data_validate;
    $test_user['email'] = 'testuser1@example.com';

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate duplicate user email', count($warnings), 1);
}

// URLの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。URLは書式を検証していない）
    $test_user = $data_validate;
    $test_user['url'] = str_repeat('a', 200);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user url (boundary)', count($warnings), 0);
}

// URLの長さテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['url'] = str_repeat('a', 201);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user url', count($warnings), 1);
}

// 自己紹介の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['text'] = str_repeat('あ', 1000);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user text (boundary)', count($warnings), 0);
}

// 自己紹介の長さテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['text'] = str_repeat('あ', 1001);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user text', count($warnings), 1);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_user = $data_validate;
    $test_user['memo'] = str_repeat('あ', 5000);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_user = $data_validate;
    $test_user['memo'] = str_repeat('あ', 5001);

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果
    test_equals('validate max_length user memo', count($warnings), 1);
}

// 暗証コードテスト
{
    // データ（パスワード再設定で使う。メールアドレスと暗証コードの組み合わせを確認する）
    model('update_users', [
        'set'   => [
            'token_code' => 'ABCD1234',
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

    // 確認
    $warnings_valid = model('validate_users', [
        'key'        => 'testuser1@example.com',
        'token_code' => 'ABCD1234',
    ]);
    $warnings_invalid = model('validate_users', [
        'key'        => 'testuser1@example.com',
        'token_code' => 'XXXXXXXX',
    ]);
    $warnings_empty = model('validate_users', [
        'key'        => 'testuser1@example.com',
        'token_code' => '',
    ]);

    // 結果
    test_equals('validate user token_code', count($warnings_valid), 0);
    test_equals('validate user token_code (invalid)', count($warnings_invalid), 1);
    test_equals('validate required user token_code', count($warnings_empty), 1);
}

// 属性の更新テスト
{
    // 更新
    model('update_users', [
        'set'   => [
            'name' => 'テスト太郎',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'             => $inserted_id,
        'attribute_sets' => [$attribute_ids[0], $attribute_ids[1]],
    ]);

    // 結果
    $attribute_sets = model('select_attribute_sets', [
        'where'    => 'user_id = ' . intval($inserted_id),
        'order_by' => 'attribute_id',
    ]);

    test_equals('set attribute users', count($attribute_sets), 2);
    test_equals('set attribute users attribute_id', intval($attribute_sets[0]['attribute_id']), $attribute_ids[0]);

    // 更新（ひも付けを1つに減らす）
    model('update_users', [
        'set'   => [
            'name' => 'テスト太郎',
        ],
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ], [
        'id'             => $inserted_id,
        'attribute_sets' => [$attribute_ids[1]],
    ]);

    // 結果（古いひも付けは消えること）
    $attribute_sets = model('select_attribute_sets', [
        'where' => 'user_id = ' . intval($inserted_id),
    ]);

    test_equals('set attribute users (replaced)', count($attribute_sets), 1);
    test_equals('set attribute users (replaced attribute_id)', intval($attribute_sets[0]['attribute_id']), $attribute_ids[1]);
}

// 関連データの取得テスト
{
    // 取得
    $users = model('select_users', [
        'where' => 'users.id = ' . intval($inserted_id),
    ], [
        'associate' => true,
    ]);

    // 結果
    test_equals('select associate user', count($users), 1);
    test_array_haskey('select associate user attribute_sets', $users[0], 'attribute_sets');
    test_equals('select associate user attribute_sets (count)', count($users[0]['attribute_sets']), 1);
}

// 更新テスト
{
    // データ（編集フォームからの入力を想定し、検証には id を含める）
    $test_user = $data_user;
    $test_user['id']   = $inserted_id;
    $test_user['name'] = 'テスト次郎';
    $test_user['url']  = 'https://example.com/';

    // 更新
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);
    if (empty($warnings)) {
        model('update_users', [
            'set'   => [
                'name' => $test_user['name'],
                'url'  => $test_user['url'],
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
    $users = model('select_users', [
        'select' => 'name, url',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update users name', $users[0]['name'], 'テスト次郎');
    test_equals('update users url', $users[0]['url'], 'https://example.com/');
}

// 削除テスト
{
    // 削除
    model('delete_users', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $users = model('select_users', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete users', count($users), 0);

    // 結果（レコード自体は残り、削除日時・ユーザー名・メールアドレスが書き換わること）
    $users = db_select([
        'select' => 'username, email, deleted',
        'from'   => DATABASE_PREFIX . 'users',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete users (record)', count($users), 1);
    test_not_equals('delete users (deleted)', $users[0]['deleted'], null);
    test_regexp('delete users (username)', $users[0]['username'], '^DELETED \d{14} testuser1$');
    test_regexp('delete users (email)', $users[0]['email'], '^DELETED \d{14} testuser1@example\.com$');

    // 結果（関連する属性は削除されること）
    $attribute_sets = model('select_attribute_sets', [
        'where' => 'user_id = ' . intval($inserted_id),
    ]);

    test_equals('delete users (attribute_sets)', count($attribute_sets), 0);
}

// 削除後の再登録テスト
{
    // データ（削除したユーザーと同じユーザー名・メールアドレス）
    $test_user = $data_user;

    // 確認
    $test_user = model('normalize_users', $test_user);
    $warnings  = model('validate_users', $test_user);

    // 結果（削除時に名前が書き換わるため、同じ値で登録できること）
    test_equals('validate duplicate user (deleted)', count($warnings), 0);
}

// 物理削除テスト
{
    // データ
    $test_user = $data_user;
    $test_user['username'] = 'testuser3';
    $test_user['email']    = 'testuser3@example.com';

    // 登録
    $test_user = model('normalize_users', $test_user);
    $deleted_id = test_user_insert($test_user);

    // 削除
    model('delete_users', [
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
    $users = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'users',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete users (physical)', count($users), 0);
}

// 表示用データ作成テスト
{
    // 確認（入力欄は分までなので、秒を落とす）
    $view_user = model('view_users', [
        'attribute_begin' => '2026-02-01 09:30:00',
        'attribute_end'   => '2026-03-01 10:30:00',
        'username'        => 'testuser1',
    ]);

    // 結果
    test_equals('view users attribute_begin', $view_user['attribute_begin'], '2026-02-01 09:30');
    test_equals('view users attribute_end', $view_user['attribute_end'], '2026-03-01 10:30');
    test_equals('view users username', $view_user['username'], 'testuser1');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attributes;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'attribute_sets;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/users.php',
    ]);
}

/**
 * ユーザーを登録してIDを取得
 *
 * 正規化した配列には id や確認パスワードなどカラム以外のキーが含まれるため、
 * 管理画面の user_post.php と同じように values を組み立てる。
 *
 * @param array $user
 * @param array $options
 *
 * @return int|null
 */
function test_user_insert($user, $options = [])
{
    $password_salt = hash_salt();

    $resource = model('insert_users', [
        'values' => [
            'username'        => $user['username'],
            'password'        => hash_crypt($user['password'], $password_salt . ':' . $GLOBALS['config']['hash_salt']),
            'password_salt'   => $password_salt,
            'authority_id'    => $user['authority_id'],
            'attribute_begin' => $user['attribute_begin'],
            'attribute_end'   => $user['attribute_end'],
            'enabled'         => $user['enabled'],
            'name'            => $user['name'],
            'email'           => $user['email'],
            'email_verified'  => 1,
            'url'             => $user['url'],
            'text'            => $user['text'],
            'memo'            => $user['memo'],
        ],
    ], $options);
    if (!$resource) {
        return null;
    }

    $users = model('select_users', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);

    return intval($users[0]['id']);
}
