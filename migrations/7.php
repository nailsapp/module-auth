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
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}session` CHANGE `session_id` `id` VARCHAR(128)  CHARACTER SET utf8  COLLATE utf8_general_ci  NOT NULL  DEFAULT '0';");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}session` CHANGE `user_data` `data` BLOB  NOT NULL;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}session` CHANGE `last_activity` `timestamp` INT(10)  UNSIGNED  NOT NULL  DEFAULT '0';");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}session` DROP `user_agent`;");
    }
}
