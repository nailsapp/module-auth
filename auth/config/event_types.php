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

$config['event_types'] = array();

$config['event_types'][0]					= new stdClass();
$config['event_types'][0]->slug				= 'did_log_in';
$config['event_types'][0]->label			= '';
$config['event_types'][0]->description		= '';
$config['event_types'][0]->hooks			= array();

$config['event_types'][1]					= new stdClass();
$config['event_types'][1]->slug				= 'did_log_out';
$config['event_types'][1]->label			= '';
$config['event_types'][1]->description		= '';
$config['event_types'][1]->hooks			= array();

$config['event_types'][2]					= new stdClass();
$config['event_types'][2]->slug				= 'did_register';
$config['event_types'][2]->label			= '';
$config['event_types'][2]->description		= '';
$config['event_types'][2]->hooks			= array();

$config['event_types'][3]					= new stdClass();
$config['event_types'][3]->slug				= 'did_link_provider';
$config['event_types'][3]->label			= '';
$config['event_types'][3]->description		= '';
$config['event_types'][3]->hooks			= array();
