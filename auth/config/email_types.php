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

$config['email_types'] = [
    (object) [
        'slug'             => 'verify_email',
        'name'             => 'Auth: Verify Email (Generic)',
        'description'      => 'Email sent with a verification code',
        'isUnsubscribable' => false,
        'template_header'  => '',
        'template_body'    => 'auth/email/verify_email',
        'template_footer'  => '',
        'default_subject'  => 'Please verify your email',
    ],
    (object) [
        'slug'             => 'forgotten_password',
        'name'             => 'Auth: Forgotten Password',
        'description'      => 'Email which is sent when a user requests a password reset.',
        'isUnsubscribable' => false,
        'template_header'  => '',
        'template_body'    => 'auth/email/forgotten_password',
        'template_footer'  => '',
        'default_subject'  => 'Reset your Password',
    ],
    (object) [
        'slug'             => 'new_user',
        'name'             => 'Auth: Welcome Email (Generic)',
        'description'      => 'Email sent to new users when they register on site, or when an administrator creates a new user account.',
        'isUnsubscribable' => false,
        'template_header'  => '',
        'template_body'    => 'auth/email/new_user',
        'template_footer'  => '',
        'default_subject'  => 'Welcome',
    ],
    (object) [
        'slug'             => 'password_updated',
        'name'             => 'Auth: Password Updated',
        'description'      => 'Email sent to users when their password is updated, regardless of who updated it.',
        'isUnsubscribable' => false,
        'template_header'  => '',
        'template_body'    => 'auth/email/password_updated',
        'template_footer'  => '',
        'default_subject'  => 'Your Password Has Been Updated',
    ],
];
