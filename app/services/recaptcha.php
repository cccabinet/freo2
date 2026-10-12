<?php

import('libs/modules/recaptcha.php');

/**
 * reCAPTCHAの確認
 *
 * reCAPTCHAが無効なら確認しない。一度通ったら、同じセッションでは確認しない
 *
 * @return bool
 */
function service_recaptcha_verify()
{
    if ($GLOBALS['config']['recaptcha_enable'] != true || !empty($_SESSION['recaptcha'])) {
        return true;
    }

    if (!recaptcha_verify($GLOBALS['config']['recaptcha_secret_key'])) {
        return false;
    }

    $_SESSION['recaptcha'] = true;

    return true;
}
