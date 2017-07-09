<?php

return [
    'services' => [
        'Session' => function () {
            if (class_exists('\App\Auth\Library\Session')) {
                return new \App\Auth\Library\Session();
            } else {
                return new \Nails\Auth\Library\Session();
            }
        },
        'SocialSignOn' => function () {
            if (class_exists('\App\Auth\Library\SocialSignOn')) {
                return new \App\Auth\Library\SocialSignOn();
            } else {
                return new \Nails\Auth\Library\SocialSignOn();
            }
        },
    ],
    'models'   => [
        'Auth' => function () {
            if (class_exists('\App\Auth\Model\Auth')) {
                return new \App\Auth\Model\Auth();
            } else {
                return new \Nails\Auth\Model\Auth();
            }
        },
        'User' => function () {
            if (class_exists('\App\Auth\Model\User')) {
                return new \App\Auth\Model\User();
            } else {
                return new \Nails\Auth\Model\User();
            }
        },
        'UserMeta' => function () {
            if (class_exists('\App\Auth\Model\User\Meta')) {
                return new \App\Auth\Model\User\Meta();
            } else {
                return new \Nails\Auth\Model\User\Meta();
            }
        },
        'UserAccessToken' => function () {
            if (class_exists('\App\Auth\Model\User\AccessToken')) {
                return new \App\Auth\Model\User\AccessToken();
            } else {
                return new \Nails\Auth\Model\User\AccessToken();
            }
        },
        'UserGroup' => function () {
            if (class_exists('\App\Auth\Model\User\Group')) {
                return new \App\Auth\Model\User\Group();
            } else {
                return new \Nails\Auth\Model\User\Group();
            }
        },
        'UserPassword' => function () {
            if (class_exists('\App\Auth\Model\User\Password')) {
                return new \App\Auth\Model\User\Password();
            } else {
                return new \Nails\Auth\Model\User\Password();
            }
        },
    ],
];
