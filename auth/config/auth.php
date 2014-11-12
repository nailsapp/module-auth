<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Auth Variables
| -------------------------------------------------------------------------
|
| Control aspects of auth at the app level with this config file.
|
| Full details of configurable options are available at
| TODO: Link to docs
|
*/

//	Disable errors when submitting the forgotten password form
$config['auth_forgotten_pass_always_succeed']	= FALSE;

//	Toggle the "remember me" functionality
$config['auth_enable_remember_me']				= TRUE;

//	Toggle logins via hashes functionality
$config['auth_enable_hashed_login']				= TRUE;

//	On login show the last seen time as a human friendly string
$config['auth_show_nicetime_on_login']			= TRUE;

//	On login show the last known IP of the user
$config['auth_show_last_ip_on_login']			= FALSE;

//	Toggle two factor security
$config['auth_two_factor_enable']				= FALSE;

//	If enabled, define how many system questions a user must have
$config['auth_two_factor_num_questions']		= 1;

//	If enabled, define how many custom questons a user must have
$config['auth_two_factor_num_custom_question']	= 0;

//	Define system questions for two factor auth
$config['auth_two_factor_questions']			= array();
$config['auth_two_factor_questions'][]			= 'What was your childhood nickname? ';
$config['auth_two_factor_questions'][]			= 'In what city did you meet your spouse/significant other?';
$config['auth_two_factor_questions'][]			= 'What is the name of your favorite childhood friend? ';
$config['auth_two_factor_questions'][]			= 'What is the middle name of your oldest child?';
$config['auth_two_factor_questions'][]			= 'What is your oldest sibling\'s middle name?';
$config['auth_two_factor_questions'][]			= 'What was your childhood phone number including area code?';
$config['auth_two_factor_questions'][]			= 'What is your oldest cousin\'s first and last name?';
$config['auth_two_factor_questions'][]			= 'What was the name of your first stuffed animal?';
$config['auth_two_factor_questions'][]			= 'In what city or town did your mother and father meet? ';
$config['auth_two_factor_questions'][]			= 'Where were you when you had your first kiss? ';
$config['auth_two_factor_questions'][]			= 'What is the first name of the boy or girl that you first kissed?';
$config['auth_two_factor_questions'][]			= 'In what city does your nearest sibling live? ';
$config['auth_two_factor_questions'][]			= 'What is your oldest sibling\'s birthday month and year? (e.g., January 1900) ';
$config['auth_two_factor_questions'][]			= 'What is your oldest brother\'s birthday month and year? (e.g., January 1900) ';
$config['auth_two_factor_questions'][]			= 'What is your oldest sister\'s birthday month and year? (e.g., January 1900) ';
$config['auth_two_factor_questions'][]			= 'What is your maternal grandmother\'s maiden name?';
$config['auth_two_factor_questions'][]			= 'In what city or town was your first job?';
$config['auth_two_factor_questions'][]			= 'What is the name of the place your wedding reception was held?';
$config['auth_two_factor_questions'][]			= 'What is the name of a college or university you applied to but didn\'t attend?';
$config['auth_two_factor_questions'][]			= 'Where were you when you first heard about 9/11?';

//	Define password strength rules
$config['auth_password_rules']					= array();

//	Minimum password length
$config['auth_password_rules']['min_length']	= 6;

//	Maximum password length, 0 means unlimited length
$config['auth_password_rules']['max_length']	= 0;

/**
 * Define sets of characters which a password must contain
 * symbol, lower_alpha, upper_alpha and number are special
 * strings and will render the charset for you. Any other
 * string will be treated as a charset itself.
 */

$config['auth_password_rules']['contains']		= array();
$config['auth_password_rules']['contains'][]	= 'symbol';
$config['auth_password_rules']['contains'][]	= 'lower_alpha';
$config['auth_password_rules']['contains'][]	= 'upper_alpha';
$config['auth_password_rules']['contains'][]	= 'number';

