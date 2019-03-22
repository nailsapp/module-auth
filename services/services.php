<?php

return [
    'services' => [
        'Session'      => function () {
            if (class_exists('\App\Auth\Service\Session')) {
                return new \App\Auth\Service\Session();
            } else {
                return new \Nails\Auth\Service\Session();
            }
        },
        'SocialSignOn' => function () {
            if (class_exists('\App\Auth\Service\SocialSignOn')) {
                return new \App\Auth\Service\SocialSignOn();
            } else {
                return new \Nails\Auth\Service\SocialSignOn();
            }
        },
        'UserMeta'     => function () {
            if (class_exists('\App\Auth\Service\User\Meta')) {
                return new \App\Auth\Service\User\Meta();
            } else {
                return new \Nails\Auth\Service\User\Meta();
            }
        },
    ],
    'models'   => [
        'Auth'            => function () {
            if (class_exists('\App\Auth\Model\Auth')) {
                return new \App\Auth\Model\Auth();
            } else {
                return new \Nails\Auth\Model\Auth();
            }
        },
        'User'            => function () {
            if (class_exists('\App\Auth\Model\User')) {
                return new \App\Auth\Model\User();
            } else {
                return new \Nails\Auth\Model\User();
            }
        },
        'UserAccessToken' => function () {
            if (class_exists('\App\Auth\Model\User\AccessToken')) {
                return new \App\Auth\Model\User\AccessToken();
            } else {
                return new \Nails\Auth\Model\User\AccessToken();
            }
        },
        'UserGroup'       => function () {
            if (class_exists('\App\Auth\Model\User\Group')) {
                return new \App\Auth\Model\User\Group();
            } else {
                return new \Nails\Auth\Model\User\Group();
            }
        },
        'UserPassword'    => function () {
            if (class_exists('\App\Auth\Model\User\Password')) {
                return new \App\Auth\Model\User\Password();
            } else {
                return new \Nails\Auth\Model\User\Password();
            }
        },
    ],
];
