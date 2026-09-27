<?php

// 設定ファイルを読み込み
import('app/config.php');

// コードカバレッジの記録を開始
if (!isset($_GET['_test'])) {
    service('coverage.php');
    service_coverage_start();
}

// ライブラリを読み込み
model('contacts.php');
model('logs.php');
service('contact.php');

// リクエスト情報を用意
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'freo/2';

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'contacts;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// 正常データ（公開側のお問い合わせフォームからの送信を想定）
$data_contact = [
    'name'    => 'テスト太郎',
    'email'   => 'testuser1@example.com',
    'subject' => 'お問い合わせの件名',
    'message' => 'お問い合わせの内容です。',
];

// トランザクションを開始
db_transaction();

// 正常登録テスト
{
    // データ
    $test_contact = $data_contact;

    // 登録
    $warnings = model('validate_contacts', $test_contact);
    if (empty($warnings)) {
        service_contact_insert([
            'values' => $test_contact,
        ]);
    } else {
        debug($warnings);
    }

    // 結果
    $contacts = model('select_contacts', [
        'select'   => 'name, email, subject, message',
        'order_by' => 'id DESC',
        'limit'    => 10,
    ]);

    test_equals('insert contact', count($contacts), 1);
    test_equals('insert contact subject', $contacts[0]['subject'], 'お問い合わせの件名');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('insert contact log', count($logs), 1);
    test_equals('insert contact log model', $logs[0]['model'], 'contacts');
    test_equals('insert contact log ip', $logs[0]['ip'], '127.0.0.1');
}

// 登録したお問い合わせのIDを取得
$contacts = model('select_contacts', [
    'select'   => 'id',
    'order_by' => 'id DESC',
    'limit'    => 1,
]);
$inserted_id = intval($contacts[0]['id']);

// 状況の初期値テスト
{
    // 結果（登録時の状況は、送信内容によらずサービスが「未対応」に固定すること）
    $contacts = model('select_contacts', [
        'select' => 'status',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('insert contact status', $contacts[0]['status'], 'opened');
}

// 状況の初期値（指定しても上書きされる）テスト
{
    // データ（状況を指定して送信されても無視されること）
    $test_contact = $data_contact;
    $test_contact['subject'] = '状況を指定した件名';
    $test_contact['status']  = 'closed';

    // 登録
    service_contact_insert([
        'values' => $test_contact,
    ]);

    // 結果
    $contacts = model('select_contacts', [
        'select' => 'status',
        'where'  => 'subject = ' . db_escape('状況を指定した件名'),
    ]);

    test_equals('insert contact status (overwrite)', count($contacts), 1);
    test_equals('insert contact status (overwrite value)', $contacts[0]['status'], 'opened');
}

// 更新テスト
{
    // データ（管理画面では状況とメモを変更する）
    $test_contact = [
        'status' => 'processing',
        'memo'   => '対応中です。',
    ];

    // 更新
    $warnings = model('validate_contacts', $test_contact);
    if (empty($warnings)) {
        service_contact_update([
            'set'   => $test_contact,
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
    $contacts = model('select_contacts', [
        'select' => 'status, memo',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update contact status', $contacts[0]['status'], 'processing');
    test_equals('update contact memo', $contacts[0]['memo'], '対応中です。');

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('update'),
    ]);

    test_equals('update contact log', count($logs), 1);
    test_equals('update contact log model', $logs[0]['model'], 'contacts');
}

// 最終編集日時の確認テスト
{
    // 更新（編集開始後に更新されていないので、競合とは判定されない）
    service_contact_update([
        'set'   => [
            'status' => 'closed',
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
    $contacts = model('select_contacts', [
        'select' => 'status',
        'where'  => 'id = ' . $inserted_id,
    ]);

    test_equals('update contact (modified check)', $contacts[0]['status'], 'closed');
}

// 操作ログの重複抑止テスト
{
    // 結果（service_log_record() は同じ model と exec の組み合わせを1リクエストにつき1回しか記録しないため、登録・更新を繰り返してもログは増えない）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('insert'),
    ]);

    test_equals('record contact log once', count($logs), 1);
}

// 削除テスト
{
    // 削除
    service_contact_delete([
        'where' => [
            'id = :id',
            [
                'id' => $inserted_id,
            ],
        ],
    ]);

    // 結果
    $contacts = model('select_contacts', [
        'where' => 'id = ' . $inserted_id,
    ]);

    test_equals('delete contact', count($contacts), 0);

    // 結果（操作ログが記録されること）
    $logs = model('select_logs', [
        'where' => 'exec = ' . db_escape('delete'),
    ]);

    test_equals('delete contact log', count($logs), 1);
    test_equals('delete contact log model', $logs[0]['model'], 'contacts');
}

// トランザクションを終了
db_rollback();

// 既存データ削除
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'contacts;');
db_query('TRUNCATE TABLE ' . DATABASE_PREFIX . 'logs;');

// コードカバレッジの記録を終了
if (!isset($_GET['_test'])) {
    $coverages = service_coverage_end();

    service_coverage_output($coverages, [
        'app/services/contact.php',
    ]);
}
