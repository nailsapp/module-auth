<?php

namespace Nails\Auth\Resource\User;

use Nails\Common\Resource;

/**
 * Class AdminRecovery
 *
 * @package Nails\Auth\Resource\User
 */
class AdminRecovery extends Resource
{
    /** @var int */
    public $oldUserId;

    /** @var int */
    public $newUserId;

    /** @var string */
    public $hash;

    /** @var string */
    public $name;

    /** @var string */
    public $email;

    /** @var string */
    public $returnTo;

    /** @var string */
    public $loginUrl;
}
