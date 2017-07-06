<?php

/**
 * Access Token API endpoints
 *
 * @package     Nails
 * @subpackage  module-api
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Api\Auth;

use Nails\Api\Controller\Base;
use Nails\Factory;

class AccessToken extends Base
{
    /**
     * Retrieves an access token for a user
     * @return array
     */
    public function postIndex()
    {
        $oInput            = Factory::service('Input');
        $oAuthModel        = Factory::model('Auth', 'nailsapp/module-auth');
        $oAccessTokenModel = Factory::model('UserAccessToken', 'nailsapp/module-auth');
        $sIdentifier       = $oInput->post('identifier');
        $sPassword         = $oInput->post('password');
        $sScope            = $oInput->post('scope');
        $sLabel            = $oInput->post('tokenLabel');
        $bIsValid          = $oAuthModel->verifyCredentials($sIdentifier, $sPassword);

        if ($bIsValid) {

            /**
             * User credentials are valid, but a few other tests are still required:
             * - User is not suspended
             * - Password is not temporary
             * - Password is not expired
             * - @todo: handle 2FA, perhaps?
             */

            $oUserModel         = Factory::model('User', 'nailsapp/module-auth');
            $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
            $oUser              = $oUserModel->getByIdentifier($sIdentifier);
            $bIsSuspended       = $oUser->is_suspended;
            $bPwIsTemp          = $oUser->temp_pw;
            $bPwIsExpired       = $oUserPasswordModel->isExpired($oUser->id);

            if ($bIsSuspended) {

                $aOut = [
                    'status' => 401,
                    'error'  => 'User account is suspended.',
                ];

            } elseif ($bPwIsTemp) {

                $aOut = [
                    'status' => 400,
                    'error'  => 'Password is temporary.',
                ];

            } elseif ($bPwIsExpired) {

                $aOut = [
                    'status' => 400,
                    'error'  => 'Password has expired.',
                ];

            } else {

                $oToken = $oAccessTokenModel->create(
                    [
                        'user_id' => $oUser->id,
                        'label'   => $sLabel,
                        'scope'   => $sScope,
                    ]
                );

                if ($oToken) {
                    $aOut = [
                        'token'   => $oToken->token,
                        'expires' => $oToken->expires,
                    ];
                } else {
                    $aOut = [
                        'status' => 500,
                        'error'  => 'Failed to generate access token. ' . $oAccessTokenModel->lastError(),
                    ];
                }
            }

        } else {
            $aOut = [
                'status' => 401,
                'error'  => 'Invalid login credentials.',
            ];
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Revoke an access token for the authenticated user
     * @return array
     */
    public function postRevoke()
    {
        $oAccessTokenModel = Factory::model('UserAccessToken', 'nailsapp/module-auth');
        $aOut              = [];

        if (isLoggedIn()) {

            $oInput       = Factory::service('Input');
            $sAccessToken = $oInput->post('access_token');

            if (!empty($sAccessToken)) {

                if (!$oAccessTokenModel->revoke(activeUser('id'), $sAccessToken)) {
                    $aOut = [
                        'status' => 500,
                        'error'  => 'Failed to revoke access token. ' . $oAccessTokenModel->lastError(),
                    ];
                }

            } else {
                $aOut = [
                    'status' => 400,
                    'error'  => 'An access token to revoke must be provided.',
                ];
            }

        } else {
            $aOut = [
                'status' => 401,
                'error'  => 'You must be logged in.',
            ];
        }

        return $aOut;
    }
}
