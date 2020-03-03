<?php

namespace Nails\Auth\DataExport\Source\User;

use Nails\Admin\DataExport\SourceResponse;
use Nails\Admin\Interfaces\DataExport\Source;
use Nails\Auth\Constants;
use Nails\Config;
use Nails\Factory;

/**
 * Class All
 *
 * @package Nails\Auth\DataExport\Source\User
 */
class All implements Source
{
    /**
     * Returns the format's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Members: All';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the format's file name
     *
     * @return string
     */
    public function getFileName(): string
    {
        return 'members-all';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the format's description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Export a list of all the site\'s registered users and their meta data.';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of additional options for the export
     *
     * @return array
     */
    public function getOptions(): array
    {
        return [];
    }

    // --------------------------------------------------------------------------

    /**
     * Provides an opportunity for the source to decide whether it is available or not to the user
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return userHasPermission('admin:auth:accounts:browse');
    }

    // --------------------------------------------------------------------------

    /**
     * Execute the data export
     *
     * @param array $aData Any data to pass to the source
     *
     * @return SourceResponse
     */
    public function execute($aData = [])
    {
        $oDb             = Factory::service('Database');
        $oUserModel      = Factory::model('User', Constants::MODULE_SLUG);
        $oUserGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);

        $aTables = [
            $oUserModel->getTableName(),
            $oUserGroupModel->getTableName(),
            Config::get('NAILS_DB_PREFIX') . 'user_email',
        ];

        $aResult = $oDb->query('
            SHOW TABLES
            FROM `' . Config::get('DB_DATABASE') . '`
            WHERE
                `Tables_in_' . Config::get('DB_DATABASE') . '` LIKE "' . Config::get('NAILS_DB_PREFIX') . 'user_meta_%"
                OR `Tables_in_' . Config::get('DB_DATABASE') . '` LIKE "' . Config::get('APP_DB_PREFIX') . 'user_meta_%"
        ')->result();

        foreach ($aResult as $oTable) {
            $aTables[] = $oTable->{'Tables_in_' . Config::get('DB_DATABASE')};
        }

        $aOut = [];
        foreach ($aTables as $sTable) {
            $oResponse = Factory::factory('DataExportSourceResponse', 'nails/module-admin');
            $oSource   = $oDb->get($sTable);
            $aFields   = arrayExtractProperty($oDb->query('DESCRIBE ' . $sTable)->result(), 'Field');
            $aOut[]    = $oResponse
                ->setLabel('Table: ' . $sTable)
                ->setFileName($sTable)
                ->setFields($aFields)
                ->setSource($oSource);
        }

        return $aOut;
    }
}
