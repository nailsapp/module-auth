<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Resource\User;

/**
 * Class Meta
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Meta implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Meta';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return int|null
     */
    public function getOrder(): ?int
    {
        return 1;
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
