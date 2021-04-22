<?php

/**
 * Migration:   4
 * Started:     29/09/2015
 * Finalised:   29/09/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration4 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code` DROP FOREIGN KEY `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code_ibfk_1`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code` CHANGE `secretId` `secret_id` INT(11)  UNSIGNED  NOT NULL;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code` ADD FOREIGN KEY (`secret_id`) REFERENCES `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` (`id`) ON DELETE CASCADE;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` DROP FOREIGN KEY `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret_ibfk_1`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` CHANGE `userId` `user_id` INT(11)  UNSIGNED  NOT NULL;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` ADD FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countSends` `count_sends` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countSoftBounce` `count_soft_bounce` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_email` CHANGE `countHardBounce` `count_hard_bounce` INT(11)  UNSIGNED  NOT NULL  DEFAULT '0';");
    }
}
