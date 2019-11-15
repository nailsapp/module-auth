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
     * @return float|null
     */
    public function getOrder(): ?float;

    // --------------------------------------------------------------------------

    /**
     * Returns the tab's body
     *
     * @param User $oUser The user being edited
     *
     * @return string
     */
    public function getBody(User $oUser): string;

    // --------------------------------------------------------------------------

    /**
     * Returns additional markup, outside of the main <form> element
     *
     * @param User $oUser The user being edited
     *
     * @return string
     */
    public function getAdditionalMarkup(User $oUser): string;

    // --------------------------------------------------------------------------

    /**
     * Returns an array of validation rules compatible with Validator objects
     *
     * @param User $oUser The user being edited
     *
     * @return array
     */
    public function getValidationRules(User $oUser): array;
}
