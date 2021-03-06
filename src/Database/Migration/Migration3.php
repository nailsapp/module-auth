<?php

/**
 * Migration:   3
 * Started:     23/09/2015
 * Finalised:   23/09/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration3 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user_group` ADD `password_rules` TEXT  NULL  AFTER `acl`;");
    }
}
