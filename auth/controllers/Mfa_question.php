<?php

/**
 * Handles Multi-Factor Authentication when authTypeMode is 'QUESTION'
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Factory;
use Nails\Auth\Controller\BaseMfa;

class Mfa_question extends BaseMfa
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

                    $oFormValidation = Factory::service('FormValidation');

                    for ($i = 0; $i < $this->data['num_questions']; $i++) {

                        $oFormValidation->set_rules(
                            'question[' . $i . '][question]',
                            '',
                            'xss_clean|required|is_natural_no_zero'
                        );

                        $oFormValidation->set_rules(
                            'question[' . $i . '][answer]',
                            '',
                            'xss_clean|trim|required'
                        );
                    }

                    for ($i = 0; $i < $this->data['num_custom_questions']; $i++) {

                        $oFormValidation->set_rules(
                            'custom_question[' . $i . '][question]',
                            '',
                            'xss_clean|trim|required'
                        );

                        $oFormValidation->set_rules(
                            'custom_question[' . $i . '][answer]',
                            '',
                            'xss_clean|trim|required'
                        );
                    }

                    $oFormValidation->set_message('required', lang('fv_required'));
                    $oFormValidation->set_message('is_natural_no_zero', lang('fv_required'));

                    if ($oFormValidation->run()) {

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

                                $oSession = Factory::service('Session', 'nailsapp/module-auth');
                                $oSession->set_flashdata($status, $message);

                                $this->loginUser();

                            } else {

                                $oUserModel = Factory::model('User', 'nailsapp/module-auth');

                                $this->data['error']  = lang('auth_twofactor_question_set_fail');
                                $this->data['error'] .= ' ' . $oUserModel->lastError();
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

                $oView = Factory::service('View');
                $oView->load('structure/header/blank', $this->data);
                $oView->load('auth/mfa/question/set', $this->data);
                $oView->load('structure/footer/blank', $this->data);
            }
        }
    }

    // --------------------------------------------------------------------------

    protected function askQuestion()
    {
        //  Ask away cap'n!
        $this->data['page']->title = lang('auth_twofactor_answer_title');

        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/mfa/question/ask', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }
}
