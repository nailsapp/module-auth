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

namespace Nails\Auth\Service;

use Nails\Factory;
use Nails\Environment;
use Nails\Common\Exception\NailsException;
use Nails\Common\Service\ErrorHandler;
use Nails\Common\Traits\ErrorHandling;
use Nails\Common\Traits\Caching;

class SocialSignOn
{
    use ErrorHandling;
    use Caching;

    // --------------------------------------------------------------------------

    protected $oDb;
    protected $oUserModel;
    protected $aProviders;
    protected $oHybridAuth;

    // --------------------------------------------------------------------------

    /**
     * SocialSignOn constructor.
     */
    public function __construct()
    {
        $oCi              = get_instance();
        $this->oDb        = Factory::service('Database');
        $this->oUserModel = Factory::model('User', 'nails/module-auth');

        $this->aProviders = ['all' => [], 'enabled' => [], 'disabled' => []];

        //  Set up Providers
        $oCi->config->load('auth/auth');
        $aConfig = $oCi->config->item('auth_social_signon_providers');

        if (is_array($aConfig) && !empty($aConfig)) {

            foreach ($aConfig as $aProvider) {

                $this->aProviders['all'][strtolower($aProvider['slug'])] = $aProvider;

                if (appSetting('auth_social_signon_' . $aProvider['slug'] . '_enabled', 'auth')) {
                    $this->aProviders['enabled'][strtolower($aProvider['slug'])] = $aProvider;
                } else {
                    $this->aProviders['disabled'][strtolower($aProvider['slug'])] = $aProvider;
                }
            }

        } else {
            throw new NailsException(
                'No providers for HybridAuth have been specified or the configuration array is empty.'
            );
        }

        // --------------------------------------------------------------------------

        //  Set up Hybrid Auth
        $oDate   = Factory::factory('DateTime');
        $aConfig = [
            'base_url'   => site_url('vendor/hybridauth/hybridauth/hybridauth/index.php'),
            'providers'  => [],
            'debug_mode' => Environment::not(Environment::ENV_PROD),
            'debug_file' => DEPLOY_LOG_DIR . 'log-hybrid-auth-' . $oDate->format('Y-m-d') . '.php',
        ];

        foreach ($this->aProviders['enabled'] as $aProvider) {

            $aTemp            = [];
            $aTemp['enabled'] = true;

            if ($aProvider['fields']) {

                foreach ($aProvider['fields'] as $key => $label) {

                    if (is_array($label) && !isset($label['label'])) {

                        $aTemp[$key] = [];

                        foreach ($label as $key1 => $label1) {
                            $aTemp[$key][$key1] = appSetting(
                                'auth_social_signon_' . $aProvider['slug'] . '_' . $key . '_' . $key1,
                                'auth'
                            );
                        }

                    } else {
                        $aTemp[$key] = appSetting('auth_social_signon_' . $aProvider['slug'] . '_' . $key, 'auth');
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
                    ErrorHandler::halt($e->getMessage());
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
        return appSetting('auth_social_signon_enabled', 'auth') && $this->getProviders('ENABLED');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a list of providers, optionally filtered by availability
     *
     * @param  string $status The filter to apply
     *
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
     *
     * @param  string $provider The provider to return
     *
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
     *
     * @param  string $sProvider The provider to return
     *
     * @return mixed            String on success, null on failure
     */
    protected function getProviderClass($sProvider)
    {
        $aProviders = $this->getProviders();
        return isset($aProviders[strtolower($sProvider)]['class']) ? $aProviders[strtolower($sProvider)]['class'] : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a provider is valid and enabled
     *
     * @param  string $sProvider The provider to check
     *
     * @return boolean
     */
    public function isValidProvider($sProvider)
    {
        return !empty($this->aProviders['enabled'][strtolower($sProvider)]);
    }

    // --------------------------------------------------------------------------

    /**
     * Authenticates a user using Hybrid Auth's authenticate method
     *
     * @param  string $sProvider The provider to authenticate against
     * @param  mixed  $mParams   Additional parameters to pass to the Provider
     *
     * @return Hybrid_Provider_Adapter|false
     */
    public function authenticate($sProvider, $mParams = null)
    {
        try {
            $sProvider = $this->getProviderClass($sProvider);
            return $this->oHybridAuth->authenticate($sProvider, $mParams);
        } catch (\Exception $e) {
            $this->setError('Provider Error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the user's profile for a particular provider.
     *
     * @param  string $provider The name of the provider.
     *
     * @return mixed           Hybrid_User_Profile on success, false on failure
     */
    public function getUserProfile($provider)
    {
        $oAdapter = $this->authenticate($provider);

        try {
            return $oAdapter->getUserProfile();
        } catch (\Exception $e) {
            $this->setError('Provider Error: ' . $e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Logs out all providers
     * @return void
     */
    public function logout()
    {
        $this->oHybridAuth->logoutAllProviders();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a local user profile via a provider and provider ID
     *
     * @param  string $provider   The provider to use
     * @param  string $identifier The provider's user ID
     *
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

        return $this->oUserModel->getById($oUser->user_id);
    }

    // --------------------------------------------------------------------------

    /**
     * Saves the social session data to the user's account
     *
     * @param  mixed  $user_id  The User's ID (if null, then the active user ID is used)
     * @param  string $provider The providers to save
     *
     * @return boolean
     */
    public function saveSession($user_id = null, $provider = [])
    {
        if (empty($user_id)) {
            $user_id = activeUser('id');
        }

        if (empty($user_id)) {
            $this->setError('Must specify which user ID\'s session to save.');
            return false;
        }

        $oUser = $this->oUserModel->getById($user_id);

        if (!$oUser) {
            $this->setError('Invalid User ID');
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
        $_save        = [];
        $_identifiers = [];

        //  Now we sort the session into individual providers
        foreach ($_session as $key => $value) {

            //  Get the bits
            list($hauth, $provider) = explode('.', $key, 3);

            if (!isset($_save[$provider])) {
                $_save[$provider] = [];
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

            //  Connected?
            if (!$this->isConnectedWith($provider)) {
                unset($_save[$provider]);
                continue;
            }

            //  Got an identifier?
            try {

                $oAdapter = $this->oHybridAuth->getAdapter($provider);
                $oProfile = $oAdapter->getUserProfile();

                if (!empty($oProfile->identifier)) {
                    $_identifiers[$provider] = $oProfile->identifier;
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
        $aExisting = $this->oDb->get(NAILS_DB_PREFIX . 'user_social')->result();
        $aExists   = [];

        foreach ($aExisting as $existing) {
            $aExists[$existing->provider] = $existing->id;
        }

        // --------------------------------------------------------------------------

        //  Save data
        $this->oDb->trans_begin();

        $oNow = Factory::factory('DateTime');

        foreach ($_save as $provider => $keys) {

            if (isset($aExists[$provider])) {

                //  Update
                $aData = [
                    'identifier'   => $_identifiers[$provider],
                    'session_data' => serialize($keys),
                    'modified'     => $oNow->format('Y-m-d H:i{s'),
                ];

                $this->oDb->set($aData);
                $this->oDb->where('id', $aExists[$provider]);
                $this->oDb->update(NAILS_DB_PREFIX . 'user_social');

            } else {

                //  Insert
                $aData = [
                    'user_id'      => (int) $user_id,
                    'provider'     => $provider,
                    'identifier'   => $_identifiers[$provider],
                    'session_data' => serialize($keys),
                    'created'      => $oNow->format('Y-m-d H:i:s'),
                    'modified'     => $oNow->format('Y-m-d H:i:s'),
                ];

                $this->oDb->set($aData);
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
     *
     * @param  mixed $user_id The User's ID (if null, then the active user ID is used)
     *
     * @return boolean
     */
    public function restoreSession($user_id = null)
    {
        if (empty($user_id)) {
            $user_id = activeUser('id');
        }

        if (empty($user_id)) {
            $this->setError('Must specify which user ID\'s session to restore.');
            return false;
        }

        $oUser = $this->oUserModel->getById($user_id);

        if (!$oUser) {
            $this->setError('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Clean slate
        $this->oHybridAuth->logoutAllProviders();

        // --------------------------------------------------------------------------

        $this->oDb->where('user_id', $oUser->id);
        $aSessions = $this->oDb->get(NAILS_DB_PREFIX . 'user_social')->result();
        $aRestore  = [];

        foreach ($aSessions as $oSession) {
            $oSession->session_data = unserialize($oSession->session_data);
            $aRestore               = array_merge($aRestore, $oSession->session_data);
        }

        return $this->oHybridAuth->restoreSessionData(serialize($aRestore));
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is connected with $provider
     *
     * @param  string $sProvider the provider to test for
     *
     * @return boolean
     */
    public function isConnectedWith($sProvider)
    {
        $sProvider = $this->getProviderClass($sProvider);
        return $this->oHybridAuth->isConnectedWith($sProvider);
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
     *
     * @param  string $sProvider The provider whose API you wish to call
     * @param  string $call      The API call
     *
     * @return mixed
     */
    public function api($sProvider, $call = '')
    {
        if (!$this->isConnectedWith($sProvider)) {
            $this->setError('Not connected with provider "' . $sProvider . '"');
            return false;
        }

        try {

            $sProvider = $this->getProviderClass($sProvider);
            $oProvider = $this->oHybridAuth->getAdapter($sProvider);
            return $oProvider->api()->api($call);

        } catch (\Exception $e) {
            $this->setError('Provider Error: ' . $e->getMessage());
            return false;
        }
    }
}
