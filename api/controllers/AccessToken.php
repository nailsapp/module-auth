<?php

namespace Nails\Api\Auth;

/**
 * Access Token API endpoints
 *
 * @package     Nails
 * @subpackage  module-api
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class AccessToken extends \ApiController
{
    /**
     * Retrieves an access token for a user
     * @return array
     */
    public function postIndex()
    {
        get_instance()->load->model('auth/auth_model');

        $identifier = $this->input->post('identifier');
        $password   = $this->input->post('password');
        $scope      = $this->input->post('scope');
        $label      = $this->input->post('tokenLabel');
        $isValid    = get_instance()->auth_model->verifyCredentials($identifier, $password);

        if ($isValid) {

            $user = $this->user_model->getByIdentifier($identifier);

            $this->load->model('auth/user_access_token_model');
            $token = $this->user_access_token_model->create(
                array(
                    'user_id' => $user->id,
                    'label'   => $label,
                    'scope'   => $scope
                )
            );

            if ($token) {

                $out = array(
                    'token'   => $token->token,
                    'expires' => $token->expires
                );

            } else {

                $out = array(
                    'status' => 500,
                    'error'  => 'Failed to generate access token. ' . $this->user_access_token_model->last_error()
                );
            }

        } else {

            $out = array(
                'status' => 400,
                'error'  => 'Invalid login credentials'
            );
        }

        return $out;
    }
}
