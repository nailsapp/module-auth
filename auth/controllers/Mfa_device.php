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

class Mfa_device extends BaseMfa
{
    public function _remap()
    {
        if ($this->authMfaMode == 'DEVICE') {

            $this->index();

        } else {

            show_404();
        }
    }

    // --------------------------------------------------------------------------

    public function index()
    {
        //  Validates the request token and generates a new one for the next request
        $this->validateToken();

        // --------------------------------------------------------------------------

        //  Has this user already set up an MFA?
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        $mfaDevice  = $oAuthModel->mfaDeviceSecretGet($this->mfaUser->id);

        if ($mfaDevice) {

            $this->requestCode();

        } else {

            $this->setupDevice();
        }
    }

    // --------------------------------------------------------------------------

    protected function setupDevice()
    {
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        if ($this->input->post()) {

            $oSession        = Factory::service('Session', 'nailsapp/module-auth');
            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaSecret', '', 'required');
            $oFormValidation->set_rules('mfaCode1', '', 'required');
            $oFormValidation->set_rules('mfaCode2', '', 'required');

            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $secret = $this->input->post('mfaSecret', true);
                $code1  = $this->input->post('mfaCode1', true);
                $code2  = $this->input->post('mfaCode2', true);

                //  Verify the inout
                if ($oAuthModel->mfaDeviceSecretValidate($this->mfaUser->id, $secret, $code1, $code2)) {

                    //  Codes have been validated and saved to the DB, sign the user in and move on
                    $status  = 'success';
                    $message = '<strong>Multi Factor Authentication Enabled!</strong><br />You successfully';
                    $message .= 'associated an MFA device with your account. You will be required to use it ';
                    $message .= 'the next time you log in.';

                    $oSession->set_flashdata($status, $message);

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
            $this->input->post('mfaSecret', true)
        );

        if (!$this->data['secret']) {

            $status  = 'error';
            $message = '<Strong>Sorry,</strong> it has not been possible to get an MFA device set up for this user. ';
            $message .= $oAuthModel->lastError();

            $oSession->set_flashdata($status, $message);

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

    protected function requestCode()
    {
        if ($this->input->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaCode', '', 'required');
            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
                $code       = $this->input->post('mfaCode', true);

                //  Verify the inout
                if ($oAuthModel->mfaDeviceCodeValidate($this->mfaUser->id, $code)) {

                    //  Valid code, go ahead and log in!
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