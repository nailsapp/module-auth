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

use Nails\Factory;

class AccessToken extends \Nails\Api\Controller\Base
{
    /**
     * Retrieves an access token for a user
     * @return array
     */
    public function postIndex()
    {
        $oAuthModel        = Factory::model('Auth', 'nailsapp/module-auth');
        $oAccessTokenModel = Factory::model('UserAccessToken', 'nailsapp/module-auth');
        $sIdentifier       = $this->input->post('identifier');
        $sPassword         = $this->input->post('password');
        $sScope            = $this->input->post('scope');
        $sLabel            = $this->input->post('tokenLabel');
        $bIsValid          = $oAuthModel->verifyCredentials($sIdentifier, $sPassword);

        if ($bIsValid) {

            /**
             * User credentials are valid, but a few other tests are still required:
             * - User is not suspended
             * - Password is not temporary
             * - Password is not expired
             * - @todo: handle 2FA, perhaps?
             */

            $oUser        = $this->user_model->getByIdentifier($sIdentifier);
            $bIsSuspended = $oUser->is_suspended;
            $bPwIsTemp    = $oUser->temp_pw;
            $bPwIsExpired = $this->user_password_model->isExpired($oUser->id);

            if ($bIsSuspended) {

                $aOut = array(
                    'status' => 401,
                    'error'  => 'User account is suspended.'
                );

            } elseif ($bPwIsTemp) {

                $aOut = array(
                    'status' => 400,
                    'error'  => 'Password is temporary.'
                );

            } elseif ($bPwIsExpired) {

                $aOut = array(
                    'status' => 400,
                    'error'  => 'Password has expired.'
                );

            } else {

                $oToken = $oAccessTokenModel->create(
                    array(
                        'user_id' => $oUser->id,
                        'label'   => $sLabel,
                        'scope'   => $sScope
                    )
                );

                if ($oToken) {

                    $aOut = array(
                        'token'   => $oToken->token,
                        'expires' => $oToken->expires
                    );

                } else {

                    $aOut = array(
                        'status' => 500,
                        'error'  => 'Failed to generate access token. ' . $oAccessTokenModel->last_error()
                    );
                }
            }

        } else {

            $aOut = array(
                'status' => 401,
                'error'  => 'Invalid login credentials.'
            );
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
        $aOut              = array();

        if ($this->user_model->isLoggedIn()) {

            $sAccessToken = $this->input->post('accessToken');

            if (!empty($sAccessToken)) {

                if (!$oAccessTokenModel->revoke(activeUser('id'), $sAccessToken)) {

                    $aOut = array(
                        'status' => 500,
                        'error' => 'Failed to revoke access token. ' . $oAccessTokenModel->last_error()
                    );
                }

            } else {

                $aOut = array(
                    'status' => 400,
                    'error' => 'An access token to revoke must be provided.'
                );
            }

        } else {

            $aOut = array(
                'status' => 401,
                'error' => 'You must be logged in.'
            );
        }

        return $aOut;
    }
}
