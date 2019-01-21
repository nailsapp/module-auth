<?php

/**
 * Migration:   2
 * Started:     24/03/2015
 * Finalised:   24/03/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleAuth;

use Nails\Common\Console\Migrate\Base;

class Migration2 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("
            CREATE TABLE `{{NAILS_DB_PREFIX}}user_auth_access_token` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` int(11) unsigned NOT NULL,
                `label` varchar(100) DEFAULT NULL,
                `token` varchar(64) NOT NULL DEFAULT '',
                `expires` datetime DEFAULT NULL,
                `scope` varchar(100) DEFAULT NULL,
                `created` datetime NOT NULL,
                `created_by` int(11) unsigned DEFAULT NULL,
                `modified` datetime NOT NULL,
                `modified_by` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `created_by` (`created_by`),
                KEY `modified_by` (`modified_by`),
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_access_token_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_access_token_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE SET NULL,
                CONSTRAINT `{{NAILS_DB_PREFIX}}user_auth_access_token_ibfk_3` FOREIGN KEY (`modified_by`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON UPDATE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }
}
