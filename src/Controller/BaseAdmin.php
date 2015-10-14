<?php

/**
 * This class provides some common Auth controller functionality in admin
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Controller;

use Nails\Admin\Controller\Base;

class BaseAdmin extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->asset->load('nails.admin.module.auth.css', 'NAILS');
    }
}
