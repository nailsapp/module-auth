<?php

/**
 * This config file defines event types for this module.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

$config['event_types'] = [
    (object) [
        'slug'        => 'did_log_in',
        'label'       => '',
        'description' => '',
        'hooks'       => [],
    ],
    (object) [
        'slug'        => 'did_log_out',
        'label'       => '',
        'description' => '',
        'hooks'       => [],
    ],
    (object) [
        'slug'        => 'did_register',
        'label'       => '',
        'description' => '',
        'hooks'       => [],
    ],
    (object) [
        'slug'        => 'did_link_provider',
        'label'       => '',
        'description' => '',
        'hooks'       => [],
    ],
];
