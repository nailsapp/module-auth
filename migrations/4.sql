ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code` CHANGE `secretId` `secret_id` INT(11)  UNSIGNED  NOT NULL;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` CHANGE `userId` `user_id` INT(11)  UNSIGNED  NOT NULL;
ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countSends` `count_sends` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';
ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countSoftBounce` `count_soft_bounce` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';
ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countHardBounce` `count_hard_bounce` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';
