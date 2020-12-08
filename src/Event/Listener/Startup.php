<?php

namespace Nails\Auth\Event\Listener;

use Nails\Common\Events;
use Nails\Common\Events\Subscription;
use Nails\Common\Exception\NailsException;
use Nails\Config;
use Nails\Functions;
use ReflectionException;

/**
 * Class Startup
 *
 * @package Nails\Auth\Event\Listener
 */
class Startup extends Subscription
{
    /**
     * Startup constructor.
     *
     * @throws NailsException
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->setEvent(Events::SYSTEM_STARTUP)
            ->setNamespace(Events::getEventNamespace())
            ->setCallback([$this, 'execute']);
    }

    // --------------------------------------------------------------------------

    /**
     * Define email constants
     */
    public function execute()
    {
        //  @todo (Pablo - 2020-11-08) - Remove this once a unified settings system is in place
        Config::default('APP_NATIVE_LOGIN_USING', 'BOTH');   //  [EMAIL|USERNAME|BOTH]
    }
}
