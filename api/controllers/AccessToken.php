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
        $oAuthModel  = Factory::model('Auth', 'nailsapp/module-auth');
        $sIdentifier = $this->input->post('identifier');
        $sPassword   = $this->input->post('password');
        $sScope      = $this->input->post('scope');
        $sLabel      = $this->input->post('tokenLabel');
        $bIsValid    = $oAuthModel->verifyCredentials($sIdentifier, $sPassword);

        if ($bIsValid) {

            $oUser = $this->user_model->getByIdentifier($sIdentifier);

            $this->load->model('auth/user_access_token_model');
            $oToken = $this->user_access_token_model->create(
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
                    'error'  => 'Failed to generate access token. ' . $this->user_access_token_model->last_error()
                );
            }

        } else {

            $aOut = array(
                'status' => 400,
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
        $aOut = array();

        if ($this->user_model->isLoggedIn()) {

            $sAccessToken = $this->input->post('accessToken');

            if (!empty($sAccessToken)) {

                if (!$this->user_access_token_model->revoke(activeUser('id'), $sAccessToken)) {

                    $aOut = array(
                        'status' => 500,
                        'error' => 'Failed to revoke access token. ' . $this->user_access_token_model->last_error()
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
