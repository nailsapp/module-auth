<?php

namespace Nails\Auth\Interfaces\Admin\User;

use Nails\Auth\Resource\User;

/**
 * Interface Tab
 *
 * @package Nails\Auth\Interfaces\Admin\User
 */
interface Tab
{
    /**
     * Returns the tab's label
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Return the order in which the tabs should render
     *
     * @return int|null
     */
    public function getOrder(): ?int;

    // --------------------------------------------------------------------------

    /**
     * Returns the tab's body
     *
     * @param User $oUser The user being edited
     *
     * @return string
     */
    public function getBody(User $oUser): string;
}
