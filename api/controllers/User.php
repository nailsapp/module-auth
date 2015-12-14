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

use Nails\Factory;

class User extends \Nails\Api\Controller\Base
{
    public static $requiresAuthentication = true;

    // --------------------------------------------------------------------------

    /**
     * Returns basic details about the currently logged in user
     * @return array
     */
    public function anyIndex()
    {
        return array(
            'user' => array(
                'id'         => activeUser('id'),
                'first_name' => activeUser('first_name'),
                'last_name'  => activeUser('last_name'),
                'email'      => activeUser('email'),
                'username'   => activeUser('username'),
                'avatar'     => cdnAvatar(),
                'gender'     => activeUser('gender')
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Search for a user
     */
    public function getSearch()
    {
        if (!userHasPermission('admin:auth:accounts:browse')) {

            return array(
                'status' => 401,
                'error' => 'You are not authorised to search users.'
            );

        } else {

            $sKeywords  = $this->input->get('keywords');
            $oUserModel = Factory::model('User', 'nailsapp/module-auth');

            if (strlen($sKeywords) >= 3) {

                $oResult = $oUserModel->search($sKeywords);
                $aOut    = array();

                foreach ($oResult->data as $oUser) {
                    $aOut[] = $this->formatUser($oUser);
                }

                return array(
                    'data' => $aOut
                );

            } else {

                return array(
                    'status' => 400,
                    'error' => 'Search term must be 3 characters or longer.'
                );
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a user by their ID
     * @return array
     */
    public function getId($iId = null)
    {
        if (empty($iId)) {

            return array();
        }

        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $oUser      = $oUserModel->getById($iId);

        if (empty($oUser)) {

            return array();

        } else {

            return array(
                'data' => $this->formatUser($oUser)
            );
        }
    }

    // --------------------------------------------------------------------------

    public function formatUser($oUser)
    {
        return array(
            'id'    => $oUser->id,
            'name'  => $oUser->first_name . ' ' . $oUser->last_name,
            'email' => $oUser->email
        );
    }
}
