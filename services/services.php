<?php

return array(
    'models' => array(
        'Auth' => function () {
            return new \Nails\Auth\Model\Auth();
        },
        'User' => function () {
            return new \Nails\Auth\Model\User();
        },
        'UserAccessToken' => function () {
            return new \Nails\Auth\Model\User\AccessToken();
        },
        'UserGroup' => function () {
            return new \Nails\Auth\Model\User\Group();
        },
        'UserPassword' => function () {
            return new \Nails\Auth\Model\User\Password();
        }
    )
);
