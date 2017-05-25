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
        $oUserModel      = Factory::model('User', 'nailsapp/module-auth');
        $oUserGroupModel = Factory::model('UserGroup', 'nailsapp/module-auth');

        $aOut = [
            (object) [
                'label'    => 'Table: ' . $oUserModel->getTableName(),
                'filename' => $oUserModel->getTableName(),
                'fields'   => [],
                'data'     => [],
            ],
            (object) [
                'label'    => 'Table: ' . $oUserGroupModel->getTableName(),
                'filename' => $oUserGroupModel->getTableName(),
                'fields'   => [],
                'data'     => [],
            ],
            (object) [
                'label'    => 'Table: ' . NAILS_DB_PREFIX . 'user_email',
                //  @todo - Use the email model (when it exists)
                'filename' => NAILS_DB_PREFIX . 'user_email',
                'fields'   => [],
                'data'     => [],
            ],
        ];

        $aTables = $oDb->query('
            SHOW TABLES
            FROM `' . DEPLOY_DB_DATABASE . '`
            WHERE 
                `Tables_in_' . DEPLOY_DB_DATABASE . '` LIKE "' . NAILS_DB_PREFIX . 'user_meta_%"
                OR `Tables_in_' . DEPLOY_DB_DATABASE . '` LIKE "' . APP_DB_PREFIX . 'user_meta_%"
        ')->result();

        foreach ($aTables as $oTable) {
            $sTable = $oTable->{'Tables_in_' . DEPLOY_DB_DATABASE};
            $aOut[] = (object) [
                'label'    => 'Table: ' . $sTable,
                'filename' => $sTable,
                'fields'   => [],
                'data'     => [],
            ];
        }

        //  Fetch the data from the tables
        foreach ($aOut as $oItem) {
            $aFields = $oDb->query('DESCRIBE ' . $oItem->filename)->result();
            foreach ($aFields as $oField) {
                $oItem->fields[] = $oField->Field;
            }
            $oItem->data = $oDb->get($oItem->filename)->result_array();
        }

        return $aOut;
    }
}
