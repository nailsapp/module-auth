<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Social_signon
{
	protected $_ci;
	protected $_providers;
	protected $_hybrid;

	// --------------------------------------------------------------------------

	public function __construct()
	{
		$this->_ci			=& get_instance();
		$this->_providers	= array( 'all' => array(), 'enabled' => array(), 'disabled' => array() );

		//	Set up Providers
		$this->_ci->config->load( 'auth/auth' );
		$_config = $this->_ci->config->item( 'auth_social_signon_providers' );

		if ( is_array( $_config ) && ! empty( $_config ) ) :

			foreach( $_config AS $provider ) :

				$this->_providers['all'][$provider['slug']] = $provider;

				if ( app_setting( 'auth_social_signon_' . $provider['slug'] . '_enabled' )  ) :

					$this->_providers['enabled'][$provider['slug']] = $provider;

				else :

					$this->_providers['disabled'][$provider['slug']] = $provider;

				endif;

			endforeach;

		else :

			show_fatal_error( 'No providers are configured', 'No providers for HybridAuth have been specified or the configuration array is empty.' );

		endif;

		// --------------------------------------------------------------------------

		//	Set up Hybrid Auth
		$_config				= array();
		$_config['base_url']	= site_url( 'vendor/hybridauth/hybridauth/hybridauth' ). '/';	//	Trailing slash fixes Facebook
		$_config['providers']	= array();
		$_config['debug_mode']	= ENVIRONMENT !== 'production';
		$_config['debug_file']	= DEPLOY_LOG_DIR .  'log-hybrid-auth-' . date( 'Y-m-d' ) . '.php';

		foreach ( $this->_providers['enabled'] AS $provider ) :

			$_temp				= array();
			$_temp				= array();
			$_temp['enabled']	= TRUE;

			if ( $provider['fields'] ) :

				$_temp['keys'] = array();

				foreach ( $provider['fields'] AS $key => $label ) :

					$_temp['keys'][$key] = app_setting( 'auth_social_signon_' . $provider['slug'] . '_' . $key );

				endforeach;

				if ( ! empty( $provider['wrapper'] ) ) :

					$_temp['wrapper'] = $provider['wrapper'];

				endif;

			endif;

			$_config['providers'][$provider['slug']] = $_temp;

		endforeach;
//dumpanddie($_config);
		$this->_hybrid = new Hybrid_Auth( $_config );
	}


	// --------------------------------------------------------------------------


	public function is_enabled()
	{
		return app_setting( 'auth_social_signon_enabled' ) && $this->get_providers( 'ENABLED' );
	}


	// --------------------------------------------------------------------------


	public function get_providers( $status = NULL )
	{
		if ( $status == 'ENABLED' ) :

			return $this->_providers['enabled'];

		elseif( $status == 'DISABLED' ) :

			return $this->_providers['disabled'];

		else :

			return $this->_providers['all'];

		endif;
	}


	// --------------------------------------------------------------------------


	public function is_valid_provider( $provider )
	{
		return ! empty( $this->_providers['enabled'][$provider]['slug'] );
	}


	// --------------------------------------------------------------------------


	public function authenticate( $provider, $params = NULL )
	{
		return $this->_hybrid->authenticate( $provider, $params );
	}


	// --------------------------------------------------------------------------


	public function logout()
	{
		$this->_hybrid->logoutAllProviders();
	}


	// --------------------------------------------------------------------------


	public function get_session_data()
	{
		return $this->_hybrid->getSessionData();
	}


	// --------------------------------------------------------------------------


	public function set_session_data( $data = NULL )
	{
		return $this->_hybrid->setSessionData( $data );
	}
}

/* End of file Social_signon.php */
/* Location: ./module-auth/auth/libraries/Social_signon.php */