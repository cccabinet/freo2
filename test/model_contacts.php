<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
// insert_users() は関連する属性のひも付けも更新するため、そのモデルも必要になる
model('contacts.php');
model('users.php');
model('attribute_sets.php');

// 既存データ削除
// users は admin などの前提データ（シナリオテストが使う）があるため、テスト用のユーザーだけを削除する
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'contacts;');
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');

// 正常データ（公開側のお問い合わせフォームからの送信を想定）
$data_contact = [
    'name'    => 'テスト太郎',
    'email'   => 'testuser1@example.com',
    'subject' => 'お問い合わせの件名',
    'message' => 'お問い合わせの内容です。',
];

// トランザクションを開始
db_transaction();

// 前提データを登録（会員からのお問い合わせを想定したユーザー。テスト用ユーザーは最小権限で作る）
model('insert_users', [
    'values' => [
        'username'       => 'testuser1',
        'password'       => 'dummy',
        'password_salt'  => 'dummy',
        'authority_id'   => 3,
        'enabled'        => 1,
        'name'           => 'テスト太郎',
        'email'          => 'testuser1@example.com',
        'email_verified' => 1,
    ],
]);
$users = model('select_users', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$user_id = intval($users[0]['id']);

// 初期値テスト
{
    // 確認
    $default_contact = model('default_contacts');

    // 結果
    test_equals('default contact id', $default_contact['id'], null);
    test_equals('default contact user_id', $default_contact['user_id'], null);
    test_equals('default contact name', $default_contact['name'], '');
    test_equals('default contact email', $default_contact['email'], '');
    test_equals('default contact subject', $default_contact['subject'], '');
    test_equals('default contact message', $default_contact['message'], '');
    test_equals('default contact status', $default_contact['status'], 'opened');
    test_equals('default contact memo', $default_contact['memo'], null);
    test_regexp('default contact created', $default_contact['created'], '^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$');
}

// 正常登録テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['user_id'] = $user_id;
    $test_contact['status']  = 'opened';

    // 登録
    $warnings = model('validate_contacts', $test_contact);

    // 結果（正常データでは警告が出ないこと）
    test_equals('validate contact', count($warnings), 0);

    if (empty($warnings)) {
        model('insert_contacts', [
            'values' => $test_contact,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $contacts = model('select_contacts', [
        'select'   => 'user_id, name, email, subject, message, status, memo',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert contact', count($contacts), 1);
    test_equals('insert contact user_id', intval($contacts[0]['user_id']), $user_id);
    test_equals('insert contact name', $contacts[0]['name'], 'テスト太郎');
    test_equals('insert contact email', $contacts[0]['email'], 'testuser1@example.com');
    test_equals('insert contact subject', $contacts[0]['subject'], 'お問い合わせの件名');
    test_equals('insert contact status', $contacts[0]['status'], 'opened');

    // 結果（未入力の項目は NULL として保存されること）
    test_equals('insert contact memo', $contacts[0]['memo'], null);
}

// 登録したお問い合わせのIDを取得
$contacts = model('select_contacts', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($contacts[0]['id']);

// お名前の必須テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['name'] = '';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate required contact name', count($warnings), 1);
}

// お名前の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない。文字数で数えることの確認も兼ねてマルチバイト文字を使う）
    $test_contact = $data_contact;
    $test_contact['name'] = str_repeat('あ', 50);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact name (boundary)', count($warnings), 0);
}

// お名前の長さテスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['name'] = str_repeat('あ', 51);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact name', count($warnings), 1);
}

// メールアドレスの必須テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['email'] = '';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate required contact email', count($warnings), 1);
}

// メールアドレスの書式テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['email'] = 'testuser1.example.com';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate email contact email', count($warnings), 1);
}

// メールアドレスの長さ（境界値）テスト
{
    // データ（@example.com が12文字なので、上限ちょうどの80文字になる）
    $test_contact = $data_contact;
    $test_contact['email'] = str_repeat('a', 68) . '@example.com';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact email (boundary)', count($warnings), 0);
}

// メールアドレスの長さテスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['email'] = str_repeat('a', 69) . '@example.com';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact email', count($warnings), 1);
}

// お問い合わせ件名の必須テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['subject'] = '';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate required contact subject', count($warnings), 1);
}

