<?php import('app/views/auth/header.php') ?>

    <main class="col-11 col-md-7 mx-auto my-4">
        <div class="mb-4 text-center">
            <h1 class="h3">
                <a href="<?php t(MAIN_FILE) ?>/auth/home"><?php h($GLOBALS['string']['heading_user']) ?></a>
            </h1>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header heading"><?php h($_view['title']) ?></div>
            <div class="card-body">
                <?php e($GLOBALS['setting']['text_auth_modify']) ?>

                <?php if (isset($_view['warnings'])) : ?>
                <div class="alert alert-danger">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
                    <?php foreach ($_view['warnings'] as $warning) : ?>
                    <?php h($warning) ?>
                    <?php endforeach ?>
                </div>
                <?php endif ?>

                <form action="<?php t(MAIN_FILE) ?>/auth/modify" method="post" class="register validate">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <div class="form-group mb-2">
                        <label class="fw-bold">ユーザー名 <span class="badge text-bg-danger"><?php h($GLOBALS['string']['text_required']) ?></span></label>
                        <input type="text" name="username" size="30" value="<?php t($_view['user']['username']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">パスワード（変更したい場合のみ入力）</label>
                        <input type="password" name="password" size="30" value="<?php t($_view['user']['password']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">パスワード確認（同じものをもう一度入力）</label>
                        <input type="password" name="password_confirm" size="30" value="<?php t($_view['user']['password']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">名前</label>
                        <input type="text" name="name" size="30" value="<?php t($_view['user']['name']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">メールアドレス <span class="badge text-bg-danger"><?php h($GLOBALS['string']['text_required']) ?></span></label>
                        <input type="text" name="email" size="30" value="<?php t($_view['user']['email']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">URL</label>
                        <input type="text" name="url" size="30" value="<?php t($_view['user']['url']) ?>" class="form-control">
                    </div>
                    <div class="form-group mb-2">
                        <label class="fw-bold">自己紹介</label>
                        <textarea name="text" rows="10" cols="20" class="form-control"><?php t($_view['user']['text']) ?></textarea>
                    </div>
                    <h2 class="h5 mt-4 mb-3 pb-2 border-bottom">2段階認証</h2>
                    <p>ログインするとき、パスワードに加えて、認証用メールアドレスに届くコードの入力を求めます。</p>
                    <div class="form-group mb-2">
                        <label class="fw-bold">利用 <span class="badge text-bg-danger"><?php h($GLOBALS['string']['text_required']) ?></span></label>
                        <select name="twostep" class="form-select" style="width: 200px;">
                            <?php foreach ($GLOBALS['config']['option']['user']['twostep'] as $key => $value) : ?>
                            <option value="<?php t($key) ?>"<?php $key == $_view['user']['twostep'] ? e(' selected="selected"') : '' ?>><?php t($value) ?></option>
                            <?php endforeach ?>
                        </select>
                    </div>
                    <div class="form-group mb-2 for-twostep">
                        <label class="fw-bold">認証用メールアドレス <span class="badge text-bg-danger"><?php h($GLOBALS['string']['text_required']) ?></span></label>
                        <input type="text" name="twostep_email" size="30" value="<?php t($_view['user']['twostep_email']) ?>" class="form-control">
                        <div class="form-text">登録する前に、このメールアドレスにコードを送って、届くことを確認します。</div>
                    </div>
                    <div class="form-group mt-4">
                        <button type="submit" class="btn btn-primary px-4"><?php h($GLOBALS['string']['button_auth_modify']) ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php e($_view['widget_sets']['auth_page']) ?>
    </main>
    <div class="my-4 text-center">
        <?php if ($GLOBALS['authority']['power'] >= 1) : ?>
        <a href="<?php t(MAIN_FILE) ?>/admin/home"><?php h($GLOBALS['string']['text_goto_admin_home']) ?></a>
        <?php else : ?>
        <a href="<?php t(MAIN_FILE) ?>/auth/home"><?php h($GLOBALS['string']['text_goto_auth_home']) ?></a>
        <?php endif ?>
    </div>

<?php import('app/views/auth/footer.php') ?>
