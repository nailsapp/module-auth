<?php

/**
 * Auth config (social signon)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

/**
 * Define how exceptions thrown at init stage are handled
 * This can be either "error", or "reinit"
 */

$config['auth_social_signon_init_fail_behaviour'] = 'error';

/**
 * Define which providers to use (as supported by HybridAuth)
 * http://hybridauth.sourceforge.net/userguide.html
 */

$config['auth_social_signon_providers'] = array();
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'facebook',
    'class'     => 'Facebook',
    'label'     => 'Facebook',
    'fields'    => array(
        'keys'  => array(
            'id'        => array('label' => 'App ID', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        ),
        'scope' => array('label' => 'Scope', 'required' => false),
        'page'  => array('label' => 'Page', 'required' => false)
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'twitter',
    'class'     => 'Twitter',
    'label'     => 'Twitter',
    'fields'    => array(
        'keys'  => array(
            'key'       => array('label' => 'Key', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'linkedin',
    'class'     => 'LinkedIn',
    'label'     => 'LinkedIn',
    'fields'    => array(
        'keys'  => array(
            'key'       => array('label' => 'Key', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'foursquare',
    'class'     => 'Foursquare',
    'label'     => 'FourSquare',
    'fields'    => array(
        'keys'  => array(
            'id'        => array('label' => 'App ID', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'google',
    'class'     => 'Google',
    'label'     => 'Google',
    'fields'    => array(
        'keys'  => array(
            'id'        => array('label' => 'App ID', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'instagram',
    'class'     => 'Instagram',
    'label'     => 'Instagram',
    'fields'    => array(
        'keys'  => array(
            'id'        => array('label' => 'App ID', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    ),
    'wrapper'   => array(
        'class' => 'Hybrid_Providers_Instagram',
        'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-instagram/Providers/Instagram.php'
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'github',
    'class'     => 'GitHub',
    'label'     => 'Github',
    'fields'    => array(
        'keys'  => array(
            'id'        => array('label' => 'App ID', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    ),
    'wrapper'   => array(
        'class' => 'Hybrid_Providers_GitHub',
        'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-github/Providers/GitHub.php'
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => '500px',
    'class'     => 'px500',
    'label'     => '500px',
    'fields'    => array(
        'keys'  => array(
            'key'       => array('label' => 'Key', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    ),
    'wrapper'   => array(
        'class' => 'Hybrid_Providers_px500',
        'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-500px/Providers/px500.php'
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'tumblr',
    'class'     => 'Tumblr',
    'label'     => 'Tumblr',
    'fields'    => array(
        'keys'  => array(
            'key'       => array('label' => 'Key', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    ),
    'wrapper'   => array(
        'class' => 'Hybrid_Providers_Tumblr',
        'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-tumblr/Providers/Tumblr.php'
    )
);
$config['auth_social_signon_providers'][] = array(
    'slug'      => 'vimeo',
    'class'     => 'Vimeo',
    'label'     => 'Vimeo',
    'fields'    => array(
        'keys'  => array(
            'key'       => array('label' => 'Key', 'required' => true),
            'secret'    => array('label' => 'Secret', 'required' => true, 'encrypted' => true)
        )
    ),
    'wrapper'   => array(
        'class' => 'Hybrid_Providers_Vimeo',
        'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-vimeo/Providers/Vimeo.php'
    )
);
