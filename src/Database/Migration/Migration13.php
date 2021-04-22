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

namespace Nails\Auth\Database\Migration;

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
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user` CHANGE `password_engine` `password_engine` VARCHAR(255) DEFAULT NULL;');
        $this->query('UPDATE `{{NAILS_DB_PREFIX}}user` SET `password_engine` = "' . str_replace('\\', '\\\\', Sha1::class) . '" WHERE `password_engine` = "NAILS_1";');
    }
}
