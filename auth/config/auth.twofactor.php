<?php

/**
 * Auth config (two factor authentication)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

/**
 * Enable two factor authentication
 * Options are:
 *
 * - 'QUESTION' to enable security questions
 * - 'DEVICE' to enable one-time codes from a device
 */

$config['authTwoFactorMode'] = false;

$config['authTwoFactor'] = [
    'QUESTION' => [
        // The number of system questions a user must have
        'numQuestions'     => 1,

        // The number of user questions a user must have
        'numUserQuestions' => 0,

        //  The questions the system can use
        'questions'        => [
            'What was your childhood nickname? ',
            'In what city did you meet your spouse/significant other?',
            'What is the name of your favorite childhood friend? ',
            'What is the middle name of your oldest child?',
            'What is your oldest sibling\'s middle name?',
            'What was your childhood phone number including area code?',
            'What is your oldest cousin\'s first and last name?',
            'What was the name of your first stuffed animal?',
            'In what city or town did your mother and father meet? ',
            'Where were you when you had your first kiss? ',
            'What is the first name of the boy or girl that you first kissed?',
            'In what city does your nearest sibling live? ',
            'What is your oldest sibling\'s birthday month and year? (e.g., January 1900) ',
            'What is your oldest brother\'s birthday month and year? (e.g., January 1900) ',
            'What is your oldest sister\'s birthday month and year? (e.g., January 1900) ',
            'What is your maternal grandmother\'s maiden name?',
            'In what city or town was your first job?',
            'What is the name of the place your wedding reception was held?',
            'What is the name of a college or university you applied to but didn\'t attend?',
            'Where were you when you first heard about 9/11?',
        ],
    ],
    'DEVICE'   => [],
];
