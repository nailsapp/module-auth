<?php

/**
 * User registration facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Controller\Base;
use Nails\Auth\Model\User\Group;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Session;
use Nails\Auth\Service\SocialSignOn;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Factory;

class Register extends Base
{
    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Is registration enabled
        if (!appSetting('user_registration_enabled', 'auth')) {
            show404();
        }

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_register');
    }

    // --------------------------------------------------------------------------

    /**
     * Display registration form, validate data and create user
     *
     * @return void
     */
    public function index()
    {
        /** @var Session $oSession */
        $oSession = Factory::service('Session', 'nails/module-auth');

        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {
            $oSession->setFlashData(
                'error',
                lang('auth_no_access_already_logged_in', activeUser('email'))
            );
            redirect('/');
        }

        // --------------------------------------------------------------------------

        /** @var Group $oUserGroupModel */
        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        $iDefaultGroupId = $oUserGroupModel->getDefaultGroupId();

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            //  Validate input
            /** @var FormValidation $oFormValidation */
            $oFormValidation = Factory::service('FormValidation');
            $oFormValidation->set_rules('first_name', '', 'required');
            $oFormValidation->set_rules('last_name', '', 'required');
            $oFormValidation->set_rules('password', '', 'required');

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'required');

                if ($oInput->post('email')) {

                    $oFormValidation->set_rules(
                        'email',
                        '',
                        'valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                    );
                }

            } else {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );
                $oFormValidation->set_rules(
                    'username',
                    '',
                    'required'
                );
            }

            // --------------------------------------------------------------------------

            //  Change default messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {
                $sMessage = lang('auth_register_email_is_unique', site_url('auth/password/forgotten'));
            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {
                $sMessage = lang('auth_register_username_is_unique', site_url('auth/password/forgotten'));
            } else {
                $sMessage = lang('auth_register_identity_is_unique', site_url('auth/password/forgotten'));
            }

            $oFormValidation->set_message('is_unique', $sMessage);

            // --------------------------------------------------------------------------

            //  Run validation
            if ($oFormValidation->run()) {

                //  Attempt the registration
                $aInsertData = [
                    'email'      => trim($oInput->post('email')),
                    'username'   => trim($oInput->post('username')),
                    'group_id'   => $iDefaultGroupId,
                    'password'   => $oInput->post('password'),
                    'first_name' => trim($oInput->post('first_name')),
                    'last_name'  => trim($oInput->post('last_name')),
                ];

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($oSession->getUserData('referred_by')) {
                    $aInsertData['referred_by'] = $oSession->getUserData('referred_by');
                }

                // --------------------------------------------------------------------------

                /** @var \Nails\Auth\Model\User $oUserModel */
                $oUserModel = Factory::model('User', 'nails/module-auth');
                $oUser      = $oUserModel->create($aInsertData);

                if ($oUser) {

                    //  Create an event for this event
                    create_event('did_register', ['method' => 'native'], $oUser->id);

                    //  Log the user in
                    if (!$oUserModel->setLoginData($oUser->id)) {
                        //  Login failed for some reason, send them to the login page to try again
                        redirect('auth/login');
                    } else {
                        $oSession->setFlashData(
                            'success',
                            lang('auth_register_flashdata_welcome', $oUser->first_name)
                        );
                    }

                    // --------------------------------------------------------------------------

                    //  Redirect to the group homepage
                    //  @todo (Pablo - 2017-07-11) - Setting for forced email activation
                    //  @todo (Pablo - 2017-07-11) - Handle setting MFA questions and/or devices

                    $oGroup = $oUserGroupModel->getById($oUser->group_id);

                    if ($oGroup->registration_redirect) {
                        $sRedirectUrl = $oGroup->registration_redirect;
                    } else {
                        $sRedirectUrl = $oGroup->default_homepage;
                    }

                    redirect($sRedirectUrl);

                } else {
                    $this->data['error'] = 'Could not create new user account. ' . $oUserModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        /** @var SocialSignOn $oSocial */
        $oSocial = Factory::service('SocialSignOn', 'nails/module-auth');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');

        $this->data['social_signon_enabled']   = $oSocial->isEnabled();
        $this->data['social_signon_providers'] = $oSocial->getProviders('ENABLED');
        $this->data['passwordRulesAsString']   = $oUserPasswordModel->getRulesAsString($iDefaultGroupId);

        // --------------------------------------------------------------------------

        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/register/form.php');

        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/register/form',
                'structure/footer/blank',
            ]);
    }
}
