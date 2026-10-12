ALTER TABLE users ADD token_failed INT UNSIGNED COMMENT '認証失敗回数' AFTER token_expire;
ALTER TABLE users ADD twostep_failed INT UNSIGNED COMMENT '2段階認証失敗回数' AFTER twostep_expire;
