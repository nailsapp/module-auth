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

use Nails\Auth\Controller\BaseMfa;
use Nails\Common\Exception\NailsException;
use Nails\Factory;

class MfaQuestion extends BaseMfa
{
    /**
     * Ensures we're use the correct MFA type
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function _remap()
    {
        if ($this->authMfaMode == 'QUESTION') {
            $this->index();
        } else {
            show404();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets up, or asks an MFA Question
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function index()
    {
        //  Validates the request token and generates a new one for the next request
        $this->validateToken();

        // --------------------------------------------------------------------------

        $oInput     = Factory::service('Input');
        $oAuthModel = Factory::model('Auth', 'nails/module-auth');

        if ($oInput->post('answer')) {

            /**
             * Validate the answer, if correct then log user in and forward, if
             * not then generate a new token and show errors
             */

            $this->data['question'] = $oAuthModel->mfaQuestionGet($this->mfaUser->id);
            $bIsValid               = $oAuthModel->mfaQuestionValidate(
                $this->data['question']->id,
                $this->mfaUser->id,
                $oInput->post('answer')
            );

            if ($bIsValid) {
                $this->loginUser();
            } else {
                $this->data['error'] = lang('auth_twofactor_answer_incorrect');
                $this->askQuestion();
            }

        } else {

            //  Determine whether the user has any security questions set
            $this->data['question'] = $oAuthModel->mfaQuestionGet($this->mfaUser->id);

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
                    $this->data['num_questions'] = $this->authMfaConfig['numQuestions'];
                }

                //  The number of user generated questions a user must have
                $this->data['num_custom_questions'] = $this->authMfaConfig['numUserQuestions'];

                if ($this->data['num_questions'] + $this->data['num_custom_questions'] <= 0) {
                    throw new NailsException('Two-factor auth is enabled, but no questions available');
                }

                if ($oInput->post()) {

                    $oFormValidation = Factory::service('FormValidation');

                    for ($i = 0; $i < $this->data['num_questions']; $i++) {

                        $oFormValidation->set_rules(
                            'question[' . $i . '][question]',
                            '',
                            'required|is_natural_no_zero'
                        );

                        $oFormValidation->set_rules(
                            'question[' . $i . '][answer]',
                            '',
                            'trim|required'
                        );
                    }

                    for ($i = 0; $i < $this->data['num_custom_questions']; $i++) {

                        $oFormValidation->set_rules(
                            'custom_question[' . $i . '][question]',
                            '',
                            'trim|required'
                        );

                        $oFormValidation->set_rules(
                            'custom_question[' . $i . '][answer]',
                            '',
                            'trim|required'
                        );
                    }

                    $oFormValidation->set_message('required', lang('fv_required'));
                    $oFormValidation->set_message('is_natural_no_zero', lang('fv_required'));

                    if ($oFormValidation->run()) {

                        //  Make sure that we have different questions
                        $aQuestionIndex = [];
                        $aQuestion      = (array) $oInput->post('question', true);
                        $bError         = false;

                        foreach ($aQuestion as $q) {
                            if (array_search($q['question'], $aQuestionIndex) === false) {
                                $aQuestionIndex[] = $q['question'];
                            } else {
                                $bError = true;
                                break;
                            }
                        }

                        $aQuestionIndex = [];
                        $aQuestion      = (array) $oInput->post('custom_question', true);

                        foreach ($aQuestion as $q) {
                            if (array_search($q['question'], $aQuestionIndex) === false) {
                                $aQuestionIndex[] = $q['question'];
                            } else {
                                $bError = true;
                                break;
                            }
                        }

                        if (!$bError) {

                            //  Good arrows. Save questions
                            $aData = [];

                            if ($oInput->post('question', true)) {

                                foreach ($oInput->post('question', true) as $q) {

                                    $oTemp = new stdClass();

                                    if (isset($this->data['questions'][$q['question'] - 1])) {
                                        $oTemp->question = $this->data['questions'][$q['question'] - 1];
                                    } else {
                                        $oTemp->question = null;
                                    }
                                    $oTemp->answer = $q['answer'];

                                    $aData[] = $oTemp;
                                }
                            }

                            if ($oInput->post('custom_question', true)) {
                                foreach ((array) $oInput->post('custom_question', true) as $aQuestion) {
                                    $aData[] = (object) [
                                        'question' => trim($aQuestion['question']),
                                        'answer'   => $aQuestion['answer'],
                                    ];
                                }
                            }

                            if ($oAuthModel->mfaQuestionSet($this->mfaUser->id, $aData)) {

                                $sStatus  = 'success';
                                $sMessage = '<strong>Multi Factor Authentication Enabled!</strong><br />You ';
                                $sMessage .= 'successfully set your security questions. You will be asked to answer ';
                                $sMessage .= 'one of them every time you log in.';

                                $oSession = Factory::service('Session', 'nails/module-auth');
                                $oSession->setFlashData($sStatus, $sMessage);

                                $this->loginUser();

                            } else {

                                $oUserModel          = Factory::model('User', 'nails/module-auth');
                                $this->data['error'] = lang('auth_twofactor_question_set_fail');
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
                $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/question/set.php');
                Factory::service('View')
                    ->load([
                        'structure/header/blank',
                        'auth/mfa/question/set',
                        'structure/footer/blank',
                    ]);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Asks one of the user's questions
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function askQuestion()
    {
        //  Ask away cap'n!
        $this->data['page']->title = lang('auth_twofactor_answer_title');
        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/question/ask.php');
        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/mfa/question/ask',
                'structure/footer/blank',
            ]);
    }
}
