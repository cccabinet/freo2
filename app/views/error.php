<?php if (isset($GLOBALS['config'])) : ?>
<?php import('app/views/header.php') ?>
<?php else : ?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="<?php t(MAIN_CHARSET) ?>">
        <title>Error</title>
    </head>
    <body>
        <h1>Error</h1>
<?php endif ?>

    <div id="contact">
        <h2 class="h3 mt-4 mb-3">ERROR</h2>
        <div class="alert alert-danger">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
            <?php h($_view['message']) ?>
        </div>
        <p><a href="<?php t(MAIN_FILE) ?>/" class="btn btn-secondary">BACK</a></p>
    </div>

<?php if (isset($GLOBALS['config'])) : ?>
<?php import('app/views/footer.php') ?>
<?php else : ?>
        <?php if (function_exists('loader_js')) : ?>
        <script src="<?php t($GLOBALS['config']['http_path']) ?><?php t(loader_js('jquery.min.js')) ?>"></script>
        <?php import('app/views/test.php') ?>
        <?php endif ?>
    </body>
</html>
<?php endif ?>
