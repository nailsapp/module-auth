<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * Reset password facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Reset_Password extends NAILS_Auth_Controller
{
    /**
     * Constructor
     *
     * @access  public
     * @param   none
     * @return  void
     **/
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  If user is logged in they shouldn't be accessing this method
        if ($this->user_model->isLoggedIn()) {

            $this->session->set_flashdata('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validate the supplied assets and if valid present the user with a reset form
     *
     * @access  public
     * @param   int     $id     The ID of the user to reset
     * @param   strgin  hash    The hash to validate against
     * @return  void
     **/
    protected function validate($id, $hash)
    {
        //  Check auth credentials
        $user = $this->user_model->get_by_id($id);

        // --------------------------------------------------------------------------

        if ($user !== false && isset($user->salt) && $hash == md5($user->salt)) {

            //  Valid combination, is there MFA on the account?
            if ($this->config->item('authTwoFactorMode')) {

                /**
                 * This variable will stop the password resetting until we're confident
                 * that MFA has been passed
                 */

                $mfaValid = false;

                /**
                 * Check the user's account to see if they have MFA enabled, if so
                 * require that they pass that before allowing the password to be reset
                 */

                switch ($this->config->item('authTwoFactorMode')) {

                    case 'QUESTION':

                        $this->data['mfaQuestion'] = $this->auth_model->mfaQuestionGet($user->id);

                        if ($this->data['mfaQuestion']) {

                            if ($this->input->post()) {

                                //  Validate answer
                                $isValid = $this->auth_model->mfaQuestionValidate(
                                    $this->data['mfaQuestion']->id,
                                    $user->id,
                                    $this->input->post('mfaAnswer')
                                );

                                if ($isValid) {

                                    $mfaValid = true;

                                } else {

                                    $this->data['error']  = '<strong>Sorry,</strong> the answer to your security ';
                                    $this->data['error'] .= 'question was incorrect.';
                                }
                            }

                        } else {

                            //  No questions set up, allow for now
                            $mfaValid = true;
                        }

                        break;

                    case 'DEVICE':

                        $this->data['mfaDevice'] = $this->auth_model->mfaDeviceSecretGet($user->id);

                        if ($this->data['mfaDevice']) {

                            if ($this->input->post()) {

                                //  Validate answer
                                $isValid = $this->auth_model->mfaDeviceCodeValidate(
                                    $user->id,
                                    $this->input->post('mfaCode')
                                );

                                if ($isValid) {

                                    $mfaValid = true;

                                } else {

                                    $this->data['error']  = '<strong>Sorry,</strong> that code could not be validated. ';
                                    $this->data['error'] .= $this->auth_model->last_error();
                                }
                            }

                        } else {

                            //  No devices set up, allow for now
                            $mfaValid = true;
                        }
                        break;
                }

            } else {

                //  No MFA so just set this to true
                $mfaValid = true;
            }

            // --------------------------------------------------------------------------

            // Only run if MFA has been passed and there's POST data
            if ($mfaValid && $this->input->post()) {

                // Validate data
                $this->load->library('form_validation');

                // --------------------------------------------------------------------------

                /**
                 * Define rules - I know it's not usual to give fields names, but in this case
                 * it allows the matches message to have more context (a name, rather than a
                 * field name)
                 */
                $this->form_validation->set_rules('new_password', 'Password', 'required|matches[confirm_pass]');
                $this->form_validation->set_rules('confirm_pass', 'Confirm Password', 'required');

                // --------------------------------------------------------------------------

                //  Set custom messages
                $this->form_validation->set_message('required', lang('fv_required'));
                $this->form_validation->set_message('matches', lang('fv_matches'));

                // --------------------------------------------------------------------------

                //  Run validation
                if ($this->form_validation->run()) {

                    //  Validated, update user and login.
                    $data                            = array();
                    $data['forgotten_password_code'] = null;
                    $data['temp_pw']                 = false;
                    $data['password']                = $this->input->post('new_password');
                    $remember                        = (bool) $this->input->get('remember');

                    //  Reset the password
                    if ($this->user_model->update($user->id, $data)) {

                        //  Log the user in
                        switch (APP_NATIVE_LOGIN_USING) {

                            case 'EMAIL' :

                                $loginUser = $this->auth_model->login(
                                    $user->email,
                                    $this->input->post('new_password'),
                                    $remember
                                );
                                break;

                            case 'USERNAME' :

                                $loginUser = $this->auth_model->login(
                                    $user->username,
                                    $this->input->post('new_password'),
                                    $remember
                                );
                                break;

                            default :

                                $loginUser = $this->auth_model->login(
                                    $user->email,
                                    $this->input->post('new_password'),
                                    $remember
                                );
                                break;
                        }

                        if ($loginUser) {

                            //  Say hello
                            if ($loginUser->last_login) {

                                if ($this->config->item('authShowNicetimeOnLogin')) {

                                    $lastLogin = niceTime(strtotime($loginUser->last_login));

                                } else {

                                    $lastLogin = toUserDatetime($loginUser->last_login);
                                }

                                if ($this->config->item('authShowLastIpOnLogin')) {

                                    $status  = 'message';
                                    $message = lang(
                                        'auth_login_ok_welcome_with_ip',
                                        array(
                                            $loginUser->first_name,
                                            $lastLogin,
                                            $loginUser->last_ip
                                        )
                                    );

                                } else {

                                    $status  = 'message';
                                    $message = lang(
                                        'auth_login_ok_welcome',
                                        array(
                                            $loginUser->first_name,
                                            $lastLogin
                                        )
                                    );
                                }

                            } else {

                                $status  = 'message';
                                $message = lang(
                                    'auth_login_ok_welcome_notime',
                                    array(
                                        $loginUser->first_name
                                    )
                                );
                            }

                            $this->session->set_flashdata($status, $message);

                            //  If MFA is setup then we'll need to set the user's session data
                            if ($this->config->item('authTwoFactorMode')) {

                                $this->user_model->setLoginData($user->id);
                            }

                            //  Log user in and forward to wherever they need to go
                            if ($this->input->get('return_to')) {

                                redirect($this->input->get('return_to'));

                            } elseif ($user->group_homepage) {

                                redirect($user->group_homepage);

                            } else {

                                redirect('/');
                            }

                        } else {

                            $this->data['error'] = lang('auth_forgot_reset_badlogin', site_url('auth/login'));
                        }

                    } else {

                        $this->data['error'] = lang('auth_forgot_reset_badupdate', $this->user_model->last_error());
                    }

                } else {

                    $this->data['error'] = lang('fv_there_were_errors');
                }
            }

            // --------------------------------------------------------------------------

            //  Set data
            $this->data['page']->title = lang('auth_title_reset');

            $this->data['auth']       = new stdClass();
            $this->data['auth']->id   = $id;
            $this->data['auth']->hash = $hash;

            $this->data['passwordRules'] = $this->user_password_model->getRulesAsString($user->group_id);

            $this->data['return_to'] = $this->input->get('return_to');
            $this->data['remember']  = $this->input->get('remember');

            if (empty($this->data['message'])) {

                switch ($this->input->get('reason')) {

                    case 'EXPIRED' :

                        $this->data['message'] = lang(
                            'auth_login_pw_expired',
                            $this->user_password_model->expiresAfter($user->group_id)
                        );
                        break;

                    case 'TEMP' :
                    default :

                        $this->data['message'] = lang('auth_login_pw_temp');
                        break;
                }
            }

            // --------------------------------------------------------------------------

            //  Load the views
            $this->load->view('structure/header', $this->data);
            $this->load->view('auth/password/change_temp', $this->data);
            $this->load->view('structure/footer', $this->data);

            return;
        }

        // --------------------------------------------------------------------------

        show_404();
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests to the right method
     * @param   string  $id The ID of the user to reset, as per the URL
     * @return  void
     **/
    public function _remap($id)
    {
        $this->validate($id, $this->uri->segment(4));
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

    class Reset_Password extends NAILS_Reset_Password
    {
    }
}
