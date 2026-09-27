/* テスト用ユーザー（test オブジェクトはページごとに作り直されるため、値は定数で持つ） */
var scenarioUser = {
    username: 'scenario-user',
    password: 'scenario1234',
    name: 'シナリオ',
    email: 'scenario-user@example.com',
    authority: '閲覧者'
};

/* アカウントが凍結されるまでのログイン失敗回数 */
var lockoutCount = 10;

/* ユーザー一覧から、ユーザー名で対象の行を取得する */
var userRow = function(username) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().find('code').text().trim() === username;
    });
};

/* 権限の選択肢から、名前で対象を取得する */
var authorityOption = function(form, name) {
    return form.find('select[name="authority_id"] option').filter(function() {
        return $(this).text() === name;
    });
};

/* 管理者用ページからログアウトする */
var logout = function(name) {
    test.click('a:contains("' + name + 'さん")');
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

/* テスト用ユーザーで count 回目のログイン失敗を起こすステップを作る（2回目以降は、前回の失敗が表示されていることも確認する） */
var failedLoginStep = function(count) {
    return function() {
        if (count > 1) {
            test.assertText('div.alert-danger', 'ユーザー名もしくはパスワードが違います。', (count - 1) + '回目の失敗でエラーが表示されていません。');
        }

        login(scenarioUser.username, 'wrong-password-1');
    };
};

var failedLoginSteps = [];
for (var i = 1; i <= lockoutCount; i++) {
    failedLoginSteps.push(failedLoginStep(i));
}

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
    // ログインできたことを確認してユーザー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("ユーザー管理")');
    },
    // 前回のテストデータが残っていないことを確認してユーザー登録ページに移動
    function() {
        test.assert(userRow(scenarioUser.username).length === 0, 'ユーザー ' + scenarioUser.username + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("ユーザー登録")');
    },
    // 閲覧者のユーザーを登録
    function() {
        var form      = $('form.register');
        var authority = authorityOption(form, scenarioUser.authority);

        test.assert(form.find('input[name="username"]').length === 1, 'ユーザー登録フォームが表示されていません。');
        test.assert(authority.length === 1, '権限「' + scenarioUser.authority + '」がありません。');

        form.find('input[name="username"]').val(scenarioUser.username);
        form.find('input[name="password"]').val(scenarioUser.password);
        form.find('input[name="password_confirm"]').val(scenarioUser.password);
        form.find('input[name="name"]').val(scenarioUser.name);
        form.find('input[name="email"]').val(scenarioUser.email);
        form.find('select[name="authority_id"]').val(authority.val());
        form.find('select[name="enabled"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 登録できたことを確認して、再度ユーザー登録ページに移動
    function() {
        test.assertText('div.alert-success', 'ユーザーを登録しました。', 'ユーザーを登録できていません。');
        test.assert(userRow(scenarioUser.username).length === 1, '登録したユーザーが一覧にありません。');
        test.assertText(userRow(scenarioUser.username), scenarioUser.authority, '登録したユーザーの権限が「' + scenarioUser.authority + '」になっていません。');

        test.click('a:contains("ユーザー登録")');
    },
    // 同じユーザー名では登録できず、ユーザー名にだけ入力エラーが表示されることを確認してログアウト
    function() {
        var form      = $('form.register');
        var authority = authorityOption(form, scenarioUser.authority);

        // 重複チェックが壊れていた場合に登録されても害が無いよう、権限は必ず閲覧者にする
        test.assert(authority.length === 1, '権限「' + scenarioUser.authority + '」がありません。');

        form.find('input[name="username"]').val(scenarioUser.username);
        form.find('input[name="password"]').val(scenarioUser.password);
        form.find('input[name="password_confirm"]').val(scenarioUser.password);
        form.find('input[name="email"]').val('scenario-user-duplicate@example.com');
        form.find('select[name="authority_id"]').val(authority.val());

        $('div.warning').remove();
        test.click('form.register button[type="submit"]');
        test.wait(function() {
            return $('form.register div.warning').length > 0;
        }, function() {
            test.assertText(form.find('input[name="username"]').parent().find('div.warning'), '入力されたユーザー名はすでに使用されています。', 'ユーザー名の重複エラーが表示されていません。');
            test.assert($('form.register div.warning').length === 1, 'ユーザー名以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            logout('管理者');
        }, '重複したユーザー名で入力エラーが表示されませんでした。');
    },
    // 閲覧者でログイン
    function() {
        login(scenarioUser.username, scenarioUser.password);
    },
    // 閲覧者には権限外のメニューが表示されないことを確認し、カテゴリー管理のURLを直接開く
    function() {
        test.assertText('body', scenarioUser.name + 'さん', '閲覧者でログインできていません。');
        test.assertNotExists('a:contains("カテゴリー管理")', '閲覧者に「カテゴリー管理」メニューが表示されています。');
        test.assertNotExists('a:contains("ユーザー管理")', '閲覧者に「ユーザー管理」メニューが表示されています。');

        test.visit('/admin/category');
    },
    // カテゴリー管理が拒否されたことを確認し、ユーザー管理のURLを直接開く
    function() {
        test.assertText('div.alert-danger', '不正なアクセスです。', '閲覧者がカテゴリー管理にアクセスできています。');
        test.visit('/admin/user');
    },
    // ユーザー管理も拒否されたことを確認してログアウト
    function() {
        test.assertText('div.alert-danger', '不正なアクセスです。', '閲覧者がユーザー管理にアクセスできています。');
        test.visit('/auth/logout');
    }
].concat(failedLoginSteps, [
    // 最後の失敗を確認したうえで、正しいパスワードでログインを試みる
    function() {
        test.assertText('div.alert-danger', 'ユーザー名もしくはパスワードが違います。', lockoutCount + '回目の失敗でエラーが表示されていません。');

        login(scenarioUser.username, scenarioUser.password);
    },
    // アカウントが凍結され、正しいパスワードでもログインできないことを確認してログインページに移動
    function() {
        test.assertText('div.alert-danger', '凍結されています', lockoutCount + '回失敗した後に、正しいパスワードでログインできてしまいます。');
        test.visit('/auth/');
    },
    // 管理者でログイン（管理者は凍結されていない）
    function() {
        login('admin', 'abcd1234');
    },
    // 管理者でログインできたことを確認してユーザー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', '管理者でログインできていません。');
        test.click('a:contains("ユーザー管理")');
    },
    // テスト用ユーザーの編集ページに移動
    function() {
        test.click(userRow(scenarioUser.username).find('a'), '一覧の ' + scenarioUser.username + ' の編集リンクが見つかりません。');
    },
    // テスト用ユーザーを削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="username"]', scenarioUser.username, '削除対象が ' + scenarioUser.username + ' ではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認してログアウト
    function() {
        test.assertText('div.alert-success', 'ユーザーを削除しました。', 'ユーザーを削除できていません。');
        test.assert(userRow(scenarioUser.username).length === 0, '削除したユーザーが一覧に残っています。');

        logout('管理者');
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
]);
