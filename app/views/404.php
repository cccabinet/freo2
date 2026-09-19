<?php if (isset($GLOBALS['config'])) : ?>
<?php import('app/views/header.php') ?>
<?php else : ?>
<!DOCTYPE html>
<html lang="ja">
    <head>
        <meta charset="<?php t(MAIN_CHARSET) ?>">
        <title>404 Not Found</title>
    </head>
    <body>
        <h1>404 Not Found</h1>
<?php endif ?>

    <div id="contact">
        <h2 class="h3 mt-4 mb-3">404 Not Found</h2>
        <div class="alert alert-danger">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
            The requested URL was not found on this server.
        </div>
        <p><a href="<?php t(MAIN_FILE) ?>/" class="btn btn-secondary">BACK</a></p>
    </div>

<?php if (isset($GLOBALS['config'])) : ?>
<?php import('app/views/footer.php') ?>
<?php else : ?>
        <?php import('app/views/test.php') ?>
    </body>
</html>
<?php endif ?>
