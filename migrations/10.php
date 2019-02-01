<?php

/**
 * Migration:   10
 * Started:     01/02/2019
 * Finalised:   01/02/2019
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Database Migration
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Database\Migration\Nails\ModuleAuth;

use Nails\Common\Console\Migrate\Base;

class Migration10 extends Base
{
    /**
     * Execute the migration
     *
     * @return Void
     */
    public function execute()
    {
        $this->query('ALTER TABLE `{{NAILS_DB_PREFIX}}user_auth_two_factor_question` CHANGE `last_requested` `last_requested` DATETIME NULL;');
    }
}
