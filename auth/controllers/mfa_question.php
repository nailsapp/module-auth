<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_mfa.php';

/**
 * Handles Multi-Factor Authentication when authTypeMode is 'QUESTION'
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Mfa_question extends NAILS_Auth_Mfa_Controller
{
    public function _remap()
    {
        if ($this->authMfaMode == 'QUESTION') {

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

        if ($this->input->post('answer')) {

            /**
             * Validate the answer, if correct then log user in and forward, if
             * not then generate a new token and show errors
             */

            $this->data['question'] = $this->auth_model->mfaQuestionGet($this->mfaUser->id);
            $isValid                = $this->auth_model->mfaQuestionValidate(
                $this->data['question']->id,
                $this->mfaUser->id,
                $this->input->post('answer')
            );

            if ($isValid) {

                $this->loginUser();

            } else {

                $this->data['error'] = lang('auth_twofactor_answer_incorrect');
                $this->askQuestion();
            }

        } else {

            //  Determine whether the user has any security questions set
            $this->data['question'] = $this->auth_model->mfaQuestionGet($this->mfaUser->id);

            if ($this->data['question']) {

                //  Ask away cap'n!
                $this->askQuestion();

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

                            if ($this->auth_model->mfaQuestionSet($this->mfaUser->id, $data)) {

                                $status   = 'success';
                                $message  = '<strong>Multi Factor Authentication Enabled!</strong><br />You ';
                                $message .= 'successfully set your security questions. You will be asked to answer ';
                                $message .= 'one of them every time you log in.';

                                $this->session->set_flashdata($status, $message);

                                $this->loginUser();

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
                $this->load->view('auth/mfa/question/set', $this->data);
                $this->load->view('structure/footer', $this->data);
            }
        }
    }

    // --------------------------------------------------------------------------

    protected function askQuestion()
    {
        //  Ask away cap'n!
        $this->data['page']->title = lang('auth_twofactor_answer_title');

        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/mfa/question/ask', $this->data);
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

if (!defined('NAILS_ALLOW_EXTENSION_MFA_QUESTION')) {

    class Mfa_question extends NAILS_Mfa_question
    {
    }
}