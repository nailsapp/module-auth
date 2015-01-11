<?php

/**
 * This class provides some common Auth controller functionality
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Auth_Controller extends NAILS_Controller
{
	public function __construct()
	{
		parent::__construct();

		// --------------------------------------------------------------------------

		//	Load config
		$this->config->load('auth/auth');

		// --------------------------------------------------------------------------

		//	Load language file
		$this->lang->load('auth/auth');

		// --------------------------------------------------------------------------

		//	Load model
		$this->load->model('auth/auth_model');
	}
}
