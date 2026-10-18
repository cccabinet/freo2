UPDATE users SET password = CONCAT(password, '/', password_salt);
ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL COMMENT 'パスワード';
ALTER TABLE users DROP COLUMN password_salt;
