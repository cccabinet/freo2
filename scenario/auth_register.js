/* テスト用の会員（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioMember = {
    username:  'scenario-member',
    password:  'scenario5678',
    name:      'シナリオ会員',
    email:     'scenario-member@example.com',
    url:       'https://example.com/scenario-member',
    text:      'シナリオから登録した会員です。',
    authority: 'ゲスト'
};

/* ユーザー登録完了メールの件名（設定 mail_register_subject の初期値） */
var registerSubject = 'ユーザー登録完了';

/* ユーザー一覧から、ユーザー名で対象の行を取得する */
var userRow = function(username) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().find('code').text().trim() === username;
    });
};

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

/* ユーザー登録フォームに正しい内容を入力して送信する */
var submitRegister = function() {
    var form = $('form.register');

    form.find('input[name="username"]').val(scenarioMember.username);
    form.find('input[name="password"]').val(scenarioMember.password);
    form.find('input[name="password_confirm"]').val(scenarioMember.password);
    form.find('input[name="name"]').val(scenarioMember.name);
    form.find('input[name="email"]').val(scenarioMember.email);
    form.find('input[name="url"]').val(scenarioMember.url);
    form.find('textarea[name="text"]').val(scenarioMember.text);

    test.click('form.register button[type="submit"]');
};

/* 確認画面に入力内容が並んでいることを検証する */
var assertPreview = function() {
    test.assertText('main', scenarioMember.username, '確認画面にユーザー名が表示されていません。');
    test.assertText('main', scenarioMember.name, '確認画面に名前が表示されていません。');
    test.assertText('main', scenarioMember.email, '確認画面にメールアドレスが表示されていません。');
};

/* 「訪問者によるユーザー新規登録」の設定を切り替える */
var switchRegisterSetting = function(enable) {
    var checkbox = $('form.register input[name="user_use_register"]');

    test.assert(checkbox.length === 1, '「訪問者によるユーザー新規登録」の設定項目がありません。');
    test.assert(checkbox.prop('checked') === !enable, '「訪問者によるユーザー新規登録」が想定と逆の状態になっています。');

    checkbox.prop('checked', enable);
    test.click('form.register button[type="submit"]');
};

/* 管理者用ページからログアウトする */
var logout = function() {
    test.click('a:contains("管理者さん")');
    setTimeout(function() {
        test.click('a:contains("ログアウト")');
    }, 500);
};

/* ログインフォームから送信する */
var login = function(username, password) {
    var form = $('form:eq(0)');

    test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

    form.find('input[name="username"]').val(username);
    form.find('input[name="password"]').val(password);
    test.click('form:eq(0) button[type="submit"]');
};

