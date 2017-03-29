<?php

namespace Nails\Auth\DataExport\Source;

use Nails\Admin\Interfaces\DataExport\Source;
use Nails\Factory;

/**
 * Class UsersEmail
 * @package Nails\Auth\DataExport
 */
class UsersEmail implements Source
{
    /**
     * Returns the format's label
     * @return string
     */
    public function getLabel()
    {
        return 'Members: Names and Email';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the format's file name
     * @return string
     */
    public function getFileName()
    {
        return 'members-all';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the format's description
     * @return string
     */
    public function getDescription()
    {
        return 'Export a list of all the site\'s registered users and their email addresses.';
    }

    // --------------------------------------------------------------------------

    /**
     * Provides an opportunity for the source to decide whether it is available or not to the user
     * @return bool
     */
    public function isEnabled()
    {
        return userHasPermission('admin:auth:accounts:browse');
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the data export
     * @return bool|\stdClass
     */
    public function execute()
    {
        //  Fetch all users via the User model
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $aUsers     = $oUserModel->getAll();

        //  Set column headings
        $oOut = (object) [
            'label'    => $this->getLabel(),
            'filename' => 'members-name-email',
            'fields'   => ['id', 'first_name', 'last_name', 'email'],
            'data'     => [],
        ];

        //  Add each user to the output array
        foreach ($aUsers as $oUser) {
            $oOut->data[] = [
                $oUser->id,
                $oUser->first_name,
                $oUser->last_name,
                $oUser->email,
            ];
        }

        return $oOut;
    }
}
