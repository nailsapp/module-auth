<?php

/**
 * Proxies Nails\Common\Service\Session for backwards compatibility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Service;

/**
 * Class Session
 *
 * @package Nails\Auth\Service
 */
class Session extends \Nails\common\Service\Session
{
    //  @todo (Pablo - 2020-03-02) - Remove this proxy when releasing Nails V1
}
