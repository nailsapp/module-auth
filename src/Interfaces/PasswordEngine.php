<?php

namespace Nails\Auth\Interfaces;

use Nails\Auth\Resource\User;

/**
 * Interface PasswordEngine
 *
 * @package Nails\Auth\Interfaces
 */
interface PasswordEngine
{
    /**
     * Returns the hashed password
     *
     * @param string|null $sPassword The password to hash
     * @param string|null $sSalt     The salt to hash with
     *
     * @return string
     */
    public function hash(?string $sPassword, ?string $sSalt): string;
}
