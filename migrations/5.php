<?php

/**
 * Migration:   5
 * Started:     29/09/2015
 * Finalised:   29/09/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleAuth;

use Nails\Common\Console\Migrate\Base;

class Migration5 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("RENAME TABLE `{{NAILS_DB_PREFIX}}user_meta` TO `{{NAILS_DB_PREFIX}}user_meta_app`;");
        $this->query("DROP TABLE `{{NAILS_DB_PREFIX}}user_meta_language`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP `id`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP FOREIGN KEY `{{NAILS_DB_PREFIX}}user_meta_app_ibfk_3`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP INDEX `user_id_2`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` DROP INDEX `user_id`;");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` ADD PRIMARY KEY (`user_id`);");
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_app` ADD FOREIGN KEY (`user_id`) REFERENCES `{{NAILS_DB_PREFIX}}user` (`id`) ON DELETE CASCADE;");
    }
}