// お問い合わせ件名の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_contact = $data_contact;
    $test_contact['subject'] = str_repeat('あ', 100);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact subject (boundary)', count($warnings), 0);
}

// お問い合わせ件名の長さテスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['subject'] = str_repeat('あ', 101);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact subject', count($warnings), 1);
}

// お問い合わせ内容の必須テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['message'] = '';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate required contact message', count($warnings), 1);
}

// お問い合わせ内容の長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_contact = $data_contact;
    $test_contact['message'] = str_repeat('あ', 5000);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact message (boundary)', count($warnings), 0);
}

// お問い合わせ内容の長さテスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['message'] = str_repeat('あ', 5001);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact message', count($warnings), 1);
}

// 状況の値テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['status'] = 'unknown';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate list contact status', count($warnings), 1);
}

// 状況の値（対応中）テスト
{
    // データ（設定にある値なら警告は出ない）
    $test_contact = $data_contact;
    $test_contact['status'] = 'processing';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate list contact status (processing)', count($warnings), 0);
}

// 状況の値（対応不要）テスト
{
    // データ（設定にある値なら警告は出ない）
    $test_contact = $data_contact;
    $test_contact['status'] = 'unnecessary';

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate list contact status (unnecessary)', count($warnings), 0);
}

// メモの長さ（境界値）テスト
{
    // データ（上限ちょうどのため警告は出ない）
    $test_contact = $data_contact;
    $test_contact['memo'] = str_repeat('あ', 5000);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact memo (boundary)', count($warnings), 0);
}

// メモの長さテスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['memo'] = str_repeat('あ', 5001);

    // 確認
    $warnings = model('validate_contacts', $test_contact);

    // 結果
    test_equals('validate max_length contact memo', count($warnings), 1);
}

// 関連データの取得テスト
{
    // 取得
    $contacts = model('select_contacts', [
        'where' => 'contacts.id = ' . intval($inserted_id),
    ], [
        'associate' => true,
    ]);

    // 結果（会員から送られた場合は、ユーザーの情報が付くこと）
    test_equals('select associate contact', count($contacts), 1);
    test_array_haskey('select associate contact user_username', $contacts[0], 'user_username');
    test_equals('select associate contact user_username (value)', $contacts[0]['user_username'], 'testuser1');
}

// 絞り込みテスト
{
    // 確認
    $filter = model('filter_contacts', [
        'name'    => 'テスト',
        'status'  => 'opened',
        'keyword' => 'お問い合わせ',
    ], [
        'associate' => true,
    ]);

    // 結果（条件が AND で連結されること）
    test_contains('filter contacts name', $filter['where'], 'contacts.name LIKE ' . db_escape('%テスト%'));
    test_contains('filter contacts status', $filter['where'], 'contacts.status = ' . db_escape('opened'));
    test_contains('filter contacts keyword', $filter['where'], 'contacts.subject LIKE ' . db_escape('%お問い合わせ%'));
    test_contains('filter contacts and', $filter['where'], ' AND ');

    // 結果（ページャー用のクエリ文字列が作られること）
    test_contains('filter contacts pager', $filter['pager'], 'status=opened');
}

// 絞り込み（未入力）テスト
{
    // 確認
    $filter = model('filter_contacts', [
        'name'   => '',
        'status' => '',
    ], [
        'associate' => true,
    ]);

    // 結果（未入力の項目は条件に含めないこと）
    test_not_contains('filter contacts (empty)', $filter['where'], 'contacts.name');
    test_equals('filter contacts pager (empty)', $filter['pager'], '');

    // 結果（状況を指定していないときは、対応の済んだものを条件で除くこと）
    test_equals('filter contacts status (default)', $filter['where'], 'contacts.status NOT IN(' . db_escape('closed') . ',' . db_escape('unnecessary') . ')');
}

// 絞り込み（状況の指定なし）テスト
{
    // 確認（管理画面を開いた直後は status のキー自体が無い）
    $filter = model('filter_contacts', [
        'keyword' => 'お問い合わせ',
    ], [
        'associate' => true,
    ]);

    // 結果（キーが無くても、対応の済んだものを条件で除くこと）
    test_contains('filter contacts status (not set)', $filter['where'], 'contacts.status NOT IN(');
    test_contains('filter contacts status (not set keyword)', $filter['where'], 'contacts.subject LIKE ' . db_escape('%お問い合わせ%'));
}

