/* テスト用の会員（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioMember = {
    username: 'scenario-life',
    password: 'scenario5678',
    name:     'シナリオ会員',
    email:    'scenario-life@example.com'
};

/* 会員情報の編集で設定し直す名前（元の名前と部分一致しないよう、別の文言にする） */
var modifiedName = 'へんしゅう後の会員';

/* メールアドレス存在確認メールの件名（設定 mail_verify_subject の初期値） */
var verifySubject = 'メールアドレス存在確認';

/* ログインフォームから送信する */
var login = function(username, password) {
    var form = $('form:eq(0)');

    test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

    form.find('input[name="username"]').val(username);
    form.find('input[name="password"]').val(password);
    test.click('form:eq(0) button[type="submit"]');
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

test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // 管理者用ページにログイン
    function() {
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
    // 訪問者によるユーザー新規登録を有効にする（退会にはこの設定が必要）
    function() {
        switchRegisterSetting(true);
    },
    // 設定できたことを確認してログアウト
    function() {
        test.assertText('div.alert-success', '設定を登録しました。', '設定を登録できていません。');

        test.visit('/auth/logout');
    },
    // ユーザー登録ページに移動
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ユーザー登録")', 'ログインページにユーザー登録へのリンクがありません。');
    },
    // 会員を登録（入力エラーや「修正」の確認は register.js が見ているので、ここでは最短で通す）
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="username"]').length === 1, 'ユーザー登録フォームが表示されていません。');

        form.find('input[name="username"]').val(scenarioMember.username);
        form.find('input[name="password"]').val(scenarioMember.password);
        form.find('input[name="password_confirm"]').val(scenarioMember.password);
        form.find('input[name="name"]').val(scenarioMember.name);
        form.find('input[name="email"]').val(scenarioMember.email);
        test.click('form.register button[type="submit"]');
    },
    // 確認画面から登録する
    function() {
        test.assertText('main', scenarioMember.username, '確認画面にユーザー名が表示されていません。');

        test.click('form button[type="submit"]', '確認画面に登録ボタンがありません。');
    },
    // 登録できたことを確認してログインページに移動
    function() {
        test.assertText('main', 'ユーザー情報を登録しました。', '会員を登録できていません。');

        test.visit('/auth/');
    },
    // 登録した会員でログイン
    function() {
        login(scenarioMember.username, scenarioMember.password);
    },
    // メールアドレスが未確認であることを確認して、存在確認を送信する
    function() {
        test.assertText('main', 'ようこそ、' + scenarioMember.name + 'さん', '会員でログインできていません。');
        test.assertText('main', 'メールアドレスの存在を確認してください。', '登録直後の会員に、メールアドレス未確認の案内が表示されていません。');

        test.click('a:contains("メールアドレスの存在を確認してください。")', 'メールアドレスの存在確認へのリンクがありません。');
    },
    // 送信できたことを確認して、記録された存在確認メールを開く
    function() {
        test.assertText('main', 'メールアドレスの存在確認が送信されました。', 'メールアドレスの存在確認を送信できていません。');

        // mail_log の記録は <時刻>_<宛先>.txt。/tool/test/mail は指定の時刻から10秒前まで遡って探す
        test.visit('/tool/test/mail?date=' + test.date + '&filename=' + encodeURIComponent(test.time + '_' + scenarioMember.email));
    },
    // メールの内容を確認して、本文に書かれた認証用URLを開く
    function() {
        test.assertText('body', 'to: ' + scenarioMember.email, '存在確認メールが記録されていません。（mail_log が無効になっている可能性があります）');
        test.assertText('body', 'subject: ' + verifySubject, '存在確認メールの件名が「' + verifySubject + '」になっていません。');
        test.assertText('body', 'メールアドレス存在確認用URL', '存在確認メールの本文が想定と違います。');

        test.click('a[href*="email_verify"]', '存在確認メールの本文に認証用URLのリンクがありません。');
    },
    // 認証できたことを確認して、会員ページに戻る
    function() {
        test.assertText('main', 'メールアドレス認証が完了しました。', 'メールアドレスを認証できていません。');

        test.visit('/auth/home');
    },
    // 未確認の案内が消えたことを確認して、ユーザー情報編集ページに移動
    function() {
        test.assertNoText('main', 'メールアドレスの存在を確認してください。', '認証したのに、メールアドレス未確認の案内が表示されています。');

        test.click('a:contains("ユーザー情報編集")', '会員ページに「ユーザー情報編集」がありません。');
    },
    // 名前を変えて送信（パスワードは空欄のまま＝変更しない）
    function() {
        var form = $('form.register');

        test.assertValue('form.register input[name="username"]', scenarioMember.username, '編集対象が ' + scenarioMember.username + ' ではありません。');
        test.assertValue('form.register input[name="name"]', scenarioMember.name, '編集画面に登録した名前が表示されていません。');
        test.assertValue('form.register input[name="password"]', '', '編集画面にパスワードが再表示されています。');

        form.find('input[name="name"]').val(modifiedName);
        test.click('form.register button[type="submit"]');
    },
    // 確認画面から登録する
    function() {
        test.assertText('main', modifiedName, '確認画面に編集後の名前が表示されていません。');
        test.assertNoText('main', scenarioMember.name, '確認画面に編集前の名前が表示されています。');

        test.click('form button[type="submit"]', '確認画面に登録ボタンがありません。');
    },
    // 編集できたことを確認して、会員ページに戻る
    function() {
        test.assertText('main', 'ユーザー情報を編集しました。', 'ユーザー情報を編集できていません。');

        test.visit('/auth/home');
    },
    // 名前が変わったことを確認して、ユーザー情報削除ページに移動
    function() {
        test.assertText('main', 'ようこそ、' + modifiedName + 'さん', '会員ページに編集後の名前が表示されていません。');

        test.click('a:contains("ユーザー情報削除")', '会員ページに「ユーザー情報削除」がありません。');
    },
    // 退会に進む
    function() {
        test.assertText('main', 'ユーザー情報の削除を行います。', 'ユーザー情報削除ページが表示されていません。');

        test.click('main form button[type="submit"]', 'ユーザー情報削除ページに削除ボタンがありません。');
    },
    // 確認画面から退会する
    function() {
        test.assertText('main', '本当にユーザー情報を削除してもよろしいですか？', 'ユーザー情報削除の確認画面が表示されていません。');

        test.click('main form button[type="submit"]', '確認画面に削除ボタンがありません。');
    },
    // 退会できたことを確認して、ログインページに移動
    function() {
        test.assertText('main', 'ユーザー情報を削除しました。', '退会できていません。');

        test.visit('/auth/');
    },
    // 退会した会員でログインを試みる
    function() {
        login(scenarioMember.username, scenarioMember.password);
    },
    // ログインできないことを確認して、管理者用ページにログイン
    function() {
        test.assertText('div.alert-danger', 'ユーザー名もしくはパスワードが違います。', '退会した会員でログインできてしまいます。');

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
