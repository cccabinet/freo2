test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // 誤ったパスワードでログイン
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('wrong-password-1');
        test.click('form:eq(0) button[type="submit"]');
    },
    // ログインできないことを確認し、存在しないユーザーでログイン
    function() {
        var form = $('form:eq(0)');

        test.assertText('div.alert-danger', 'ユーザー名もしくはパスワードが違います。', '誤ったパスワードでエラーが表示されていません。');
        test.assertNoText('body', '管理者さん', '誤ったパスワードでログインできてしまいました。');
        test.assertValue('form:eq(0) input[name="username"]', 'admin', '入力したユーザー名が再表示されていません。');

        form.find('input[name="username"]').val('scenario-nobody');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // ログインできないことを確認し、正しいパスワードでログイン
    function() {
        var form = $('form:eq(0)');

        test.assertText('div.alert-danger', 'ユーザー名もしくはパスワードが違います。', '存在しないユーザーでエラーが表示されていません。');
        test.assertExists('form:eq(0) input[name="password"]', '存在しないユーザーでログインできてしまいました。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // 失敗の後でもログインできることを確認してログアウト
    function() {
        test.assertText('body', '管理者さん', 'ログイン失敗の後に、正しいパスワードでログインできません。');

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
