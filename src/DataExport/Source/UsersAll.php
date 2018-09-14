<?php

namespace Nails\Auth\DataExport\Source;

use Nails\Admin\Interfaces\DataExport\Source;
use Nails\Factory;

/**
 * Class UsersAll
 * @package Nails\Auth\DataExport
 */
class UsersAll implements Source
{
    /**
     * Returns the format's label
     * @return string
     */
    public function getLabel()
    {
        return 'Members: All';
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
        return 'Export a list of all the site\'s registered users and their meta data.';
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
     * @return bool|array
     */
    public function execute($aData = [])
    {
        $oDb             = Factory::service('Database');
        $oUserModel      = Factory::model('User', 'nails/module-auth');
        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');

        $aTables = [
            $oUserModel->getTableName(),
            $oUserGroupModel->getTableName(),
            NAILS_DB_PREFIX . 'user_email',
        ];

        $aResult = $oDb->query('
            SHOW TABLES
            FROM `' . DEPLOY_DB_DATABASE . '`
            WHERE
                `Tables_in_' . DEPLOY_DB_DATABASE . '` LIKE "' . NAILS_DB_PREFIX . 'user_meta_%"
                OR `Tables_in_' . DEPLOY_DB_DATABASE . '` LIKE "' . APP_DB_PREFIX . 'user_meta_%"
        ')->result();

        foreach ($aResult as $oTable) {
            $aTables[] = $oTable->{'Tables_in_' . DEPLOY_DB_DATABASE};
        }

        $aOut = [];
        foreach ($aTables as $sTable) {
            $oResponse = Factory::factory('DataExportSourceResponse', 'nails/module-admin');
            $oSource   = $oDb->get($sTable);
            $aFields   = arrayExtractProperty($oDb->query('DESCRIBE ' . $sTable)->result(), 'Field');
            $aOut[]    = $oResponse
                ->setLabel('Table: ' . $sTable)
                ->setFilename($sTable)
                ->setFields($aFields)
                ->setSource($oSource);
        }

        return $aOut;
    }
}
