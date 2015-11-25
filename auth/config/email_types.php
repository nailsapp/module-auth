<?php

/**
 * This config file defines email types for this module.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

$config['email_types'] = array();

$config['email_types'][0]                   = new stdClass();
$config['email_types'][0]->slug             = 'verify_email';
$config['email_types'][0]->name             = 'Verify Email (Generic)';
$config['email_types'][0]->description      = 'Email sent with a verification code';
$config['email_types'][0]->template_header  = '';
$config['email_types'][0]->template_body    = 'auth/email/verify_email';
$config['email_types'][0]->template_footer  = '';
$config['email_types'][0]->default_subject  = 'Please verify your email';

$config['email_types'][1]                   = new stdClass();
$config['email_types'][1]->slug             = 'forgotten_password';
$config['email_types'][1]->name             = 'Forgotten Password';
$config['email_types'][1]->description      = 'Email which is sent when a user requests a password reset.';
$config['email_types'][1]->template_header  = '';
$config['email_types'][1]->template_body    = 'auth/email/forgotten_password';
$config['email_types'][1]->template_footer  = '';
$config['email_types'][1]->default_subject  = 'Reset your Password';

$config['email_types'][2]                   = new stdClass();
$config['email_types'][2]->slug             = 'new_user';
$config['email_types'][2]->name             = 'Welcome Email (Generic)';
$config['email_types'][2]->description      = 'Email sent to new users when they register on site, or when an administrator creates a new user account.';
$config['email_types'][2]->template_header  = '';
$config['email_types'][2]->template_body    = 'auth/email/new_user';
$config['email_types'][2]->template_footer  = '';
$config['email_types'][2]->default_subject  = 'Welcome';

$config['email_types'][3]                   = new stdClass();
$config['email_types'][3]->slug             = 'password_updated';
$config['email_types'][3]->name             = 'Password Updated';
$config['email_types'][3]->description      = 'Email sent to users when their password is updated, regardless of who updated it.';
$config['email_types'][3]->template_header  = '';
$config['email_types'][3]->template_body    = 'auth/email/password_updated';
$config['email_types'][3]->template_footer  = '';
$config['email_types'][3]->default_subject  = 'Your Password Has Been Updated';
