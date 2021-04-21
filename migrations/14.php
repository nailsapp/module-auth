<?php

/**
 * Migration:   13
 * Started:     19/02/2020
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleAuth;

use Nails\Auth\Auth\PasswordEngine\Sha1;
use Nails\Common\Console\Migrate\Base;

class Migration13 extends Base
{
    /**
     * Execute the migration
     *
     * @return Void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user` CHANGE `failed_login_count` `failed_login_count` INT UNSIGNED NOT NULL DEFAULT 0;');
    }
}
