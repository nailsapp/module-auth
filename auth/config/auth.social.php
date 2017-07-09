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
$config['auth_social_signon_providers'] = [
    [
        'slug'   => 'facebook',
        'class'  => 'Facebook',
        'label'  => 'Facebook',
        'fields' => [
            'keys'  => [
                'id'     => ['label' => 'App ID', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
            'scope' => ['label' => 'Scope', 'required' => false],
            'page'  => ['label' => 'Page', 'required' => false],
        ],
    ],
    [
        'slug'   => 'twitter',
        'class'  => 'Twitter',
        'label'  => 'Twitter',
        'fields' => [
            'keys' => [
                'key'    => ['label' => 'Key', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
    ],
    [
        'slug'   => 'linkedin',
        'class'  => 'LinkedIn',
        'label'  => 'LinkedIn',
        'fields' => [
            'keys' => [
                'key'    => ['label' => 'Key', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
    ],
    [
        'slug'   => 'foursquare',
        'class'  => 'Foursquare',
        'label'  => 'FourSquare',
        'fields' => [
            'keys' => [
                'id'     => ['label' => 'App ID', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
    ],
    [
        'slug'   => 'google',
        'class'  => 'Google',
        'label'  => 'Google',
        'fields' => [
            'keys'  => [
                'id'     => ['label' => 'App ID', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
            'scope' => ['label' => 'Scope', 'required' => false],
        ],
    ],
    [
        'slug'    => 'instagram',
        'class'   => 'Instagram',
        'label'   => 'Instagram',
        'fields'  => [
            'keys' => [
                'id'     => ['label' => 'App ID', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
        'wrapper' => [
            'class' => 'Hybrid_Providers_Instagram',
            'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-instagram/Providers/Instagram.php',
        ],
    ],
    [
        'slug'    => 'github',
        'class'   => 'GitHub',
        'label'   => 'Github',
        'fields'  => [
            'keys' => [
                'id'     => ['label' => 'App ID', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
        'wrapper' => [
            'class' => 'Hybrid_Providers_GitHub',
            'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-github/Providers/GitHub.php',
        ],
    ],
    [
        'slug'    => '500px',
        'class'   => 'px500',
        'label'   => '500px',
        'fields'  => [
            'keys' => [
                'key'    => ['label' => 'Key', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
        'wrapper' => [
            'class' => 'Hybrid_Providers_px500',
            'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-500px/Providers/px500.php',
        ],
    ],
    [
        'slug'    => 'tumblr',
        'class'   => 'Tumblr',
        'label'   => 'Tumblr',
        'fields'  => [
            'keys' => [
                'key'    => ['label' => 'Key', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
        'wrapper' => [
            'class' => 'Hybrid_Providers_Tumblr',
            'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-tumblr/Providers/Tumblr.php',
        ],
    ],
    [
        'slug'    => 'vimeo',
        'class'   => 'Vimeo',
        'label'   => 'Vimeo',
        'fields'  => [
            'keys' => [
                'key'    => ['label' => 'Key', 'required' => true],
                'secret' => ['label' => 'Secret', 'required' => true, 'encrypted' => true],
            ],
        ],
        'wrapper' => [
            'class' => 'Hybrid_Providers_Vimeo',
            'path'  => FCPATH . 'vendor/hybridauth/hybridauth/additional-providers/hybridauth-vimeo/Providers/Vimeo.php',
        ],
    ],
];
