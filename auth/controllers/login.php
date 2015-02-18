<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * User login facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Login extends NAILS_Auth_Controller
{
    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Load libraries
        $this->load->library('form_validation');
        $this->load->library('auth/social_signon');

        // --------------------------------------------------------------------------

        //  Where are we returning user to?
        $returnTo = $this->input->get('return_to');

        if ($returnTo) {

            $returnTo = preg_match('#^(http|https)\://#', $returnTo) ? $returnTo : site_url($returnTo);
            $returnTo = parse_url($returnTo);

            //  urlencode the query if there is one
            if (!empty($returnTo['query'])) {

                //  Break it apart and glue it together (urlencoded)
                $query = parse_str($returnTo['query'], $query_ar);
                $returnTo['query'] = http_build_query($query_ar);
            }

            $this->data['return_to']  = '';
            $this->data['return_to'] .= !empty($returnTo['scheme'])   ? $returnTo['scheme'] . '://' : 'http://';
            $this->data['return_to'] .= !empty($returnTo['host'])     ? $returnTo['host']           : site_url();
            $this->data['return_to'] .= !empty($returnTo['path'])     ? $returnTo['path']           : '';
            $this->data['return_to'] .= !empty($returnTo['query'])    ? '?' . $returnTo['query']    : '';

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
        if ($this->user_model->isLoggedIn()) {

            redirect($this->data['return_to']);
        }

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        if ($this->input->post()) {

            //  Validate input

            //  The rules vary depending on what login methods are enabled.
            switch (APP_NATIVE_LOGIN_USING) {

                case 'EMAIL':

                    $this->form_validation->set_rules('identifier', 'Email',    'required|xss_clean|trim|valid_email');
                    break;

                case 'USERNAME':

                    $this->form_validation->set_rules('identifier', 'Username', 'required|xss_clean|trim');
                    break;

                default:

                    $this->form_validation->set_rules('identifier', 'Username or Email',    'xss_clean|trim');
                    break;
            }

            //  Password is always required, obviously.
            $this->form_validation->set_rules('password', 'Password', 'required|xss_clean');
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('valid_email', lang('fv_valid_email'));

            if ($this->form_validation->run()) {

                //  Attempt the log in
                $identifier = $this->input->post('identifier');
                $password   = $this->input->post('password');
                $rememberMe = (bool) $this->input->post('remember');

                $user = $this->auth_model->login($identifier, $password, $rememberMe);

                if ($user) {

                    $this->_login($user, $rememberMe);

                } else {

                    //  Login failed
                    $this->data['error'] = $this->auth_model->last_error();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $this->data['social_signon_enabled']   = $this->social_signon->is_enabled();
        $this->data['social_signon_providers'] = $this->social_signon->get_providers('ENABLED');

        // --------------------------------------------------------------------------

        //  Load the views
        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/login/form', $this->data);
        $this->load->view('structure/footer', $this->data);
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
        if ($user->is_suspended) {

            $this->data['error'] = lang('auth_login_fail_suspended');
            return false;

        } elseif (!empty($user->temp_pw)) {

            /**
             * Temporary password detected, log user out and redirect to
             * temp password reset page.
             *
             * temp_pw will be an array containing the user's ID and hash
             *
             **/

            $query = array();

            if ($this->data['return_to']) {

                $query['return_to'] = $this->data['return_to'];
            }

            /**
             * Log the user out and remove the 'remember me' cookie - if we don't do this
             * then the password reset page will see a logged in user and go nuts
             * (i.e error).
             */

            if ($remember) {

                $query['remember'] = true;
            }

            $query = $query ? '?' . http_build_query($query) : '';

            $this->auth_model->logout();

            redirect('auth/reset_password/' . $user->id . '/' . md5($user->salt) . $query);

        } elseif ($this->config->item('authTwoFactorMode')) {

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
            switch ($this->config->item('authTwoFactorMode')) {

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

                $this->load->helper('date');

                $lastLogin = $this->config->item('auth_show_nicetime_on_login') ? niceTime(strtotime($user->last_login)) : toUserDatetime($user->last_login);

                if ($this->config->item('auth_show_last_ip_on_login')) {

                    $status  = 'message';
                    $message = lang('auth_login_ok_welcome_with_ip', array($user->first_name, $lastLogin, $user->last_ip));

                } else {

                    $status  = 'message';
                    $message = lang('auth_login_ok_welcome', array($user->first_name, $lastLogin));
                }

            } else {

                $status  = 'message';
                $message = lang('auth_login_ok_welcome_notime', array($user->first_name));
            }

            $this->session->set_flashdata($status, $message);

            $redirectUrl = $this->data['return_to'] ? $this->data['return_to'] : $user->group_homepage;

            // --------------------------------------------------------------------------

            //  Generate an event for this log in
            create_event('did_log_in', array('provider' => $provider), $user->id);

            // --------------------------------------------------------------------------

            redirect($redirectUrl);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user in using hashes of their user ID and password; easy way of
     * automatically logging a user in from the likes of an email.
     * @return  void
     **/
    public function with_hashes()
    {
        if (!$this->config->item('auth_enable_hashed_login')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $hash['id'] = $this->uri->segment(4);
        $hash['pw'] = $this->uri->segment(5);

        if (empty($hash['id']) || empty($hash['pw'])) {

            show_error($lang['auth_with_hashes_incomplete_creds']);
        }

        // --------------------------------------------------------------------------

        /**
         * If the user is already logged in we need to check to see if we check to see if they are
         * attempting to login as themselves, if so we redirect, otherwise we log them out and try
         * again using the hashes.
         */

        if ($this->user_model->isLoggedIn()) {

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

        $user = $this->user_model->get_by_hashes($hash['id'], $hash['pw']);

        // --------------------------------------------------------------------------

        if ($user) {

            //  User was verified, log the user in
            $this->user_model->setLoginData($user->id);

            // --------------------------------------------------------------------------

            //  Say hello
            if ($user->last_login) {

                $this->load->helper('date');

                $lastLogin = $this->config->item('auth_show_nicetime_on_login') ? niceTime(strtotime($user->last_login)) : toUserDatetime($user->last_login);

                if ($this->config->item('auth_show_last_ip_on_login')) {

                    $status  = 'message';
                    $message = lang('auth_login_ok_welcome_with_ip', array($user->first_name, $lastLogin, $user->last_ip));

                } else {

                    $status  = 'message';
                    $message = lang('auth_login_ok_welcome', array($user->first_name, $user->last_login));
                }

            } else {

                $status  = 'message';
                $message = lang('auth_login_ok_welcome_notime', array($user->first_name));
            }

            $this->session->set_flashdata($status, $message);

            // --------------------------------------------------------------------------

            //  Update their last login
            $this->user_model->updateLastLogin($user->id);

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
            $this->session->set_flashdata('error', lang('auth_with_hashes_autologin_fail'));
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
        //  Get the adapter, HybridAuth will handle the redirect
        $adapter  = $this->social_signon->authenticate($provider);
        $provider = $this->social_signon->get_provider($provider);

        // --------------------------------------------------------------------------

        //  Fetch the user's social profile and, if one exists, the local profile.
        try {

            $socialUser = $adapter->getUserProfile();

        } catch(Exception $e) {

            //  Failed to fetch from the provider, something must have gone wrong
            log_message('error', 'HybridAuth failed to fetch data from provider.');
            log_message('error', 'Error Code: ' . $e->getCode());
            log_message('error', 'Error Message: ' . $e->getMessage());

            if (empty($provider)) {

                $this->session->set_flashdata('error', '<strong>Sorry,</strong> there was a problem communicating with the network.');

            } else {

                $this->session->set_flashdata('error', '<strong>Sorry,</strong> there was a problem communicating with ' . $provider['label'] . '.');
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

        $user = $this->social_signon->get_user_by_provider_identifier($provider, $socialUser->identifier);

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

            if ($this->user_model->isLoggedIn() && activeUser('id') == $user->id) {

                /**
                 * Logged in user is already logged in and is the social user.
                 * Silly user, just redirect them to where they need to go.
                 */

                $this->session->set_flashdata('message', lang('auth_social_already_linked', $provider['label']));

                if ($this->data['return_to']) {

                    redirect($this->data['return_to']);

                } else {

                    redirect($user->group_homepage);
                }

            } elseif ($this->user_model->isLoggedIn() && activeUser('id') != $user->id) {

                /**
                 * Hmm, a user was found for this Provider ID, but it's not the actively logged
                 * in user. This means that this provider account is already registered.
                 */

                $this->session->set_flashdata('error', lang('auth_social_account_in_use', array($provider['label'], APP_NAME)));

                if ($this->data['return_to']) {

                    redirect($this->data['return_to']);

                } else {

                    redirect($user->group_homepage);
                }

            } else {

                //  Fab, user exists, try to log them in
                $this->user_model->setLoginData($user->id);
                $this->social_signon->save_session($user->id);

                if (!$this->_login($user)) {

                    $this->session->set_flashdata('error', $this->data['error']);

                    $redirectUrl = 'auth/login';

                    if ($this->data['return_to']) {

                        $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($redirectUrl);
                }
            }

        } elseif ($this_model->user->isLoggedIn()) {

            /**
             * User is logged in and it look's like the provider isn't being used by
             * anyone else. Go ahead and link the two accounts together.
             */

            if ($this->social_signon->save_session(activeUser('id'), $provider)) {

                create_event('did_link_provider',array('provider' => $provider));
                $this->session->set_flashdata('success', lang('auth_social_linked_ok', $provider['label']));

            } else {

                $this->session->set_flashdata('error', lang('auth_social_linked_fail', $provider['label']));
            }

            redirect($this->data['return_to']);

        } else {

            /**
             * Didn't find a user and the active user isn't logged in, assume they want
             * to regster an account. I mean, who wouldn't, this site is AwEsOmE.
             */

            if (app_setting('user_registration_enabled', 'app')) {

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

                    $this->requestData($requiredData, $provider);
                }

                /**
                 * We have everything we need to create the user account However, first we need to
                 * make sure that our data is valid and not in use. At this point it's not the
                 * user's fault so don't throw an error.
                 */

                //  Check email
                if (isset($requiredData['email'])) {

                    $check = $this->user_model->get_by_email($requiredData['email']);

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

                    $check = $this->user_model->get_by_username($requiredData['username']);

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

                    } elseif($requiredData['first_name'] || $requiredData['last_name']) {

                        $username = $requiredData['first_name'] . ' ' . $requiredData['last_name'];

                    } else {

                        $username = 'user' . date('YmdHis');
                    }

                    $basename = url_title($username, '-', true);
                    $requiredData['username'] = $basename;

                    $user = $this->user_model->get_by_username($requiredData['username']);

                    while ($user) {

                        $requiredData['username'] = increment_string($basename, '');
                        $user = $this->user_model->get_by_username($requiredData['username']);
                    }
                }

                // --------------------------------------------------------------------------

                //  Request data?
                if (!empty($requestData)) {

                    $this->requestData($requiredData, $provider);
                }

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($this->session->userdata('referred_by')) {

                    $optionalData['referred_by'] = $this->session->userdata('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Merge data arrays
                $data = array_merge($requiredData, $optionalData);

                // --------------------------------------------------------------------------

                //  Create user
                $newUser = $this->user_model->create($data);

                if ($newUser) {

                    /**
                     * Welcome aboard, matey
                     * - Save provider details
                     * - Upload profile image if available
                     */

                    $this->social_signon->save_session($newUser->id, $provider);

                    if (!empty($socialUser->photoURL)) {

                        //  Has profile image
                        $imgUrl = $socialUser->photoURL;

                    } elseif (!empty($newUser->email)) {

                        //  Attempt gravatar
                        $imgUrl = 'http://www.gravatar.com/avatar/' . md5($newUser->email) . '?d=404&s=2048&r=pg';
                    }

                    if (!empty($imgUrl)) {

                        //  Fetch the image
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_URL, $imgUrl);
                        $imgData = curl_exec($ch);

                        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {

                            //  Attempt upload
                            $this->load->library('cdn/cdn');

                            //  Save file to cache
                            $cacheFile = DEPLOY_CACHE_DIR . 'new-user-profile-image-' . $newUser->id;

                            if (@file_put_contents($cacheFile, $imgData)) {

                                $_upload = $this->cdn->object_create($cacheFile, 'profile-images', array());

                                if ($_upload) {

                                    $data                = array();
                                    $data['profile_img'] = $_upload->id;

                                    $this->user_model->update($newUser->id, $data);

                                } else {

                                    log_message('debug', 'Failed to uload user\'s profile image');
                                    log_message('debug', $this->cdn->last_error());
                                }
                            }
                        }
                    }

                    // --------------------------------------------------------------------------

                    //  Aint that swell, all registered!Redirect!
                    $this->user_model->setLoginData($newUser->id);

                    // --------------------------------------------------------------------------

                    //  Create an event for this event
                    create_event('did_register', array('method' => $provider),$newUser->id);

                    // --------------------------------------------------------------------------

                    //  Redirect
                    $this->session->set_flashdata('success', lang('auth_social_register_ok', $newUser->first_name));

                    /**
                     * Registrations will be forced to the registration redirect, regardless
                     * of what else has been set
                     */

                    $group     = $this->user_group_model->get_by_id($newUser->group_id);
                    $redirectUrl  = $group->registration_redirect ? $group->registration_redirect : $group->default_homepage;

                    redirect($redirectUrl);

                } else {

                    //  Oh dear, something went wrong
                    $status  = 'error';
                    $message = '<strong>Sorry,</strong> something went wrong and your account could not be created.';
                    $this->session->set_flashdata($status, $message);

                    $redirectUrl = 'auth/login';

                    if ($this->data['return_to']) {

                        $redirectUrl .= '?return_to=' . urlencode($this->data['return_to']);
                    }

                    redirect($redirectUrl);
                }

            } else {

                //  How unfortunate, registration is disabled. Redrect back to the login page
                $this->session->set_flashdata('error', lang('auth_social_register_disabled'));

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

            if (isset($requiredData['email'])) {

                $this->form_validation->set_rules('email', 'email', 'xss_clean|trim|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]');
            }

            if (isset($requiredData['username'])) {

                $this->form_validation->set_rules('username', 'username', 'xss_clean|trim|required|is_unique[' . NAILS_DB_PREFIX . 'user.username]');
            }

            if (empty($requiredData['first_name'])) {

                $this->form_validation->set_rules('first_name', '', 'xss_clean|trim|required');
            }

            if (empty($requiredData['last_name'])) {

                $this->form_validation->set_rules('last_name', '', 'xss_clean|trim|required');
            }

            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $this->form_validation->set_message('is_unique', lang('fv_email_already_registered', site_url('auth/forgotten_password')));

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $this->form_validation->set_message('is_unique', lang('fv_username_already_registered', site_url('auth/forgotten_password')));

            } else {

                $this->form_validation->set_message('is_unique', lang('fv_identity_already_registered', site_url('auth/forgotten_password')));
            }

            $this->load->library('form_validation');

            if ($this->form_validation->run()) {

                //  Valid!Ensure required data is set correctly then allow system to move on.
                if (isset($requiredData['email'])) {

                    $requiredData['email'] = $this->input->post('email');
                }

                if (isset($requiredData['username'])) {

                    $requiredData['username'] = $this->input->post('username');
                }

                if (empty($requiredData['first_name'])) {

                    $requiredData['first_name'] = $this->input->post('first_name');
                }

                if (empty($requiredData['last_name'])) {

                    $requiredData['last_name'] = $this->input->post('last_name');
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

        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/register/social_request_data', $this->data);
        $this->load->view('structure/footer', $this->data);
        echo $this->output->get_output();
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
            if ($this->social_signon->is_valid_provider($method)) {

                $this->socialSignon($method);

            } else {

                show_404();
            }
        }
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' AUTH MODULE
 *
 * The following block of code makes it simple to extend one of the core auth
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION')) {

    class Login extends NAILS_Login
    {
    }
}
