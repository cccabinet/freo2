<?php

if (DEBUG_LEVEL) {
    /* テスト */
    if ($_REQUEST['_mode'] === 'home' && $_REQUEST['_work'] === 'index' && isset($_GET['test'])) {
        // 開始時・終了時にログイン状態を破棄
        if ($_GET['test'] === 'start' || $_GET['test'] === 'end') {
            unset($_SESSION['auth']);
        }

        if ($_GET['test'] === 'start') {
            // テスト開始
            if (isset($_GET['test_target']) && preg_match('/^[\w\-]+$/', $_GET['test_target'])) {
                // 個別テスト
                $_SESSION['test'] = array(
                    'target'  => $_GET['test_target'],
                    'session' => 0,
                    'start'   => localdate(),
                    'bulk'    => false,
                );
            } else {
                // 一括テスト
                if (empty($_SESSION['test'])) {
                    $flag = true;
                } else {
                    $flag = false;
                }

                $target = null;
                if ($dh = opendir('scenario/')) {
                    while (($entry = readdir($dh)) !== false) {
                        if (!is_file('scenario/' . $entry)) {
                            continue;
                        }

                        if (preg_match('/^([\w\-]+)\.js$/', $entry, $matches)) {
                            if (isset($_SESSION['test']) && $_SESSION['test']['target'] === $matches[1] && $flag === false) {
                                $flag = true;
                            } elseif ($flag === true) {
                                $target = $matches[1];

                                break;
                            }
                        }
                    }
                    closedir($dh);
                } else {
                    echo '<div class="error">テストシナリオ格納ディレクトリを開けません。</div>';
                    exit;
                }

                if ($target === null) {
                    if (empty($_SESSION['test'])) {
                        echo '<div class="error">テストシナリオが見つかりません。</div>';
                        exit;
                    } else {
                        unset($_SESSION['test']);

                        echo "<script>\n";
                        echo "window.alert('一括テストが終了しました。');\n";
                        echo "window.location.href = '" . t(MAIN_FILE, true) . "/?test=end&complete=" . localdate('YmdHis') . "';\n";
                        echo "</script>\n";
                    }
                }

                if ($target !== null) {
                    $_SESSION['test'] = array(
                        'target'  => $target,
                        'session' => 0,
                        'start'   => localdate(),
                        'bulk'    => true,
                    );
                }
            }
        } elseif ($_GET['test'] === 'end') {
            // テスト終了
            unset($_SESSION['test']);

            echo "<script>\n";
            echo "window.location.href = '" . t(MAIN_FILE, true) . "/tool/test';\n";
            echo "</script>\n";
        }
    }

    // 通常のページ表示以外ではテストを実行しない（iframe内やJSON応答を除外）
    if (isset($_REQUEST['_type']) && $_REQUEST['_type'] !== 'html') {
        return;
    }

    if (isset($_SESSION['test'])) {
        // テスト実行

?>
<style>
dl#test {
    z-index: 9999;
    position:  absolute;
    bottom: 0;
    left: 10px;
    padding: 5px 10px;
    border: 1px solid #CCCCCC;
    background-color: #FFFFFF;
}
dl#test dt {
    clear: left;
    float: left;
}
dl#test dt:after {
    content: ":";
}
dl#test dd {
    margin-left: 5em;
}
</style>
<dl id="test">
    <dt>target</dt>
        <dd><?php t($_SESSION['test']['target']) ?></dd>
    <dt>session</dt>
        <dd><?php t($_SESSION['test']['session']) ?></dd>
    <dt>start</dt>
        <dd><?php t($_SESSION['test']['start']) ?></dd>
    <dt>bulk</dt>
        <dd><?php t($_SESSION['test']['bulk'] ? 'true' : 'false') ?></dd>
    <dt>datetime</dt>
        <dd><?php t(localdate('Ymd His')) ?></dd>