test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // ユーザー登録が無効であることを確認して、管理者用ページにログイン
    function() {
        test.assertNotExists('a:contains("ユーザー登録")', 'ユーザー登録へのリンクが表示されています。このシナリオは「訪問者によるユーザー新規登録」が無効であることを前提にしています。');

        login('admin', 'abcd1234');
    },
    // ログインできたことを確認して設定ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("設定")');
    },
    // ユーザー設定に移動
    function() {
        test.click('a:contains("ユーザー設定")', '設定の一覧に「ユーザー設定」がありません。');
    },
    // 訪問者によるユーザー新規登録を有効にする
    function() {
        switchRegisterSetting(true);
    },
    // 設定できたことを確認してログアウト
    function() {
        test.assertText('div.alert-success', '設定を登録しました。', '設定を登録できていません。');
        test.assert($('form.register input[name="user_use_register"]').prop('checked') === true, '「訪問者によるユーザー新規登録」が有効になっていません。');

        logout();
    },
    // ユーザー登録へのリンクが表示されたことを確認して、ユーザー登録ページに移動
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ユーザー登録")', 'ログインページにユーザー登録へのリンクがありません。');
    },
    // 未入力で送信すると、必須項目それぞれに入力エラーが表示されることを確認
    function() {
        test.assert($('form.register input[name="username"]').length === 1, 'ユーザー登録フォームが表示されていません。');

        submitAndWait({ username: '', password: '', password_confirm: '', name: '', email: '' }, function() {
            test.assertText(fieldWarning('username'), 'ユーザー名が入力されていません。', 'ユーザー名未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('password'), 'パスワードが入力されていません。', 'パスワード未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('email'), 'メールアドレスが入力されていません。', 'メールアドレス未入力のエラーが表示されていません。');

            test.reload();
        });
    },
    // 確認用パスワードが違うと、パスワードにだけ入力エラーが表示されることを確認
    function() {
        submitAndWait({
            username:         scenarioMember.username,
            password:         scenarioMember.password,
            password_confirm: scenarioMember.password + 'x',
            name:             scenarioMember.name,
            email:            scenarioMember.email
        }, function() {
            test.assertText(fieldWarning('password'), 'パスワードと確認パスワードが一致しません。', 'パスワード不一致のエラーが表示されていません。');
            test.assert($('form.register div.warning').length === 1, 'パスワード以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.reload();
        });
    },
    // 正しい内容を入力して確認画面に進む
    function() {
        submitRegister();
    },
    // 確認画面に入力内容が表示されることを確認して、「修正」で入力画面に戻る
    function() {
        assertPreview();

        test.click('a:contains("修正")', '確認画面に「修正」のリンクがありません。');
    },
    // 入力内容が復元されていることを確認して、もう一度確認画面に進む
    function() {
        // パスワードは、入力エラー時と確認画面から戻ったときの再出力を見直す予定があるため検証しない（CLAUDE.md「既知の課題」）
        test.assertValue('form.register input[name="username"]', scenarioMember.username, '「修正」で戻ったときにユーザー名が復元されていません。');
        test.assertValue('form.register input[name="name"]', scenarioMember.name, '「修正」で戻ったときに名前が復元されていません。');
        test.assertValue('form.register input[name="email"]', scenarioMember.email, '「修正」で戻ったときにメールアドレスが復元されていません。');
        test.assertValue('form.register textarea[name="text"]', scenarioMember.text, '「修正」で戻ったときに自己紹介が復元されていません。');

        submitRegister();
    },
    // 確認画面から登録する
    function() {
        assertPreview();

        test.click('form button[type="submit"]', '確認画面に登録ボタンがありません。');
    },
    // 登録できたことを確認して、記録された登録完了メールを開く
    function() {
        test.assertText('main', 'ユーザー情報を登録しました。', 'ユーザーを登録できていません。');

        // mail_log の記録は <時刻>_<宛先>.txt。/tool/test/mail は指定の時刻から10秒前まで遡って探す
        test.visit('/tool/test/mail?date=' + test.date + '&filename=' + encodeURIComponent(test.time + '_' + scenarioMember.email));
    },
    // 登録完了メールの宛先・件名・本文を確認して、ログインページに移動
    function() {
        test.assertText('body', 'to: ' + scenarioMember.email, '登録完了メールが記録されていません。（mail_log が無効になっている可能性があります）');
        test.assertText('body', 'subject: ' + registerSubject, '登録完了メールの件名が「' + registerSubject + '」になっていません。');
        test.assertText('body', 'ユーザー情報が登録されました。', '登録完了メールの本文が想定と違います。');

        test.visit('/auth/');
    },
    // 登録した会員でログイン
    function() {
        login(scenarioMember.username, scenarioMember.password);
    },
    // 会員ページが表示されることを確認してログアウト
    function() {
        test.assertText('main', 'ようこそ、' + scenarioMember.name + 'さん', '登録した会員でログインできていません。');
        test.assertText('main', 'メールアドレスの存在を確認してください。', '登録直後の会員に、メールアドレス未確認の案内が表示されていません。');

        test.visit('/auth/logout');
    },
    // 管理者用ページにログイン
    function() {
        login('admin', 'abcd1234');
    },
    // ログインできたことを確認してユーザー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("ユーザー管理")');
    },
    // 登録した会員が一覧にあることを確認して、編集ページに移動
    function() {
        var row = userRow(scenarioMember.username);

        test.assert(row.length === 1, '登録した会員が一覧にありません。');
        test.assertText(row, scenarioMember.authority, '登録した会員の権限が「' + scenarioMember.authority + '」になっていません。');
        test.assertText(row, scenarioMember.email, '登録した会員のメールアドレスが一覧に表示されていません。');

        test.click(row.find('a'), '一覧の ' + scenarioMember.username + ' の編集リンクが見つかりません。');
    },
    // 登録した会員を削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="username"]', scenarioMember.username, '削除対象が ' + scenarioMember.username + ' ではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認して設定ページに移動
    function() {
        test.assertText('div.alert-success', 'ユーザーを削除しました。', 'ユーザーを削除できていません。');
        test.assert(userRow(scenarioMember.username).length === 0, '削除した会員が一覧に残っています。');

        test.click('a:contains("設定")');
    },
    // ユーザー設定に移動
    function() {
        test.click('a:contains("ユーザー設定")', '設定の一覧に「ユーザー設定」がありません。');
    },
    // 訪問者によるユーザー新規登録を元の無効に戻す
    function() {
        switchRegisterSetting(false);
    },
    // 設定を戻せたことを確認してホームに移動
    function() {
        test.assertText('div.alert-success', '設定を登録しました。', '設定を登録できていません。');
        test.assert($('form.register input[name="user_use_register"]').prop('checked') === false, '「訪問者によるユーザー新規登録」を無効に戻せていません。');

        test.click('a:contains("ホーム")');
    },
    // 管理者用ページからログアウト
    function() {
        test.assertExists('a:contains("管理者さん")', '管理者用ページが表示されていません。');

        logout();
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
