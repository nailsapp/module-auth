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

use Nails\Factory;
use Nails\Auth\Controller\BaseMfa;

class MfaDevice extends BaseMfa
{
    /**
     * Ensures we're use the correct MFA type
     */
    public function _remap()
    {
        if ($this->authMfaMode == 'DEVICE') {
            $this->index();
        } else {
            show_404();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Remaps requests to the correct method
     */
    public function index()
    {
        //  Validates the request token and generates a new one for the next request
        $this->validateToken();

        // --------------------------------------------------------------------------

        //  Has this user already set up an MFA?
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
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
     */
    protected function setupDevice()
    {
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        $oInput     = Factory::service('Input');

        if ($oInput->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaSecret', '', 'required');
            $oFormValidation->set_rules('mfaCode1', '', 'required');
            $oFormValidation->set_rules('mfaCode2', '', 'required');

            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $sSecret = $oInput->post('mfaSecret');
                $sCode1  = $oInput->post('mfaCode1');
                $sCode2  = $oInput->post('mfaCode2');

                //  Verify the inout
                if ($oAuthModel->mfaDeviceSecretValidate($this->mfaUser->id, $sSecret, $sCode1, $sCode2)) {

                    //  Codes have been validated and saved to the DB, sign the user in and move on
                    $sStatus  = 'success';
                    $sMessage = '<strong>Multi Factor Authentication Enabled!</strong><br />You successfully';
                    $sMessage .= 'associated an MFA device with your account. You will be required to use it ';
                    $sMessage .= 'the next time you log in.';

                    $oSession->set_flashdata($sStatus, $sMessage);

                    $this->loginUser();

                } else {
                    $this->data['error'] = '<strong>Sorry,</strong> those codes failed to validate. Please try again.';
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Generate the secret
        $this->data['secret'] = $oAuthModel->mfaDeviceSecretGenerate(
            $this->mfaUser->id,
            $oInput->post('mfaSecret', true)
        );

        if (!$this->data['secret']) {

            $sStatus  = 'error';
            $sMessage = '<Strong>Sorry,</strong> it has not been possible to get an MFA device set up for this user. ';
            $sMessage .= $oAuthModel->lastError();

            $oSession->set_flashdata($sStatus, $sMessage);

            if ($this->returnTo) {
                redirect('auth/login?return_to=' . $this->returnTo);
            } else {
                redirect('auth/login');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Set up a new MFA device';

        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/mfa/device/setup', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Requests a code from the user
     */
    protected function requestCode()
    {
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaCode', '', 'required');
            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
                $sCode      = $oInput->post('mfaCode');

                //  Verify the inout
                if ($oAuthModel->mfaDeviceCodeValidate($this->mfaUser->id, $sCode)) {
                    $this->loginUser();
                } else {
                    $this->data['error'] = '<strong>Sorry,</strong> that code failed to validate. Please try again. ';
                    $this->data['error'] .= $oAuthModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Enter your Code';

        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/mfa/device/ask', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }
}
