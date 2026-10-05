/* テスト用のお問い合わせ（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioContact = {
    name:    'シナリオ太郎',
    email:   'scenario-contact@example.com',
    subject: 'シナリオのお問い合わせ',
    message: 'シナリオから送信したお問い合わせの内容です。'
};

/* 自動返信メールの件名（設定 mail_contact_subject の初期値） */
var replySubject = 'お問い合わせありがとうございます';

/* 入力欄の直後に表示された入力エラーを取得する */
var fieldWarning = function(name) {
    return $('form.register [name="' + name + '"]').parent().find('div.warning');
};

/* 入力して送信し、入力エラーが表示されるまで待つ */
var submitAndWait = function(values, callback) {
    var form = $('form.register');

    for (var name in values) {
        form.find('[name="' + name + '"]').val(values[name]);
    }

    $('div.warning').remove();
    test.click('form.register button[type="submit"]');
    test.wait(function() {
        return $('form.register div.warning').length > 0;
    }, callback, '入力エラーが表示されませんでした。');
};

/* お問い合わせフォームに正しい内容を入力して送信する */
var submitContact = function() {
    var form = $('form.register');

    form.find('input[name="name"]').val(scenarioContact.name);
    form.find('input[name="email"]').val(scenarioContact.email);
    form.find('input[name="subject"]').val(scenarioContact.subject);
    form.find('textarea[name="message"]').val(scenarioContact.message);

    test.click('form.register button[type="submit"]');
};

/* 確認画面に入力内容が並んでいることを検証する */
var assertPreview = function() {
    test.assertText('#contact dl', scenarioContact.name, '確認画面にお名前が表示されていません。');
    test.assertText('#contact dl', scenarioContact.email, '確認画面にメールアドレスが表示されていません。');
    test.assertText('#contact dl', scenarioContact.subject, '確認画面にお問い合わせ件名が表示されていません。');
    test.assertText('#contact dl', scenarioContact.message, '確認画面にお問い合わせ内容が表示されていません。');
};

/* お問い合わせ一覧から、件名で対象の行を取得する */
var contactRow = function(subject) {
    return $('table tbody tr').filter(function() {
        return $(this).text().indexOf(subject) !== -1;
    });
};

