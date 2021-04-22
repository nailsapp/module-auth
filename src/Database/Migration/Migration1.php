<?php

/**
 * Migration:   1
 * Started:     27/01/2015
 * Finalised:   27/01/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration1 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `secretId` int(11) unsigned NOT NULL,
                `code` varchar(10) NOT NULL DEFAULT '',
                `used` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `secret_id` (`secretId`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_code_ibfk_1` FOREIGN KEY (`secretId`) REFERENCES `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `userId` int(11) unsigned NOT NULL,
                `secret` varchar(500) NOT NULL DEFAULT '',
                `created` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`userId`,`secret`(255)),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_two_factor_device_secret_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }
}
