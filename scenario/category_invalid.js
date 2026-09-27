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

/* カテゴリー一覧から、コードで対象の行を取得する */
var categoryRow = function(code) {
    return $('table tbody tr').filter(function() {
        return $(this).find('td code').text().trim() === code;
    });
};

/* カテゴリー一覧に、指定したコードの行があるか */
var categoryRowExists = function(code) {
    return categoryRow(code).length > 0;
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
        test.assert(!categoryRowExists('scenario-invalid'), 'カテゴリー scenario-invalid が残っています。前回のテストが中断した可能性があります。');
        test.click('a:contains("カテゴリー登録")');
    },
    // 未入力で送信すると、必須項目それぞれに入力エラーが表示されることを確認
    function() {
        test.assert($('form.register').length === 1, 'カテゴリー登録フォームが表示されていません。');

        submitAndWait({ code: '', name: '', type_id: '' }, function() {
            test.assertText(fieldWarning('code'), 'コードが入力されていません。', 'コード未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('name'), '名前が入力されていません。', '名前未入力のエラーが表示されていません。');
            test.assertText(fieldWarning('type_id'), '型が入力されていません。', '型未選択のエラーが表示されていません。');

            test.reload();
        });
    },
    // 形式・長さが不正な値で送信すると、該当項目にだけ入力エラーが表示されることを確認
    function() {
        submitAndWait({ code: 'コード', name: 'あ'.repeat(21), type_id: '1' }, function() {
            test.assertText(fieldWarning('code'), 'コードは半角英数字で入力してください。', 'コード形式のエラーが表示されていません。');
            test.assertText(fieldWarning('name'), '名前は20文字以内で入力してください。', '名前21文字のエラーが表示されていません。');
            test.assert(fieldWarning('type_id').length === 0, '正しく選択した型に入力エラーが表示されています。');

            test.reload();
        });
    },
    // 境界値: コード1文字(下限-1)は入力エラー、名前20文字(上限ちょうど)は入力エラーにならないことを確認
    function() {
        submitAndWait({ code: 'a'.repeat(1), name: 'あ'.repeat(20), type_id: '1' }, function() {
            test.assertText(fieldWarning('code'), 'コードは2文字以上80文字以内で入力してください。', 'コード1文字のエラーが表示されていません。');
            test.assert(fieldWarning('name').length === 0, '名前20文字で入力エラーが表示されています。');

            test.reload();
        });
    },
    // 正しい入力内容のまま、ワンタイムトークンだけを不正にして送信
    function() {
        var form   = $('form.register');
        var values = { id: '', code: 'scenario-invalid', name: 'シナリオ', type_id: '1', memo: '' };

        test.assert(form.find('input[name="_token"]').val() !== '', 'ワンタイムトークンがフォームにありません。');

        // common.js の form.validate は送信前にAjaxで検証し、ERRORなら alert を出すだけで画面遷移しないので外す
        form.off('submit');

        for (var name in values) {
            form.find('[name="' + name + '"]').val(values[name]);
        }
        form.find('input[name="_token"]').val('invalid-token');

        test.click('form.register button[type="submit"]');
    },
    // 不正なトークンの送信がエラー画面で拒否されたことを確認してカテゴリー管理ページに移動
    function() {
        test.assertText('div.alert-danger', '不正な操作が検出されました。', '不正なトークンの送信が拒否されていません。');
        test.visit('/admin/category');
    },
    // 拒否された送信でカテゴリーが登録されていないことを確認してカテゴリー登録ページに移動
    function() {
        test.assert($('table').length === 1, 'カテゴリー管理ページが表示されていません。');
        test.assert(!categoryRowExists('scenario-invalid'), '不正なトークンの送信でカテゴリーが登録されています。');
        test.click('a:contains("カテゴリー登録")');
    },
    // コードの重複を確認するため、正しい内容で1件登録する
    function() {
        var form = $('form.register');

        test.assert(form.find('input[name="code"]').length === 1, 'カテゴリー登録フォームが表示されていません。');

        form.find('input[name="code"]').val('scenario-invalid');
        form.find('input[name="name"]').val('シナリオ');
        form.find('select[name="type_id"]').val('1');
        test.click('form.register button[type="submit"]');
    },
    // 登録できたことを確認して、もう一度カテゴリー登録ページに移動
    function() {
        test.assertText('div.alert-success', 'カテゴリーを登録しました。', 'カテゴリーを登録できていません。');
        test.assert(categoryRowExists('scenario-invalid'), '登録したカテゴリーが一覧にありません。');

        test.click('a:contains("カテゴリー登録")');
    },
    // 同じコードで送信すると、コードにだけ入力エラーが表示されることを確認
    function() {
        submitAndWait({ code: 'scenario-invalid', name: 'シナリオ2', type_id: '1' }, function() {
            test.assertText(fieldWarning('code'), '入力されたコードはすでに使用されています。', 'コード重複のエラーが表示されていません。');
            test.assert($('form.register div.warning').length === 1, 'コード以外にも入力エラーが表示されています。（' + $('form.register div.warning').text() + '）');

            test.visit('/admin/category');
        });
    },
    // 重複した送信で増えていないことを確認して、カテゴリー編集ページに移動
    function() {
        test.assert(categoryRow('scenario-invalid').length === 1, 'コードが重複したカテゴリーが登録されています。');

        test.click(categoryRow('scenario-invalid').find('a'), '一覧の scenario-invalid の編集リンクが見つかりません。');
    },
    // 登録したカテゴリーを削除
    function() {
        var form = $('form.delete');

        test.assertValue('form.register input[name="code"]', 'scenario-invalid', '削除対象が scenario-invalid ではありません。');
        test.assert(form.length === 1, '削除フォームが表示されていません。');

        form.off('submit');
        test.click('form.delete button[type="submit"]');
    },
    // 削除できたことを確認してホームに移動
    function() {
        test.assertText('div.alert-success', 'カテゴリーを削除しました。', 'カテゴリーを削除できていません。');
        test.assert(!categoryRowExists('scenario-invalid'), '削除したカテゴリーが一覧に残っています。');

        test.click('a:contains("ホーム")');
    },
    // 管理者用ページからログアウト
    function() {
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
