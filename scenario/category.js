/* カテゴリー一覧から、コードで対象の行を取得する */
var categoryRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').text().trim() === code;
    });
};

test.scenario = [
    // 初期ページからログインページに移動
    function() {
        test.assertExists('a:contains("ログイン")', '初期ページにログインへのリンクがありません。');
        test.click('a:contains("ログイン")');
    },
    // 管理者用ページにログイン
    function() {
        var form = $('form:eq(0)');

        test.assert(form.find('input[name="username"]').length === 1, 'ログインフォームが表示されていません。');

        form.find('input[name="username"]').val('admin');
        form.find('input[name="password"]').val('abcd1234');
        test.click('form:eq(0) button[type="submit"]');
    },
    // ログインできたことを確認してカテゴリー管理ページに移動
    function() {
        test.assertText('body', '管理者さん', 'ログインできていません。');
        test.click('a:contains("カテゴリー管理")');
    },
    // 前回のテストデータが残っていないことを確認してカテゴリー登録ページに移動
    function() {
        test.assert(categoryRow('test1').length === 0, 'カテゴリー test1 が残っています。前回のテストが中断した可能性があります。');
        test.assert(categoryRow('test2').length === 0, 'カテゴリー test2 が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("カテゴリー登録")');
    },
    // カテゴリーを登録
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="code"]').length === 1, 'カテゴリー登録フォームが表示されていません。');
        test.assert(form.find('select[name="type_id"] option[value="1"]').length === 1, '対象に指定する型(types.id = 1)がありません。');

        form.find('input[name="code"]').val('test1');
        form.find('input[name="name"]').val('テスト1');
        form.find('select[name="type_id"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 登録できたことを確認してカテゴリー編集ページに移動
    function() {
        test.assertText('div.alert-success', 'カテゴリーを登録しました。', 'カテゴリーを登録できていません。');
        test.assert(categoryRow('test1').length === 1, '登録したカテゴリー test1 が一覧にありません。');
        test.assertText(categoryRow('test1'), 'テスト1', '登録したカテゴリーの名前が「テスト1」になっていません。');

        test.click(categoryRow('test1').find('a'), '一覧の test1 の編集リンクが見つかりません。');
    },
    // カテゴリーを編集
    function() {
        var form = $('form.register');

        test.assertValue('form.register input[name="code"]', 'test1', '編集対象が test1 ではありません。');

        form.find('input[name="code"]').val('test2');
        form.find('input[name="name"]').val('テスト2');
        test.click('form.register button[type="submit"]');
    },
    // 編集できたことを確認してカテゴリー編集ページに移動
    function() {
        test.assertText('div.alert-success', 'カテゴリーを登録しました。', 'カテゴリーを編集できていません。');
        test.assert(categoryRow('test2').length === 1, '編集したカテゴリー test2 が一覧にありません。');
        test.assert(categoryRow('test1').length === 0, '編集前のカテゴリー test1 が一覧に残っています。');
        test.assertText(categoryRow('test2'), 'テスト2', '編集したカテゴリーの名前が「テスト2」になっていません。');

        test.click(categoryRow('test2').find('a'), '一覧の test2 の編集リンクが見つかりません。');
    },
    // カテゴリーを削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="code"]', 'test2', '削除対象が test2 ではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認してホームに移動
    function() {
        test.assertText('div.alert-success', 'カテゴリーを削除しました。', 'カテゴリーを削除できていません。');
        test.assert(categoryRow('test2').length === 0, '削除したカテゴリー test2 が一覧に残っています。');

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
