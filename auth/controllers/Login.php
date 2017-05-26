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

use Nails\Factory;
use Nails\Auth\Controller\Base;
use Nails\Common\Exception\NailsException;

class Login extends Base
{
    /**
     * The social sign on instance
     * @var \Nails\Auth\Library\SocialSignOn
     */
    private $oSocial;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Load libraries
        $this->oSocial = Factory::service('SocialSignOn', 'nailsapp/module-auth');

        // --------------------------------------------------------------------------

        //  Where are we returning user to?
        $returnTo = $this->input->get('return_to');

        if ($returnTo) {

            $returnTo = preg_match('#^(http|https)\://#', $returnTo) ? $returnTo : site_url($returnTo);
            $returnTo = parse_url($returnTo);

            //  urlencode the query if there is one
            if (!empty($returnTo['query'])) {

                //  Break it apart and glue it together (urlencoded)
                parse_str($returnTo['query'], $query_ar);
                $returnTo['query'] = http_build_query($query_ar);
            }

            $this->data['return_to']  = '';
            $this->data['return_to'] .= !empty($returnTo['scheme']) ? $returnTo['scheme'] . '://' : 'http://';
            $this->data['return_to'] .= !empty($returnTo['host']) ? $returnTo['host'] : site_url();
            $this->data['return_to'] .= !empty($returnTo['port']) ? ':' . $returnTo['port'] : '';
            $this->data['return_to'] .= !empty($returnTo['path']) ? $returnTo['path'] : '';
            $this->data['return_to'] .= !empty($returnTo['query']) ? '?' . $returnTo['query'] : '';

        } else {

            $this->data['return_to'] = '';
        }

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_login');
    }

    // --------------------------------------------------------------------------

    /**
     * Validate data and log the user in.
     * @return  void
     **/
    public function index()
    {
        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {

            redirect($this->data['return_to']);
        }

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        if ($this->input->post()) {

            //  Validate input
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
                $identifier = $this->input->post('identifier', true );
                $password   = $this->input->post('password', true );
                $rememberMe = (bool) $this->input->post('remember' );

                $user = $this->auth_model->login($identifier, $password, $rememberMe);

                if ($user) {

                    $this->_login($user, $rememberMe);

                } else {

                    //  Login failed
                    $this->data['error'] = $this->auth_model->lastError();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $this->data['social_signon_enabled']   = $this->oSocial->isEnabled();
        $this->data['social_signon_providers'] = $this->oSocial->getProviders('ENABLED');

        // --------------------------------------------------------------------------

        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/login/form', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Handles the next stage of login after successfully authenticating
     * @param  stdClass $user     The user object
     * @param  boolean  $remember Whether to set the rememberMe cookie or not
     * @param  string   $provider Which provider authenticated the login
     * @return void
     */
    protected function _login($user, $remember = false, $provider = 'native')
    {
        $oConfig = Factory::service('Config');

        if ($user->is_suspended) {

            $this->data['error'] = lang('auth_login_fail_suspended');
            return false;

        } elseif (!empty($user->temp_pw)) {

            /**
             * Temporary password detected, log user out and redirect to
             * password reset page.
             **/

            $this->resetPassword($user->id, $user->salt, $remember, 'TEMP');


        } elseif ($this->user_password_model->isExpired($user->id)) {

            /**
             * Expired password detected, log user out and redirect to
             * password reset page.
             **/

            $this->resetPassword($user->id, $user->salt, $remember, 'EXPIRED');

        } elseif ($oConfig->item('authTwoFactorMode')) {

            //  Generate token
            $twoFactorToken = $this->auth_model->mfaTokenGenerate($user->id);

            if (!$twoFactorToken) {

                $subject = 'Failed to generate two-factor auth token';
                $message = 'A user tried to login and the system failed to generate a two-factor auth token.';
                showFatalError($subject, $message);
            }

            //  Is there any query data?
            $query = array();

            if ($this->data['return_to']) {

                $query['return_to'] = $this->data['return_to'];
            }

            if ($remember) {

                $query['remember'] = true;
            }

            $query = $query ? '?' . http_build_query($query) : '';

            //  Where we sending the user?
            switch ($oConfig->item('authTwoFactorMode')) {

                case 'QUESTION':
                    $controller = 'mfa_question';
                    break;

                case 'DEVICE':
                    $controller = 'mfa_device';
                    break;
            }

            //  Compile the URL
            $url = array(
                'auth',
                $controller,
                $user->id,
                $twoFactorToken['salt'],
                $twoFactorToken['token']
            );

            $url = implode($url, '/') . $query;

            //  Login was successful, redirect to the appropriate MFA page
            redirect($url);

        } else {

            //  Finally! Send this user on their merry way...
            if ($user->last_login) {

                $lastLogin = $oConfig->item('authShowNicetimeOnLogin') ? niceTime(strtotime($user->last_login)) : toUserDatetime($user->last_login);

                if ($oConfig->item('authShowLastIpOnLogin')) {

                    $status  = 'positive';
                    $message = lang('auth_login_ok_welcome_with_ip', array($user->first_name, $lastLogin, $user->last_ip));

                } else {

                    $status  = 'positive';
                    $message = lang('auth_login_ok_welcome', array($user->first_name, $lastLogin));
                }

            } else {

                $status  = 'positive';
                $message = lang('auth_login_ok_welcome_notime', array($user->first_name));
            }

            $oSession = Factory::service('Session', 'nailsapp/module-auth');
            $oSession->set_flashdata($status, $message);

            $redirectUrl = $this->data['return_to'] ? $this->data['return_to'] : $user->group_homepage;

            // --------------------------------------------------------------------------

            //  Generate an event for this log in
            create_event('did_log_in', array('provider' => $provider), $user->id);

            // --------------------------------------------------------------------------

            redirect($redirectUrl);
        }
    }

    // --------------------------------------------------------------------------

    protected function resetPassword($iUserId, $sUserSalt, $bRemember, $sReason = '')
    {
        $aQuery = array();

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

        $this->auth_model->logout();

        redirect('auth/reset_password/' . $iUserId . '/' . md5($sUserSalt) . $aQuery);
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user in using hashes of their user ID and password; easy way of
     * automatically logging a user in from the likes of an email.
     * @return  void
     **/
    public function with_hashes()
    {
        $oConfig = Factory::service('Config');

        if (!$oConfig->item('authEnableHashedLogin')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $hash['id'] = $this->uri->segment(4);
        $hash['pw'] = $this->uri->segment(5);

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
                $this->auth_model->logout();

                redirect(preg_replace('/^\//', '', $_SERVER['REQUEST_URI']));
            }
        }

        // --------------------------------------------------------------------------

        /**
         * The active user is a guest, we must look up the hashed user and log them in
         * if all is ok otherwise we report an error.
         */

        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $user       = $oUserModel->getByHashes($hash['id'], $hash['pw']);

        // --------------------------------------------------------------------------

        if ($user) {

            //  User was verified, log the user in
            $oUserModel->setLoginData($user->id);

            // --------------------------------------------------------------------------

            //  Say hello
            if ($user->last_login) {

                $lastLogin = $oConfig->item('authShowNicetimeOnLogin') ? niceTime(strtotime($user->last_login)) : toUserDatetime($user->last_login);

                if ($oConfig->item('authShowLastIpOnLogin')) {

                    $status  = 'positive';
                    $message = lang('auth_login_ok_welcome_with_ip', array($user->first_name, $lastLogin, $user->last_ip));

                } else {

                    $status  = 'positive';
                    $message = lang('auth_login_ok_welcome', array($user->first_name, $user->last_login));
                }

            } else {

                $status  = 'positive';
                $message = lang('auth_login_ok_welcome_notime', array($user->first_name));
            }

            $oSession->set_flashdata($status, $message);

            // --------------------------------------------------------------------------

            //  Update their last login
            $oUserModel->updateLastLogin($user->id);

            // --------------------------------------------------------------------------

            //  Redirect user
            if ($this->data['return_to'] != site_url()) {

                //  We have somewhere we want to go
                redirect($this->data['return_to']);

            } else {

                //  Nowhere to go? Send them to their default homepage
                redirect($user->group_homepage);
            }

        } else {

            //  Bad lookup, invalid hash.
            $oSession->set_flashdata('error', lang('auth_with_hashes_autologin_fail'));
            redirect($this->data['return_to']);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handle login/registration via social provider
     * @param  string $provider The provider to use
     * @return void
     */
    protected function socialSignon($provider)
    {
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');

        //  Get the adapter, HybridAuth will handle the redirect
        $adapter  = $this->oSocial->authenticate($provider);
        $provider = $this->oSocial->getProvider($provider);

        // --------------------------------------------------------------------------

        //  Fetch the user's social profile and, if one exists, the local profile.
        try {

            $socialUser = $adapter->getUserProfile();

        } catch (Exception $e) {

            //  Failed to fetch from the provider, something must have gone wrong
            log_message('error', 'HybridAuth failed to fetch data from provider.');
            log_message('error', 'Error Code: ' . $e->getCode());
            log_message('error', 'Error Message: ' . $e->getMessage());

            if (empty($provider)) {
                $oSession->set_flashdata(
                    'error',
                    '<strong>Sorry,</strong> there was a problem communicating with the network.'
                );
            } else {
                $oSession->set_flashdata(
                    'error',
                    '<strong>Sorry,</strong> there was a problem communicating with ' . $provider['label'] . '.'
                );
            }

            if ($this->uri->segment(4) == 'register') {
                $redirectUrl = 'auth/register';
            } else {
                $redirectUrl = 'auth/login';
            }

            if ($this->data['return_to']) {
                $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
            }

            redirect($redirectUrl);
        }

        $user = $this->oSocial->getUserByProviderId($provider['slug'], $socialUser->identifier);

        // --------------------------------------------------------------------------

        /**
         * See if we already know about this user, react accordingly.
         * If a user already exists for this provder/identifier then it's logical
         * to spok them in, I mean, log them in - provided of course they aren't
         * already logged in, if they are then silly user. If no user is recognised
         * then we need to register them, providing, of course that registration is
         * enabled and that no one else on the system has their email address.
         * On that note, we need to respect APP_NATIVE_LOGIN_USING; if the provider
         * cannot satisfy this then we'll need to interrupt registration and ask them
         * for either a username or an email (or both).
         */

        if ($user) {

            if (isLoggedIn() && activeUser('id') == $user->id) {

                /**
                 * Logged in user is already logged in and is the social user.
                 * Silly user, just redirect them to where they need to go.
                 */

                $oSession->set_flashdata('message', lang('auth_social_already_linked', $provider['label']));

                if ($this->data['return_to']) {

                    redirect($this->data['return_to']);

                } else {

                    redirect($user->group_homepage);
                }

            } elseif (isLoggedIn() && activeUser('id') != $user->id) {

                /**
                 * Hmm, a user was found for this Provider ID, but it's not the actively logged
                 * in user. This means that this provider account is already registered.
                 */

                $oSession->set_flashdata('error', lang('auth_social_account_in_use', array($provider['label'], APP_NAME)));

                if ($this->data['return_to']) {

                    redirect($this->data['return_to']);

                } else {

                    redirect($user->group_homepage);
                }

            } else {

                //  Fab, user exists, try to log them in
                $oUserModel->setLoginData($user->id);
                $this->oSocial->saveSession($user->id);

                if (!$this->_login($user)) {

                    $oSession->set_flashdata('error', $this->data['error']);

                    $redirectUrl = 'auth/login';

                    if ($this->data['return_to']) {

                        $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($redirectUrl);
                }
            }

        } elseif (isLoggedIn()) {

            /**
             * User is logged in and it look's like the provider isn't being used by
             * anyone else. Go ahead and link the two accounts together.
             */

            if ($this->oSocial->saveSession(activeUser('id'), $provider)) {

                create_event('did_link_provider', array('provider' => $provider));
                $oSession->set_flashdata('success', lang('auth_social_linked_ok', $provider['label']));

            } else {

                $oSession->set_flashdata('error', lang('auth_social_linked_fail', $provider['label']));
            }

            redirect($this->data['return_to']);

        } else {

            /**
             * Didn't find a user and the active user isn't logged in, assume they want
             * to regster an account. I mean, who wouldn't, this site is AwEsOmE.
             */

            if (appSetting('user_registration_enabled', 'auth')) {

                $requiredData = array();
                $optionalData = array();

                //  Fetch required data
                switch (APP_NATIVE_LOGIN_USING) {

                    case 'EMAIL':
                        $requiredData['email'] = trim($socialUser->email);
                        break;

                    case 'USERNAME':
                        $requiredData['username'] = !empty($socialUser->username) ? trim($socialUser->username) : '';
                        break;

                    default:
                        $requiredData['email']    = trim($socialUser->email);
                        $requiredData['username'] = !empty($socialUser->username) ? trim($socialUser->username) : '';
                        break;
                }

                $requiredData['first_name']   = trim($socialUser->firstName);
                $requiredData['last_name']    = trim($socialUser->lastName);

                //  And any optional data
                if (checkdate($socialUser->birthMonth, $socialUser->birthDay, $socialUser->birthYear)) {

                    $optionalData['dob']          = array();
                    $optionalData['dob']['year']  = trim($socialUser->birthYear);
                    $optionalData['dob']['month'] = str_pad(trim($socialUser->birthMonth), 2, 0, STR_PAD_LEFT);
                    $optionalData['dob']['day']   = str_pad(trim($socialUser->birthDay), 2, 0, STR_PAD_LEFT);
                    $optionalData['dob']          = implode('-', $optionalData['dob']);
                }

                switch (strtoupper($socialUser->gender)) {

                    case 'MALE' :
                        $optionalData['gender'] = 'MALE';
                        break;

                    case 'FEMALE' :
                        $optionalData['gender'] = 'FEMALE';
                        break;
                }

                // --------------------------------------------------------------------------

                /**
                 * If any required fields are missing then we need to interrupt the registration
                 * flow and ask for them
                 */

                if (count($requiredData) !== count(array_filter($requiredData))) {

                    /**
                     * @TODO: One day work out a way of doing this so that we don't need to call
                     * the API again etc, uses unnessecary calls. Then again, maybe it *is*
                     * necessary.
                     */

                    $this->requestData($requiredData, $provider['slug']);
                }

                /**
                 * We have everything we need to create the user account However, first we need to
                 * make sure that our data is valid and not in use. At this point it's not the
                 * user's fault so don't throw an error.
                 */

                //  Check email
                if (isset($requiredData['email'])) {

                    $check = $oUserModel->getByEmail($requiredData['email']);

                    if ($check) {

                        $requiredData['email'] = '';
                        $requestData           = true;
                    }
                }

                // --------------------------------------------------------------------------

                if (isset($requiredData['username'])) {

                    /**
                     * Username was set using provider provided username, check it's valid if
                     * not, then request one. At this point it's not the user's fault so don't
                     * throw an error.
                     */

                    $check = $oUserModel->getByUsername($requiredData['username']);

                    if ($check) {
                        $requiredData['username'] = '';
                        $requestData              = true;
                    }

                } else {

                    /**
                     * No username, make one up for them, try to use the social_user username
                     * (as it might not have been set above), failing that use the user's name,
                     * failing THAT use a random string
                     */

                    if (!empty($socialUser->username)) {

                        $username = $socialUser->username;

                    } elseif ($requiredData['first_name'] || $requiredData['last_name']) {

                        $username = $requiredData['first_name'] . ' ' . $requiredData['last_name'];

                    } else {

                        $oDate    = Factory::factory('DateTime');
                        $username = 'user' . $oDate->format('YmdHis');
                    }

                    $basename = url_title($username, '-', true);
                    $requiredData['username'] = $basename;

                    $user = $oUserModel->getByUsername($requiredData['username']);

                    while ($user) {
                        $requiredData['username'] = increment_string($basename, '');
                        $user = $oUserModel->getByUsername($requiredData['username']);
                    }
                }

                // --------------------------------------------------------------------------

                //  Request data?
                if (!empty($requestData)) {
                    $this->requestData($requiredData, $provider->slug);
                }

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($oSession->userdata('referred_by')) {
                    $optionalData['referred_by'] = $oSession->userdata('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Merge data arrays
                $data = array_merge($requiredData, $optionalData);

                // --------------------------------------------------------------------------

                //  Create user
                $newUser = $oUserModel->create($data);

                if ($newUser) {

                    /**
                     * Welcome aboard, matey
                     * - Save provider details
                     * - Upload profile image if available
                     */

                    $this->oSocial->saveSession($newUser->id, $provider);

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
                                $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');

                                //  Save file to cache
                                $cacheFile = DEPLOY_CACHE_DIR . 'new-user-profile-image-' . $newUser->id;

                                if (@file_put_contents($cacheFile, (string) $oResponse->getBody)) {

                                    $_upload = $oCdn->objectCreate($cacheFile, 'profile-images', array());

                                    if ($_upload) {

                                        $data                = array();
                                        $data['profile_img'] = $_upload->id;

                                        $oUserModel->update($newUser->id, $data);

                                    } else {
                                        log_message('debug', 'Failed to upload user\'s profile image');
                                        log_message('debug', $oCdn->lastError());
                                    }
                                }
                            }

                        } catch (\Exception $e) {
                            log_message('debug', 'Failed to upload user\'s profile image');
                            log_message('debug', $e->getMessage());
                        }
                    }

                    // --------------------------------------------------------------------------

                    //  Aint that swell, all registered!Redirect!
                    $oUserModel->setLoginData($newUser->id);

                    // --------------------------------------------------------------------------

                    //  Create an event for this event
                    create_event('did_register', array('method' => $provider), $newUser->id);

                    // --------------------------------------------------------------------------

                    //  Redirect
                    $oSession->set_flashdata('success', lang('auth_social_register_ok', $newUser->first_name));

                    if (empty($this->data['return_to'])) {
                        $group       = $this->user_group_model->getById($newUser->group_id);
                        $redirectUrl = $group->registration_redirect ? $group->registration_redirect : $group->default_homepage;
                    } else {
                        $redirectUrl = $this->data['return_to'];
                    }

                    redirect($redirectUrl);

                } else {

                    //  Oh dear, something went wrong
                    $status  = 'error';
                    $message = '<strong>Sorry,</strong> something went wrong and your account could not be created.';
                    $oSession->set_flashdata($status, $message);

                    $redirectUrl = 'auth/login';

                    if ($this->data['return_to']) {
                        $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($redirectUrl);
                }

            } else {

                //  How unfortunate, registration is disabled. Redrect back to the login page
                $oSession->set_flashdata('error', lang('auth_social_register_disabled'));

                $redirectUrl = 'auth/login';

                if ($this->data['return_to']) {
                    $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                }

                redirect($redirectUrl);
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Handles requesting of additional data from the user
     * @param  array  &$requiredData An array of fields to request
     * @param  string $provider      The provider to use
     * @return void
     */
    protected function requestData(&$requiredData, $provider)
    {
        if ($this->input->post()) {

            $oFormValidation = Factory::service('FormValidation');

            if (isset($requiredData['email'])) {
                $oFormValidation->set_rules('email', 'email', 'trim|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]');
            }

            if (isset($requiredData['username'])) {
                $oFormValidation->set_rules('username', 'username', 'trim|required|is_unique[' . NAILS_DB_PREFIX . 'user.username]');
            }

            if (empty($requiredData['first_name'])) {
                $oFormValidation->set_rules('first_name', '', 'trim|required');
            }

            if (empty($requiredData['last_name'])) {
                $oFormValidation->set_rules('last_name', '', 'trim|required');
            }

            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_email_already_registered', site_url('auth/forgotten_password'))
                );
            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_username_already_registered', site_url('auth/forgotten_password'))
                );
            } else {
                $oFormValidation->set_message(
                    'is_unique',
                    lang('fv_identity_already_registered', site_url('auth/forgotten_password'))
                );
            }

            if ($oFormValidation->run()) {

                //  Valid!Ensure required data is set correctly then allow system to move on.
                if (isset($requiredData['email'])) {
                    $requiredData['email'] = $this->input->post('email', true);
                }

                if (isset($requiredData['username'])) {
                    $requiredData['username'] = $this->input->post('username', true);
                }

                if (empty($requiredData['first_name'])) {
                    $requiredData['first_name'] = $this->input->post('first_name', true);
                }

                if (empty($requiredData['last_name'])) {
                    $requiredData['last_name'] = $this->input->post('last_name', true);
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
                $this->requestDataForm($requiredData, $provider);
            }

        } else {
            $this->requestDataForm($requiredData, $provider);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the "request data" form
     * @param  array &$requiredData An array of fields to request
     * @param  string $provider     The provider being used
     * @return void
     */
    protected function requestDataForm(&$requiredData, $provider)
    {
        $this->data['required_data'] = $requiredData;
        $this->data['form_url']      = 'auth/login/' . $provider;

        if ($this->uri->segment(4) == 'register') {
            $this->data['form_url'] .= '/register';
        }

        if ($this->data['return_to']) {
            $this->data['form_url'] .= '?return_to=' . urlencode($this->data['return_to']);
        }

        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/register/social_request_data', $this->data);
        $oView->load('structure/footer/blank', $this->data);

        $oOutput = Factory::service('Output');
        echo $oOutput->get_output();
        exit();
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests aprpopriately
     * @return void
     */
    public function _remap()
    {
        $method = $this->uri->segment(3) ? $this->uri->segment(3) : 'index';

        if (method_exists($this, $method) && substr($method, 0, 1) != '_') {

            $this->{$method}();

        } else {

            //  Assume the 3rd segment is a login provider supported by Hybrid Auth
            if ($this->oSocial->isValidProvider($method)) {
                $this->socialSignon($method);
            } else {
                show_404();
            }
        }
    }
}
