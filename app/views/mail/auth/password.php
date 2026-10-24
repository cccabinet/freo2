<?php e($GLOBALS['setting']['mail_body_begin']) ?>

認証コード
<?php e($_view['token_code']) ?><?php e("\n") ?>

有効期限
<?php e(localdate('Y/m/d H:i', $_view['token_expire'])) ?><?php e("\n") ?>

<?php e($GLOBALS['setting']['mail_body_end']) ?>
