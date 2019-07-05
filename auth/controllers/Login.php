<?php

/**
 * User login facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Controller\Base;
use Nails\Auth\Exception\AuthException;
use Nails\Auth\Model\Auth;
use Nails\Auth\Model\User\Group;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Session;
use Nails\Auth\Service\SocialSignOn;
use Nails\Cdn\Service\Cdn;
use Nails\Common\Exception\NailsException;
use Nails\Common\Service\Config;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\Logger;
use Nails\Common\Service\Output;
use Nails\Common\Service\Uri;
use Nails\Factory;

class Login extends Base
{
    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        $sReturnTo = $oInput->get('return_to');

        if ($sReturnTo) {

            $sReturnTo = preg_match('#^https?\://#', $sReturnTo) ? $sReturnTo : site_url($sReturnTo);
            $aReturnTo = parse_url($sReturnTo);

            //  urlencode the query if there is one
            if (!empty($aReturnTo['query'])) {
                //  Break it apart and glue it together (urlencoded)
                parse_str($aReturnTo['query'], $aQuery);
                $aReturnTo['query'] = http_build_query($aQuery);
            }

            if (empty($aReturnTo['host']) && site_url() === '/') {
                $this->data['return_to'] = [
                    !empty($aReturnTo['path']) ? $aReturnTo['path'] : '',
                    !empty($aReturnTo['query']) ? '?' . $aReturnTo['query'] : '',
                ];
            } else {
                $this->data['return_to'] = [
                    !empty($aReturnTo['scheme']) ? $aReturnTo['scheme'] . '://' : 'http://',
                    !empty($aReturnTo['host']) ? $aReturnTo['host'] : site_url(),
                    !empty($aReturnTo['port']) ? ':' . $aReturnTo['port'] : '',
                    !empty($aReturnTo['path']) ? $aReturnTo['path'] : '',
                    !empty($aReturnTo['query']) ? '?' . $aReturnTo['query'] : '',
                ];
            }

        } else {
            $this->data['return_to'] = [];
        }

        $this->data['return_to'] = implode('', $this->data['return_to']);

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_login');
    }

    // --------------------------------------------------------------------------

    /**
     * Validate data and log the user in.
     *
     * @return  void
     * @throws \Nails\Common\Exception\FactoryException
     **/
    public function index()
    {
        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {
            redirect($this->data['return_to']);
        }

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            //  Validate input
            /** @var FormValidation $oFormValidation */
            $oFormValidation = Factory::service('FormValidation');

            //  The rules vary depending on what login methods are enabled.
            switch (APP_NATIVE_LOGIN_USING) {

                case 'EMAIL':
                    $oFormValidation->set_rules('identifier', 'Email', 'required|trim|valid_email');
                    break;

                case 'USERNAME':
                    $oFormValidation->set_rules('identifier', 'Username', 'required|trim');
                    break;

                default:
                    $oFormValidation->set_rules('identifier', 'Username or Email', 'trim');
                    break;
            }

            //  Password is always required, obviously.
            $oFormValidation->set_rules('password', 'Password', 'required');
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if ($oFormValidation->run()) {

                //  Attempt the log in
                $sIdentifier = $oInput->post('identifier');
                $sPassword   = $oInput->post('password');
                $bRememberMe = (bool) $oInput->post('remember');

                /** @var Auth $oAuthModel */
                $oAuthModel = Factory::model('Auth', 'nails/module-auth');

                $oUser = $oAuthModel->login($sIdentifier, $sPassword, $bRememberMe);

                if ($oUser) {
                    $this->_login($oUser, $bRememberMe);
                } else {
                    $this->data['error'] = $oAuthModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        /** @var SocialSignOn $oSocial */
        $oSocial = Factory::service('SocialSignOn', 'nails/module-auth');

        $this->data['social_signon_enabled']   = $oSocial->isEnabled();
        $this->data['social_signon_providers'] = $oSocial->getProviders('ENABLED');

        // --------------------------------------------------------------------------

        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/login/form.php');

        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/login/form',
                'structure/footer/blank',
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Handles the next stage of login after successfully authenticating
     *
     * @param stdClass $oUser     The user object
     * @param boolean  $bRemember Whether to set the rememberMe cookie or not
     * @param string   $sProvider Which provider authenticated the login
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function _login($oUser, $bRemember = false, $sProvider = 'native')
    {
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
        /** @var Auth $oAuthModel */
        $oAuthModel = Factory::model('Auth', 'nails/module-auth');

        if ($oUser->is_suspended) {

            $this->data['error'] = lang('auth_login_fail_suspended');
            return;

        } elseif (!empty($oUser->temp_pw)) {

            /**
             * Temporary password detected, log user out and redirect to
             * password reset page.
             **/
            $this->resetPassword($oUser->id, $oUser->salt, $bRemember, 'TEMP');

        } elseif ($oUserPasswordModel->isExpired($oUser->id)) {

            /**
             * Expired password detected, log user out and redirect to
             * password reset page.
             **/
            $this->resetPassword($oUser->id, $oUser->salt, $bRemember, 'EXPIRED');

        } elseif ($oConfig->item('authTwoFactorMode')) {

            //  Generate token
            $aTwoFactorToken = $oAuthModel->mfaTokenGenerate($oUser->id);

            if (!$aTwoFactorToken) {
                throw new RuntimeException(
                    'A user tried to login and the system failed to generate a two-factor auth token.'
                );
            }

            //  Is there any query data?
            $aQuery = [];

            if ($this->data['return_to']) {
                $aQuery['return_to'] = $this->data['return_to'];
            }

            if ($bRemember) {
                $aQuery['remember'] = true;
            }

            $sQuery = !empty($aQuery) ? '?' . http_build_query($aQuery) : '';

            //  Where we sending the user?
            switch ($oConfig->item('authTwoFactorMode')) {

                case 'QUESTION':
                    $sController = 'mfa/question';
                    break;

                case 'DEVICE':
                    $sController = 'mfa/device';
                    break;

                default:
                    throw new NailsException('"' . $oConfig->item('authTwoFactorMode') . '" is not a valid MFA Mode');
                    break;
            }

            //  Compile the URL
            $aUrl = [
                'auth',
                $sController,
                $oUser->id,
                $aTwoFactorToken['salt'],
                $aTwoFactorToken['token'],
            ];

            //  Login was successful, redirect to the appropriate MFA page
            redirect(implode($aUrl, '/') . $sQuery);

        } else {

            //  Finally! Send this user on their merry way...
            if ($oUser->last_login) {

                $lastLogin = $oConfig->item('authShowNicetimeOnLogin') ? niceTime(strtotime($oUser->last_login)) : toUserDatetime($oUser->last_login);

                if ($oConfig->item('authShowLastIpOnLogin')) {
                    $sStatus  = 'positive';
                    $sMessage = lang('auth_login_ok_welcome_with_ip', [
                        $oUser->first_name,
                        $lastLogin,
                        $oUser->last_ip,
                    ]);
                } else {
                    $sStatus  = 'positive';
                    $sMessage = lang('auth_login_ok_welcome', [$oUser->first_name, $lastLogin]);
                }

            } else {
                $sStatus  = 'positive';
                $sMessage = lang('auth_login_ok_welcome_notime', [$oUser->first_name]);
            }

            /** @var Session $oSession */
            $oSession = Factory::service('Session', 'nails/module-auth');
            $oSession->setFlashData($sStatus, $sMessage);

            $sRedirectUrl = $this->data['return_to'] ? $this->data['return_to'] : $oUser->group_homepage;

            // --------------------------------------------------------------------------

            //  Generate an event for this log in
            create_event('did_log_in', ['provider' => $sProvider], $oUser->id);

            // --------------------------------------------------------------------------

            redirect($sRedirectUrl);
        }
    }

    // --------------------------------------------------------------------------

    protected function resetPassword($iUserId, $sUserSalt, $bRemember, $sReason = '')
    {
        $aQuery = [];

        if ($this->data['return_to']) {
            $aQuery['return_to'] = $this->data['return_to'];
        }

        if ($bRemember) {
            $aQuery['remember'] = true;
        }

        if ($sReason) {
            $aQuery['reason'] = $sReason;
        }

        $aQuery = $aQuery ? '?' . http_build_query($aQuery) : '';

        /**
         * Log the user out and remove the 'remember me' cookie - if we don't do this
         * then the password reset page will see a logged in user and go nuts
         * (i.e error).
         */

        /** @var Auth $oAuthModel */
        $oAuthModel = Factory::model('Auth', 'nails/module-auth');
        $oAuthModel->logout();

        redirect('auth/password/reset/' . $iUserId . '/' . md5($sUserSalt) . $aQuery);
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user in using hashes of their user ID and password; easy way of
     * automatically logging a user in from the likes of an email.
     *
     * @return  void
     * @throws NailsException
     */
    public function with_hashes()
    {
        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');

        if (!$oConfig->item('authEnableHashedLogin')) {
            show404();
        }

        // --------------------------------------------------------------------------

        $hash['id'] = $oUri->segment(4);
        $hash['pw'] = $oUri->segment(5);

        if (empty($hash['id']) || empty($hash['pw'])) {
            throw new NailsException(lang('auth_with_hashes_incomplete_creds'), 1);
        }

        // --------------------------------------------------------------------------

        /**
         * If the user is already logged in we need to check to see if we check to see if they are
         * attempting to login as themselves, if so we redirect, otherwise we log them out and try
         * again using the hashes.
         */

        if (isLoggedIn()) {

            if (md5(activeUser('id')) == $hash['id']) {

                //  We are attempting to log in as who we're already logged in as, redirect normally
                if ($this->data['return_to']) {

                    redirect($this->data['return_to']);

                } else {

                    //  Nowhere to go? Send them to their default homepage
                    redirect(activeUser('group_homepage'));
                }

            } else {

                //  We are logging in as someone else, log the current user out and try again
                /** @var Auth $oAuthModel */
                $oAuthModel = Factory::model('Auth', 'nails/module-auth');
                $oAuthModel->logout();

                redirect(preg_replace('/^\//', '', $_SERVER['REQUEST_URI']));
            }
        }

        // --------------------------------------------------------------------------

        /**
         * The active user is a guest, we must look up the hashed user and log them in
         * if all is ok otherwise we report an error.
         */

        /** @var Session $oSession */
        $oSession = Factory::service('Session', 'nails/module-auth');
        /** @var \Nails\Auth\Model\User $oUserModel */
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oUser      = $oUserModel->getByHashes($hash['id'], $hash['pw']);

        // --------------------------------------------------------------------------

        if ($oUser) {

            //  User was verified, log the user in
            $oUserModel->setLoginData($oUser->id);

            // --------------------------------------------------------------------------

            //  Say hello
            if ($oUser->last_login) {

                $lastLogin = $oConfig->item('authShowNicetimeOnLogin') ? niceTime(strtotime($oUser->last_login)) : toUserDatetime($oUser->last_login);

                if ($oConfig->item('authShowLastIpOnLogin')) {
                    $sStatus  = 'positive';
                    $sMessage = lang('auth_login_ok_welcome_with_ip', [
                        $oUser->first_name,
                        $lastLogin,
                        $oUser->last_ip,
                    ]);
                } else {
                    $sStatus  = 'positive';
                    $sMessage = lang('auth_login_ok_welcome', [$oUser->first_name, $oUser->last_login]);
                }

            } else {
                $sStatus  = 'positive';
                $sMessage = lang('auth_login_ok_welcome_notime', [$oUser->first_name]);
            }

            $oSession->setFlashData($sStatus, $sMessage);

            // --------------------------------------------------------------------------

            //  Update their last login
            $oUserModel->updateLastLogin($oUser->id);

            // --------------------------------------------------------------------------

            //  Redirect user
            if ($this->data['return_to'] != site_url()) {

                //  We have somewhere we want to go
                redirect($this->data['return_to']);

            } else {

                //  Nowhere to go? Send them to their default homepage
                redirect($oUser->group_homepage);
            }

        } else {

            //  Bad lookup, invalid hash.
            $oSession->setFlashData('error', lang('auth_with_hashes_autologin_fail'));
            redirect($this->data['return_to']);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handle login/registration via social provider
     *
     * @param string $provider The provider to use
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function socialSignon($provider)
    {
        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        /** @var Logger $oLogger */
        $oLogger = Factory::service('Logger');
        /** @var Session $oSession */
        $oSession = Factory::service('Session', 'nails/module-auth');
        /** @var SocialSignOn $oSocial */
        $oSocial = Factory::service('SocialSignOn', 'nails/module-auth');
        /** @var \Nails\Auth\Model\User $oUserModel */
        $oUserModel = Factory::model('User', 'nails/module-auth');

        //  Fetch the user's social profile and, if one exists, the local profile.
        try {

            //  Get the adapter, HybridAuth will handle the redirect
            $adapter = $oSocial->authenticate($provider);
            if (empty($adapter)) {
                throw new AuthException('Failed to authenticate with provider. ' . $oSocial->lastError());
            }

            $provider = $oSocial->getProvider($provider);
            if (empty($provider)) {
                throw new AuthException('Failed to get provider adapter. ' . $oSocial->lastError());
            }

            $socialUser = $adapter->getUserProfile();

        } catch (Exception $e) {

            //  Failed to fetch from the provider, something must have gone wrong
            $oLogger->line('HybridAuth failed to fetch data from provider.');
            $oLogger->line('Error Code: ' . $e->getCode());
            $oLogger->line('Error Message: ' . $e->getMessage());

            if (empty($provider)) {
                $oSession->setFlashData(
                    'error',
                    'Sorry, there was a problem communicating with the network.'
                );
            } else {
                $oSession->setFlashData(
                    'error',
                    'Sorry, there was a problem communicating with ' . $provider['label'] . '.'
                );
            }

            if ($oUri->segment(4) == 'register') {
                $sRedirectUrl = 'auth/register';
            } else {
                $sRedirectUrl = 'auth/login';
            }

            if ($this->data['return_to']) {
                $sRedirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
            }

            redirect($sRedirectUrl);
        }

        $oUser = $oSocial->getUserByProviderId($provider['slug'], $socialUser->identifier);

        // --------------------------------------------------------------------------

        /**
         * See if we already know about this user, react accordingly.
         * If a user already exists for this provider/identifier then it's logical
         * to spok them in, I mean, log them in - provided of course they aren't
         * already logged in, if they are then silly user. If no user is recognised
         * then we need to register them, providing, of course that registration is
         * enabled and that no one else on the system has their email address.
         * On that note, we need to respect APP_NATIVE_LOGIN_USING; if the provider
         * cannot satisfy this then we'll need to interrupt registration and ask them
         * for either a username or an email (or both).
         */

        if ($oUser) {

            if (isLoggedIn() && activeUser('id') == $oUser->id) {

                /**
                 * Logged in user is already logged in and is the social user.
                 * Silly user, just redirect them to where they need to go.
                 */

                $oSession->setFlashData('message', lang('auth_social_already_linked', $provider['label']));

                if ($this->data['return_to']) {
                    redirect($this->data['return_to']);
                } else {
                    redirect($oUser->group_homepage);
                }

            } elseif (isLoggedIn() && activeUser('id') != $oUser->id) {

                /**
                 * Hmm, a user was found for this Provider ID, but it's not the actively logged
                 * in user. This means that this provider account is already registered.
                 */

                $oSession->setFlashData('error', lang('auth_social_account_in_use', [$provider['label'], APP_NAME]));

                if ($this->data['return_to']) {
                    redirect($this->data['return_to']);
                } else {
                    redirect($oUser->group_homepage);
                }

            } else {

                //  Fab, user exists, try to log them in
                $oUserModel->setLoginData($oUser->id);
                $oSocial->saveSession($oUser->id);

                if (!$this->_login($oUser)) {

                    $oSession->setFlashData('error', $this->data['error']);

                    $sRedirectUrl = 'auth/login';

                    if ($this->data['return_to']) {
                        $sRedirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($sRedirectUrl);
                }
            }

        } elseif (isLoggedIn()) {

            /**
             * User is logged in and it look's like the provider isn't being used by
             * anyone else. Go ahead and link the two accounts together.
             */

            if ($oSocial->saveSession(activeUser('id'), $provider)) {

                create_event('did_link_provider', ['provider' => $provider]);
                $oSession->setFlashData('success', lang('auth_social_linked_ok', $provider['label']));

            } else {
                $oSession->setFlashData('error', lang('auth_social_linked_fail', $provider['label']));
            }

            redirect($this->data['return_to']);

        } else {

            /**
             * Didn't find a user and the active user isn't logged in, assume they want
             * to register an account. I mean, who wouldn't, this site is AwEsOmE.
             */

            if (appSetting('user_registration_enabled', 'auth')) {

                $aRequiredData = [];
                $aOptionalData = [];

                //  Fetch required data
                switch (APP_NATIVE_LOGIN_USING) {

                    case 'EMAIL':
                        $aRequiredData['email'] = trim($socialUser->email);
                        break;

                    case 'USERNAME':
                        $aRequiredData['username'] = !empty($socialUser->username) ? trim($socialUser->username) : '';
                        break;

                    default:
                        $aRequiredData['email']    = trim($socialUser->email);
                        $aRequiredData['username'] = !empty($socialUser->username) ? trim($socialUser->username) : '';
                        break;
                }

                $aRequiredData['first_name'] = trim($socialUser->firstName);
                $aRequiredData['last_name']  = trim($socialUser->lastName);

                //  And any optional data
                if (checkdate($socialUser->birthMonth, $socialUser->birthDay, $socialUser->birthYear)) {

                    $aOptionalData['dob']          = [];
                    $aOptionalData['dob']['year']  = trim($socialUser->birthYear);
                    $aOptionalData['dob']['month'] = str_pad(trim($socialUser->birthMonth), 2, 0, STR_PAD_LEFT);
                    $aOptionalData['dob']['day']   = str_pad(trim($socialUser->birthDay), 2, 0, STR_PAD_LEFT);
                    $aOptionalData['dob']          = implode('-', $aOptionalData['dob']);
                }

                switch (strtoupper($socialUser->gender)) {

                    case 'MALE':
                        $aOptionalData['gender'] = 'MALE';
                        break;

                    case 'FEMALE':
                        $aOptionalData['gender'] = 'FEMALE';
                        break;
                }

                // --------------------------------------------------------------------------

                /**
                 * If any required fields are missing then we need to interrupt the registration
                 * flow and ask for them
                 */

                if (count($aRequiredData) !== count(array_filter($aRequiredData))) {

                    /**
                     * @TODO: One day work out a way of doing this so that we don't need to call
                     * the API again etc, uses unnecessary calls. Then again, maybe it *is*
                     * necessary.
                     */

                    $this->requestData($aRequiredData, $provider['slug']);
                }

                /**
                 * We have everything we need to create the user account However, first we need to
                 * make sure that our data is valid and not in use. At this point it's not the
                 * user's fault so don't throw an error.
                 */

                //  Check email
                if (isset($aRequiredData['email'])) {

                    $check = $oUserModel->getByEmail($aRequiredData['email']);

                    if ($check) {
                        $aRequiredData['email'] = '';
                        $requestData            = true;
                    }
                }

                // --------------------------------------------------------------------------

                if (isset($aRequiredData['username'])) {

                    /**
                     * Username was set using provider provided username, check it's valid if
                     * not, then request one. At this point it's not the user's fault so don't
                     * throw an error.
                     */

                    $check = $oUserModel->getByUsername($aRequiredData['username']);

                    if ($check) {
                        $aRequiredData['username'] = '';
                        $requestData               = true;
                    }

                } else {

                    /**
                     * No username, make one up for them, try to use the social_user username
                     * (as it might not have been set above), failing that use the user's name,
                     * failing THAT use a random string
                     */

                    if (!empty($socialUser->username)) {

                        $sUsername = $socialUser->username;

                    } elseif ($aRequiredData['first_name'] || $aRequiredData['last_name']) {

                        $sUsername = $aRequiredData['first_name'] . ' ' . $aRequiredData['last_name'];

                    } else {
                        $oDate     = Factory::factory('DateTime');
                        $sUsername = 'user' . $oDate->format('YmdHis');
                    }

                    $basename                  = url_title($sUsername, '-', true);
                    $aRequiredData['username'] = $basename;

                    $oUser = $oUserModel->getByUsername($aRequiredData['username']);

                    while ($oUser) {
                        $aRequiredData['username'] = increment_string($basename, '');
                        $oUser                     = $oUserModel->getByUsername($aRequiredData['username']);
                    }
                }

                // --------------------------------------------------------------------------

                //  Request data?
                if (!empty($requestData)) {
                    $this->requestData($aRequiredData, $provider->slug);
                }

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($oSession->getUserData('referred_by')) {
                    $aOptionalData['referred_by'] = $oSession->getUserData('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Merge data arrays
                $data = array_merge($aRequiredData, $aOptionalData);

                // --------------------------------------------------------------------------

                //  Create user
                $newUser = $oUserModel->create($data);

                if ($newUser) {

                    /**
                     * Welcome aboard, matey
                     * - Save provider details
                     * - Upload profile image if available
                     */

                    $oSocial->saveSession($newUser->id, $provider);

                    if (!empty($socialUser->photoURL)) {

                        //  Has profile image
                        $imgUrl = $socialUser->photoURL;

                    } elseif (!empty($newUser->email)) {

                        //  Attempt gravatar
                        $imgUrl = 'http://www.gravatar.com/avatar/' . md5($newUser->email) . '?d=404&s=2048&r=pg';
                    }

                    if (!empty($imgUrl)) {

                        //  Fetch the image
                        //  @todo Consider streaming directly to the filesystem
                        $oHttpClient = Factory::factory('HttpClient');

                        try {

                            $oResponse = $oHttpClient->get($imgUrl);

                            if ($oResponse->getStatusCode() === 200) {

                                //  Attempt upload
                                /** @var Cdn $oCdn */
                                $oCdn = Factory::service('Cdn', 'nails/module-cdn');

                                //  Save file to cache
                                $cacheFile = CACHE_PATH . 'new-user-profile-image-' . $newUser->id;

                                if (@file_put_contents($cacheFile, (string) $oResponse->getBody)) {

                                    $_upload = $oCdn->objectCreate($cacheFile, 'profile-images', []);

                                    if ($_upload) {

                                        $data                = [];
                                        $data['profile_img'] = $_upload->id;

                                        $oUserModel->update($newUser->id, $data);

                                    } else {
                                        $oLogger->line('Failed to upload user\'s profile image');
                                        $oLogger->line($oCdn->lastError());
                                    }
                                }
                            }

                        } catch (\Exception $e) {
                            $oLogger->line('Failed to upload user\'s profile image');
                            $oLogger->line($e->getMessage());
                        }
                    }

                    // --------------------------------------------------------------------------

                    //  Aint that swell, all registered! Redirect!
                    $oUserModel->setLoginData($newUser->id);

                    // --------------------------------------------------------------------------

                    //  Create an event for this event
                    create_event('did_register', ['method' => $provider], $newUser->id);

                    // --------------------------------------------------------------------------

                    //  Redirect
                    $oSession->setFlashData('success', lang('auth_social_register_ok', $newUser->first_name));

                    if (empty($this->data['return_to'])) {
                        /** @var Group $oUserGroupModel */
                        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
                        $group           = $oUserGroupModel->getById($newUser->group_id);
                        $sRedirectUrl    = $group->registration_redirect ? $group->registration_redirect : $group->default_homepage;
                    } else {
                        $sRedirectUrl = $this->data['return_to'];
                    }

                    redirect($sRedirectUrl);

                } else {

                    //  Oh dear, something went wrong
                    $sStatus  = 'error';
                    $sMessage = 'Sorry, something went wrong and your account could not be created.';
                    $oSession->setFlashData($sStatus, $sMessage);

                    $sRedirectUrl = 'auth/login';

                    if ($this->data['return_to']) {
                        $sRedirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($sRedirectUrl);
                }

            } else {

                //  How unfortunate, registration is disabled. Redirect back to the login page
                $oSession->setFlashData('error', lang('auth_social_register_disabled'));

                $sRedirectUrl = 'auth/login';

                if ($this->data['return_to']) {
                    $sRedirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                }

                redirect($sRedirectUrl);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handles requesting of additional data from the user
     *
     * @param array  &$aRequiredData An array of fields to request
     * @param string  $provider      The provider to use
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function requestData(&$aRequiredData, $provider)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            /** @var FormValidation $oFormValidation */
            $oFormValidation = Factory::service('FormValidation');

            if (isset($aRequiredData['email'])) {
                $oFormValidation->set_rules('email', 'email', 'trim|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]');
            }

            if (isset($aRequiredData['username'])) {
                $oFormValidation->set_rules('username', 'username', 'trim|required|is_unique[' . NAILS_DB_PREFIX . 'user.username]');
            }

            if (empty($aRequiredData['first_name'])) {
                $oFormValidation->set_rules('first_name', '', 'trim|required');
            }

            if (empty($aRequiredData['last_name'])) {
                $oFormValidation->set_rules('last_name', '', 'trim|required');
            }

            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_email_already_registered', site_url('auth/password/forgotten'))
                );
            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_username_already_registered', site_url('auth/password/forgotten'))
                );
            } else {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_identity_already_registered', site_url('auth/password/forgotten'))
                );
            }

            if ($oFormValidation->run()) {

                //  Valid!Ensure required data is set correctly then allow system to move on.
                if (isset($aRequiredData['email'])) {
                    $aRequiredData['email'] = $oInput->post('email');
                }

                if (isset($aRequiredData['username'])) {
                    $aRequiredData['username'] = $oInput->post('username');
                }

                if (empty($aRequiredData['first_name'])) {
                    $aRequiredData['first_name'] = $oInput->post('first_name');
                }

                if (empty($aRequiredData['last_name'])) {
                    $aRequiredData['last_name'] = $oInput->post('last_name');
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
                $this->requestDataForm($aRequiredData, $provider);
            }

        } else {
            $this->requestDataForm($aRequiredData, $provider);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the "request data" form
     *
     * @param array  &$aRequiredData An array of fields to request
     * @param string  $provider      The provider being used
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function requestDataForm(&$aRequiredData, $provider)
    {
        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');

        $this->data['required_data'] = $aRequiredData;
        $this->data['form_url']      = 'auth/login/' . $provider;

        if ($oUri->segment(4) == 'register') {
            $this->data['form_url'] .= '/register';
        }

        if ($this->data['return_to']) {
            $this->data['form_url'] .= '?return_to=' . urlencode($this->data['return_to']);
        }

        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/register/social_request_data',
                'structure/footer/blank',
            ]);

        /** @var Output $oOutput */
        $oOutput = Factory::service('Output');
        echo $oOutput->get_output();
        exit();
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests appropriately
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function _remap()
    {
        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');

        $method = $oUri->segment(3) ? $oUri->segment(3) : 'index';

        if (method_exists($this, $method) && substr($method, 0, 1) != '_') {

            $this->{$method}();

        } else {

            //  Assume the 3rd segment is a login provider supported by Hybrid Auth
            /** @var SocialSignOn $oSocial */
            $oSocial = Factory::service('SocialSignOn', 'nails/module-auth');
            if ($oSocial->isValidProvider($method)) {
                $this->socialSignon($method);
            } else {
                show404();
            }
        }
    }
}
