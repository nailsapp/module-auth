<?php

namespace Nails\Auth\Resource;

use Nails\Common\Resource\Date;
use Nails\Common\Resource\DateTime;
use Nails\Common\Resource\Entity;

/**
 * Class User
 *
 * @package Nails\Auth\Resource
 */
class User extends Entity
{
    /** @var string */
    public $id_md5;

    /** @var int */
    public $group_id;

    /** @var string */
    public $ip_address;

    /** @var string */
    public $last_ip;

    /** @var string */
    public $username;

    /** @var string */
    public $password;

    /** @var string */
    public $password_md5;

    /** @var string */
    public $password_engine;

    /** @var DateTime */
    public $password_changed;

    /** @var string */
    public $salt;

    /** @var string */
    public $forgotten_password_code;

    /** @var string */
    public $remember_code;

    /** @var DateTime */
    public $last_login;

    /** @var DateTime */
    public $last_seen;

    /** @var bool */
    public $is_suspended;

    /** @var bool */
    public $temp_pw;

    /** @var string */
    public $failed_login_count;

    /** @var DateTime */
    public $failed_login_expires;

    /** @var DateTime */
    public $last_update;

    /** @var string */
    public $user_acl;

    /** @var int */
    public $login_count;

    /** @var string */
    public $referral;

    /** @var int */
    public $referred_by;

    /** @var string */
    public $salutation;

    /** @var string */
    public $first_name;

    /** @var string */
    public $last_name;

    /** @var string */
    public $gender;

    /** @var Date */
    public $dob;

    /** @var int */
    public $profile_img;

    /** @var string */
    public $timezone;

    /** @var string */
    public $datetime_format_date;

    /** @var string */
    public $datetime_format_time;

    /** @var string */
    public $language;

    /** @var string */
    public $email;

    /** @var string */
    public $email_verification_code;

    /** @var bool */
    public $email_is_verified;

    /** @var DateTime */
    public $email_is_verified_on;

    /** @var string */
    public $group_slug;

    /** @var string */
    public $group_name;

    /** @var string */
    public $group_homepage;

    /** @var string */
    public $group_acl;

    /** @var string */
    public $acl;
}