test.scenario = [
    // 初期ページからお問い合わせページに移動
    function() {
        test.assertExists('a:contains("お問い合わせ")', '初期ページにお問い合わせへのリンクがありません。');
        test.click('a:contains("お問い合わせ")');
    },
    // 未入力で送信すると、必須項目それぞれに入力エラーが表示されることを確認
    function() {
        test.assert($('form.register').length === 1, 'お問い合わせフォームが表示されていません。');

        submitAndWait({ name: '', email: '', subject: '', message: '' }, function() {
            test.assertText(fieldWarning('name'), 'お名前が入力されていません。', 'お名前未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('email'), 'メールアドレスが入力されていません。', 'メールアドレス未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('subject'), 'お問い合わせ件名が入力されていません。', 'お問い合わせ件名未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('message'), 'お問い合わせ内容が入力されていません。', 'お問い合わせ内容未入力のエラーが表示されていません。');

            test.reload();
        });
    },
    // 形式が不正なメールアドレスだけが入力エラーになることを確認
    function() {
        submitAndWait({
            name:    scenarioContact.name,
            email:   'scenario-contact',
            subject: scenarioContact.subject,
            message: scenarioContact.message
        }, function() {
            test.assertText(fieldWarning('email'), 'メールアドレスの入力内容が正しくありません。', 'メールアドレスの形式のエラーが表示されていません。');
            test.assert($('form.register div.warning').length === 1, 'メールアドレス以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.reload();
        });
    },
    // 正しい内容を入力して確認画面に進む
    function() {
        submitContact();
    },
    // 確認画面に入力内容が表示されることを確認して、「修正」で入力画面に戻る
    function() {
        assertPreview();

        test.click('a:contains("修正")', '確認画面に「修正」のリンクがありません。');
    },
    // 入力内容が復元されていることを確認して、もう一度確認画面に進む
    function() {
        test.assertValue('form.register input[name="name"]', scenarioContact.name, '「修正」で戻ったときにお名前が復元されていません。');
        test.assertValue('form.register input[name="email"]', scenarioContact.email, '「修正」で戻ったときにメールアドレスが復元されていません。');
        test.assertValue('form.register input[name="subject"]', scenarioContact.subject, '「修正」で戻ったときにお問い合わせ件名が復元されていません。');
        test.assertValue('form.register textarea[name="message"]', scenarioContact.message, '「修正」で戻ったときにお問い合わせ内容が復元されていません。');

        submitContact();
    },
    // 確認画面から送信する
    function() {
        assertPreview();

        test.click('form button[type="submit"]', '確認画面に送信ボタンがありません。');
    },
    // 送信できたことを確認して、記録された自動返信メールを開く
    function() {
        test.assertText('#contact', 'お問い合わせを送信しました。', 'お問い合わせを送信できていません。');

        // mail_log の記録は <時刻>_<宛先>.txt。/tool/test/mail は指定の時刻から10秒前まで遡って探す
        test.visit('/tool/test/mail?date=' + test.date + '&filename=' + encodeURIComponent(test.time + '_' + scenarioContact.email));
    },
    // 自動返信メールの宛先・件名・本文を確認して、ログインページに移動
    function() {
        test.assertText('body', 'to: ' + scenarioContact.email, '自動返信メールが記録されていません。（mail_log が無効になっている可能性があります）');
        test.assertText('body', 'subject: ' + replySubject, '自動返信メールの件名が「' + replySubject + '」になっていません。');
        test.assertText('body', scenarioContact.subject, '自動返信メールの本文にお問い合わせ件名が含まれていません。');
        test.assertText('body', scenarioContact.message, '自動返信メールの本文にお問い合わせ内容が含まれていません。');

        test.visit('/auth/');
    },
    // 管理者用ページにログイン
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // ログインできたことを確認してお問い合わせ管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("お問い合わせ管理")');
    },
    // 送信したお問い合わせが一覧にあることを確認して、表示ページに移動
    function() {
        var row = contactRow(scenarioContact.subject);

        test.assert(row.length === 1, '送信したお問い合わせが一覧にありません。');
        test.assert(row.find('span.badge').text().trim() === '未対応', '送信したお問い合わせの状況が「未対応」になっていません。');

        test.click(row.find('a:contains("表示")'), '一覧に「表示」のリンクが見つかりません。');
    },
    // 表示ページに送信した内容が並んでいることを確認して、お問い合わせ管理ページに戻る
    function() {
        test.assertText('main', scenarioContact.name, '表示ページにお名前が表示されていません。');
        test.assertText('main', scenarioContact.email, '表示ページにメールアドレスが表示されていません。');
        test.assertText('main', scenarioContact.subject, '表示ページにお問い合わせ件名が表示されていません。');
        test.assertText('main', scenarioContact.message, '表示ページにお問い合わせ内容が表示されていません。');

        test.click('a:contains("お問い合わせ管理")');
    },
    // お問い合わせ編集ページに移動
    function() {
        test.click(contactRow(scenarioContact.subject).find('a:contains("編集")'), '一覧に「編集」のリンクが見つかりません。');
    },
    // 状況を「完了」にして登録
    function() {
        var form = $('form.register');

        test.assertValue('form.register input[name="subject"]', scenarioContact.subject, '編集対象が送信したお問い合わせではありません。');
        test.assertValue('form.register select[name="status"]', 'opened', '編集画面の状況が「未対応」になっていません。');

        form.find('select[name="status"]').val('closed');
        test.click('form.register button[type="submit"]');
    },
    // 完了にすると既定の一覧から消えることを確認して、すべての状況で絞り込む
    function() {
        test.assertText('div.alert-success', 'お問い合わせを登録しました。', 'お問い合わせを編集できていません。');
        test.assert(contactRow(scenarioContact.subject).length === 0, '完了にしたお問い合わせが、既定の一覧に残っています。');

        test.assertExists('form select[name="status"] option[value="all"]', '絞り込みに「すべて」の選択肢がありません。');

        $('form select[name="status"]').val('all');
        test.click('form button:contains("絞り込み")', '「絞り込み」のボタンが見つかりません。');
    },
    // すべての状況では残っていることを確認して、もう一度編集ページに移動
    function() {
        var row = contactRow(scenarioContact.subject);

        test.assert(row.length === 1, '完了にしたお問い合わせが、すべての状況の一覧にありません。');
        test.assert(row.find('span.badge').text().trim() === '完了', 'お問い合わせの状況が「完了」になっていません。');

        test.click(row.find('a:contains("編集")'), '一覧に「編集」のリンクが見つかりません。');
    },
    // お問い合わせを削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="subject"]', scenarioContact.subject, '削除対象が送信したお問い合わせではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認してホームに移動
    function() {
        test.assertText('div.alert-success', 'お問い合わせを削除しました。', 'お問い合わせを削除できていません。');
        test.assert(contactRow(scenarioContact.subject).length === 0, '削除したお問い合わせが一覧に残っています。');

        test.click('a:contains("ホーム")');
    },
    // 管理者用ページからログアウト
    function() {
        test.assertExists('a:contains("管理者さん")', '管理者用ページが表示されていません。');

        test.click('a:contains("管理者さん")');
        setTimeout(function() {
            test.click('a:contains("ログアウト")');
        }, 500);
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
