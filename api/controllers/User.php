<?php

/**
 * Returns information about the currently logged in user
 *
 * @package     Nails
 * @subpackage  module-api
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Api\Auth;

use Nails\Api\Controller\DefaultController;
use Nails\Factory;

class User extends DefaultController
{
    const CONFIG_MODEL_NAME         = 'User';
    const CONFIG_MODEL_PROVIDER     = 'nailsapp/module-auth';
    const CONFIG_MIN_SEARCH_LENGTH  = 3;
    const CONFIG_POST_IGNORE_FIELDS = [
        'id',
        'slug',
        'created',
        'is_deleted',
        'created_by',
        'modified',
        'modified_by',
        'id_md5',
        'group_id',
        'ip_address',
        'last_ip',
        'username',
        'password',
        'password_md5',
        'password_engine',
        'password_changed',
        'salt',
        'forgotten_password_code',
        'remember_code',
        'last_login',
        'last_seen',
        'is_suspended',
        'temp_pw',
        'failed_login_count',
        'failed_login_expires',
        'last_update',
        'user_acl',
        'login_count',
        'referral',
        'referred_by',
    ];

    // --------------------------------------------------------------------------

    /**
     * Search for an item
     *
     * @return array
     */
    public function getSearch()
    {
        if (!userHasPermission('admin:auth:accounts:browse')) {

            return [
                'status' => 401,
                'error'  => 'You are not authorised to search users.',
            ];

        } else {

            return parent::getSearch();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a user by their email
     *
     * @return array
     */
    public function getEmail()
    {
        if (!userHasPermission('admin:auth:accounts:browse')) {

            return [
                'status' => 401,
                'error'  => 'You are not authorised to search users.',
            ];

        } else {

            $oInput = Factory::service('Input');
            $sEmail = $oInput->get('email');

            if (empty($sEmail)) {
                return [
                    'status' => 404,
                ];
            }

            if (!valid_email($sEmail)) {
                return [
                    'status' => 400,
                ];
            }

            $oUserModel = Factory::model(static::CONFIG_MODEL_NAME, static::CONFIG_MODEL_PROVIDER);
            $oUser      = $oUserModel->getByEmail($sEmail);

            if (empty($oUser)) {

                return [
                    'status' => 404,
                ];

            } else {

                return [
                    'data' => $this->formatObject($oUser),
                ];
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Creates or updates a user
     *
     * @return array
     */
    public function postRemap()
    {
        $oUri    = Factory::service('Uri');
        $iItemId = (int) $oUri->segment(4);
        if ($iItemId && $iItemId != activeUSer('id') && !userHasPermission('admin:auth:accounts:editothers')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to update this resource',
            ];
        } elseif (!$iItemId && !userHasPermission('admin:auth:accounts:create')) {
            return [
                'status' => 401,
                'error'  => 'You do not have permission to create this type of resource',
            ];
        }

        return parent::postRemap();
    }

    // --------------------------------------------------------------------------

    /**
     * Format the output
     *
     * @param \stdClass $oObj The object to format
     *
     * @return array
     */
    public function formatObject($oObj)
    {
        return [
            'id'         => $oObj->id,
            'first_name' => $oObj->first_name,
            'last_name'  => $oObj->last_name,
            'email'      => $oObj->email,
        ];
    }
}
