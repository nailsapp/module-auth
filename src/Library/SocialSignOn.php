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

namespace Nails\Auth\Library;

use Nails\Factory;

class SocialSignOn
{
    use \Nails\Common\Traits\ErrorHandling;
    use \Nails\Common\Traits\Caching;

    // --------------------------------------------------------------------------

    protected $oDb;
    protected $oUserModel;
    protected $aProviders;
    protected $oHybridAuth;

    // --------------------------------------------------------------------------

    /**
     * Cosntructs the class
     */
    public function __construct()
    {
        $oCi              = get_instance();
        $this->oDb        = Factory::service('Database');
        $this->oUserModel = Factory::model('User', 'nailsapp/module-auth');

        $this->aProviders = array('all' => array(), 'enabled' => array(), 'disabled' => array());

        //  Set up Providers
        $oCi->config->load('auth/auth');
        $aConfig = $oCi->config->item('auth_social_signon_providers');

        if (is_array($aConfig) && !empty($aConfig)) {

            foreach ($aConfig as $aProvider) {

                $this->aProviders['all'][strtolower($aProvider['slug'])] = $aProvider;

                if (app_setting('auth_social_signon_' . $aProvider['slug'] . '_enabled', 'auth')) {

                    $this->aProviders['enabled'][strtolower($aProvider['slug'])] = $aProvider;

                } else {

                    $this->aProviders['disabled'][strtolower($aProvider['slug'])] = $aProvider;
                }
            }

        } else {

            showFatalError(
                'No providers are configured',
                'No providers for HybridAuth have been specified or the configuration array is empty.'
            );
        }

        // --------------------------------------------------------------------------

        //  Set up Hybrid Auth
        $aConfig               = array();
        $aConfig['base_url']   = site_url('vendor/hybridauth/hybridauth/hybridauth/index.php');
        $aConfig['providers']  = array();
        $aConfig['debug_mode'] = strtoupper(ENVIRONMENT) !== 'PRODUCTION';
        $aConfig['debug_file'] = DEPLOY_LOG_DIR .  'log-hybrid-auth-' . date('Y-m-d') . '.php';

        foreach ($this->aProviders['enabled'] as $aProvider) {

            $aTemp              = array();
            $aTemp['enabled']   = true;

            if ($aProvider['fields']) {

                foreach ($aProvider['fields'] as $key => $label) {

                    if (is_array($label) && !isset($label['label'])) {

                        $aTemp[$key] = array();

                        foreach ($label as $key1 => $label1) {

                            $aTemp[$key][$key1] = app_setting(
                                'auth_social_signon_' . $aProvider['slug'] . '_' . $key . '_' . $key1,
                                'auth'
                            );
                        }

                    } else {

                        $aTemp[$key] = app_setting('auth_social_signon_' . $aProvider['slug'] . '_' . $key, 'auth');
                    }
                }

                if (!empty($aProvider['wrapper'])) {

                    $aTemp['wrapper'] = $aProvider['wrapper'];
                }
            }

            $aConfig['providers'][$aProvider['class']] = $aTemp;
        }

        try {

            $this->oHybridAuth = new \Hybrid_Auth($aConfig);

        } catch (\Exception $e) {

            /**
             * An exception occurred during instantiation, this is probably a result
             * of the user denying the authentication request. If we reinit the Hybrid_Auth
             * things work again, but it's as if nothing ever happened and the user may be
             * redirected back to the authentication screen (which is annoying). The
             * alternative is to bail out with an error; this informs the user that something
             * *did* happened but results in an unfriendly error screen and potential drop off.
             */

            switch ($oCi->config->item('auth_social_signon_init_fail_behaviour')) {

                case 'reinit':

                    $this->oHybridAuth = new \Hybrid_Auth($aConfig);
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
    public function isEnabled()
    {
        return app_setting('auth_social_signon_enabled', 'auth') && $this->getProviders('ENABLED');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a list of providers, optionally filtered by availability
     * @param  string $status The filter to apply
     * @return array
     */
    public function getProviders($status = null)
    {
        if ($status == 'ENABLED') {

            return $this->aProviders['enabled'];

        } elseif ($status == 'DISABLED') {

            return $this->aProviders['disabled'];

        } else {

            return $this->aProviders['all'];
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the details of a particular provider
     * @param  string $provider The provider to return
     * @return mixed            Array on success, false on failure
     */
    public function getProvider($provider)
    {
        if (isset($this->aProviders['all'][strtolower($provider)])) {

            return $this->aProviders['all'][strtolower($provider)];

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the correct casing for a provider
     * @param  string $provider The provider to return
     * @return mixed            String on success, null on failure
     */
    protected function getProviderClass($provider)
    {
        $providers = $this->getProviders();
        return isset($providers[strtolower($provider)]['class']) ? $providers[strtolower($provider)]['class'] : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a provider is valid and enabled
     * @param  string  $provider The provider to check
     * @return boolean
     */
    public function isValidProvider($provider)
    {
        return !empty($this->aProviders['enabled'][strtolower($provider)]);
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

            $provider = $this->getProviderClass($provider);
            return $this->oHybridAuth->authenticate($provider, $params);

        } catch (\Exception $e) {
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
    public function getUserProfile($provider)
    {
        $adapter = $this->authenticate($provider);

        try {

            return $adapter->getUserProfile();

        } catch (\Exception $e) {

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
        $this->oHybridAuth->logoutAllProviders();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a local user profile via a provider and provider ID
     * @param  string  $provider   The provider to use
     * @param  string  $identifier The provider's user ID
     * @return mixed               stdClass on success, false on failure
     */
    public function getUserByProviderId($provider, $identifier)
    {
        $this->oDb->select('user_id');
        $this->oDb->where('provider', $provider);
        $this->oDb->where('identifier', $identifier);

        $oUser = $this->oDb->get(NAILS_DB_PREFIX . 'user_social')->row();

        if (empty($oUser)) {

            return false;
        }

        return $this->oUserModel->get_by_id($oUser->user_id, $extended);
    }

    // --------------------------------------------------------------------------

    /**
     * Saves the social session data to the user's account
     * @param  mixed  $user_id  The User's ID (if null, then the active user ID is used)
     * @param  string $provider The providers to save
     * @return boolean
     */
    public function saveSession($user_id = null, $provider = array())
    {
        if (empty($user_id)) {

            $user_id = activeUser('id');
        }

        if (empty($user_id)) {

            $this->_set_error('Must specify which user ID\'s session to save.');
            return false;
        }

        $_user = $this->oUserModel->get_by_id($user_id);

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

        $_session     = $this->oHybridAuth->getSessionData();
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
            if (!$this->isConnectedWith($provider)) {

                unset($_save[$provider]);
                continue;
            }

            //  Got an identifier?
            try {

                $_adapter = $this->oHybridAuth->getAdapter($provider);
                $_profile = $_adapter->getUserProfile();

                if (!empty($_profile->identifier)) {

                    $_identifiers[$provider] = $_profile->identifier;
                }

            } catch (\Exception $e) {

                unset($_save[$provider]);
                continue;
            }
        }

        // --------------------------------------------------------------------------

        /**
         * Get the user's existing data, so we know whether we're inserting or
         * updating their data
         */

        $this->oDb->where('user_id', $user_id);
        $_existing  = $this->oDb->get(NAILS_DB_PREFIX . 'user_social')->result();
        $_exists    = array();

        foreach ($_existing as $existing) {

            $_exists[$existing->provider] = $existing->id;
        }

        // --------------------------------------------------------------------------

        //  Save data
        $this->oDb->trans_begin();

        foreach ($_save as $provider => $keys) {

            if (isset($_exists[$provider])) {

                //  Update
                $_data                  = array();
                $_data['identifier']    = $_identifiers[$provider];
                $_data['session_data']  = serialize($keys);
                $_data['modified']      = date('Y-m-d H:i{s');

                $this->oDb->set($_data);
                $this->oDb->where('id', $_exists[$provider]);
                $this->oDb->update(NAILS_DB_PREFIX . 'user_social');

            } else {

                //  Insert
                $_data                  = array();
                $_data['user_id']       = (int) $user_id;
                $_data['provider']      = $provider;
                $_data['identifier']    = $_identifiers[$provider];
                $_data['session_data']  = serialize($keys);
                $_data['created']       = date('Y-m-d H:i:s');
                $_data['modified']      = $_data['created'];

                $this->oDb->set($_data);
                $this->oDb->insert(NAILS_DB_PREFIX . 'user_social');
            }
        }

        // --------------------------------------------------------------------------

        if ($this->oDb->trans_status() === false) {

            $this->oDb->trans_rollback();
            return false;

        } else {

            $this->oDb->trans_commit();
            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Restores a user's social session
     * @param  mixed $user_id The User's ID (if null, then the active user ID is used)
     * @return boolean
     */
    public function restoreSession($user_id = null)
    {
        if (empty($user_id)) {

            $user_id = activeUser('id');
        }

        if (empty($user_id)) {

            $this->_set_error('Must specify which user ID\'s session to restore.');
            return false;
        }

        $_user = $this->oUserModel->get_by_id($user_id);

        if (!$_user) {

            $this->_set_error('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Clean slate
        $this->oHybridAuth->logoutAllProviders();

        // --------------------------------------------------------------------------

        $this->oDb->where('user_id', $_user->id);
        $_sessions  = $this->oDb->get(NAILS_DB_PREFIX . 'user_social')->result();
        $_restore   = array();

        foreach ($_sessions as $session) {

            $session->session_data = unserialize($session->session_data);
            $_restore = array_merge($_restore, $session->session_data);
        }

        return $this->oHybridAuth->restoreSessionData(serialize($_restore));
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is connected with $provider
     * @param  string  $provider the provider to test for
     * @return boolean
     */
    public function isConnectedWith($provider)
    {
        $provider = $this->getProviderClass($provider);
        return $this->oHybridAuth->isConnectedWith($provider);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of connected providers (for the active user)
     * @return array
     */
    public function getConnectedProviders()
    {
        return $this->oHybridAuth->getConnectedProviders();
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
        if (!  $this->isConnectedWith($provider)) {

            $this->_set_error('Not connected with provider "' . $provider . '"');
            return false;
        }

        try {

            $_provider = $this->getProviderClass($provider);
            $_provider = $this->oHybridAuth->getAdapter($_provider);
            return $_provider->api()->api($call);

        } catch (\Exception $e) {

            $this->_set_error('Provider Error: ' . $e->getMessage());
            return false;
        }
    }
}
