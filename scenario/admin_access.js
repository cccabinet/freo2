/*
 * 投稿者（power 2）の権限境界を見るためのユーザー。
 * 「テスト用ユーザーは最小権限で作る」のが原則だが、投稿者に入れる画面／入れない画面を
 * 確認するにはこの権限でないといけないので、ここだけ例外にしている。
 */
var scenarioEditor = {
    username:  'scenario-editor',
    password:  'scenario5678',
    name:      'シナリオ投稿者',
    email:     'scenario-editor@example.com',
    authority: '投稿者'
};

/* 有効化されていないユーザー。権限は最小の「閲覧者」でよい */
var scenarioDisabled = {
    username:  'scenario-disabled',
    password:  'scenario5678',
    name:      'シナリオ無効',
    email:     'scenario-disabled@example.com',
    authority: '閲覧者'
};

/* 未ログインで開こうとする管理画面（ログイン後にここへ戻ることも確認する） */
var restrictedUrl = '/admin/category';

/* 投稿者が入れない管理画面（before.php の判定より） */
var deniedUrls = ['/admin/user', '/admin/setting', '/admin/field'];

/* 投稿者に表示されないメニュー */
var deniedMenus = ['ユーザー管理', '設定', 'フィールド管理', 'ウィジェット管理'];

/* ユーザー一覧から、ユーザー名で対象の行を取得する（メールアドレスの列も code なので先頭の td だけを見る） */
var userRow = function(username) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td').first().find('code').text().trim() === username;
    });
};

/* 選択肢から、表示名で対象を取得する */
var optionByText = function(form, name, text) {
    return form.find('select[name="' + name + '"] option').filter(function() {
        return $(this).text() === text;
    });
};

/* ログインフォームから送信する */
var login = function(username, password) {
    var form = $('form:eq(0)');

    test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

    form.find('input[name="username"]').val(username);
    form.find('input[name="password"]').val(password);
    test.click('form:eq(0) button[type="submit"]');
};

/* テスト用ユーザーを登録する */
var registerUser = function(user, enabled) {
    var form      = $('form.register');
    var authority = optionByText(form, 'authority_id', user.authority);

    test.assert(form.find('input[name="username"]').length === 1, 'ユーザー登録フォームが表示されていません。');
    test.assert(authority.length === 1, '権限「' + user.authority + '」がありません。');

    form.find('input[name="username"]').val(user.username);
    form.find('input[name="password"]').val(user.password);
    form.find('input[name="password_confirm"]').val(user.password);
    form.find('input[name="name"]').val(user.name);
    form.find('input[name="email"]').val(user.email);
    form.find('select[name="authority_id"]').val(authority.val());
    form.find('select[name="enabled"]').val(enabled);
    test.click('form.register button[type="submit"]');
};

/* 編集ページでユーザーを削除する */
var deleteUser = function(username) {
    var form = $('form.delete');

    test.assertValue('form.register input[name="username"]', username, '削除対象が ' + username + ' ではありません。');
    test.assert(form.length === 1, '削除フォームが表示されていません。');

    form.off('submit');
    test.click('form.delete button[type="submit"]');
};

/* 管理者用ページからログアウトする */
var logout = function(name) {
    test.click('a:contains("' + name + 'さん")');
    setTimeout(function() {
        test.click('a:contains("ログアウト")');
    }, 500);
};

