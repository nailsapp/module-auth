<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_mfa.php';

/**
 * Handles Multi-Factor Authentication when authTypeMode is 'DEVICE'
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Mfa_device extends NAILS_Auth_Mfa_Controller
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

            $this->load->library('form_validation');

            $this->form_validation->set_rules('mfaSecret', '', 'xss_clean|required');
            $this->form_validation->set_rules('mfaCode1', '', 'xss_clean|required');
            $this->form_validation->set_rules('mfaCode2', '', 'xss_clean|required');

            $this->form_validation->set_message('required', lang('fv_required'));

            if ($this->form_validation->run()) {

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
            $message .= $this->auth_model->last_error();

            $this->session->set_flashdata($status, $message);

            if ($this->returnTo) {

                redirect('auth/login?return_to=' . $this->returnTo);

            } else {

                redirect('auth/login');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Set up a new MFA device';

        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/mfa/device/setup', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    protected function requestCode()
    {
        if ($this->input->post()) {

            $this->load->library('form_validation');

            $this->form_validation->set_rules('mfaCode', '', 'xss_clean|required');
            $this->form_validation->set_message('required', lang('fv_required'));

            if ($this->form_validation->run()) {

                $code = $this->input->post('mfaCode');

                //  Verify the inout
                if ($this->auth_model->mfaDeviceCodeValidate($this->mfaUser->id, $code)) {

                    //  Valid code, go ahead and log in!
                    $this->loginUser();

                } else {

                    $this->data['error']  = '<strong>Sorry,</strong> that code failed to validate. Please try again. ';
                    $this->data['error'] .= $this->auth_model->last_error();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Render the page
        $this->data['page']->title = 'Enter your Code';

        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/mfa/device/ask', $this->data);
        $this->load->view('structure/footer', $this->data);
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' AUTH MODULE
 *
 * The following block of code makes it simple to extend one of the core auth
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_MFA_DEVICE')) {

    class Mfa_device extends NAILS_Mfa_device
    {
    }
}
