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
        return 'members-name-email';
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
     * Returns an array of additional options for the export
     * @return array
     */
    public function getOptions()
    {
        return [];
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
     *
     * @param array $aData Any data to pass to the source
     *
     * @return \stdClass
     */
    public function execute($aData = [])
    {
        $oDb        = Factory::service('Database');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oResponse  = Factory::factory('DataExportSourceResponse', 'nails/module-admin');

        $oSource = $oDb
            ->select('u.id, u.first_name, u.last_name, ue.email')
            ->join(NAILS_DB_PREFIX . 'user_email ue', 'u.id = ue.user_id AND ue.is_primary = 1', 'LEFT')
            ->get($oUserModel->getTableName() . ' u');

        return $oResponse
            ->setLabel($this->getLabel())
            ->setFilename($this->getFileName())
            ->setFields(['id', 'first_name', 'last_name', 'email'])
            ->setSource($oSource);
    }
}
