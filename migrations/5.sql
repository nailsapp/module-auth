RENAME TABLE `{{NAILS_DB_PREFIX}}user_meta` TO `{{NAILS_DB_PREFIX}}user_meta_app`;
DROP TABLE `{{NAILS_DB_PREFIX}}user_meta_language`;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP `id`;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP FOREIGN KEY `{{NAILS_DB_PREFIX}}user_meta_app_ibfk_3`;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP INDEX `user_id_2`;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP INDEX `user_id`;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` ADD PRIMARY KEY (`user_id`);
ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` ADD FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE;
