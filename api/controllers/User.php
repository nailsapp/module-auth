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
    const CONFIG_MODEL_NAME = 'User';
    const CONFIG_MODEL_PROVIDER = 'nailsapp/module-auth';
    const CONFIG_MIN_SEARCH_LENGTH = 3;

    // --------------------------------------------------------------------------

    /**
     * Search for an item
     *
     * @return array
     */
    public function getSearch()
    {
        if (!userHasPermission('admin:auth:accounts:browse')) {

            return array(
                'status' => 401,
                'error'  => 'You are not authorised to search users.'
            );

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

            return array(
                'status' => 401,
                'error'  => 'You are not authorised to search users.'
            );

        } else {

            $oInput = Factory::service('Input');
            $sEmail = $oInput->get('email');

            if (empty($sEmail)) {
                return array(
                    'status' => 404
                );
            }

            if (!valid_email($sEmail)) {
                return array(
                    'status' => 400
                );
            }

            $oUserModel = Factory::model(static::CONFIG_MODEL_NAME, static::CONFIG_MODEL_PROVIDER);
            $oUser      = $oUserModel->getByEmail($sEmail);

            if (empty($oUser)) {

                return array(
                    'status' => 404
                );

            } else {

                return array(
                    'data' => $this->formatObject($oUser)
                );
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Format the output
     *
     * @param \stdClass $oObj The object to format
     * @return array
     */
    public function formatObject($oObj)
    {
        return array(
            'id'         => $oObj->id,
            'first_name' => $oObj->first_name,
            'last_name'  => $oObj->last_name,
            'email'      => $oObj->email
        );
    }
}
