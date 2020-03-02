<?php

use Nails\Auth\Model;
use Nails\Auth\Service;
use Nails\Auth\Resource;

return [
    'services'  => [
        'Authentication' => function (): Service\Authentication {
            if (class_exists('\App\Auth\Service\Authentication')) {
                return new \App\Auth\Service\Authentication();
            } else {
                return new Service\Authentication();
            }
        },
        'Session'        => function (): Service\Session {
            if (\Nails\Environment::is(\Nails\Environment::ENV_DEV)) {
                trigger_error(
                    'Loading the Sesison via nails/module-auth is deprecated. Load via nails/common instead.',
                    E_USER_DEPRECATED
                );
            }
            if (class_exists('\App\Auth\Service\Session')) {
                return new \App\Auth\Service\Session();
            } else {
                return new Service\Session();
            }
        },
        'SocialSignOn'   => function (): Service\SocialSignOn {
            if (class_exists('\App\Auth\Service\SocialSignOn')) {
                return new \App\Auth\Service\SocialSignOn();
            } else {
                return new Service\SocialSignOn();
            }
        },
        'UserEvent'      => function (): Service\User\Event {
            if (class_exists('\App\Auth\Service\User\Event')) {
                return new \App\Auth\Service\User\Event();
            } else {
                return new Service\User\Event();
            }
        },
        'UserMeta'       => function (): Service\User\Meta {
            if (class_exists('\App\Auth\Service\User\Meta')) {
                return new \App\Auth\Service\User\Meta();
            } else {
                return new Service\User\Meta();
            }
        },
    ],
    'models'    => [
        'User'            => function (): Model\User {
            if (class_exists('\App\Auth\Model\User')) {
                return new \App\Auth\Model\User();
            } else {
                return new Model\User();
            }
        },
        'UserAccessToken' => function (): Model\User\AccessToken {
            if (class_exists('\App\Auth\Model\User\AccessToken')) {
                return new \App\Auth\Model\User\AccessToken();
            } else {
                return new Model\User\AccessToken();
            }
        },
        'UserEvent'       => function (): Model\User\Event {
            if (class_exists('\App\Auth\Model\User\Event')) {
                return new \App\Auth\Model\User\Event();
            } else {
                return new Model\User\Event();
            }
        },
        'UserGroup'       => function (): Model\User\Group {
            if (class_exists('\App\Auth\Model\User\Group')) {
                return new \App\Auth\Model\User\Group();
            } else {
                return new Model\User\Group();
            }
        },
        'UserPassword'    => function (): Model\User\Password {
            if (class_exists('\App\Auth\Model\User\Password')) {
                return new \App\Auth\Model\User\Password();
            } else {
                return new Model\User\Password();
            }
        },
    ],
    'resources' => [
        'User'      => function ($mObj): Resource\User {
            if (class_exists('\App\Auth\Resource\User')) {
                return new \App\Auth\Resource\User($mObj);
            } else {
                return new Resource\User($mObj);
            }
        },
        'UserEvent' => function ($mObj): Resource\User\Event {
            if (class_exists('\App\Auth\Resource\User\Event')) {
                return new \App\Auth\Resource\User\Event($mObj);
            } else {
                return new Resource\User\Event($mObj);
            }
        },
    ],
];