// 絞り込み（すべての状況）テスト
{
    // 確認
    $filter = model('filter_contacts', [
        'status' => 'all',
    ], [
        'associate' => true,
    ]);

    // 結果（状況では絞り込まないこと）
    test_equals('filter contacts status (all)', $filter['where'], '');

    // 結果（ページャーには引き継ぐこと）
    test_equals('filter contacts pager (all)', $filter['pager'], 'status=all');
}

// 絞り込み（表示しない状況が無い場合）テスト
{
    // データ（設定を空にすると、状況を指定しなくても条件を付けない）
    $contact_status_hidden = $GLOBALS['config']['contact_status_hidden'];
    $GLOBALS['config']['contact_status_hidden'] = [];

    // 確認
    $filter = model('filter_contacts', [
        'status' => '',
    ], [
        'associate' => true,
    ]);

    $GLOBALS['config']['contact_status_hidden'] = $contact_status_hidden;

    // 結果
    test_equals('filter contacts status (hidden none)', $filter['where'], '');
}

// 絞り込み（関連データなし）テスト
{
    // 確認
    $filter = model('filter_contacts', [
        'status' => 'opened',
    ]);

    // 結果
    test_equals('filter contacts (not associate)', $filter['where'], null);
    test_equals('filter contacts pager (not associate)', $filter['pager'], null);
}

// 更新テスト
{
    // データ（管理画面では状況とメモを変更する）
    $test_contact = [
        'status' => 'closed',
        'memo'   => '対応しました。',
    ];

    // 更新
    $warnings = model('validate_contacts', $test_contact);
    if (empty($warnings)) {
        model('update_contacts', [
            'set'   => $test_contact,
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
    $contacts = model('select_contacts', [
        'select' => 'status, memo',
        'where'  => 'id = ' . intval($inserted_id),
    ]);

    test_equals('update contacts status', $contacts[0]['status'], 'closed');
    test_equals('update contacts memo', $contacts[0]['memo'], '対応しました。');
}

// 削除テスト
{
    // 削除
    model('delete_contacts', [
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果（取得対象からは外れること）
    $contacts = model('select_contacts', [
        'where' => 'id = ' . intval($inserted_id),
    ]);

    test_equals('delete contacts', count($contacts), 0);

    // 結果（レコード自体は残り、削除日時が入ること）
    $contacts = db_select([
        'select' => 'subject, deleted',
        'from'   => DATABASE_PREFIX . 'contacts',
        'where'  => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    test_equals('delete contacts (record)', count($contacts), 1);
    test_not_equals('delete contacts (deleted)', $contacts[0]['deleted'], null);
    test_equals('delete contacts (subject)', $contacts[0]['subject'], 'お問い合わせの件名');
}

// 物理削除テスト
{
    // データ
    $test_contact = $data_contact;
    $test_contact['subject'] = '物理削除の件名';
    $test_contact['status']  = 'opened';

    // 登録
    model('insert_contacts', [
        'values' => $test_contact,
    ]);

    $contacts = model('select_contacts', [
        'select'   => 'id',
        'order_by' => 'id DESC',
        'limit'    => 1,
    ]);
    $deleted_id = intval($contacts[0]['id']);

    // 削除
    model('delete_contacts', [
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
    $contacts = db_select([
        'select' => 'id',
        'from'   => DATABASE_PREFIX . 'contacts',
        'where'  => 'id = ' . intval($deleted_id),
    ]);

    test_equals('delete contacts (physical)', count($contacts), 0);
}

// 表示用データ作成テスト
{
    // 確認（お問い合わせは変換せずにそのまま返す）
    $view_contact = model('view_contacts', [
        'name'    => 'テスト太郎',
        'message' => "1行目\n2行目",
    ]);

    // 結果
    test_equals('view contacts name', $view_contact['name'], 'テスト太郎');
    test_equals('view contacts message', $view_contact['message'], "1行目\n2行目");
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'contacts;');
db_query('DELETE FROM ' . DATABASE_PREFIX . 'users WHERE username LIKE ' . db_escape('%testuser%') . ' OR email LIKE ' . db_escape('%testuser%') . ';');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/models/contacts.php',
    ]);
}
