-- Міграція для додавання поля remember_token
ALTER TABLE `users` ADD COLUMN `remember_token` VARCHAR(64) DEFAULT NULL AFTER `password`;
