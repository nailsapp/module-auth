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

namespace Nails\Auth\Api\Controller;

use Nails\Api\Controller\Base;
use Nails\Api\Exception\ApiException;
use Nails\Factory;

class AccessToken extends Base
{
    /**
     * Retrieves an access token for a user
     * @return ApiResponse
     */
    public function postIndex()
    {
        $oInput            = Factory::service('Input');
        $oHttpCodes        = Factory::service('HttpCodes');
        $oAuthModel        = Factory::model('Auth', 'nails/module-auth');
        $oAccessTokenModel = Factory::model('UserAccessToken', 'nails/module-auth');
        $sIdentifier       = $oInput->post('identifier');
        $sPassword         = $oInput->post('password');
        $sScope            = $oInput->post('scope');
        $sLabel            = $oInput->post('label');
        $bIsValid          = $oAuthModel->verifyCredentials($sIdentifier, $sPassword);

        if (!$bIsValid) {
            throw new ApiException(
                'Invalid login credentials',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        /**
         * User credentials are valid, but a few other tests are still required:
         * - User is not suspended
         * - Password is not temporary
         * - Password is not expired
         * - @todo: handle 2FA, perhaps?
         */

        $oUserModel         = Factory::model('User', 'nails/module-auth');
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
        $oUser              = $oUserModel->getByIdentifier($sIdentifier);
        $bIsSuspended       = $oUser->is_suspended;
        $bPwIsTemp          = $oUser->temp_pw;
        $bPwIsExpired       = $oUserPasswordModel->isExpired($oUser->id);

        if ($bIsSuspended) {
            throw new ApiException(
                'User account is suspended',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        } elseif ($bPwIsTemp) {
            throw new ApiException(
                'Password is temporary',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        } elseif ($bPwIsExpired) {
            throw new ApiException(
                'Password has expired',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oToken = $oAccessTokenModel->create([
            'user_id' => $oUser->id,
            'label'   => $sLabel,
            'scope'   => $sScope,
        ]);

        if (!$oToken) {
            throw new ApiException(
                'Failed to generate access token. ' . $oAccessTokenModel->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return Factory::factory('ApiResponse', 'nails/module-api')
                      ->setData([
                          'token'   => $oToken->token,
                          'expires' => $oToken->expires,
                      ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Revoke an access token for the authenticated user
     * @return ApiResponse
     */
    public function postRevoke()
    {
        $oHttpCodes        = Factory::service('HttpCodes');
        $oAccessTokenModel = Factory::model('UserAccessToken', 'nails/module-auth');

        if (!isLoggedIn()) {
            throw new ApiException(
                'You must be logged in',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oInput       = Factory::service('Input');
        $sAccessToken = $oInput->post('access_token');

        if (empty($sAccessToken)) {
            throw new ApiException(
                'An access token to revoke must be provided',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        if (!$oAccessTokenModel->revoke(activeUser('id'), $sAccessToken)) {
            throw new ApiException(
                'Failed to revoke access token. ' . $oAccessTokenModel->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return Factory::factory('ApiResponse', 'nails/module-api');
    }
}
