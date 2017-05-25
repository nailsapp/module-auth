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
        $mfaDevice = $this->auth_model->mfaDeviceSecretGet($this->mfaUser->id);

        if ($mfaDevice) {

            $this->requestCode();

        } else {

            $this->setupDevice();
        }
    }

    // --------------------------------------------------------------------------

    protected function setupDevice()
    {
        if ($this->input->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaSecret', '', 'xss_clean|required');
            $oFormValidation->set_rules('mfaCode1', '', 'xss_clean|required');
            $oFormValidation->set_rules('mfaCode2', '', 'xss_clean|required');

            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $secret = $this->input->post('mfaSecret');
                $code1  = $this->input->post('mfaCode1');
                $code2  = $this->input->post('mfaCode2');

                //  Verify the inout
                if ($this->auth_model->mfaDeviceSecretValidate($this->mfaUser->id, $secret, $code1, $code2)) {

                    //  Codes have been validated and saved to the DB, sign the user in and move on
                    $status   = 'success';
                    $message  = '<strong>Multi Factor Authentication Enabled!</strong><br />You successfully';
                    $message .= 'associated an MFA device with your account. You will be required ot use it ';
                    $message .= 'the next time you log in.';

                    $this->session->set_flashdata($status, $message);

                    $this->loginUser();

                } else {

                    $this->data['error'] = '<strong>Sorry,</strong> those codes failed to validate. Please try again.';
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Generate the secret
        $this->data['secret'] = $this->auth_model->mfaDeviceSecretGenerate(
            $this->mfaUser->id,
            $this->input->post('mfaSecret')
        );

        if (!$this->data['secret']) {

            $status   = 'error';
            $message  = '<Strong>Sorry,</strong> it has not been possible to get an MFA device set up for this user. ';
            $message .= $this->auth_model->lastError();

            $this->session->set_flashdata($status, $message);

            if ($this->returnTo) {

                redirect('auth/login?return_to=' . $this->returnTo);

            } else {

                redirect('auth/login');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Set up a new MFA device';

        $this->load->view('structure/header/blank', $this->data);
        $this->load->view('auth/mfa/device/setup', $this->data);
        $this->load->view('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    protected function requestCode()
    {
        if ($this->input->post()) {

            $oFormValidation = Factory::service('FormValidation');

            $oFormValidation->set_rules('mfaCode', '', 'xss_clean|required');
            $oFormValidation->set_message('required', lang('fv_required'));

            if ($oFormValidation->run()) {

                $code = $this->input->post('mfaCode');

                //  Verify the inout
                if ($this->auth_model->mfaDeviceCodeValidate($this->mfaUser->id, $code)) {

                    //  Valid code, go ahead and log in!
                    $this->loginUser();

                } else {

                    $this->data['error']  = '<strong>Sorry,</strong> that code failed to validate. Please try again. ';
                    $this->data['error'] .= $this->auth_model->lastError();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Enter your Code';

        $this->load->view('structure/header/blank', $this->data);
        $this->load->view('auth/mfa/device/ask', $this->data);
        $this->load->view('structure/footer/blank', $this->data);
    }
}
