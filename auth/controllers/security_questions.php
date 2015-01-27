<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * Security Questions/Two-factor auth facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Security_questions extends NAILS_Auth_Controller
{
    protected $authMfaMode;
    protected $authMfaConfig;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        $this->authMfaMode = $this->config->item('authTwoFactorMode');
        $config = $this->config->item('authTwoFactor');
        $this->authMfaConfig = $config['QUESTION'];
    }

    // --------------------------------------------------------------------------

    public function _remap()
    {
        if ($this->authMfaMode == 'QUESTION') {

            $returnTo = $this->input->get('return_to', true);
            $remember = $this->input->get('remember', true);
            $userId   = $this->uri->segment(3);
            $user     = $this->user_model->get_by_id($userId);

            if (!$user) {

                $this->session->set_flashdata('error', lang('auth_twofactor_token_unverified'));

                if ($returnTo) {

                    redirect('auth/login?return_to=' . $returnTo);

                } else {

                    redirect('auth/login');
                }
            }

            $sale        = $this->uri->segment(4);
            $token       = $this->uri->segment(5);
            $ipAddress   = $this->input->ip_address();
            $loginMethod = $this->uri->segment(6) ? $this->uri->segment(6) : 'native';

            //  Safety first
            switch(strtolower($loginMethod)) {

                case 'facebook':
                case 'twitter':
                case 'linkedin':
                case 'native':

                    //  All good, homies.
                    break;

                default:

                    $loginMethod = 'native';
                    break;
            }

            if ($this->auth_model->verify_two_factor_token($user->id, $sale, $token, $ipAddress)) {

                //  Token is valid, generate a new one for the next request
                $this->data['token'] = $this->auth_model->generate_two_factor_token($user->id);

                //  Set data for the views
                $this->data['user_id']      = $user->id;
                $this->data['login_method'] = $loginMethod;
                $this->data['return_to']    = $returnTo;
                $this->data['remember']     = $remember;

                if ($this->input->post('answer')) {

                    /**
                     * Validate the answer, if correct then log user in and forward, if
                     * not then generate a new token and show errors
                     */

                    $this->data['question'] = $this->user_model->get_security_question($user->id);
                    $isValid                = $this->user_model->validate_security_answer(
                        $this->data['question']->id,
                        $user->id,
                        $this->input->post('answer')
                    );

                    if ($isValid) {

                        //  Set login data for this user
                        $this->user_model->set_login_data($user->id);

                        //  If we're remembering this user set a cookie
                        if ($remember) {

                            $this->user_model->set_remember_cookie($user->id, $user->password, $user->email);
                        }

                        //  Update their last login and increment their login count
                        $this->user_model->update_last_login($user->id);

                        // --------------------------------------------------------------------------

                        //  Generate an event for this log in
                        create_event('did_log_in', array('method' => $loginMethod), $user->id);

                        // --------------------------------------------------------------------------

                        //  Say hello
                        if ($user->last_login) {

                            $this->load->helper('date');

                            if ($this->config->item('auth_show_nicetime_on_login')) {

                                $lastLogin = nice_time(strtotime($user->last_login));

                            } else {

                                $lastLogin = user_datetime($user->last_login);
                            }

                            if ($this->config->item('auth_show_last_ip_on_login')) {

                                $status  = 'message';
                                $message = lang(
                                    'auth_login_ok_welcome_with_ip',
                                    array(
                                        $user->first_name,
                                        $lastLogin,
                                        $user->last_ip
                                    )
                                );

                            } else {

                                $status  = 'message';
                                $message = lang(
                                    'auth_login_ok_welcome',
                                    array(
                                        $user->first_name,
                                        $lastLogin
                                    )
                                );
                            }

                        } else {

                            $status  = 'message';
                            $message = lang(
                                'auth_login_ok_welcome_notime',
                                array(
                                    $user->first_name
                                )
                            );
                        }

                        $this->session->set_flashdata($status, $message);

                        // --------------------------------------------------------------------------

                        //  Delete the token we generated, its no needed, eh!
                        $this->auth_model->delete_two_factor_token($this->data['token']['id']);

                        // --------------------------------------------------------------------------

                        $redirect = $returnTo != site_url() ? $returnTo : $user->group_homepage;

                        redirect($redirect);

                    } else {

                        $this->data['error'] = lang('auth_twofactor_answer_incorrect');

                        //  Ask away cap'n!
                        $this->data['page']->title = lang('auth_twofactor_answer_title');

                        $this->load->view('structure/header', $this->data);
                        $this->load->view('auth/security_question/ask', $this->data);
                        $this->load->view('structure/footer', $this->data);
                    }

                } else {

                    //  Determine whether the user has any security questions set
                    $this->data['question'] = $this->user_model->get_security_question($user->id);

                    if ($this->data['question']) {

                        //  Ask away cap'n!
                        $this->data['page']->title = 'Security Question';

                        $this->load->view('structure/header', $this->data);
                        $this->load->view('auth/security_question/ask', $this->data);
                        $this->load->view('structure/footer', $this->data);

                    } else {

                        //  Fetch the security questions
                        $this->data['questions'] = $this->authMfaConfig['questions'];

                        /**
                         * Determine how many questions a user must have, if the number of questions
                         * is smaller than the number of questions available, use the smaller.
                         */

                        if (count($this->data['questions']) < $this->authMfaConfig['numQuestions']) {

                            $this->data['num_questions'] = count($this->data['questions']);

                        } else {

                            $this->data['num_questions'] = count($this->authMfaConfig['numQuestions']);
                        }

                        //  The number of user generated questions a user must have
                        $this->data['num_custom_questions'] = $this->authMfaConfig['numUserQuestions'];

                        if ($this->data['num_questions'] + $this->data['num_custom_questions'] <= 0) {

                            $subject  = 'Two-factor auth is enabled, but no questions available';
                            $message  = 'A user tried to set security questions but there are no questions available ';
                            $message .= 'for them to choose. Please ensure auth.twofactor.php is configured correctly.';

                            showFatalError($subject, $message);
                        }

                        if ($this->input->post()) {

                            $this->load->library('form_validation');

                            for ($i = 0; $i < $this->data['num_questions']; $i++) {

                                $this->form_validation->set_rules(
                                    'question[' . $i . '][question]',
                                    '',
                                    'xss_clean|required|is_natural_no_zero'
                                );

                                $this->form_validation->set_rules(
                                    'question[' . $i . '][answer]',
                                    '',
                                    'xss_clean|trim|required'
                                );
                            }

                            for ($i = 0; $i < $this->data['num_custom_questions']; $i++) {

                                $this->form_validation->set_rules(
                                    'custom_question[' . $i . '][question]',
                                    '',
                                    'xss_clean|trim|required'
                                );

                                $this->form_validation->set_rules(
                                    'custom_question[' . $i . '][answer]',
                                    '',
                                    'xss_clean|trim|required'
                                );
                            }

                            $this->form_validation->set_message('required', lang('fv_required'));
                            $this->form_validation->set_message('is_natural_no_zero', lang('fv_required'));

                            if ($this->form_validation->run()) {

                                //  Make sure that we have different questions
                                $questionIndex = array();
                                $question      = (array) $this->input->post('question');
                                $error         = false;

                                foreach ($question as $q) {

                                    if (array_search($q['question'], $questionIndex) === false) {

                                        $questionIndex[] = $q['question'];

                                    } else {

                                        $error = true;
                                        break;
                                    }
                                }

                                $questionIndex = array();
                                $question      = (array) $this->input->post('custom_question');

                                foreach ($question as $q) {

                                    if (array_search($q['question'], $questionIndex) === false) {

                                        $questionIndex[] = $q['question'];

                                    } else {

                                        $error = true;
                                        break;
                                    }
                                }

                                if (!$error) {

                                    //  Good arrows. Save questions
                                    $data = array();

                                    if ($this->input->post('question')) {

                                        foreach ($this->input->post('question') as $q) {

                                            $temp = new stdClass();

                                            if (isset($this->data['questions'][$q['question']-1])) {

                                                $temp->question = $this->data['questions'][$q['question']-1];

                                            } else {

                                                $temp->question = null;
                                            }
                                            $temp->answer = $q['answer'];

                                            $data[] = $temp;
                                        }
                                    }

                                    if ($this->input->post('custom_question')) {

                                        foreach ((array) $this->input->post('custom_question') as $q) {

                                            $temp           = new stdClass();
                                            $temp->question = trim($q['question']);
                                            $temp->answer   = $q['answer'];

                                            $data[] = $temp;
                                        }
                                    }

                                    if ($this->user_model->set_security_questions($user->id, $data)) {

                                        //  Set login data for this user
                                        $this->user_model->set_login_data($user->id);

                                        //  If we're remembering this user set a cookie
                                        if ($remember) {

                                            $this->user_model->set_remember_cookie(
                                                $user->id,
                                                $user->password,
                                                $user->email
                                            );
                                        }

                                        //  Update their last login and increment their login count
                                        $this->user_model->update_last_login($user->id);

                                        // --------------------------------------------------------------------------

                                        //  Generate an event for this log in
                                        create_event('did_log_in', array('method' => $loginMethod), $user->id);

                                        // --------------------------------------------------------------------------

                                        //  Say hello
                                        if ($user->last_login) {

                                            $this->load->helper('date');

                                            if ($this->config->item('auth_show_nicetime_on_login')) {

                                                $lastLogin = nice_time(strtotime($user->last_login));

                                            } else {

                                                $lastLogin = user_datetime($user->last_login);
                                            }

                                            if ($this->config->item('auth_show_last_ip_on_login')) {

                                                $status  = 'message';
                                                $message = lang(
                                                    'auth_login_ok_welcome_with_ip',
                                                    array(
                                                        $user->first_name,
                                                        $lastLogin,
                                                        $user->last_ip
                                                    )
                                                );

                                            } else {

                                                $status  = 'message';
                                                $message = lang(
                                                    'auth_login_ok_welcome',
                                                    array(
                                                        $user->first_name,
                                                        $lastLogin
                                                    )
                                                );
                                            }

                                        } else {

                                            $status  = 'message';
                                            $message = lang(
                                                'auth_login_ok_welcome_notime',
                                                array(
                                                    $user->first_name
                                                )
                                            );
                                        }

                                        $this->session->set_flashdata($status, $message);

                                        // --------------------------------------------------------------------------

                                        //  Delete the token we generated, its no needed, eh!
                                        $this->auth_model->delete_two_factor_token($this->data['token']['id']);

                                        // --------------------------------------------------------------------------

                                        $redirect = $returnTo != site_url() ? $returnTo : $user->group_homepage;
                                        redirect($redirect);

                                    } else {

                                        $this->data['error']  = lang('auth_twofactor_question_set_fail');
                                        $this->data['error'] .= ' ' . $this->user_model->last_error();
                                    }

                                } else {

                                    $this->data['error'] = lang('auth_twofactor_question_unique');
                                }

                            } else {

                                $this->data['error'] = lang('fv_there_were_errors');
                            }
                        }

                        //  No questions, request they set them
                        $this->data['page']->title = lang('auth_twofactor_question_set_title');

                        $this->load->view('structure/header', $this->data);
                        $this->load->view('auth/security_question/set', $this->data);
                        $this->load->view('structure/footer', $this->data);
                    }
                }

            } else {

                $this->session->set_flashdata('error', lang('auth_twofactor_token_unverified'));

                $query              = array();
                $query['return_to'] = $returnTo;
                $query['remember']  = $remember;

                $query = array_filter($query);

                if ($query) {

                    $query = '?' . http_build_query($query);

                } else {

                    $query = '';
                }

                redirect('auth/login' . $query);
            }

        } else {

            show_404();
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

if (!defined('NAILS_ALLOW_EXTENSION_SECURITY_QUESTIONS')) {

    class Security_questions extends NAILS_Security_questions
    {
    }
}