//	Define strings which should not be used as a password
$config['auth_password_rules']['is_not']		= array();
$config['auth_password_rules']['is_not'][]		= 'password';
$config['auth_password_rules']['is_not'][]		= '123456789';

// --------------------------------------------------------------------------

//	Social Signon

/**
 * Define how exceptions thrown at init stage are handled
 * This can be either "error", or "reinit"
 */

$config['auth_social_signon_init_fail_behaviour'] = 'error';

/**
 * Define which providers to use (as supported by HybridAuth)
 * http://hybridauth.sourceforge.net/userguide.html
 */

$config['auth_social_signon_providers']		= array();
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'facebook',
	'class'		=> 'Facebook',
	'label'		=> 'Facebook',
	'fields'	=> array(
		'keys'	=> array(
			'id'		=> array( 'label' => 'App ID', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		),
		'scope'	=> array( 'label' => 'Scope', 'required' => FALSE ),
		'page'	=> array( 'label' => 'Page', 'required' => FALSE )
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'twitter',
	'class'		=> 'Twitter',
	'label'		=> 'Twitter',
	'fields'	=> array(
		'keys'	=> array(
			'key'		=> array( 'label' => 'Key', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'linkedin',
	'class'		=> 'LinkedIn',
	'label'		=> 'LinkedIn',
	'fields'	=> array(
		'keys'	=> array(
			'key'		=> array( 'label' => 'Key', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'foursquare',
	'class'		=> 'Foursquare',
	'label'		=> 'FourSquare',
	'fields'	=> array(
		'keys'	=> array(
			'id'		=> array( 'label' => 'App ID', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'google',
	'class'		=> 'Google',
	'label'		=> 'Google',
	'fields'	=> array(
		'keys'	=> array(
			'id'		=> array( 'label' => 'App ID', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'instagram',
	'class'		=> 'Instagram',
	'label'		=> 'Instagram',
	'fields'	=> array(
		'keys'	=> array(
			'id'		=> array( 'label' => 'App ID', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	),
	'wrapper'	=> array(
		'class'	=> 'Hybrid_Providers_Instagram',
		'path'	=> FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-instagram/Providers/Instagram.php'
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'github',
	'class'		=> 'GitHub',
	'label'		=> 'Github',
	'fields'	=> array(
		'keys'	=> array(
			'id'		=> array( 'label' => 'App ID', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	),
	'wrapper'	=> array(
		'class'	=> 'Hybrid_Providers_GitHub',
		'path'	=> FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-github/Providers/GitHub.php'
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> '500px',
	'class'		=> 'px500',
	'label'		=> '500px',
	'fields'	=> array(
		'keys'	=> array(
			'key'		=> array( 'label' => 'Key', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	),
	'wrapper'	=> array(
		'class'	=> 'Hybrid_Providers_px500',
		'path'	=> FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-500px/Providers/px500.php'
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'tumblr',
	'class'		=> 'Tumblr',
	'label'		=> 'Tumblr',
	'fields'	=> array(
		'keys'	=> array(
			'key'		=> array( 'label' => 'Key', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	),
	'wrapper'	=> array(
		'class'	=> 'Hybrid_Providers_Tumblr',
		'path'	=> FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-tumblr/Providers/Tumblr.php'
	)
);
$config['auth_social_signon_providers'][]	= array(
	'slug'		=> 'vimeo',
	'class'		=> 'Vimeo',
	'label'		=> 'Vimeo',
	'fields'	=> array(
		'keys'	=> array(
			'key'		=> array( 'label' => 'Key', 'required' => TRUE ),
			'secret'	=> array( 'label' => 'Secret', 'required' => TRUE, 'encrypted' => TRUE )
		)
	),
	'wrapper'	=> array(
		'class'	=> 'Hybrid_Providers_Vimeo',
		'path'	=> FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-vimeo/Providers/Vimeo.php'
	)
);

