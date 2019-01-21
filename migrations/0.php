<?php

/**
 * Migration:   0
 * Started:     09/01/2015
 * Finalised:   09/01/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleAuth;

use Nails\Common\Console\Migrate\Base;

class Migration0 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("
            CREATE TABLE `nails_session` (
                `session_id` varchar(40) NOT NULL DEFAULT '0',
                `ip_address` varchar(45) NOT NULL DEFAULT '0',
                `user_agent` varchar(120) NOT NULL,
                `last_activity` int(10) unsigned NOT NULL DEFAULT '0',
                `user_data` text NOT NULL,
                PRIMARY KEY (`session_id`),
                KEY `last_activity_idx` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `id_md5` char(32) DEFAULT NULL,
                `group_id` int(11) unsigned NOT NULL,
                `ip_address` varchar(39) NOT NULL DEFAULT '',
                `last_ip` varchar(39) DEFAULT NULL,
                `username` varchar(30) DEFAULT NULL,
                `password` varchar(40) DEFAULT '',
                `password_md5` char(32) DEFAULT NULL,
                `password_engine` varchar(10) DEFAULT NULL,
                `password_changed` datetime DEFAULT NULL,
                `salt` varchar(40) DEFAULT NULL,
                `forgotten_password_code` varchar(100) DEFAULT NULL,
                `remember_code` varchar(255) DEFAULT NULL,
                `created` datetime NOT NULL,
                `last_login` datetime DEFAULT NULL,
                `last_seen` datetime DEFAULT NULL,
                `is_suspended` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `temp_pw` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `failed_login_count` tinyint(4) unsigned NOT NULL DEFAULT '0',
                `failed_login_expires` datetime DEFAULT NULL,
                `last_update` datetime DEFAULT NULL,
                `user_acl` text,
                `login_count` int(11) unsigned NOT NULL DEFAULT '0',
                `admin_data` text,
                `referral` varchar(10) DEFAULT NULL,
                `referred_by` int(11) unsigned DEFAULT NULL,
                `salutation` varchar(15) DEFAULT NULL,
                `first_name` varchar(150) DEFAULT NULL,
                `last_name` varchar(150) DEFAULT NULL,
                `gender` enum('UNDISCLOSED','MALE','FEMALE','TRANSGENDER','OTHER') NOT NULL DEFAULT 'UNDISCLOSED',
                `dob` date DEFAULT NULL,
                `profile_img` int(11) unsigned DEFAULT NULL,
                `timezone` varchar(40) DEFAULT NULL,
                `datetime_format_date` varchar(20) DEFAULT NULL,
                `datetime_format_time` varchar(20) DEFAULT NULL,
                `language` varchar(20) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `group_id` (`group_id`),
                KEY `id_md5` (`id_md5`),
                KEY `password_md5` (`password_md5`),
                KEY `forgotten_password_code` (`forgotten_password_code`),
                KEY `referred_by` (`referred_by`),
                KEY `profile_img` (`profile_img`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `{{NAILS_DB_PREFIX}}user_group` (`id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_ibfk_3` FOREIGN KEY (`referred_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_ibfk_8` FOREIGN KEY (`profile_img`) REFERENCES `{{NAILS_DB_PREFIX}}cdn_object` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_question` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `question` varchar(500) NOT NULL DEFAULT '',
                `answer` varchar(500) NOT NULL DEFAULT '',
                `salt` varchar(40) NOT NULL DEFAULT '',
                `created` datetime DEFAULT NULL,
                `last_requested` datetime NOT NULL,
                `last_requested_ip` varchar(39) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_two_factor_question_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_token` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `token` varchar(40) NOT NULL DEFAULT '',
                `salt` varchar(32) NOT NULL DEFAULT '',
                `created` datetime NOT NULL,
                `expires` datetime NOT NULL,
                `ip` varchar(39) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`,`salt`,`token`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_two_factor_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_email` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `email` varchar(255) NOT NULL DEFAULT '',
                `code` varchar(300) NOT NULL DEFAULT '',
                `is_verified` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `is_primary` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `date_added` datetime NOT NULL,
                `date_verified` datetime DEFAULT NULL,
                `countSends` int(11) unsigned NOT NULL DEFAULT '0',
                `countSoftBounce` int(11) unsigned NOT NULL DEFAULT '0',
                `countHardBounce` int(11) unsigned NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `email` (`email`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_email_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_email_blocker` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `type` varchar(50) NOT NULL DEFAULT '',
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_email_blocker_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_group` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `slug` varchar(150) NOT NULL DEFAULT '',
                `label` varchar(150) NOT NULL DEFAULT '',
                `description` varchar(500) NOT NULL,
                `default_homepage` varchar(255) NOT NULL,
                `registration_redirect` varchar(255) DEFAULT NULL,
                `acl` text,
                `is_default` tinyint(1) unsigned NOT NULL DEFAULT '0',
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`slug`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_group_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_group_ibfk_2` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_meta` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `user_id_2` (`user_id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_meta_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_meta_language` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `language` varchar(20) NOT NULL DEFAULT '',
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_meta_language_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_social` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `provider` varchar(50) NOT NULL DEFAULT '',
                `identifier` varchar(50) NOT NULL DEFAULT '',
                `session_data` text NOT NULL,
                `created` datetime NOT NULL,
                `modified` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`,`provider`),
                KEY `provider` (`provider`,`identifier`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_social_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("INSERT INTO `{{NAILS_DB_PREFIX}}user_group` (`slug`, `label`, `description`, `default_homepage`, `registration_redirect`, `acl`, `is_default`, `created`, `created_by`, `modified`, `modified_by`) VALUES ('superuser', 'Superuser', 'Superuser\'s have complete access to all modules in admin regardless of specific module allocations.', '/admin', NULL, '[\"admin:superuser\"]', 0, NOW(), NULL, NOW(), NULL);");
        $this->query("INSERT INTO `{{NAILS_DB_PREFIX}}user_group` (`slug`, `label`, `description`, `default_homepage`, `registration_redirect`, `acl`, `is_default`, `created`, `created_by`, `modified`, `modified_by`) VALUES ('admin', 'Administrator', 'Administrators have access to specific areas within admin.', '/admin', NULL, NULL, 0, NOW(), NULL, NOW(), NULL);");
        $this->query("INSERT INTO `{{NAILS_DB_PREFIX}}user_group` (`slug`, `label`, `description`, `default_homepage`, `registration_redirect`, `acl`, `is_default`, `created`, `created_by`, `modified`, `modified_by`) VALUES ('member', 'Member', 'Members have no access to admin.', '/', NULL, NULL, 1, NOW(), NULL, NOW(), NULL);");
    }
}

