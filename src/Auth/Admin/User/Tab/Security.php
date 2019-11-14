<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Resource\User;

/**
 * Class Security
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Security implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Security';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return int|null
     */
    public function getOrder(): ?int
    {
        return 3;
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
