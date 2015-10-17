<?php

return array(
    'models' => array(
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
        }
    )
);
