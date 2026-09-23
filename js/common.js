$(document).ready(function() {

    /*
     * ツールチップ
     */
    $('[data-bs-toggle="tooltip"]').tooltip();

    /*
     * プレビュー
     */
    $('.preview').on('click', function() {
        $(this).closest('form').attr('target', '_blank');
        $(this).closest('form').find('input[name=view]').val('preview');

        $(this).closest('form').submit();

        $(this).closest('form').find('input[name=view]').val('');
        $(this).closest('form').attr('target', '');

        return false;
    });
    $('.close').on('click', function() {
        window.close();

        return false;
    });

    /*
     * 入力フォーム
     */
    $('form[method=post] :submit').removeAttr('disabled');
    $('form[method=post]').on('submit', function() {
        var form = $(this);

        form.find(':submit').attr('disabled', 'disabled');

        setTimeout(function() {
            form.find(':submit').removeAttr('disabled');
        }, 3000);

        $(window).off('beforeunload');

        return true;
    });

    /*
     * 入力破棄確認
     */
    $('form.register input[type=text], form.register textarea').on('change', function() {
        $(window).on('beforeunload', function() {
            return '編集中の内容は破棄されます。';
        });
    });

    /*
     * 入力内容検証
     */
    $('form.validate').on('submit', function() {
        var form = $(this);

        if ((form.find('input[name=view]').length == 0 || form.find('input[name=view]').val() == '') && typeof flag === 'undefined') {
            $.ajax({
                type: form.attr('method'),
                url: form.attr('action'),
                cache: false,
                data: form.serialize() + '&_type=json',
                dataType: 'json',
                success: function(response) {
                    // トークンを更新
                    $('form input.token').val(response.values.token);
                    $('a.token').attr('data-token', response.values.token);

                    if (response.status == 'OK') {
                        // 正常終了
                        flag = true;

                        form.submit();
                    } else if (response.status == 'WARNING') {
                        // 入力エラーを表示
                        $('div.warning').remove();

                        var messages = [];

                        for (var key in response.messages) {
                            if (form.find('[id=validate_' + key + ']').length) {
                                form.find('[id=validate_' + key + ']').append('<div class="warning">' + response.messages[key] + '</div>');
                            } else if (form.find('[name=' + key + ']').length) {
                                form.find('[name=' + key + ']').parent().append('<div class="warning">' + response.messages[key] + '</div>');
                            } else {
                                messages.push(response.messages[key]);
                            }
                        }

                        if (messages.length) {
                            window.alert(messages.join('\n'));
                        }

                        if ($('.warning').length > 0) {
                            $('html, body').animate({
                                scrollTop: $('.warning').first().offset().top - 100
                            }, 500);
                        }

                        form.find(':submit').removeAttr('disabled');
                    } else if (response.status == 'ERROR') {
                        // エラーを表示
                        window.alert(response.message);

                        form.find(':submit').removeAttr('disabled');
                    } else {
                        // 予期しないエラー
                        window.alert('予期しないエラーが発生しました。');

                        form.find(':submit').removeAttr('disabled');
                    }
                },
                error: function(request, status, errorThrown) {
                    form.find(':submit').removeAttr('disabled');

                    console.log(request);
                    console.log(status);
                    console.log(errorThrown);
                }
            });

            return false;
        } else {
            return true;
        }
    });

    /*
     * 承認確認
     */
    $('a.approve').on('click', function() {
        return window.confirm('本当に承認を変更してもよろしいですか？');
    });
    $('form.approve').on('submit', function() {
        if (window.confirm('本当に承認を変更してもよろしいですか？')) {
            return true;
        } else {
            $(this).find(':submit').removeAttr('disabled');

            return false;
        }
    });

    /*
     * 削除確認
     */
    $('a.delete').on('click', function() {
        return window.confirm('本当に削除してもよろしいですか？');
    });
    $('form.delete').on('submit', function() {
        if (window.confirm('本当に削除してもよろしいですか？')) {
            return true;
        } else {
            $(this).find(':submit').removeAttr('disabled');

            return false;
        }
    });

    /*
     * ラベルとコントロールの関連付け
     */
    const nameCounts = {};

    $('input, select, textarea').each(function(index) {
      const $el = $(this);
      
      // 1. name属性がなければ既存のid、または fallback用の識別子を使用
      let rawName = $el.attr('name');
      if (!rawName) {
        rawName = $el.attr('id') || `unnamed-field-${index}`;
      }

      // 2. 括弧のパターンに応じて綺麗に変換する
      let baseName = rawName
        .replace(/\[\]/g, '')      // 「[]」は削除
        .replace(/\[/g, '-')       // 「[」を「-」に
        .replace(/\]/g, '')        // 「]」を削除
        .replace(/-+/g, '-')       // 「--」を「-」に
        .replace(/-$/, '');        // 末尾の「-」を削除

      let idName = baseName;

      // 3. 重複カウントとID生成
      const selector = $el.attr('name') 
        ? `[name="${$.escapeSelector(rawName)}"]` 
        : `[id="${$.escapeSelector(rawName)}"]`;

      if ($(selector).length > 1 || !nameCounts[rawName]) {
        if (!nameCounts[rawName]) {
          nameCounts[rawName] = 1;
        } else {
          idName = `${baseName}-${nameCounts[rawName]}`;
        }
        nameCounts[rawName]++;
      }

      // 4. 対象のlabelを探す
      let $label = $el.prev('label').length ? $el.prev('label') : $el.next('label');
      
      if (!$label.length) {
        $label = $el.closest('label');
      }
      
      if (!$label.length) {
        $label = $el.parent().prev('label').length ? $el.parent().prev('label') : $el.parent().next('label');
      }

      if (!$label.length) {
        $label = $el.closest('.form-group, .row, tr, td, dd, fieldset').find('label').first();
      }

      // 5. labelが見つかった場合のみ、idとforを付与する
      if ($label.length) {
        if (!$el.attr('id')) {
          $el.attr('id', idName);
        }
        $label.attr('for', $el.attr('id'));
      }
    });

    /*
     * クラス名に応じた role 属性の自動付与
     */
    const roleMappings = [
      { selector: 'a', className: 'btn', role: 'button' },
      { selector: 'div', className: 'alert', role: 'alert' },
      // 必要に応じて追加可能
      // { selector: 'nav', className: 'pagination', role: 'navigation' },
    ];

    roleMappings.forEach(mapping => {
      $(mapping.selector).each(function() {
        const $el = $(this);
        // 対象クラスそのもの、または「クラス名-」で始まるクラスを持っているか判定
        const hasMatchClass = $el.hasClass(mapping.className) || 
          Array.from(this.classList || []).some(c => c.startsWith(`${mapping.className}-`));

        if (hasMatchClass && !$el.attr('role')) {
          $el.attr('role', mapping.role);
        }
      });
    });

});
