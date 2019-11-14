<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Resource\User;

/**
 * Class Emails
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Emails implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Email Addresses';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return int|null
     */
    public function getOrder(): ?int
    {
        return 2;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the tab's body
     *
     * @return string
     */
    public function getBody(User $oUser): string
    {
        return '';
    }
}
