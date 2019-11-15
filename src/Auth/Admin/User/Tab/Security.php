<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Constants;
use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Resource\User;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Service\Config;
use Nails\Common\Service\Input;
use Nails\Common\Service\View;
use Nails\Factory;

/**
 * Class Security
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Security implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Security';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return float|null
     */
    public function getOrder(): ?float
    {
        return 3;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the tab's body
     *
     * @return string
     */
    public function getBody(User $oUser): string
    {
        /** @var View $oView */
        $oView = Factory::service('View');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        $oConfig->load('auth/auth');

        return $oView
            ->load(
                array_filter([
                    'Accounts/edit/inc-password',
                    $oConfig->item('authTwoFactorMode') == 'QUESTION' ? 'Accounts/edit/inc-mfa-question' : null,
                    $oConfig->item('authTwoFactorMode') == 'DEVICE' ? 'Accounts/edit/inc-mfa-device' : null,
                ]),
                [
                    'oUser'          => $oUser,
                    'sPasswordRules' => $oUserPasswordModel->getRulesAsString($oUser->group_id),
                ],
                true
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Returns additional markup, outside of the main <form> element
     *
     * @param User $oUser The user being edited
     *
     * @return string
     */
    public function getAdditionalMarkup(User $oUser): string
    {
        return '';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of validation rules compatible with Validator objects
     *inc=
     *
     * @param User $oUser The user being edited
     *
     * @return array
     */
    public function getValidationRules(User $oUser): array
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        $oConfig->load('auth/auth');

        $aRules = [
            'temp_pw' => [],
        ];

        if (!empty($oInput->post('password'))) {
            $aRules['password'] = [
                function ($sPassword) use ($oUser, $oUserPasswordModel) {
                    if (!$oUserPasswordModel->isAcceptable($oUser->group_id, $sPassword)) {
                        throw new ValidationException('Password does not meet requirements.');
                    }
                },
            ];
        }

        switch ($oConfig->item('authTwoFactorMode')) {
            case 'QUESTION':
                $aRules['reset_mfa_question'] = [];
                break;
            case 'DEVICE':
                $aRules['reset_mfa_device'] = [];
                break;
        }

        return $aRules;
    }
}
