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
                <p><code class="text-dark"><?php h($_view['user']['twostep_email']) ?></code> に、2段階認証用コードを送信しました。届いたコードを入力すると、ユーザー情報を登録します。</p>

                <?php if (isset($_view['warnings'])) : ?>
                <div class="alert alert-danger">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24"><use xlink:href="#symbol-exclamation-triangle-fill"/></svg>
                    <?php foreach ($_view['warnings'] as $warning) : ?>
                    <?php h($warning) ?>
                    <?php endforeach ?>
                </div>
                <?php endif ?>

                <?php /* 送信時に common.js が送信ボタンを無効にする（無効なボタンの name・value は送られない）ので、exec は hidden で送る */ ?>
                <form action="<?php t(MAIN_FILE) ?>/auth/modify_verify" method="post">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <input type="hidden" name="exec" value="verify">
                    <div class="form-group mb-2">
                        <label class="fw-bold">2段階認証用コード <span class="badge bg-danger"><?php h($GLOBALS['string']['text_required']) ?></span></label>
                        <input type="text" name="twostep_code" size="30" value="" class="form-control" style="width: 200px;" autocomplete="one-time-code" inputmode="numeric">
                    </div>
                    <div class="form-group mt-4">
                        <a href="<?php t(MAIN_FILE) ?>/auth/modify?referer=preview" class="btn btn-secondary px-4"><?php h($GLOBALS['string']['button_auth_modify_back']) ?></a>
                        <button type="submit" class="btn btn-primary px-4"><?php h($GLOBALS['string']['button_auth_modify']) ?></button>
                    </div>
                </form>
                <form action="<?php t(MAIN_FILE) ?>/auth/modify_verify" method="post" class="mt-3">
                    <input type="hidden" name="_token" value="<?php t($_view['token']) ?>" class="token">
                    <input type="hidden" name="exec" value="resend">
                    <button type="submit" class="btn btn-link px-0">コードを再送信する</button>
                </form>
            </div>
        </div>
        <?php e($_view['widget_sets']['auth_page']) ?>
    </main>

<?php import('app/views/auth/footer.php') ?>