</dl>
<script src="<?php t($GLOBALS['config']['http_path']) ?><?php t(loader_js('html2canvas.js')) ?>"></script>
<script>
/* テスト初期化 */
var test = {
    target: '<?php t($_SESSION['test']['target']) ?>',
    session: '<?php t($_SESSION['test']['session']) ?>',
    start: '<?php t($_SESSION['test']['start']) ?>',
    bulk: '<?php t($_SESSION['test']['bulk']) ?>',
    date: '<?php t(localdate('Ymd')) ?>',
    time: '<?php t(localdate('His')) ?>',
    executable: true,
    aborted: false,
    timeout: 10,
    interval: null,
    timer: null,
    scenario_dir: 'scenario/',
    config: {
        main_file: '<?php t(MAIN_FILE) ?>',
        http_path: '<?php t($GLOBALS['config']['http_path']) ?>'
    },
    data: {},
    scenario: [],
    /* テストを中断 */
    abort: function(message) {
        if (test.aborted) {
            return false;
        }
        test.aborted    = true;
        test.executable = false;

        if (test.interval !== null) {
            clearInterval(test.interval);
            test.interval = null;
        }
        if (test.timer !== null) {
            clearTimeout(test.timer);
            test.timer = null;
        }

        if (window.console) {
            console.error('[scenario] テスト ' + test.target + ' のステップ ' + test.session + ' が失敗しました。 ' + message);
        }

        window.alert('テスト ' + test.target + ' のステップ ' + test.session + ' が失敗しました。\n\n' + message);
        window.location.href = test.config.main_file + '/?test=end&error=' + test.date + test.time;

        return false;
    },
    /* 条件を検証 */
    assert: function(condition, message) {
        if (test.aborted) {
            return false;
        }
        if (condition) {
            return true;
        }

        return test.abort(message);
    },
    /* 要素が存在することを検証 */
    assertExists: function(selector, message) {
        return test.assert($(selector).length > 0, message ? message : '要素 ' + selector + ' が見つかりません。');
    },
    /* 要素が存在しないことを検証 */
    assertNotExists: function(selector, message) {
        return test.assert($(selector).length === 0, message ? message : '要素 ' + selector + ' が残っています。');
    },
    /* 要素に文字列が含まれることを検証 */
    assertText: function(selector, text, message) {
        var element = $(selector);

        if (element.length === 0) {
            return test.abort(message ? message : '要素 ' + selector + ' が見つかりません。');
        }

        return test.assert(element.text().indexOf(text) !== -1, message ? message : '要素 ' + selector + ' に「' + text + '」が含まれていません。');
    },
    /* 要素に文字列が含まれないことを検証 */
    assertNoText: function(selector, text, message) {
        var element = $(selector);

        if (element.length === 0) {
            return true;
        }

        return test.assert(element.text().indexOf(text) === -1, message ? message : '要素 ' + selector + ' に「' + text + '」が含まれています。');
    },
    /* 入力値を検証 */
    assertValue: function(selector, value, message) {
        var element = $(selector);

        if (element.length === 0) {
            return test.abort(message ? message : '要素 ' + selector + ' が見つかりません。');
        }

        return test.assert(element.val() === value, message ? message : '要素 ' + selector + ' の値が「' + value + '」ではなく「' + element.val() + '」です。');
    },
    /* 要素をクリック（存在しなければ中断） */
    click: function(selector, message) {
        if (test.aborted) {
            return false;
        }

        var element = $(selector);

        if (element.length === 0) {
            return test.abort(message ? message : 'クリック対象 ' + selector + ' が見つかりません。');
        }

        element[0].click();

        return true;
    },
    /* URLを直接開く（中断後は何もしない） */
    visit: function(url) {
        if (test.aborted) {
            return false;
        }

        location.href = test.config.main_file + url;

        return true;
    },
    /* ページを再読み込み（中断後は何もしない） */
    reload: function() {
        if (test.aborted) {
            return false;
        }

        location.reload();

        return true;
    },
    /* 条件を満たすまで待ってからコールバックを実行（満たさなければ中断） */
    wait: function(condition, callback, message) {
        if (test.aborted) {
            return false;
        }

        var limit = new Date().getTime() + test.timeout * 1000 / 2;
        var timer = setInterval(function() {
            if (test.aborted) {
                clearInterval(timer);

                return;
            }

            try {
                if (condition()) {
                    clearInterval(timer);
                    callback();
                } else if (new Date().getTime() > limit) {
                    clearInterval(timer);
                    test.abort(message ? message : '待機中の条件を満たしませんでした。');
                }
            } catch (e) {
                clearInterval(timer);
                test.abort('エラーが発生しました。\n' + e);
            }
        }, 100);

        return true;
    },
    /* シナリオを読み込み */
    loadScenario: function(file) {
        test.executable = false;

        var script = document.createElement('script');
        script.src = test.config.http_path + test.scenario_dir + file + '.js?' + test.start;
        script.onload = function() {
            test.executable = true;
        };
        document.body.appendChild(script);
    },
    /* スクリーンショットを保存 */
    saveScreenshot: function(name, callback) {
        html2canvas(document.body, {
            onrendered: function(canvas) {
                $.post(
                    test.config.main_file + '/tool/test/image',
                    {
                        name: name,
                        image: canvas.toDataURL('image/png')
                    },
                    callback()
                );
            }
        });
    }
};
/* テスト実行 */
$(window).on('load', function() {
    // iframe内では実行しない
    if (window.self !== window.top) {
        return;
    }

    var script = document.createElement('script');

    // シナリオを読み込む
    script.src = test.config.http_path + test.scenario_dir + test.target + '.js?' + test.start;
    script.onload = function() {
        test.interval = setInterval(function() {
            // 実行可能なシナリオがあるか確認する
            if (!test.executable || test.aborted) {
                return;
            }

            var session = Number(test.session);

            // シナリオを実行する前に停止する（失敗したら同じ操作を繰り返さない）
            test.executable = false;

            // シナリオを完了しているか調べる
            if (session < test.scenario.length) {
                // 完了していなければタイムアウトを設定する
                test.timer = setTimeout(function() {
                    test.abort('タイムアウトしました。(' + test.timeout + '秒以内にページが移動しませんでした)');
                }, test.timeout * 1000);

                // シナリオを進める
                try {
                    test.scenario[session]();
                } catch (e) {
                    test.abort('エラーが発生しました。\n' + e);
                }
            } else {
                // 完了していれば一括テストかどうか調べる
                if (test.bulk) {
                    // 一括テストなら次のシナリオへ
                    window.location.href = test.config.main_file + '/?test=start';
                } else {
                    // 個別テストなら終了する
                    setTimeout(function() {
                        window.alert('テスト ' + test.target + ' が終了しました。');
                        window.location.href = test.config.main_file + '/?test=end&complete=' + test.date + test.time;
                    }, 100);
                }
            }
        }, 100);
    };
    script.onerror = function() {
        test.abort('シナリオ ' + test.target + '.js を読み込めません。');
    };

    document.body.appendChild(script);
});
</script>
<?php

        $_SESSION['test']['session']++;
    }
}
