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

use Nails\Api;
use Nails\Auth\Constants;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Authentication;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\HttpCodes;
use Nails\Factory;

/**
 * Class AccessToken
 *
 * @package Nails\Auth\Api\Controller
 */
class AccessToken extends Api\Controller\Base
{
    /**
     * Retrieves an access token for a user
     *
     * @return Api\Factory\ApiResponse
     * @throws FactoryException
     * @throws Api\Exception\ApiException
     */
    public function postIndex()
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);
        /** @var \Nails\Auth\Model\User\AccessToken $oAccessTokenModel */
        $oAccessTokenModel = Factory::model('UserAccessToken', Constants::MODULE_SLUG);

        $aData       = $this->getRequestData();
        $sIdentifier = getFromArray('identifier', $aData);
        $sPassword   = getFromArray('password', $aData);
        $sScope      = getFromArray('scope', $aData);
        $sLabel      = getFromArray('label', $aData);

        if (!$oUserPasswordModel->isCorrect($sIdentifier, $sPassword)) {
            throw new Api\Exception\ApiException(
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

        $oUserModel         = Factory::model('User', Constants::MODULE_SLUG);
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);
        $oUser              = $oUserModel->getByIdentifier($sIdentifier);
        $bIsSuspended       = $oUser->is_suspended;
        $bPwIsTemp          = $oUser->temp_pw;
        $bPwIsExpired       = $oUserPasswordModel->isExpired($oUser->id);

        if ($bIsSuspended) {
            throw new Api\Exception\ApiException(
                'User account is suspended',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        } elseif ($bPwIsTemp) {
            throw new Api\Exception\ApiException(
                'Password is temporary',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        } elseif ($bPwIsExpired) {
            throw new Api\Exception\ApiException(
                'Password has expired',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $oToken = $oAccessTokenModel->create([
            'user_id' => $oUser->id,
            'label'   => $sLabel,
            'scope'   => $sScope,
        ], true);

        if (!$oToken) {
            throw new Api\Exception\ApiException(
                'Failed to generate access token. ' . $oAccessTokenModel->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        /** @var Api\Factory\ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG)
            ->setData([
                'token'   => $oToken->token,
                'expires' => $oToken->expires->raw,
            ]);

        return $oResponse;
    }

    // --------------------------------------------------------------------------

    /**
     * Revoke an access token for the authenticated user
     *
     * @return Api\Factory\ApiResponse
     * @throws FactoryException
     * @throws Api\Exception\ApiException
     */
    public function deleteIndex()
    {
        /** @var HttpCodes $oHttpCodes */
        $oHttpCodes = Factory::service('HttpCodes');
        /** @var \Nails\Auth\Model\User\AccessToken $oAccessTokenModel */
        $oAccessTokenModel = Factory::model('UserAccessToken', Constants::MODULE_SLUG);

        if (!isLoggedIn()) {
            throw new Api\Exception\ApiException(
                'You must be logged in',
                $oHttpCodes::STATUS_UNAUTHORIZED
            );
        }

        $sAccessToken = $this->oApiRouter->getAccessToken();

        if (empty($sAccessToken)) {
            throw new Api\Exception\ApiException(
                'An access token must be provided.',
                $oHttpCodes::STATUS_BAD_REQUEST
            );
        }

        if (!$oAccessTokenModel->revoke(activeUser('id'), $sAccessToken)) {
            throw new Api\Exception\ApiException(
                'Failed to revoke access token. ' . $oAccessTokenModel->lastError(),
                $oHttpCodes::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        /** @var Api\Factory\ApiResponse $oResponse */
        $oResponse = Factory::factory('ApiResponse', Api\Constants::MODULE_SLUG);

        return $oResponse;
    }
}