test.scenario = [
    // 未ログインのまま管理画面を開こうとする
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.visit(restrictedUrl);
    },
    // ログインページに飛ばされたことを確認して、管理者でログイン
    function() {
        test.assertExists('input[name="username"]', '未ログインで管理画面を開けています。');
        test.assert(location.search.indexOf('referer=') !== -1, 'ログインページに戻り先（referer）が引き継がれていません。（' + location.search + '）');

        login('admin', 'abcd1234');
    },
    // 元の画面に戻ったことを確認してユーザー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.assert(location.pathname.indexOf(restrictedUrl) !== -1, 'ログイン後に元の画面（' + restrictedUrl + '）へ戻っていません。（' + location.pathname + '）');

        test.click('a:contains("ユーザー管理")');
    },
    // 前回のテストデータが残っていないことを確認してユーザー登録ページに移動
    function() {
        test.assert(userRow(scenarioEditor.username).length === 0, 'ユーザー ' + scenarioEditor.username + ' が残っています。前回のテストが中断した可能性があります。');
        test.assert(userRow(scenarioDisabled.username).length === 0, 'ユーザー ' + scenarioDisabled.username + ' が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("ユーザー登録")');
    },
    // 投稿者のユーザーを登録
    function() {
        registerUser(scenarioEditor, '1');
    },
    // 登録できたことを確認して、もう一度ユーザー登録ページに移動
    function() {
        test.assertText('div.alert-success', 'ユーザーを登録しました。', 'ユーザーを登録できていません。');
        test.assertText(userRow(scenarioEditor.username), scenarioEditor.authority, '登録したユーザーの権限が「' + scenarioEditor.authority + '」になっていません。');

        test.click('a:contains("ユーザー登録")');
    },
    // 有効化されていないユーザーを登録
    function() {
        registerUser(scenarioDisabled, '0');
    },
    // 登録できたことを確認してログアウト
    function() {
        test.assertText('div.alert-success', 'ユーザーを登録しました。', 'ユーザーを登録できていません。');
        test.assert(userRow(scenarioDisabled.username).find('span.badge').last().text().trim() === '無効', '登録したユーザーが「無効」になっていません。');

        logout('管理者');
    },
    // 有効化されていないユーザーでログインを試みる
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');

        login(scenarioDisabled.username, scenarioDisabled.password);
    },
    // ログインできないことを確認して、投稿者でログイン
    function() {
        test.assertText('div.alert-danger', 'アカウントが有効化されていません。', '有効化されていないユーザーでログインできてしまいます。');

        login(scenarioEditor.username, scenarioEditor.password);
    },
    // 投稿者に権限外のメニューが表示されないことを確認して、入れる画面を開く
    function() {
        test.assertText('body', scenarioEditor.name + 'さん', '投稿者でログインできていません。');

        for (var i = 0; i < deniedMenus.length; i++) {
            test.assertNotExists('nav a:contains("' + deniedMenus[i] + '")', '投稿者に「' + deniedMenus[i] + '」のメニューが表示されています。');
        }
        test.assertExists('nav a:contains("エントリー管理")', '投稿者に「エントリー管理」のメニューが表示されていません。');

        test.visit('/admin/category');
    },
    // 投稿者でもコンテンツ系の画面には入れることを確認して、権限外の画面を開く
    function() {
        test.assertText('body', scenarioEditor.name + 'さん', '投稿者がカテゴリー管理に入れません。');
        test.assertNoText('div.alert-danger', '不正なアクセスです。', '投稿者がカテゴリー管理で拒否されています。');

        test.visit(deniedUrls[0]);
    },
    // 権限外の画面が拒否されたことを確認して、次の画面を開く
    function() {
        test.assertText('div.alert-danger', '不正なアクセスです。', '投稿者が ' + deniedUrls[0] + ' に入れています。');

        test.visit(deniedUrls[1]);
    },
    // 権限外の画面が拒否されたことを確認して、次の画面を開く
    function() {
        test.assertText('div.alert-danger', '不正なアクセスです。', '投稿者が ' + deniedUrls[1] + ' に入れています。');

        test.visit(deniedUrls[2]);
    },
    // 権限外の画面が拒否されたことを確認してログアウト
    function() {
        test.assertText('div.alert-danger', '不正なアクセスです。', '投稿者が ' + deniedUrls[2] + ' に入れています。');

        // エラー画面には管理画面のメニュー（ログアウト）が無い
        test.visit('/auth/logout');
    },
    // 管理者用ページにログイン
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');

        login('admin', 'abcd1234');
    },
    // ログインできたことを確認してユーザー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("ユーザー管理")');
    },
    // 投稿者のユーザーの編集ページに移動
    function() {
        test.click(userRow(scenarioEditor.username).find('a'), '一覧の ' + scenarioEditor.username + ' の編集リンクが見つかりません。');
    },
    // 投稿者のユーザーを削除
    function() {
        deleteUser(scenarioEditor.username);
    },
    // 削除できたことを確認して、有効化されていないユーザーの編集ページに移動
    function() {
        test.assertText('div.alert-success', 'ユーザーを削除しました。', 'ユーザーを削除できていません。');
        test.assert(userRow(scenarioEditor.username).length === 0, '削除したユーザーが一覧に残っています。');

        test.click(userRow(scenarioDisabled.username).find('a'), '一覧の ' + scenarioDisabled.username + ' の編集リンクが見つかりません。');
    },
    // 有効化されていないユーザーを削除
    function() {
        deleteUser(scenarioDisabled.username);
    },
    // 削除できたことを確認してホームに移動
    function() {
        test.assertText('div.alert-success', 'ユーザーを削除しました。', 'ユーザーを削除できていません。');
        test.assert(userRow(scenarioDisabled.username).length === 0, '削除したユーザーが一覧に残っています。');

        test.click('a:contains("ホーム")');
    },
    // 管理者用ページからログアウト
    function() {
        test.assertExists('a:contains("管理者さん")', '管理者用ページが表示されていません。');

        logout('管理者');
    },
    // ログアウトできたことを確認して初期ページに戻る
    function() {
        test.assertExists('input[name="username"]', 'ログアウトできていません。');
        test.click('a:contains("ホームページへ戻る")');
    }
];
