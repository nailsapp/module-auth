<?php

/**
 * Migration:   7
 * Started:     06/07/2017
 * Finalised:   06/07/2017
 */

namespace Nails\Database\Migration\Nailsapp\ModuleAuth;

use Nails\Common\Console\Migrate\Base;

class Migration7 extends Base
{
    /**
     * Execute the migration
     * @return void
     */
    public function execute()
    {
        $this->query("DROP TABLE `{{NAIL_DB_PREFIX}}session`;");
        $this->query("
            CREATE TABLE IF NOT EXISTS `{{NAILS_DB_PREFIX}}_session` (
                `id` varchar(128) NOT NULL,
                `ip_address` varchar(45) NOT NULL,
                `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
                `data` blob NOT NULL,
                KEY `{{NAILS_DB_PREFIX}}_session_timestamp` (`timestamp`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }
}
