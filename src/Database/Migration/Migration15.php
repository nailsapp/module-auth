<?php

/**
 * Migration:   15
 * Started:     21/04/2021
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

class Migration15 extends Base
{
    /**
     * Execute the migration
     *
     * @return Void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user_group` CHANGE `password_rules` `password_rules` JSON NULL;');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user_group` CHANGE `acl` `acl` JSON NULL;');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user` CHANGE `user_acl` `user_acl` JSON NULL;');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user_event` CHANGE `data` `data` JSON NULL;');
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user_meta_admin` CHANGE `nav_state` `nav_state` JSON NULL;');
    }
}
