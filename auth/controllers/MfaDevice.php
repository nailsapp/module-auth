<?php

/**
 * Handles Multi-Factor Authentication when authTypeMode is 'DEVICE'
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Constants;
use Nails\Common\Exception\FactoryException;
use Nails\Factory;
use Nails\Auth\Controller\BaseMfa;

class MfaDevice extends BaseMfa
{
    /**
     * Ensures we're use the correct MFA type
     *
     * @throws FactoryException
     */
    public function _remap()
    {
        if ($this->authMfaMode == 'DEVICE') {
            $this->index();
        } else {
            show404();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Remaps requests to the correct method
     *
     * @throws FactoryException
     */
    public function index()
    {
        //  Validates the request token and generates a new one for the next request
        $this->validateToken();

        // --------------------------------------------------------------------------

        //  Has this user already set up an MFA?
        $oAuthModel = Factory::model('Auth', Constants::MODULE_SLUG);
        $oMfaDevice = $oAuthModel->mfaDeviceSecretGet($this->mfaUser->id);

        if ($oMfaDevice) {
            $this->requestCode();
        } else {
            $this->setupDevice();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets up a new MFA device
     *
     * @throws FactoryException
     */
    protected function setupDevice()
    {
        $oSession   = Factory::service('Session', Constants::MODULE_SLUG);
        $oAuthModel = Factory::model('Auth', Constants::MODULE_SLUG);
        $oInput     = Factory::service('Input');

        if ($oInput->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfa_secret', '', 'required');
            $oFormValidation->set_rules('mfa_code', '', 'required');

            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $sSecret  = $oInput->post('mfa_secret');
                $sMfaCode = $oInput->post('mfa_code');

                //  Verify the inout
                if ($oAuthModel->mfaDeviceSecretValidate($this->mfaUser->id, $sSecret, $sMfaCode)) {

                    //  Codes have been validated and saved to the DB, sign the user in and move on
                    $sStatus  = 'success';
                    $sMessage = '<strong>Multi Factor Authentication Enabled!</strong><br />You successfully ';
                    $sMessage .= 'associated an MFA device with your account. You will be required to use it ';
                    $sMessage .= 'the next time you log in.';

                    $oSession->setFlashData($sStatus, $sMessage);

                    $this->loginUser();

                } else {
                    $this->data['error'] = 'Sorry, that code failed to validate. Please try again.';
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Generate the secret
        $this->data['secret'] = $oAuthModel->mfaDeviceSecretGenerate(
            $this->mfaUser->id,
            $oInput->post('mfa_secret', true)
        );

        if (!$this->data['secret']) {

            $sStatus  = 'error';
            $sMessage = '<Strong>Sorry,</strong> it has not been possible to get an MFA device set up for this user. ';
            $sMessage .= $oAuthModel->lastError();

            $oSession->setFlashData($sStatus, $sMessage);

            if ($this->returnTo) {
                redirect('auth/login?return_to=' . $this->returnTo);
            } else {
                redirect('auth/login');
            }
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Set up a new MFA device';
        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/device/setup.php');
        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/mfa/device/setup',
                'structure/footer/blank',
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Requests a code from the user
     *
     * @throws FactoryException
     */
    protected function requestCode()
    {
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfa_code', '', 'required');
            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $oAuthModel = Factory::model('Auth', Constants::MODULE_SLUG);
                $sMfaCode   = $oInput->post('mfa_code');

                //  Verify the inout
                if ($oAuthModel->mfaDeviceCodeValidate($this->mfaUser->id, $sMfaCode)) {
                    $this->loginUser();
                } else {
                    $this->data['error'] = 'Sorry, that code failed to validate. Please try again. ';
                    $this->data['error'] .= $oAuthModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Enter your Code';
        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/device/ask.php');
        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/mfa/device/ask',
                'structure/footer/blank',
            ]);
    }
}
