<?php

/**
 * This class provides social signon capability and an abstraction to social media APIs
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

class Social_signon
{
    //  Class traits
    use NAILS_COMMON_TRAIT_ERROR_HANDLING;
    use NAILS_COMMON_TRAIT_CACHING;

    protected $_ci;
    protected $db;
    protected $_providers;
    protected $_hybrid;

    // --------------------------------------------------------------------------

    /**
     * Cosntructs the class
     */
    public function __construct()
    {
        $this->_ci        =& get_instance();
        $this->db         =& $this->_ci->db;
        $this->_providers = array('all' => array(), 'enabled' => array(), 'disabled' => array());

        //  Set up Providers
        $this->_ci->config->load('auth/auth');
        $_config = $this->_ci->config->item('auth_social_signon_providers');

        if (is_array($_config) && !empty($_config)) {

            foreach ($_config as $provider) {

                $this->_providers['all'][strtolower($provider['slug'])] = $provider;

                if (app_setting('auth_social_signon_' . $provider['slug'] . '_enabled', 'auth') ) {

                    $this->_providers['enabled'][strtolower($provider['slug'])] = $provider;

                } else {

                    $this->_providers['disabled'][strtolower($provider['slug'])] = $provider;
                }
            }

        } else {

            showFatalError('No providers are configured', 'No providers for HybridAuth have been specified or the configuration array is empty.');
        }

        // --------------------------------------------------------------------------

        //  Set up Hybrid Auth
        $_config               = array();
        $_config['base_url']   = site_url('vendor/hybridauth/hybridauth/hybridauth/index.php');
        $_config['providers']  = array();
        $_config['debug_mode'] = strtoupper(ENVIRONMENT) !== 'PRODUCTION';
        $_config['debug_file'] = DEPLOY_LOG_DIR .  'log-hybrid-auth-' . date('Y-m-d') . '.php';

        foreach ($this->_providers['enabled'] as $provider) {

            $_temp              = array();
            $_temp['enabled']   = true;

            if ($provider['fields']) {

                foreach ($provider['fields'] as $key => $label) {

                    if (is_array($label) && !isset($label['label']) ) {

                        $_temp[$key] = array();

                        foreach ($label as $key1 => $label1) {

                            $_temp[$key][$key1] = app_setting('auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1, 'auth');
                        }

                    } else {

                        $_temp[$key] = app_setting('auth_social_signon_' . $provider['slug'] . '_' . $key, 'auth');
                    }
                }

                if (!empty($provider['wrapper'])) {

                    $_temp['wrapper'] = $provider['wrapper'];
                }
            }

            $_config['providers'][$provider['class']] = $_temp;
        }

        try {

            $this->_hybrid = new Hybrid_Auth($_config);

        } catch (Exception $e) {

            /**
             * An exception occurred during instantiation, this is probably a result
             * of the user denying the authentication request. If we reinit the Hybrid_Auth
             * things work again, but it's as if nothing ever happened and the user may be
             * redirected back to the authentication screen (which is annoying). The
             * alternative is to bail out with an error; this informs the user that something
             * *did* happened but results in an unfriendly error screen and potential drop off.
             */

            switch ($this->_ci->config->item('auth_social_signon_init_fail_behaviour')) {

                case 'reinit':

                    $this->_hybrid = new Hybrid_Auth($_config);
                    break;

                case 'error':
                default:

                    _NAILS_ERROR($e->getMessage());
                    break;
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether social sign on is enabled or not
     * @return boolean
     */
    public function is_enabled()
    {
        return app_setting('auth_social_signon_enabled', 'auth') && $this->get_providers('ENABLED');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a list of providers, optionally filtered by availability
     * @param  string $status The filter to apply
     * @return array
     */
    public function get_providers($status = null)
    {
        if ($status == 'ENABLED') {

            return $this->_providers['enabled'];

        } elseif($status == 'DISABLED') {

            return $this->_providers['disabled'];

        } else {

            return $this->_providers['all'];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the details of a particular provider
     * @param  string $provider The provider to return
     * @return mixed            Array on success, false on failure
     */
    public function get_provider($provider)
    {
        return isset($this->_providers['all'][strtolower($provider)]) ? $this->_providers['all'][strtolower($provider)] : false;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the correct casing for a provider
     * @param  string $provider The provider to return
     * @return mixed            String on success, null on failure
     */
    protected function _get_provider_class($provider)
    {
        $providers = $this->get_providers();
        return isset($providers[strtolower($provider)]['class']) ? $providers[strtolower($provider)]['class'] : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a provider is valid and enabled
     * @param  string  $provider The provider to check
     * @return boolean
     */
    public function is_valid_provider($provider)
    {
        return !empty($this->_providers['enabled'][strtolower($provider)]);
    }

    // --------------------------------------------------------------------------

    /**
     * Authenticates a user using Hybrid Auth's authenticate method
     * @param  string $provider The provider to authenticate against
     * @param  mixed $params    Additional parameters to pass to the Provider
     * @return Hybrid_Provider_Adapter
     */
    public function authenticate($provider, $params = null)
    {
        try {

            $provider = $this->_get_provider_class($provider);
            return $this->_hybrid->authenticate($provider, $params);

        } catch(Exception $e) {
            $this->_set_error('Provider Error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the user's profile for a particular provider.
     * @param  sting $provider The name of the provider.
     * @return mixed           Hybrid_User_Profile on success, false on failure
     */
    public function get_user_profile($provider)
    {
        $adapter = $this->authenticate($provider);

        try {

            return $adapter->getUserProfile();

        } catch (Exception $e) {

            $this->_set_error('Provider Error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Logs out all provideers
     * @return void
     */
    public function logout()
    {
        $this->_hybrid->logoutAllProviders();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a local user profile via a provider and provider ID
     * @param  mixed   $provider   The provider to use either as a string, object or array
     * @param  string  $identifier The provider's user ID
     * @return mixed               stdClass on success, false on failure
     */
    public function get_user_by_provider_identifier($provider, $identifier)
    {
        if (is_string($provider)) {

            $this->db->where('provider', $provider);

        } elseif (property_exists($provider, 'slug')) {

            $this->db->where('provider', $provider->slug);

        } elseif (array_key_exists('slug', $provider)) {

            $this->db->where('provider', $provider['slug']);

        } else {

            $this->_set_error('Could not determine provider.');
            return false;
        }

        $this->db->select('user_id');
        $this->db->where('identifier', $identifier);

        $_user = $this->db->get(NAILS_DB_PREFIX . 'user_social')->row();

        if (empty($_user)) {

            return false;
        }

        return $this->_ci->user_model->get_by_id($_user->user_id, $extended);
    }

    // --------------------------------------------------------------------------

    /**
     * Saves the social session data to the user's account
     * @param  mixed  $user_id  The User's ID (if null, then the active user ID is used)
     * @param  string $provider The providers to save
     * @return boolean
     */
    public function save_session($user_id = null, $provider = array())
    {
        if (empty($user_id)) {

            $user_id = activeUser('id');
        }

        if (empty($user_id)) {

            $this->_set_error('Must specify which user ID\'s session to save.');
            return false;
        }

        $_user = $this->_ci->user_model->get_by_id($user_id);

        if (!$_user) {

            $this->_set_error('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        if (!is_array($provider)) {

            $_provider = (array) $provider;
            $_provider = array_unique($_provider);
            $_provider = array_filter($_provider);

        } else {

            $_provider = $provider;
        }

        // --------------------------------------------------------------------------

        $_session     = $this->_hybrid->getSessionData();
        $_session     = unserialize($_session);
        $_save        = array();
        $_identifiers = array();

        //  Now we sort the session into individual providers
        foreach ($_session as $key => $value) {

            //  Get the bits
            list($hauth, $provider) = explode('.', $key, 3);

            if (!isset($_save[$provider])) {

                $_save[$provider] = array();
            }

            $_save[$provider][$key] = $value;
        }

        // --------------------------------------------------------------------------

        //  Prune any which aren't worth saving
        foreach ($_save as $provider => $values) {

            //  Are we only interested in a particular provider?
            if (!empty($_provider)) {

                if (array_search($provider, $_provider) === false) {

                    unset($_save[$provider]);
                    continue;
                }
            }

            //  Conencted?
            if (!$this->is_connected_with($provider)) {

                unset($_save[$provider]);
                continue;
            }

            //  Got an identifier?
            try {

                $_adapter = $this->_hybrid->getAdapter($provider);
                $_profile = $_adapter->getUserProfile();

                if (!empty($_profile->identifier)) {

                    $_identifiers[$provider] = $_profile->identifier;
                }

            } catch(Exception $e) {

                unset($_save[$provider]);
                continue;
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Get the user's existing data, so we know whether we're inserting or
         * updating their data
         */

        $this->db->where('user_id', $user_id);
        $_existing  = $this->db->get(NAILS_DB_PREFIX . 'user_social')->result();
        $_exists    = array();

        foreach ($_existing as $existing) {

            $_exists[$existing->provider] = $existing->id;
        }

        // --------------------------------------------------------------------------

        //  Save data
        $this->db->trans_begin();

        foreach ($_save as $provider => $keys) {

            if (isset($_exists[$provider])) {

                //  Update
                $_data                  = array();
                $_data['identifier']    = $_identifiers[$provider];
                $_data['session_data']  = serialize($keys);
                $_data['modified']      = date('Y-m-d H:i{s');

                $this->db->set($_data);
                $this->db->where('id',  $_exists[$provider]);
                $this->db->update(NAILS_DB_PREFIX . 'user_social');

            } else {

                //  Insert
                $_data                  = array();
                $_data['user_id']       = (int) $user_id;
                $_data['provider']      = $provider;
                $_data['identifier']    = $_identifiers[$provider];
                $_data['session_data']  = serialize($keys);
                $_data['created']       = date('Y-m-d H:i:s');
                $_data['modified']      = $_data['created'];

                $this->db->set($_data);
                $this->db->insert(NAILS_DB_PREFIX . 'user_social');
            }
        }

        // --------------------------------------------------------------------------

        if ($this->db->trans_status() === false) {

            $this->db->trans_rollback();
            return false;

        } else {

            $this->db->trans_commit();
            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restores a user's social session
     * @param  mixed $user_id The User's ID (if null, then the active user ID is used)
     * @return boolean
     */
    public function restore_session($user_id = null)
    {
        if (empty($user_id)) {

            $user_id = activeUser('id');
        }

        if (empty($user_id)) {

            $this->_set_error('Must specify which user ID\'s session to restore.');
            return false;
        }

        $_user = $this->_ci->user_model->get_by_id($user_id);

        if (!$_user) {

            $this->_set_error('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Clean slate
        $this->_hybrid->logoutAllProviders();

        // --------------------------------------------------------------------------

        $this->db->where('user_id', $_user->id);
        $_sessions  = $this->db->get(NAILS_DB_PREFIX . 'user_social')->result();
        $_restore   = array();

        foreach ($_sessions as $session) {

            $session->session_data = unserialize($session->session_data);
            $_restore = array_merge($_restore, $session->session_data);
        }

        return $this->_hybrid->restoreSessionData(serialize($_restore));
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is connected with $provider
     * @param  string  $provider the provider to test for
     * @return boolean
     */
    public function is_connected_with($provider)
    {
        $provider = $this->_get_provider_class($provider);
        return $this->_hybrid->isConnectedWith($provider);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of connected providers (for the active user)
     * @return array
     */
    public function get_connected_providers()
    {
        return $this->_hybrid->getConnectedProviders();
    }

    // --------------------------------------------------------------------------

    /**
     * Abstraction to a provider's API
     * @param  string $provider The provider whose API you wish to call
     * @param  string $call     The API call
     * @return mixed
     */
    public function api($provider, $call = '')
    {
        if (!  $this->is_connected_with($provider)) {

            $this->_set_error('Not connected with provider "' . $provider . '"');
            return false;
        }

        try {

            $_provider = $this->_get_provider_class($provider);
            $_provider = $this->_hybrid->getAdapter($_provider);
            return $_provider->api()->api($call);

        } catch(Exception $e) {

            $this->_set_error('Provider Error: ' . $e->getMessage());
            return false;
        }
    }
}
