<?php

/**
 * Migration:   6
 * Started:     06/11/2015
 * Finalised:   06/11/2015
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Database\Migration;

use Nails\Common\Console\Migrate\Base;

class Migration6 extends Base
{
    /**
     * Execute the migration
     * @return Void
     */
    public function execute()
    {
        $this->query("ALTER TABLE `{{NAILS_DB_PREFIX}}user` DROP `admin_data`;");
    }
}
