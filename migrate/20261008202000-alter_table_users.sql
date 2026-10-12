ALTER TABLE users ADD twostep TINYINT(1) UNSIGNED NOT NULL COMMENT '2段階認証の利用' AFTER token_expire;
ALTER TABLE users ADD twostep_email VARCHAR(255) COMMENT '2段階認証用のメールアドレス' AFTER twostep;
ALTER TABLE users ADD twostep_code VARCHAR(80) COMMENT '2段階認証用のコード' AFTER twostep_email;
ALTER TABLE users ADD twostep_expire DATETIME COMMENT '2段階認証用のコード有効期限' AFTER twostep_code;
