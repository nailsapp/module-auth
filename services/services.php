<?php

return [
    'services'  => [
        'Session'      => function (): \Nails\Auth\Service\Session {
            if (class_exists('\App\Auth\Service\Session')) {
                return new \App\Auth\Service\Session();
            } else {
                return new \Nails\Auth\Service\Session();
            }
        },
        'SocialSignOn' => function (): \Nails\Auth\Service\SocialSignOn {
            if (class_exists('\App\Auth\Service\SocialSignOn')) {
                return new \App\Auth\Service\SocialSignOn();
            } else {
                return new \Nails\Auth\Service\SocialSignOn();
            }
        },
        'UserEvent'    => function (): \Nails\Auth\Service\User\Event {
            if (class_exists('\App\Auth\Service\User\Event')) {
                return new \App\Auth\Service\User\Event();
            } else {
                return new \Nails\Auth\Service\User\Event();
            }
        },
        'UserMeta'     => function (): \Nails\Auth\Service\User\Meta {
            if (class_exists('\App\Auth\Service\User\Meta')) {
                return new \App\Auth\Service\User\Meta();
            } else {
                return new \Nails\Auth\Service\User\Meta();
            }
        },
    ],
    'models'    => [
        'Auth'            => function (): \Nails\Auth\Model\Auth {
            if (class_exists('\App\Auth\Model\Auth')) {
                return new \App\Auth\Model\Auth();
            } else {
                return new \Nails\Auth\Model\Auth();
            }
        },
        'User'            => function (): \Nails\Auth\Model\User {
            if (class_exists('\App\Auth\Model\User')) {
                return new \App\Auth\Model\User();
            } else {
                return new \Nails\Auth\Model\User();
            }
        },
        'UserAccessToken' => function (): \Nails\Auth\Model\User\AccessToken {
            if (class_exists('\App\Auth\Model\User\AccessToken')) {
                return new \App\Auth\Model\User\AccessToken();
            } else {
                return new \Nails\Auth\Model\User\AccessToken();
            }
        },
        'UserEvent'       => function (): \Nails\Auth\Model\User\Event {
            if (class_exists('\App\Auth\Model\User\Event')) {
                return new \App\Auth\Model\User\Event();
            } else {
                return new \Nails\Auth\Model\User\Event();
            }
        },
        'UserGroup'       => function (): \Nails\Auth\Model\User\Group {
            if (class_exists('\App\Auth\Model\User\Group')) {
                return new \App\Auth\Model\User\Group();
            } else {
                return new \Nails\Auth\Model\User\Group();
            }
        },
        'UserPassword'    => function (): \Nails\Auth\Model\User\Password {
            if (class_exists('\App\Auth\Model\User\Password')) {
                return new \App\Auth\Model\User\Password();
            } else {
                return new \Nails\Auth\Model\User\Password();
            }
        },
    ],
    'resources' => [
        'User'      => function ($mObj): \Nails\Auth\Resource\User {
            if (class_exists('\App\Auth\Resource\User')) {
                return new \App\Auth\Resource\User($mObj);
            } else {
                return new \Nails\Auth\Resource\User($mObj);
            }
        },
        'UserEvent' => function ($mObj): \Nails\Auth\Resource\User\Event {
            if (class_exists('\App\Auth\Resource\User\Event')) {
                return new \App\Auth\Resource\User\Event($mObj);
            } else {
                return new \Nails\Auth\Resource\User\Event($mObj);
            }
        },
    ],
];
