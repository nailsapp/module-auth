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

$config['authTwoFactor'] = array(
    'QUESTION' => array(),
    'DEVICE'   => array()
);

// --------------------------------------------------------------------------

/**
 * QUESTION
 * The following configurations apply only to the QUESTION MFA type
 */

// The number of system questions a user must have
$config['authTwoFactor']['QUESTION']['numQuestions'] = 1;

// The number of user questions a user must have
$config['authTwoFactor']['QUESTION']['numUserQuestions'] = 0;

//  The questions the system can use
$config['authTwoFactor']['QUESTION']['questions'] = array();
$config['authTwoFactor']['QUESTION']['questions'][] = 'What was your childhood nickname? ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'In what city did you meet your spouse/significant other?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is the name of your favorite childhood friend? ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is the middle name of your oldest child?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your oldest sibling\'s middle name?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What was your childhood phone number including area code?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your oldest cousin\'s first and last name?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What was the name of your first stuffed animal?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'In what city or town did your mother and father meet? ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'Where were you when you had your first kiss? ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is the first name of the boy or girl that you first kissed?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'In what city does your nearest sibling live? ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your oldest sibling\'s birthday month and year? (e.g., January 1900) ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your oldest brother\'s birthday month and year? (e.g., January 1900) ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your oldest sister\'s birthday month and year? (e.g., January 1900) ';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is your maternal grandmother\'s maiden name?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'In what city or town was your first job?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is the name of the place your wedding reception was held?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'What is the name of a college or university you applied to but didn\'t attend?';
$config['authTwoFactor']['QUESTION']['questions'][] = 'Where were you when you first heard about 9/11?';

// --------------------------------------------------------------------------

/**
 * DEVICE
 * The following configurations apply only to the DEVICE MFA type
 */

// $config['authTwoFactor']['DEVICE']['something'] = '';
