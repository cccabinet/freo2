<?php import('app/views/auth/header.php') ?>

    <main class="col-11 col-md-7 mx-auto my-4">
        <div class="mb-4 text-center">
            <h1 class="h3">
                <a href="<?php t(MAIN_FILE) ?>/auth/home"><?php h($GLOBALS['string']['heading_mypage']) ?></a>
            </h1>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header heading"><?php h($_view['title']) ?></div>
            <div class="card-body">
                <?php if (isset($_GET['ok'])) : ?>
                <div class="alert alert-success">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
                    <?php if ($_GET['ok'] === 'post') : ?>
                    フィルターを登録しました。
                    <?php endif ?>
                </div>
                <?php endif ?>

                <?php if (empty($_view['attributes'])) : ?>
                <div class="alert alert-warning">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
                    選択できるフィルターはありません。
                </div>
                <?php else : ?>
                <?php e($GLOBALS['setting']['text_auth_filter']) ?>

                <form action="<?php t(MAIN_FILE) ?>/auth/filter" method="post" class="register">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <div class="form-group mb-2" id="attribute_filters">
                        <?php foreach ($_view['attributes'] as $attribute) : ?>
                        <label><input type="checkbox" name="attribute_filters[]" value="<?php t($attribute['id']) ?>" class="form-check-input"<?php in_array($attribute['id'], $_view['attribute_filters']) ? e(' checked="checked"') : '' ?>> <?php t($attribute['name']) ?></label><br>
                        <?php endforeach ?>
                    </div>
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary px-4"><?php h($GLOBALS['string']['button_auth_filter']) ?></button>
                    </div>
                </form>
                <?php endif ?>
            </div>
        </div>
        <?php e($_view['widget_sets']['auth_page']) ?>
    </main>
    <div class="my-4 text-center">
        <a href="<?php t(MAIN_FILE) ?>/auth/home"><?php h($GLOBALS['string']['text_goto_auth_home']) ?></a>
    </div>

<?php import('app/views/auth/footer.php') ?>
