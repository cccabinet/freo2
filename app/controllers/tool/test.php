<?php

import('libs/modules/string.php');

if (DEBUG_LEVEL) {
    if (isset($_params[2]) && $_params[2] === 'mail') {
        // メール
        if ($GLOBALS['config']['mail_log'] === false) {
            return;
        }
        if (empty($_GET['date']) || !preg_match('/^\d\d\d\d\d\d\d\d$/', $_GET['date'])) {
            return;
        }
        if (empty($_GET['filename']) || !preg_match('/^[\w\-\@\.]+$/', $_GET['filename'])) {
            return;
        }

        // メールを取得
        $target = null;
        if (preg_match('/^(\d\d\d\d\d\d)(\_.+)$/', $_GET['filename'], $matches)) {
            $time = strtotime($matches[1]);
            $to   = $matches[2];

            for ($i = 0; $i < 10; $i++) {
                $filename = MAIN_APPLICATION_PATH . 'mail/' . $_GET['date'] . '/' . date('His', $time - $i) . $to . '.txt';

                if (file_exists($filename)) {
                    $target = $filename;

                    // 同じ宛先に短い間隔で複数送られている場合は、指定の時刻に近いものを使う
                    break;
                }
            }
        }
        if ($target === null) {
            $content = '該当するメールが見つかりません。';
        } else {
            $content = file_get_contents($target);
        }

        // メール表示
        echo "<!DOCTYPE html>\n";
        echo "<html>\n";
        echo "<head>\n";
        echo "<meta charset=\"" . t(MAIN_CHARSET, true) . "\" />\n";
        echo "<title>" . h($_GET['filename'], true) . "</title>\n";

        style();

        echo "</head>\n";
        echo "<body>\n";
        echo "<h1>" . h($_GET['filename'], true) . "</h1>\n";
        echo "<pre><code>" . e(string_autolink($content), true) . "</code></pre>\n";
        echo "</body>\n";
        echo "</html>\n";

        echo "<script src=\"" . t($GLOBALS['config']['http_path'], true) . t(loader_js('jquery.min.js'), true) . "\"></script>\n";
        import('app/views/test.php');

        exit;
    } elseif (isset($_params[2]) && $_params[2] === 'image') {
        // 画像
        if (empty($_POST['name']) || !preg_match('/^[\w\-\.]+$/', $_POST['name'])) {
            return;
        }
        if (empty($_POST['image'])) {
            return;
        }

        // GDが使えない環境では保存しない（error() のエラー画面もシナリオランナーを読み込んでステップが進むため、画面を出さずに終える）
        foreach (['imagecreatefromstring', 'imagepng'] as $function) {
            if (!function_exists($function)) {
                echo 'GDが有効ではないため、画像を保存できません。';

                exit;
            }
        }

        // 受け取った文字列を画像に変換
        $canvas = $_POST['image'];
        $canvas = preg_replace('/data:[^,]+,/i', '', $canvas);
        $canvas = base64_decode($canvas);
        $image  = imagecreatefromstring($canvas);
        if ($image === false) {
            echo '画像に変換できません。';

            exit;
        }

        // 画像を保存
        if (imagepng($image, $GLOBALS['config']['file_target']['test'] . $_POST['name'])) {
            ok();
        } else {
            error('画像を保存できません。');
        }

        exit;
    } else {
        unset($_SESSION['test']);

        // テスト一覧を取得
        $_view['targets'] = [];
        if ($dh = opendir('scenario/')) {
            while (($entry = readdir($dh)) !== false) {
                if (!is_file('scenario/' . $entry)) {
                    continue;
                }

                if (preg_match('/^([\w\-]+)\.js$/', $entry, $matches)) {
                    $_view['targets'][] = $matches[1];
                }
            }
            closedir($dh);

            // readdir() の順は環境やファイルの増減で変わるので、名前順に並べる
            sort($_view['targets']);
        } else {
            error('テストシナリオ格納ディレクトリを開けません。');
        }

        // テスト表示
        echo "<!DOCTYPE html>\n";
        echo "<html>\n";
        echo "<head>\n";
        echo "<meta charset=\"" . t(MAIN_CHARSET, true) . "\" />\n";
        echo "<title>Scenario test</title>\n";

        style();

        echo "</head>\n";
        echo "<body>\n";
        echo "<h1>Scenario test</h1>\n";
        echo "<p>Scenario test Index.</p>\n";
        echo "<ul>\n";
        echo "<li><a href=\"" . t(MAIN_FILE, true) . "/?test=start\">All Test.</a></li>\n";
        echo "</ul>\n";
        echo "<ol>\n";

        foreach ($_view['targets'] as $target) {
            echo "<li><a href=\"" . t(MAIN_FILE, true) . "/?test=start&amp;test_target=" . t($target, true) . "\">" . t($target, true) . "</a></li>\n";
        }

        echo "</ol>\n";

        echo "</body>\n";
        echo "</html>\n";

        exit;
    }
}
