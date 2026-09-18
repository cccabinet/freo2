<?php import('app/views/header.php') ?>

    <div id="contact">
        <h2 class="h3 mt-4 mb-3">WARNING</h2>
        <div class="alert alert-danger">
            <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
            <?php foreach ($_view['messages'] as $message) : ?>
            <?php h($message) ?>
            <?php endforeach ?>
        </div>
        <p><a href="<?php t(MAIN_FILE) ?>/" class="btn btn-secondary">BACK</a></p>
    </div>

<?php import('app/views/footer.php') ?>
