<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * Forgotten password facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 * @todo  Refactor this class so that not so much code is being duplicated, especially re: MFA
 */
class NAILS_Forgotten_Password extends NAILS_Auth_Controller
{
    /**
     * Constructor
     * @return  void
     **/
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Load libraries
        $this->load->library('form_validation');

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_forgotten_password');
    }

    // --------------------------------------------------------------------------

    /**
     * Reset password form
     * @return  void
     **/
    public function index()
    {
        //  If user is logged in they shouldn't be accessing this method
        if ($this->user_model->isLoggedIn()) {

            $this->session->set_flashdata('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        //  If there's POST data attempt to validate the user
        if ($this->input->post() || $this->input->get('identifier')) {

            //  Define vars
            $_identifier = $this->input->post('identifier');

            /**
             * Override with the $_GET variable if POST failed to return anything. Populate
             * the $_POST var with some data so form validation continues as normal, feels
             * hacky but works.
             */

            if (!$_identifier && $this->input->get('identifier')) {

                $_POST['identifier'] = $this->input->get('identifier');
                $_identifier         = $this->input->get('identifier');
            }

            // --------------------------------------------------------------------------

            /**
             * Set rules.
             * The rules vary depending on what login method is enabled.
             */

            switch (APP_NATIVE_LOGIN_USING) {

                case 'EMAIL' :

                    $this->form_validation->set_rules('identifier', '', 'required|xss_clean|trim|valid_email');
                    break;

                case 'USERNAME' :

                    $this->form_validation->set_rules('identifier', '', 'required|xss_clean|trim');
                    break;

                default:

                    $this->form_validation->set_rules('identifier', '', 'xss_clean|trim');
                    break;
            }

            // --------------------------------------------------------------------------

            //  Override default messages
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('valid_email',  lang('fv_valid_email'));

            // --------------------------------------------------------------------------

            //  Run validation
            if ($this->form_validation->run()) {

                /**
                 * Some apps may want the forgotten password tool to always return as successfull,
                 * even if it wasn't. Bad UX, if you ask me, but I'm not the client.
                 */

                $alwaysSucceed = $this->config->item('auth_forgotten_pass_always_succeed');

                //  Attempt to reset password
                if ($this->user_password_model->set_token($_identifier)) {

                    //  Send email to user
                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL' :

                            $this->data['reset_user'] = $this->user_model->get_by_email($_identifier);

                            //  User provided an email, send to that email
                            $sendToEmail = $_identifier;
                            break;

                        case 'USERNAME' :

                            $this->data['reset_user'] = $this->user_model->get_by_username($_identifier);

                            /**
                             * Can't email a username, send to their ID and let the email library
                             * handle the routing
                             */

                            $sendToId = $this->data['reset_user']->id;
                            break;

                        default:

                            if (valid_email($_identifier)) {

                                $this->data['reset_user'] = $this->user_model->get_by_email($_identifier);

                                //  User provided an email, send to that email
                                $sendToEmail = $_identifier;

                            } else {

                                $this->data['reset_user'] = $this->user_model->get_by_username($_identifier);

                                /**
                                 * Can't email a username, send to their ID and let the email library handle
                                 * the routing
                                 */

                                $sendToId = $this->data['reset_user']->id;
                            }
                            break;
                    }

                    // --------------------------------------------------------------------------

                    if (!$alwaysSucceed && isset($sendToEmail) && !$sendToEmail) {

                        //  If we're expecting an email, and none is available then we're kinda stuck
                        $this->data['error'] = lang('auth_forgot_email_fail_no_email');

                    } elseif (!$alwaysSucceed && isset($sendToId) && !$sendToId) {

                        //  If we're expecting an ID and it's empty then we're stuck again
                        $this->data['error'] = lang('auth_forgot_email_fail_no_id');

                    } elseif ($alwaysSucceed) {

                        //  Failed, but we always succeed so, yeah, succeed
                        $this->data['success'] = lang('auth_forgot_success');

                    } else {

                        //  We've got something, go go go
                        $_data       = new stdClass();
                        $_data->type = 'forgotten_password';

                        if (isset($sendToEmail) && $sendToEmail) {

                            $_data->to_email = $sendToEmail;

                        } elseif (isset($sendToId) && $sendToId) {

                            $_data->to_id = $sendToId;
                        }

                        // --------------------------------------------------------------------------

                        //  Add data for the email view
                        $_code = explode(':', $this->data['reset_user']->forgotten_password_code);

                        $_data->data                            = array();
                        $_data->data['first_name']              = title_case($this->data['reset_user']->first_name);
                        $_data->data['forgotten_password_code'] = $_code[1];
                        $_data->data['identifier']              = $_identifier;

                        // --------------------------------------------------------------------------

                        //  Send user the password reset email
                        if ($this->emailer->send($_data)) {

                            $this->data['success'] = lang('auth_forgot_success');

                        } elseif ($alwaysSucceed) {

                            $this->data['success'] = lang('auth_forgot_success');

                        } else {

                            $this->data['error'] = lang('auth_forgot_email_fail');
                        }
                    }

                } elseif ($alwaysSucceed) {

                    $this->data['success'] = lang('auth_forgot_success');

                } else {

                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL':

                            $this->data['error'] = lang('auth_forgot_code_not_set_email', $_identifier);
                            break;

                        // --------------------------------------------------------------------------

                        case 'USERNAME':

                            $this->data['error'] = lang('auth_forgot_code_not_set_username', $_identifier);
                            break;

                        // --------------------------------------------------------------------------

                        default:

                            $this->data['error'] = lang('auth_forgot_code_not_set');
                            break;
                    }
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Load the views
        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/password/forgotten', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a code
     * @param   string  $code The code to validate
     * @return  void
     */
    public function _validate($code)
    {
        /**
         * Attempt to verify code, if two factor auth is enabled then don't generate a
         * new password, we'll need the user to jump through some hoops first.
         */

        $generateNewPw = !$this->config->item('authTwoFactorMode');

        $newPw = $this->user_password_model->validate_token($code, $generateNewPw);

        // --------------------------------------------------------------------------

        //  Determine outcome of validation
        if ($newPw === 'EXPIRED') {

            //  Code has expired
            $this->data['error'] = lang('auth_forgot_expired_code');

        } elseif ($newPw === false) {

            //  Code was invalid
            $this->data['error'] = lang('auth_forgot_invalid_code');

        } else {

            if ($this->config->item('authTwoFactorMode') == 'QUESTION') {

                //  Show them a security question
                $this->data['question'] = $this->auth_model->mfaQuestionGet($newPw['user_id']);

                if ($this->data['question']) {

                    if ($this->input->post()) {

                        $isValid = $this->auth_model->mfaQuestionValidate(
                            $this->data['question']->id,
                            $newPw['user_id'],
                            $this->input->post('answer')
                        );

                        if ($isValid) {

                            //  Correct answer, reset password and render views
                            $newPw = $this->user_password_model->validate_token($code, true);

                            $this->data['new_password'] = $newPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $status  = 'notice';
                            $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                            $this->session->set_flashdata($status, $message);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->load->view('structure/header', $this->data);
                            $this->load->view('auth/password/forgotten_reset', $this->data);
                            $this->load->view('structure/footer', $this->data);
                            return;

                        } else {

                            $this->data['error'] = lang('auth_twofactor_answer_incorrect');
                        }
                    }

                    $this->data['page']->title = lang('auth_title_forgotten_password_security_question');

                    $this->load->view('structure/header', $this->data);
                    $this->load->view('auth/mfa/question/ask', $this->data);
                    $this->load->view('structure/footer', $this->data);

                } else {

                    //  No questions, reset and load views
                    $newPw = $this->user_password_model->validate_token($code, true);

                    $this->data['new_password'] = $newPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $status  = 'notice';
                    $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                    $this->session->set_flashdata($status, $message);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->load->view('structure/header', $this->data);
                    $this->load->view('auth/password/forgotten_reset', $this->data);
                    $this->load->view('structure/footer', $this->data);
                }

            } elseif ($this->config->item('authTwoFactorMode') == 'DEVICE') {

                $secret = $this->auth_model->mfaDeviceSecretGet($newPw['user_id']);

                if ($secret) {

                    if ($this->input->post()) {

                        $mfaCode = $this->input->post('mfaCode');

                        //  Verify the inout
                        if ($this->auth_model->mfaDeviceCodeValidate($newPw['user_id'], $mfaCode)) {

                            //  Correct answer, reset password and render views
                            $newPw = $this->user_password_model->validate_token($code, true);

                            $this->data['new_password'] = $newPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $status  = 'notice';
                            $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                            $this->session->set_flashdata($status, $message);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->load->view('structure/header', $this->data);
                            $this->load->view('auth/password/forgotten_reset', $this->data);
                            $this->load->view('structure/footer', $this->data);
                            return;

                        } else {

                            $this->data['error']  = '<strong>Sorry,</strong> that code failed to validate. Please try again. ';
                            $this->data['error'] .= $this->auth_model->last_error();
                        }
                    }

                    $this->data['page']->title = 'cock';

                    $this->load->view('structure/header', $this->data);
                    $this->load->view('auth/mfa/device/ask', $this->data);
                    $this->load->view('structure/footer', $this->data);

                } else {

                    //  No devices, reset and load views
                    $newPw = $this->user_password_model->validate_token($code, true);

                    $this->data['new_password'] = $newPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $status  = 'notice';
                    $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                    $this->session->set_flashdata($status, $message);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->load->view('structure/header', $this->data);
                    $this->load->view('auth/password/forgotten_reset', $this->data);
                    $this->load->view('structure/footer', $this->data);
                }

            } else {

                //  Everything worked!
                $this->data['new_password'] = $newPw['password'];

                // --------------------------------------------------------------------------

                //  Set some flashdata for the login page when they go to it; just a little reminder
                $status  = 'notice';
                $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                $this->session->set_flashdata($status, $message);

                // --------------------------------------------------------------------------

                //  Load the views
                $this->load->view('structure/header', $this->data);
                $this->load->view('auth/password/forgotten_reset', $this->data);
                $this->load->view('structure/footer', $this->data);
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Load the views
        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/password/forgotten', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests to the right method
     * @return  void
     **/
    public function _remap($method)
    {
        //  If you're logged in you shouldn't be accessing this method
        if ($this->user_model->isLoggedIn()) {

            $this->session->set_flashdata('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        if ($method == 'index') {

            $this->index();

        } else {

            $this->_validate($method);
        }
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

if (!defined('NAILS_ALLOW_EXTENSION')) {

    class Forgotten_Password extends NAILS_Forgotten_Password
    {
    }
}
