<?php

/**
 * This model contains all methods for interacting with users.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model;

use Nails\Factory;
use Nails\Common\Model\Base;

class User extends Base
{
    protected $me;
    protected $activeUser;
    protected $rememberCookie;
    protected $isRemembered;
    protected $isLoggedIn;

    // --------------------------------------------------------------------------

    /**
     * Construct the user model
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Set defaults
        $this->table              = NAILS_DB_PREFIX . 'user';
        $this->tablePrefix        = 'u';
        $this->tableSlugColumn    = 'username';
        $this->rememberCookie     = 'nailsrememberme';
        $this->adminRecoveryField = 'nailsAdminRecoveryData';
        $this->isRemembered       = null;

        // --------------------------------------------------------------------------

        //  Clear the activeUser
        $this->clearActiveUser();
    }

    // --------------------------------------------------------------------------

    /**
     * Initialise the generic user model
     * @return void
     */
    public function init()
    {
        //  Refresh user's session
        $this->refreshSession();

        // --------------------------------------------------------------------------

        //  If no user is logged in, see if there's a remembered user to be logged in
        if (!$this->isLoggedIn()) {

            $this->loginRememberedUser();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Log in a previously logged in user
     * @return boolean
     */
    protected function loginRememberedUser()
    {
        //  Is remember me functionality enabled?
        $this->config->load('auth/auth');

        if (!$this->config->item('authEnableRememberMe')) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Get the credentials from the cookie set earlier
        $remember = get_cookie($this->rememberCookie);

        if ($remember) {

            $remember = explode('|', $remember);
            $email    = isset($remember[0]) ? $remember[0] : null;
            $code     = isset($remember[1]) ? $remember[1] : null;

            if ($email && $code) {

                //  Look up the user so we can cross-check the codes
                $user = $this->getByEmail($email, true);

                if ($user && $code === $user->remember_code) {

                    //  User was validated, log them in!
                    $this->setLoginData($user->id);
                    $this->me = $user->id;
                }
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a value from the active user's session data
     * @param  string  $keys      The key to look up in activeUser
     * @param  string  $delimiter If multiple fields are requested they'll be joined by this string
     * @return mixed
     */
    public function activeUser($keys = '', $delimiter = ' ')
    {
        //  Only look for a value if we're logged in
        if (!$this->isLoggedIn()) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  If $keys is false just return the user object in its entirety
        if (empty($keys)) {

            return $this->activeUser;
        }

        // --------------------------------------------------------------------------

        //  If only one key is being requested then don't do anything fancy
        if (strpos($keys, ',') === false) {

            $val = isset($this->activeUser->{trim($keys)}) ? $this->activeUser->{trim($keys)} : null;

        } else {

            //  More than one key
            $keys = explode(',', $keys);
            $keys = array_filter($keys);
            $out  = array();

            foreach ($keys as $key) {

                //  If something is found, use that.
                if (isset($this->activeUser->{trim($key)})) {

                    $out[] = $this->activeUser->{trim($key)};
                }
            }

            //  If nothing was found, just return null
            if (empty($out)) {

                $val = null;

            } else {

                $val = implode($delimiter, $out);
            }
        }

        return $val;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the active user
     * @param stdClass $user The user obect to set
     */
    public function setActiveUser($user)
    {
        $this->activeUser = $user;

        // --------------------------------------------------------------------------

        //  Set the user's date/time formats
        $formatDate = $this->activeUser('pref_date_format');
        $formatDate = $formatDate ? $formatDate : $this->datetime_model->getDateFormatDefaultSlug();

        $formatTime = $this->activeUser('pref_time_format');
        $formatTime = $formatTime ? $formatTime : $this->datetime_model->getTimeFormatDefaultSlug();

        $this->datetime_model->setFormats($formatDate, $formatTime);
    }

    // --------------------------------------------------------------------------

    /**
     * Clear the acive user
     * @return void
     */
    public function clearActiveUser()
    {
        $this->activeUser = new \stdClass();
    }

    // --------------------------------------------------------------------------

    /**
     * Set the user's login data
     * @param mixed   $idEmail        The user's ID or email address
     * @param boolean $setSessionData Whether to set the session data or not
     */
    public function setLoginData($idEmail, $setSessionData = true)
    {
        //  Valid user?
        if (is_numeric($idEmail)) {

            $user  = $this->getById($idEmail);
            $error = 'Invalid User ID.';

        } elseif (is_string($idEmail)) {

            if (valid_email($idEmail)) {

                $user  = $this->getByEmail($idEmail);
                $error = 'Invalid User email.';

            } else {

                $this->setError('Invalid User email.');
                return false;
            }

        } else {

            $this->setError('Invalid user ID or email.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Test user
        if (!$user) {

            $this->setError($error);
            return false;

        } elseif ($user->is_suspended) {

            $this->setError('User is suspended.');
            return false;

        } else {

            //  Set the flag
            $this->isLoggedIn = true;

            //  Set session variables
            if ($setSessionData) {

                $sessionData = array(
                    'id'       => $user->id,
                    'email'    => $user->email,
                    'group_id' => $user->group_id,
                );
                $this->session->set_userdata($sessionData);
            }

            //  Set the active user
            $this->setActiveUser($user);

            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Clears the login data for a user
     * @return  void
     */
    public function clearLoginData()
    {
        //  Clear the session
        $this->session->unset_userdata('id');
        $this->session->unset_userdata('email');
        $this->session->unset_userdata('group_id');

        //  Set the flag
        $this->isLoggedIn = false;

        //  Reset the activeUser
        $this->clearActiveUser();

        //  Remove any rememebr me cookie
        $this->clearRememberCookie();
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is logged in or not.
     * @return  bool
     */
    public function isLoggedIn()
    {
        return $this->isLoggedIn;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is to be remembered
     * @return  bool
     */
    public function isRemembered()
    {
        //  Deja vu?
        if (!is_null($this->isRemembered)) {

            return $this->isRemembered;
        }

        // --------------------------------------------------------------------------

        /**
         * Look for the remember me cookie and explode it, if we're landed with a 2
         * part array then it's likely this is a valid cookie - however, this test
         * is, obviously, not gonna detect a spoof.
         */

        $cookie = get_cookie($this->rememberCookie);
        $cookie = explode('|', $cookie);

        $this->isRemembered = count($cookie) == 2 ? true : false;

        return $this->isRemembered;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user group has admin permissions.
     * @param  mixed   $user The user to check, uses activeUser if null
     * @return boolean
     */
    public function isAdmin($user = null)
    {
        return $this->hasPermission('admin:.+', $user);
    }

    // --------------------------------------------------------------------------

    /**
     * When an admin 'logs in as' another user a hash is added to the session so
     * the system can log them back in. This method is simply a quick and logical
     * way of checking if the session variable exists.
     * @return boolean
     */
    public function wasAdmin()
    {
        return (bool) $this->session->userdata($this->adminRecoveryField);
    }

    // --------------------------------------------------------------------------

    /**
     * Adds to the admin recovery array, allowing suers to login as other users multiple times, and come back
     * @param integer $loggingInAs The ID of the user who is being immitated
     * @param string  $returnTo    Where to redirect the user when they log back in
     */
    public function setAdminRecoveryData($loggingInAs, $returnTo = '')
    {
        //  Look for existing Recovery Data
        $existingRecoveryData = $this->session->userdata($this->adminRecoveryField);

        if (empty($existingRecoveryData)) {

            $existingRecoveryData = array();
        }

        //  Prepare the new element
        $adminRecoveryData            = new \stdClass();
        $adminRecoveryData->oldUserId = activeUser('id');
        $adminRecoveryData->newUserId = $loggingInAs;
        $adminRecoveryData->hash      = md5(activeUser('password'));
        $adminRecoveryData->name      = activeUser('first_name,last_name');
        $adminRecoveryData->email     = activeUser('email');
        $adminRecoveryData->returnTo  = empty($returnTo) ? $this->input->server('REQUEST_URI') : $returnTo;

        $adminRecoveryData->loginUrl  = 'auth/override/login_as/';
        $adminRecoveryData->loginUrl .= md5($adminRecoveryData->oldUserId) . '/' . $adminRecoveryData->hash;
        $adminRecoveryData->loginUrl .= '?returningAdmin=1';
        $adminRecoveryData->loginUrl  = site_url($adminRecoveryData->loginUrl);

        //  Put the new session onto the stack and save to the session
        $existingRecoveryData[] = $adminRecoveryData;

        $this->session->set_userdata($this->adminRecoveryField, $existingRecoveryData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the recovery data at the bottom of the stack, i.e the most recently added
     * @return stdClass
     */
    public function getAdminRecoveryData()
    {
        $existingRecoveryData = $this->session->userdata($this->adminRecoveryField);

        if (empty($existingRecoveryData)) {

            return array();

        } else {

            return end($existingRecoveryData);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Removes the most recently added recovery data from the stack
     * @return void
     */
    public function unsetAdminRecoveryData()
    {
        $existingRecoveryData = $this->session->userdata($this->adminRecoveryField);

        if (empty($existingRecoveryData)) {

            $existingRecoveryData = array();

        } else {

            array_pop($existingRecoveryData);
        }

        $this->session->set_userdata($this->adminRecoveryField, $existingRecoveryData);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is a superuser. Extend this method to
     * alter it's response.
     * @param  mixed   $user The user to check, uses activeUser if null
     * @return boolean
     */
    public function isSuperuser($user = null)
    {
        return $this->hasPermission('admin:superuser', $user);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the specified user has a certain ACL permission
     * @param   string  $search The permission to check for
     * @param   mixed   $user   The user to check for; if null uses activeUser, if numeric, fetches suer, if object uses that object
     * @return  boolean
     */
    public function hasPermission($search, $user = null)
    {
        //  Fetch the correct ACL
        if (is_numeric($user)) {

            $userObject = $this->getById($user);

            if (isset($userObject->acl)) {

                $acl = $userObject->acl;
                unset($userObject);

            } else {

                return false;
            }

        } elseif (isset($user->acl)) {

            $acl = $user->acl;

        } else {

            $acl = $this->activeUser('acl');
        }

        if (!$acl) {

            return false;
        }

        // --------------------------------------------------------------------------

        // Super users or CLI users can do anything their heart's desire
        if (in_array('admin:superuser', $acl) || $this->input->is_cli_request()) {

            return true;
        }

        // --------------------------------------------------------------------------

        /**
         * Test the ACL
         * We're going to use regular experessios here so we can allow for some
         * flexability in the search, i.e admin:* would return true if the user has
         * access to any of admin.
         */

        $hasPermission = false;
        foreach ($acl as $permission) {

            $pattern = '/^' . $search . '$/';
            $match   = preg_match($pattern, $permission);

            if ($match) {

                $hasPermission = true;
                break;
            }
        }

        return $hasPermission;
    }

    // --------------------------------------------------------------------------

    /**
     * This method applies the conditionals which are common across the get_*()
     * methods and the count() method.
     * @param string $data Data passed from the calling method
     * @return void
     */
    protected function getCountCommon($data = array())
    {
        //  If there's a search term, then we better get %LIKING%
        if (!empty($data['keywords'])) {

            if (empty($data['or_like'])) {

                $data['or_like'] = array();
            }

            $keywordAsId = (int) preg_replace('/[^0-9]/', '', $data['keywords']);

            if ($keywordAsId) {

                $data['or_like'][] = array(
                    'column' => 'u.id',
                    'value'  => $keywordAsId
                );
            }
            $data['or_like'][] = array(
                'column' => 'ue.email',
                'value'  => $data['keywords']
            );
            $data['or_like'][] = array(
                'column' => 'u.username',
                'value'  => $data['keywords']
            );
            $data['or_like'][] = array(
                'column' => array('u.first_name', 'u.last_name'),
                'value'  => $data['keywords']
            );
        }

        // --------------------------------------------------------------------------

        //  Let the parent method handle sorting, etc
        parent::getCountCommon($data);

        // --------------------------------------------------------------------------

        //  Define the selects
        $this->db->select($this->tablePrefix . '.*');
        $this->db->select('ue.email,ue.code email_verification_code,ue.is_verified email_is_verified');
        $this->db->select('ue.date_verified email_is_verified_on');
        $this->db->select($this->getMetaColumns('um'));
        $this->db->select('ug.slug group_slug,ug.label group_name,ug.default_homepage group_homepage,ug.acl group_acl');

        // --------------------------------------------------------------------------

        //  Define the joins
        $this->db->join(
            NAILS_DB_PREFIX . 'user_email ue',
            $this->tablePrefix . '.id = ue.user_id AND ue.is_primary = 1',
            'LEFT'
        );

        $this->db->join(
            NAILS_DB_PREFIX . 'user_meta_app um',
            $this->tablePrefix . '.id = um.user_id',
            'LEFT'
        );

        $this->db->join(
            NAILS_DB_PREFIX . 'user_group ug',
            $this->tablePrefix . '.group_id = ug.id',
            'LEFT'
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Defines the list of columns in the `user` table
     * @param  string $sPrefix The prefix to add to the columns
     * @param  array  $aCols   Any additional columns to add
     * @return array
     */
    protected function getUserColumns($sPrefix = '', $aCols = array())
    {
        $aCols   = array();
        $aCols[] = 'group_id';
        $aCols[] = 'ip_address';
        $aCols[] = 'last_ip';
        $aCols[] = 'password';
        $aCols[] = 'password_md5';
        $aCols[] = 'password_engine';
        $aCols[] = 'password_changed';
        $aCols[] = 'salt';
        $aCols[] = 'forgotten_password_code';
        $aCols[] = 'remember_code';
        $aCols[] = 'created';
        $aCols[] = 'last_login';
        $aCols[] = 'last_seen';
        $aCols[] = 'is_suspended';
        $aCols[] = 'temp_pw';
        $aCols[] = 'failed_login_count';
        $aCols[] = 'failed_login_expires';
        $aCols[] = 'last_update';
        $aCols[] = 'user_acl';
        $aCols[] = 'login_count';
        $aCols[] = 'referral';
        $aCols[] = 'referred_by';
        $aCols[] = 'salutation';
        $aCols[] = 'first_name';
        $aCols[] = 'last_name';
        $aCols[] = 'gender';
        $aCols[] = 'dob';
        $aCols[] = 'profile_img';
        $aCols[] = 'timezone';
        $aCols[] = 'datetime_format_date';
        $aCols[] = 'datetime_format_time';
        $aCols[] = 'language';

        return $this->prepareDbColumns($sPrefix, $aCols);
    }

    // --------------------------------------------------------------------------

    /**
     * Defines the list of columns in the `user_meta_app` table
     * @param  string $sPrefix The prefix to add to the columns
     * @param  array  $aCols   Any additional columns to add
     * @return array
     */
    protected function getMetaColumns($sPrefix = '', $aCols = array())
    {
        return $this->prepareDbColumns($sPrefix, $aCols);
    }

    // --------------------------------------------------------------------------

    /**
     * Filter out duplicates and prefix column names if nessecary
     * @var string
     */
    protected function prepareDbColumns($sPrefix = '', $aCols = array())
    {
        //  Clean up
        $aCols = array_unique($aCols);
        $aCols = array_filter($aCols);

        //  Prefix all the values, if needed
        if ($sPrefix) {

            foreach ($aCols as $key => &$value) {

                $value = $sPrefix . '.' . $value;
            }
        }

        return $aCols;
    }

    // --------------------------------------------------------------------------

    /**
     * Look up a user by their identifier
     * @param  string $identifier The user's identifier, either an email address or a username
     * @return mixed              false on failure, stdClass on success
     */
    public function getByIdentifier($identifier)
    {
        Factory::helper('email');

        switch (APP_NATIVE_LOGIN_USING) {

            case 'EMAIL':

                $user = $this->getByEmail($identifier);
                break;

            case 'USERNAME':

                $user = $this->getByUsername($identifier);
                break;

            default:

                if (valid_email($identifier)) {

                    $user = $this->getByEmail($identifier);

                } else {

                    $user = $this->getByUsername($identifier);
                }
                break;
        }

        return $user;
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their email address
     * @param  string $email The user's email address
     * @return mixed         stdClass on success, false on failure
     */
    public function getByEmail($email)
    {
        if (!is_string($email)) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Look up the email, and if we find an ID then fetch that user
        $this->db->select('user_id');
        $this->db->where('email', trim($email));
        $user = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

        if ($user) {

            return $this->getById($user->user_id);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their username
     * @param  string $username The user's username
     * @return mixed            stdClass on success, false on failure
     */
    public function getByUsername($username)
    {
        if (!is_string($username)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $data = array(
            'where' => array(
                array(
                    'column' => $this->tablePrefix . '.username',
                    'value'  => $username
                )
            )
        );

        $user = $this->getAll(null, null, $data);

        return empty($user) ? false : $user[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Get a specific user by a MD5 hash of their ID and password
     * @param  string $md5Id The MD5 hash of their ID
     * @param  string $md5Pw The MD5 hash of their password
     * @return mixed         stdClass on success, false on failure
     */
    public function getByHashes($md5Id, $md5Pw)
    {
        if (empty($md5Id) || empty($md5Pw)) {

            return false;
        }

        $data = array(
            'where' => array(
                array(
                    'column' => $this->tablePrefix . '.id_md5',
                    'value'  => $md5Id
                ),
                array(
                    'column' => $this->tablePrefix . '.password_md5',
                    'value'  => $md5Pw
                )
            )
        );

        $user = $this->getAll(null, null, $data);

        return empty($user) ? false : $user[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their referral code
     * @param  string $referralCode The user's referral code
     * @return mixed                stdClass on success, false on failure
     */
    public function getByReferral($referralCode)
    {
        if (!is_string($referralCode)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $data = array(
            'where' => array(
                array(
                    'column' => $this->tablePrefix . '.referral',
                    'value'  => $referralCode
                )
            )
        );

        $user = $this->getAll(null, null, $data);

        return empty($user) ? false : $user[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Get all the email addresses which are registered to a particular user ID
     * @param  integer $id The user's ID
     * @return array
     */
    public function get_emails_for_user($id)
    {
        $this->db->where('user_id', $id);
        $this->db->order_by('is_primary', 'DESC');
        $this->db->order_by('email', 'ASC');
        return $this->db->get(NAILS_DB_PREFIX . 'user_email')->result();
    }

    // --------------------------------------------------------------------------

    /**
     * Searches for objects, optionally paginated.
     * @param  string    $sKeywords       The search term
     * @param  int       $iPage           The page number of the results, if null then no pagination
     * @param  int       $iPerPage        How many items per page of paginated results
     * @param  mixed     $aData           Any data to pass to getCountCommon()
     * @param  bool      $bIncludeDeleted If non-destructive delete is enabled then this flag allows you to include deleted items
     * @return \stdClass
     */
    public function search($sKeywords, $iPage = null, $iPerPage = null, $aData = array(), $bIncludeDeleted = false)
    {
        $aData['keywords'] = $sKeywords;

        $oOut          = new \stdClass();
        $oOut->page    = $iPage;
        $oOut->perPage = $iPerPage;
        $oOut->total   = $this->countAll($aData);
        $oOut->results = $this->getAll($iPage, $iPerPage, $aData);

        return $oOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Update a user, if $user_id is not set method will attempt to update the
     * active user. If $data is passed then the method will attempt to update
     * the user and/or user_meta_* tables
     * @return  integer $id   The ID of the user to update
     * @return  array   $data Any data to be updated
     */
    public function update($user_id = null, $data = null)
    {
        $oDate = Factory::factory('DateTime');
        $data  = (array) $data;
        $_uid  = $this->getUserId($user_id);
        if (empty($_uid)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $oUser = $this->getById($_uid);
        if (empty($oUser)) {

            $this->setError('Invalid user ID');
            return false;
        }

        // --------------------------------------------------------------------------


        //  If there's some data we'll need to know the columns of `user`
        //  We also want to unset any 'dangerous' items then set it for the query

        if ($data) {

            //  Set the cols in `user` (rather than querying the DB)
            $_cols = $this->getUserColumns();

            //  Safety first, no updating of user's ID.
            unset($data->id);
            unset($data->id_md5);

            //  If we're updating the user's password we should generate a new hash
            if (array_key_exists('password', $data)) {

                $_hash = $this->user_password_model->generateHash($oUser->group_id, $data['password']);

                if (!$_hash) {

                    $this->setError($this->user_password_model->lastError());
                    return false;
                }

                $data['password']         = $_hash->password;
                $data['password_md5']     = $_hash->password_md5;
                $data['password_engine']  = $_hash->engine;
                $data['password_changed'] = $oDate->format('Y-m-d H:i:s');
                $data['salt']             = $_hash->salt;

                $_password_updated = true;

            } else {

                $_password_updated = false;
            }

            //  Set the data
            $_data_user             = array();
            $_data_meta             = array();
            $_data_email            = '';
            $_data_username         = '';
            $dataResetMfaQuestion   = false;
            $dataResetMfaDevice     = false;

            foreach ($data as $key => $val) {

                //  user or user_meta?
                if (array_search($key, $_cols) !== false) {

                    //  Careful now, some items cannot be blank and must be null
                    switch ($key) {

                        case 'profile_img':

                            $_data_user[$key] = $val ? $val : null;
                            break;

                        default:

                            $_data_user[$key] = $val;
                            break;
                    }

                } elseif ($key == 'email') {

                    $_data_email = strtolower(trim($val));

                } elseif ($key == 'username') {

                    $_data_username = strtolower(trim($val));

                } elseif ($key == 'reset_mfa_question') {

                    $dataResetMfaQuestion = $val;

                } elseif ($key == 'reset_mfa_device') {

                    $dataResetMfaDevice = $val;

                } else {

                    $_data_meta[$key] = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  If a username has been passed then check if it's available
            if (!empty($_data_username)) {

                //  Check username is valid
                if (!$this->isValidUsername($_data_username, true, $_uid)) {

                    return false;

                } else {

                    $_data_user['username'] = $_data_username;
                }
            }

            // --------------------------------------------------------------------------

            //  Begin transaction
            $_rollback = false;
            $this->db->trans_begin();

            // --------------------------------------------------------------------------

            //  Resetting security questions?
            $this->config->load('auth/auth');

            if ($this->config->item('authTwoFactorMode') == 'QUESTION' && $dataResetMfaQuestion) {

                $this->db->where('user_id', (int) $_uid);
                if (!$this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question')) {

                    /**
                     * Rollback immediately in case there's email or password changes which
                     * might send an email.
                     */

                    $this->db->trans_rollback();

                    $this->setError('could not reset user\'s Multi Factor Authentication questions.');

                    return false;
                }

            } elseif ($this->config->item('authTwoFactorMode') == 'DEVICE' && $dataResetMfaDevice) {

                $this->db->where('user_id', (int) $_uid);
                if (!$this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')) {

                    /**
                     * Rollback immediately in case there's email or password changes which
                     * might send an email.
                     */

                    $this->db->trans_rollback();

                    $this->setError('could not reset user\'s Multi Factor Authentication device.');

                    return false;
                }
            }

            // --------------------------------------------------------------------------

            //  Update the user table
            $this->db->where('id', (int) $_uid);
            $this->db->set('last_update', 'NOW()', false);

            if ($_data_user) {

                $this->db->set($_data_user);
            }

            $this->db->update(NAILS_DB_PREFIX . 'user');

            // --------------------------------------------------------------------------

            //  Update the meta table
            if ($_data_meta) {

                $this->db->where('user_id', (int) $_uid);
                $this->db->set($_data_meta);
                $this->db->update(NAILS_DB_PREFIX . 'user_meta_app');
            }

            // --------------------------------------------------------------------------

            //  If an email has been passed then attempt to update the user's email too
            if ($_data_email) {

                if (valid_email($_data_email)) {

                    //  Check if the email is already being used
                    $this->db->where('email', $_data_email);
                    $_email = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

                    if ($_email) {

                        /**
                         * Email is in use, if it's in use by the ID of this user then set
                         * it as the primary email for this account. If it's in use by
                         * another user then error
                         */

                        if ($_email->user_id == $_uid) {

                            $this->email_make_primary($_email->email);

                        } else {

                            $this->setError('Email is already in use.');
                            $_rollback = true;
                        }

                    } else {

                        /**
                         * Doesn't appear to be in use, add as a new email address and
                         * make it the primary one
                         */

                        $this->email_add($_data_email, (int) $_uid, true);
                    }

                } else {

                    //  Error, not a valid email; roll back transaction
                    $this->setError('"' . $_data_email . '" is not a valid email address.');
                    $_rollback = true;
                }
            }

            // --------------------------------------------------------------------------

            //  How'd we get on?
            if (!$_rollback && $this->db->trans_status() !== false) {

                $this->db->trans_commit();

                // --------------------------------------------------------------------------

                //  If the user's password was updated send them a notification
                if ($_password_updated) {

                    $_email                     = new \stdClass();
                    $_email->type               = 'password_updated';
                    $_email->to_id              = $_uid;
                    $_email->data               = array();
                    $_email->data['updated_at'] = $oDate->format('Y-m-d H:i:s');
                    $_email->data['updated_by'] = array(
                        'id' => $this->activeUser('id'),
                        'name' => $this->activeUser('first_name,last_name')
                    );
                    $_email->data['ip_address'] = $this->input->ipAddress();

                    $this->emailer->send($_email, true);
                }

            } else {

                $this->db->trans_rollback();
                return false;
            }

        } else {

            /**
             * If there was no data then run an update anyway on just user table. We need to
             * do this as some methods will use $this->db->set() before calling update(); not
             * sure if this is a bad design or not... sorry.
             */

            $this->db->set('last_update', 'NOW()', false);
            $this->db->where('id', (int) $_uid);
            $this->db->update(NAILS_DB_PREFIX . 'user');
        }

        // --------------------------------------------------------------------------

        //  If we just updated the active user we should probably update their session info
        if ($_uid == $this->activeUser('id')) {

            $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

            if ($data) {

                foreach ($data as $key => $val) {

                    $this->activeUser->{$key} = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  Do we need to update any timezone/date/time preferences?
            if (isset($data['timezone'])) {

                $this->datetime_model->setUserTimezone($data['timezone']);

            }

            if (isset($data['datetime_format_date'])) {

                $this->datetime_model->setDateFormat($data['datetime_format_date']);

            }

            if (isset($data['datetime_format_time'])) {

                $this->datetime_model->setTimeFormat($data['datetime_format_time']);

            }

            // --------------------------------------------------------------------------

            //  If there's a remember me cookie then update that too, but only if the password
            //  or email address has changed

            if ((isset($data['email']) || !empty($_password_updated)) && $this->isRemembered()) {

                $this->setRememberCookie();

            }

        }

        $this->setCacheUser($_uid);

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Works out the correct user ID, falls back to activeUser()
     * @param  integer $iUserId The user ID to use
     * @return integer
     */
    protected function getUserId($iUserId = null)
    {
        if (!empty($iUserId)) {

            $iUid = $iUserId;

        } elseif ($this->activeUser('id')) {

            $iUid = $this->activeUser('id');

        } else {

            $this->setError('No user ID set');
            return false;
        }

        return $iUid;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a user from the cache
     * @param  int   $userId The ID of the user to return
     * @return mixed
     */
    public function getCacheUser($userId)
    {
        return $this->getCache('user-' . $userId);
    }

    // --------------------------------------------------------------------------

    /**
     * Saves a user object to the persistent cache
     * @param int $userId The user ID to cache
     * @return boolean
     */
    public function setCacheUser($userId)
    {
        $user = $this->getById($userId, false, true);

        if (empty($user)) {

            return false;
        }

        $this->setCacheUserObj($user);

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets the user's object into the cache
     * @param  stdClass $userObj The complete user Object to cache
     * @return void
     */
    protected function setCacheUserObj($userObj)
    {
        $this->setCache('user-' . $userObj->id, $userObj);
    }

    // --------------------------------------------------------------------------

    /**
     * Removes a user object from the persistent cache
     * @param int $userId The user ID to remove from cache
     * @return void
     */
    public function unsetCacheUser($userId)
    {
        $this->unsetCache('user-' . $userId);
    }

    // --------------------------------------------------------------------------

    /**
     * Adds a new email to the user_email table. Will optionally send the verification email, too.
     * @param  string  $email       The email address to add
     * @param  int     $user_id     The ID of the user to add for, defaults to $this->activeUser('id')
     * @param  boolean $is_primary  Whether or not the email address should be the primary email address for the user
     * @param  boolean $is_verified Whether ot not the email should be marked as verified
     * @param  boolean $send_email  If unverified, whether or not the verification email should be sent
     * @return mixed                String containing verification code on success, false on failure
     */
    public function email_add($email, $user_id = null, $is_primary = false, $is_verified = false, $send_email = true)
    {
        $_user_id   = empty($user_id) ? $this->activeUser('id') : $user_id;
        $_email     = trim(strtolower($email));
        $_u         = $this->getById($_user_id);

        if (!$_u) {

            $this->setError('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Make sure the email is valid
        if (!valid_email($_email)) {

            $this->setError('"' . $_email . '" is not a valid email address');
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * Test email, if it's in use and for the same user then return true. If it's
         * in use by a different user then return an error.
         */

        $this->db->select('id, user_id, is_verified, code');
        $this->db->where('email', $_email);
        $_test = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

        if ($_test) {

            if ($_test->user_id == $_u->id) {

                /**
                 * In use, but belongs to the same user - return the code (imitates
                 * behavior of newly added email)
                 */

                if ($is_primary) {

                    $this->email_make_primary($_test->id);
                }

                //  Resend verification email?
                if ($send_email && !$_test->is_verified) {

                    $this->email_add_send_verify($_test->id);
                }

                //  Recache the user
                $this->setCacheUser($_u->id);

                return $_test->code;

            } else {

                //  In use, but belongs to another user
                $this->setError('Email in use by another user.');
                return false;
            }
        }

        // --------------------------------------------------------------------------

        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $_code          = $oPasswordModel->salt();

        $this->db->set('user_id', $_u->id);
        $this->db->set('email', $_email);
        $this->db->set('code', $_code);
        $this->db->set('is_verified', (bool) $is_verified);
        $this->db->set('date_added', 'NOW()', false);

        if ((bool) $is_verified) {

            $this->db->set('date_verified', 'NOW()', false);
        }

        $this->db->insert(NAILS_DB_PREFIX . 'user_email');

        if ($this->db->affected_rows()) {

            //  Email ID
            $_email_id = $this->db->insert_id();

            //  Make it the primary email address?
            if ($is_primary) {

                $this->email_make_primary($_email_id);
            }

            //  Send off the verification email
            if ($send_email && !$is_verified) {

                $this->email_add_send_verify($_email_id);
            }

            //  Recache the user
            $this->setCacheUser($_u->id);

            //  Update the activeUser
            if ($_u->id == $this->activeUser('id')) {

                $oDate = Factory::factory('DateTime');
                $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

                if ($is_primary) {

                    $this->activeUser->email = $_email;
                    $this->activeUser->email_verification_code = $_code;
                    $this->activeUser->email_is_verified = (bool) $is_verified;
                    $this->activeUser->email_is_verified_on = (bool) $is_verified ? $oDate->format('Y-m-d H:i:s') : null;
                }
            }

            //  Return the code
            return $_code;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Send, or resend, the verify email for a particular email address
     * @param  integer $email_id The email's ID
     * @param  integer $user_id  The user's ID
     * @return boolean
     */
    public function email_add_send_verify($email_id, $user_id = null)
    {
        //  Fetch the email and the user's group
        $this->db->select('ue.id,ue.code,ue.is_verified,ue.user_id,u.group_id');

        if (is_numeric($email_id)) {

            $this->db->where('ue.id', $email_id);

        } else {

            $this->db->where('ue.email', $email_id);

        }

        if (!empty($user_id)) {

            $this->db->where('ue.user_id', $user_id);

        }

        $this->db->join(NAILS_DB_PREFIX . 'user u', 'u.id = ue.user_id');

        $_e = $this->db->get(NAILS_DB_PREFIX . 'user_email ue')->row();

        if (!$_e) {

            $this->setError('Invalid Email.');
            return false;

        }

        if ($_e->is_verified) {

            $this->setError('Email is already verified.');
            return false;

        }

        // --------------------------------------------------------------------------

        $_email                  = new \stdClass();
        $_email->type            = 'verify_email_' . $_e->group_id;
        $_email->to_id           = $_e->user_id;
        $_email->data            = array();
        $_email->data['user_id'] = $_e->user_id;
        $_email->data['code']    = $_e->code;

        if (!$this->emailer->send($_email, true)) {

            //  Failed to send using the group email, try using the generic email template
            $_email->type = 'verify_email';

            if (!$this->emailer->send($_email, true)) {

                //  Email failed to send, for now, do nothing.
                $this->setError('The verification email failed to send.');
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes a non-primary email from the user_email table, optionally filtering
     * by $user_id
     * @param  mixed $email_id The email address, or the ID of the email address to remove
     * @param  int $user_id    The ID of the user to restrict to
     * @return boolean
     */
    public function email_delete($email_id, $user_id = null)
    {
        if (is_numeric($email_id)) {

            $this->db->where('id', $email_id);

        } else {

            $this->db->where('email', $email_id);
        }

        if (!empty($user_id)) {

            $this->db->where('user_id', $user_id);
        }

        $this->db->where('is_primary', false);
        $this->db->delete(NAILS_DB_PREFIX . 'user_email');

        if ((bool) $this->db->affected_rows()) {

            //  @todo: update the activeUser if required
            $this->setCacheUser($user_id);
            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------


    /**
     * Verifies whether the supplied $code is valid for the requested user ID or email
     * address. If it is then the email is marked as verified.
     * @param  mixed  $id_email The numeric ID of the user, or the email address
     * @param  string $code     The verification code as generated by email_add()
     * @return boolean
     */
    public function email_verify($idEmail, $code)
    {
        //  Check user exists
        if (is_numeric($idEmail)) {

            $user = $this->getById($idEmail);

        } else {

            $user = $this->getByEmail($idEmail);
        }

        if (!$user) {

            $this->setError('User does not exist.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Check if email has already been verified
        $this->db->where('user_id', $user->id);
        $this->db->where('is_verified', true);
        $this->db->where('code', $code);

        if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

            $this->setError('Email has already been verified.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Go ahead and set as verified
        $this->db->set('is_verified', true);
        $this->db->set('date_verified', 'NOW()', false);
        $this->db->where('user_id', $user->id);
        $this->db->where('is_verified', false);
        $this->db->where('code', $code);

        $this->db->update(NAILS_DB_PREFIX . 'user_email');

        if ((bool) $this->db->affected_rows()) {

            $this->setCacheUser($user->id);

            //  Update the activeUser
            if ($_u->id == $this->activeUser('id')) {

                $oDate = Factory::factory('DateTime');
                $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

                //  @todo: update the rest of the activeUser
            }

            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets an email address as the primary email address for that user.
     * @param  mixed $id_email The numeric  ID of the email address, or the email address itself
     * @param  int   $user_id  Specify the user ID which this should apply to
     * @return boolean
     */
    public function email_make_primary($id_email, $user_id = null)
    {
        //  Fetch email
        $this->db->select('id,user_id,email');

        if (is_numeric($id_email)) {

            $this->db->where('id', $id_email);

        } else {

            $this->db->where('email', $id_email);
        }

        if (!is_null($user_id)) {

            $this->db->where('user_id', $user_id);
        }

        $_email = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

        if (!$_email) {

            return false;
        }

        //  Update
        $this->db->trans_begin();

        $this->db->set('is_primary', false);
        $this->db->where('user_id', $_email->user_id);
        $this->db->update(NAILS_DB_PREFIX . 'user_email');

        $this->db->set('is_primary', true);
        $this->db->where('id', $_email->id);
        $this->db->update(NAILS_DB_PREFIX . 'user_email');

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {

            $this->db->trans_rollback();
            return false;

        } else {

            $this->db->trans_commit();
            $this->setCacheUser($_email->user_id);

            //  Update the activeUser
            if ($_email->user_id == $this->activeUser('id')) {

                $oDate = Factory::factory('DateTime');
                $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

                //  @todo: update the rest of the activeUser
            }

            return true;

        }
    }

    // --------------------------------------------------------------------------

    /**
     * Increment the user's failed logins
     * @param  integer $user_id The user ID to increment
     * @param  integer $expires How long till the block, if the threshold is reached, expires.
     * @return boolean
     */
    public function incrementFailedLogin($user_id, $expires = 300)
    {
        $oDate = Factory::factory('DateTime');
        $oDate->add(new \DateInterval('PT' . $expires . 'S'));

        $this->db->set('failed_login_count', '`failed_login_count`+1', false);
        $this->db->set('failed_login_expires', $oDate->format('Y-m-d H:i:s'));
        return $this->update($user_id);
    }

    // --------------------------------------------------------------------------

    /**
     * Reset a user's failed login
     * @param  integer $user_id The user ID to reset
     * @return boolean
     */
    public function resetFailedLogin($user_id)
    {
        $this->db->set('failed_login_count', 0);
        $this->db->set('failed_login_expires', 'null', false);
        return $this->update($user_id);
    }

    // --------------------------------------------------------------------------

    /**
     * Update a user's `last_login` field
     * @param  integer $user_id The user ID to update
     * @return boolean
     */
    public function updateLastLogin($user_id)
    {
        $this->db->set('last_login', 'NOW()', false);
        $this->db->set('login_count', 'login_count+1', false);
        return $this->update($user_id);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the user's 'rememberMe' cookie, nom nom nom
     * @param  integer $id      The User's ID
     * @param  string $password The user's password, hashed
     * @param  string $email    The user's email\
     * @return boolean
     */
    public function setRememberCookie($id = null, $password = null, $email = null)
    {
        //  Is remember me functionality enabled?
        $this->config->load('auth/auth');

        if (!$this->config->item('authEnableRememberMe')) {

            return false;
        }

        // --------------------------------------------------------------------------

        if (!$id || !$password || !$email) {

            if (!activeUser('id') ||  !activeUser('password') || !activeUser('email')) {

                return false;

            } else {

                $id       = $this->activeUser('id');
                $password = $this->activeUser('password');
                $email    = $this->activeUser('email');
            }
        }

        // --------------------------------------------------------------------------

        //  Generate a code to remember the user by and save it to the DB
        $_salt = $this->encrypt->encode(sha1($id . $password . $email . APP_PRIVATE_KEY. time()), APP_PRIVATE_KEY);

        $this->db->set('remember_code', $_salt);
        $this->db->where('id', $id);
        $this->db->update(NAILS_DB_PREFIX . 'user');

        // --------------------------------------------------------------------------

        //  Set the cookie
        $_data           = array();
        $_data['name']   = $this->rememberCookie;
        $_data['value']  = $email . '|' . $_salt;
        $_data['expire'] = 1209600; //   2 weeks

        set_cookie($_data);

        // --------------------------------------------------------------------------

        //  Update the flag
        $this->isRemembered = true;

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Clear the active user's 'rememberMe' cookie
     * @return void
     */
    public function clearRememberCookie()
    {
        delete_cookie($this->rememberCookie);

        // --------------------------------------------------------------------------

        //  Update the flag
        $this->isRemembered = false;
    }

    // --------------------------------------------------------------------------

    /**
     * Refresh the user's session from the database
     * @return void
     */
    protected function refreshSession()
    {
        //  Get the user; be wary of admin's logged in as other people
        if ($this->wasAdmin()) {

            $recoveryData = $this->getAdminRecoveryData();

            if (!empty($recoveryData->newUserId)) {

                $me = $recoveryData->newUserId;

            } else {

                $me = $this->session->userdata('id');
            }

        } else {

            $me = $this->session->userdata('id');
        }

        //  Is anybody home? Hello...?
        if (!$me) {

            $me = $this->me;

            if (!$me) {

                return false;
            }
        }

        $me = $this->getById($me);

        // --------------------------------------------------------------------------

        /**
         * If the user is isn't found (perhaps deleted) or has been suspended then
         * obviously don't proceed with the log in
         */

        if (!$me || !empty($me->is_suspended)) {

            $this->clearRememberCookie();
            $this->clearActiveUser();
            $this->clearLoginData();

            $this->isLoggedIn = false;

            return false;
        }

        // --------------------------------------------------------------------------

        //  Store this entire user in memory
        $this->setActiveUser($me);

        // --------------------------------------------------------------------------

        //  Set the user's logged in flag
        $this->isLoggedIn = true;

        // --------------------------------------------------------------------------

        //  Update user's `last_seen` and `last_ip` properties
        $this->db->set('last_seen', 'NOW()', false);
        $this->db->set('last_ip', $this->input->ipAddress());
        $this->db->where('id', $me->id);
        $this->db->update(NAILS_DB_PREFIX . 'user');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new user
     * @param  array $data          An array of data to create the user with
     * @param  boolean $sendWelcome Whether to send the welcome email
     * @return mixed                StdClass on success, false on failure
     */
    public function create($data, $sendWelcome = true)
    {
        $oDate = Factory::factory('DateTime');

        //  Has an email or a username been submitted?
        if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

            //  Email defined?
            if (empty($data['email'])) {

                $this->setError('An email address must be supplied.');
                return false;
            }

            //  Check email against DB
            $this->db->where('email', $data['email']);
            if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

                $this->setError('This email is already in use.');
                return false;
            }

        } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

            //  Username defined?
            if (empty($data['username'])) {

                $this->setError('A username must be supplied.');
                return false;
            }

            if (!$this->isValidUsername($data['username'], true)) {

                return false;
            }

        } else {

            //  Both a username and an email must be supplied
            if (empty($data['email']) || empty($data['username'])) {

                $this->setError('An email address and a username must be supplied.');
                return false;
            }

            //  Check email against DB
            $this->db->where('email', $data['email']);
            if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_email')) {

                $this->setError('This email is already in use.');
                return false;
            }

            //  Check username
            if (!$this->isValidUsername($data['username'], true)) {

                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  All should be ok, go ahead and create the account
        $_user_data = array();

        // --------------------------------------------------------------------------

        //  Check that we're dealing with a valid group
        if (empty($data['group_id'])) {

            $_user_data['group_id'] = $this->user_group_model->getDefaultGroupId();

        } else {

            $_user_data['group_id'] = $data['group_id'];
        }

        $_group = $this->user_group_model->getById($_user_data['group_id']);

        if (!$_group) {

            $this->setError('Invalid Group ID specified.');
            return false;

        } else {

            $_user_data['group_id'] = $_group->id;
        }

        // --------------------------------------------------------------------------

        /**
         * If a password has been passed then generate the encrypted strings, otherwise
         * have a null password - the user won't be able to login and will be informed
         * that they need to set a password using forgotten password.
         */

        if (empty($data['password'])) {

            $_password = $this->user_password_model->generateNullHash();

            if (!$_password) {

                $this->setError($this->user_password_model->lastError());
                return false;
            }

        } else {

            $_password = $this->user_password_model->generateHash($_user_data['group_id'], $data['password']);

            if (!$_password) {

                $this->setError($this->user_password_model->lastError());
                return false;
            }
        }

        /**
         * Do we need to inform the user of their password? This might be set if an
         * admin created the account, or if the system generated a new password
         */

        $_inform_user_pw = !empty($data['inform_user_pw']) ? true : false;

        // --------------------------------------------------------------------------

        if (!empty($data['username'])) {

            $_user_data['username'] = strtolower($data['username']);
        }

        if (!empty($data['email'])) {

            $_email             = $data['email'];
            $_email_is_verified = !empty($data['email_is_verified']);
        }

        $_user_data['password']        = $_password->password;
        $_user_data['password_md5']    = $_password->password_md5;
        $_user_data['password_engine'] = $_password->engine;
        $_user_data['salt']            = $_password->salt;
        $_user_data['ip_address']      = $this->input->ipAddress();
        $_user_data['last_ip']         = $_user_data['ip_address'];
        $_user_data['created']         = $oDate->format('Y-m-d H:i:s');
        $_user_data['last_update']     = $oDate->format('Y-m-d H:i:s');
        $_user_data['is_suspended']    = !empty($data['is_suspended']);
        $_user_data['temp_pw']         = !empty($data['temp_pw']);

        //  Referral code
        $_user_data['referral'] = $this->generateReferral();

        //  Other data
        $_user_data['salutation'] = !empty($data['salutation']) ? $data['salutation'] : null ;
        $_user_data['first_name'] = !empty($data['first_name']) ? $data['first_name'] : null ;
        $_user_data['last_name']  = !empty($data['last_name']) ? $data['last_name'] : null ;

        if (isset($data['gender'])) {

            $_user_data['gender'] = $data['gender'];
        }

        if (isset($data['timezone'])) {

            $_user_data['timezone'] = $data['timezone'];
        }

        if (isset($data['datetime_format_date'])) {

            $_user_data['datetime_format_date'] = $data['datetime_format_date'];
        }

        if (isset($data['datetime_format_time'])) {

            $_user_data['datetime_format_time'] = $data['datetime_format_time'];
        }

        if (isset($data['language'])) {

            $_user_data['language'] = $data['language'];
        }

        // --------------------------------------------------------------------------

        //  Set Meta data
        $_meta_cols = $this->getMetaColumns();
        $_meta_data = array();

        foreach ($data as $key => $val) {

            if (array_search($key, $_meta_cols) !== false) {

                $_meta_data[$key] = $val;
            }
        }

        // --------------------------------------------------------------------------

        $this->db->trans_begin();

        $this->db->set($_user_data);

        if (!$this->db->insert(NAILS_DB_PREFIX . 'user')) {

            $this->setError('Failed to create base user object.');
            $this->db->trans_rollback();
            return false;
        }

        $_id = $this->db->insert_id();

        // --------------------------------------------------------------------------

        /**
         * Update the user table with an MD5 hash of the user ID; a number of functions
         * make use of looking up this hashed information; this should be quicker.
         */

        $this->db->set('id_md5', md5($_id));
        $this->db->where('id', $_id);

        if (!$this->db->update(NAILS_DB_PREFIX . 'user')) {

            $this->setError('Failed to update base user object.');
            $this->db->trans_rollback();
            return false;
        }

        // --------------------------------------------------------------------------

        //  Create the user_meta_app record, add any extra data if needed
        $this->db->set('user_id', $_id);

        if ($_meta_data) {

            $this->db->set($_meta_data);
        }

        if (!$this->db->insert(NAILS_DB_PREFIX . 'user_meta_app')) {

            $this->setError('Failed to create user meta data object.');
            $this->db->trans_rollback();
            return false;
        }

        // --------------------------------------------------------------------------

        //  Finally add the email address to the user_email table
        if (!empty($_email)) {

            $_code = $this->email_add($_email, $_id, true, $_email_is_verified, false);

            if (!$_code) {

                //  Error will be set by email_add();
                $this->db->trans_rollback();
                return false;
            }

            //  Send the user the welcome email
            if ($sendWelcome) {

                $_email        = new \stdClass();
                $_email->type  = 'new_user_' . $_group->id;
                $_email->to_id = $_id;
                $_email->data  = array();

                //  If this user is created by an admin then take note of that.
                if ($this->isAdmin()) {

                    $_email->data['admin']              = new \stdClass();
                    $_email->data['admin']->id          = $this->activeUser('id');
                    $_email->data['admin']->first_name  = $this->activeUser('first_name');
                    $_email->data['admin']->last_name   = $this->activeUser('last_name');
                    $_email->data['admin']->group       = new \stdClass();
                    $_email->data['admin']->group->id   = $_group->id;
                    $_email->data['admin']->group->name = $_group->label;
                }

                if (!empty($data['password']) && !empty($_inform_user_pw)) {

                    $_email->data['password'] = $data['password'];

                    //  Is this a temp password? We should let them know that too
                    if ($_user_data['temp_pw']) {

                        $_email->data['temp_pw'] = !empty($_user_data['temp_pw']);
                    }
                }

                //  If the email isn't verified we'll want to include a note asking them to do so
                if (!$_email_is_verified) {

                    $_email->data['verification_code']  = $_code;
                }

                if (!$this->emailer->send($_email, true)) {

                    //  Failed to send using the group email, try using the generic email template
                    $_email->type = 'new_user';

                    if (!$this->emailer->send($_email, true)) {

                        //  Email failed to send, musn't exist, oh well.
                        $_error  = 'Failed to send welcome email.';
                        $_error .= !empty($_inform_user_pw) ? ' Inform the user their password is <strong>' . $data['password'] . '</strong>' : '';

                        $this->setError($_error);
                    }
                }
            }
        }

        // --------------------------------------------------------------------------

        //  commit the transaction and return new user object
        if ($this->db->trans_status() !== false) {

            $this->db->trans_commit();
            return $this->getById($_id);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user
     * @param  integer $userId The ID of the user to delete
     * @return boolean
     */
    public function destroy($userId)
    {
        $this->db->where('id', $userId);
        $this->db->delete(NAILS_DB_PREFIX . 'user');

        if ((bool) $this->db->affected_rows()) {

            $this->unsetCacheUser($userId);
            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Alias to destroy()
     * @param  integer $userId The ID of the user to delete
     * @return boolean
     */
    public function delete($userId)
    {
        return $this->destroy($userId);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a valid referral code
     * @return string
     */
    protected function generateReferral()
    {
        Factory::helper('string');

        while (1 > 0) {

            $referral = random_string('alnum', 8);
            $q = $this->db->get_where(NAILS_DB_PREFIX . 'user', array('referral' => $referral));

            if ($q->num_rows() == 0) {

                break;
            }
        }

        return $referral;
    }

    // --------------------------------------------------------------------------

    /**
     * Reqards a user for their referral
     * @param  integer $userId     The ID of the user who signed up
     * @param  integer $referrerId The ID of the user who made the referral
     * @return void
     */
    public function rewardReferral($userId, $referrerId)
    {
        // @todo Implement this emthod where appropriate
    }

    // --------------------------------------------------------------------------

    /**
     * Suspend a user
     * @param  integer $userId The ID of the user to suspend
     * @return boolean
     */
    public function suspend($userId)
    {
        return $this->update($userId, array('is_suspended' => true));
    }

    // --------------------------------------------------------------------------

    /**
     * Unsuspend a user
     * @param  integer $userId The ID of the user to unsuspend
     * @return boolean
     */
    public function unsuspend($userId)
    {
        return $this->update($userId, array('is_suspended' => false));
    }

    // --------------------------------------------------------------------------

    /**
     * Checks whether a username is valid
     * @param  string  $username     The username to check
     * @param  boolean $checkDb      Whether to test against the database
     * @param  mixed   $ignoreUserId The ID of a user to ignore when checking the database
     * @return boolean
     */
    public function isValidUsername($username, $checkDb = false, $ignoreUserId = null)
    {
        /**
         * Check username doesn't contain invalid characters - we're actively looking
         * for characters which are invalid so we can say "Hey! The following
         * characters are invalid" rather than making the user guess, y'know, 'cause
         * we're good guys.
         */

        $invalidChars = '/[^a-zA-Z0-9\-_\.]/';

        //  Minimum length of the username
        $minLength = 2;

        // --------------------------------------------------------------------------

        //  Check for illegal characters
        $containsInvalidChars = preg_match($invalidChars, $username);

        if ($containsInvalidChars) {

            $msg = 'Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).';

            $this->setError($msg);
            return false;
        }

        // --------------------------------------------------------------------------

        //  Check length
        if (strlen($username) < $minLength) {

            $this->setError('Usernames msut be at least ' . $minLength . ' characters long.');
            return false;
        }

        // --------------------------------------------------------------------------

        if ($checkDb) {

            $this->db->where('username', $username);

            if (!empty($ignoreUserId)) {

                $this->db->where('id !=', $ignoreUserId);
            }

            if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user')) {

                $this->setError('Username is already in use.');
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Merges users with ID in $mergeIds into $userId
     * @param  int   $userId   The user ID to keep
     * @param  array $mergeIds An array of user ID's to merge into $userId
     * @return boolean
     */
    public function merge($userId, $mergeIds, $preview = false)
    {
        if (!is_numeric($userId)) {

            $this->setError('"userId" must be numeric.');
            return false;
        }

        if (!is_array($mergeIds)) {

            $this->setError('"mergeIDs" must be an array.');
            return false;
        }

        for ($i=0; $i<count($mergeIds); $i++) {

            if (!is_numeric($mergeIds[$i])) {
                $this->setError('"mergeIDs" must contain only numerical values.');
                return false;
            }

            $mergeIds[$i] = $this->db->escape((int) $mergeIds[$i]);
        }

        if (in_array($userId, $mergeIds)) {

            $this->setError('"userId" cannot be listed as a merge user.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Look for tables which contain a user ID column in them.
        $userCols   = array();
        $userCols[] = 'user_id';
        $userCols[] = 'created_by';
        $userCols[] = 'modified_by';
        $userCols[] = 'author_id';
        $userCols[] = 'authorId';

        $userColsStr   = "'" . implode("','", $userCols) . "'";

        $ignoreTables   = array();
        $ignoreTables[] = NAILS_DB_PREFIX . 'user';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_meta_app';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_device_code';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_question';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_auth_two_factor_token';
        $ignoreTables[] = NAILS_DB_PREFIX . 'user_social';

        $ignoreTablesStr   = "'" . implode("','", $ignoreTables) . "'";

        $tables = array();
        $query  = " SELECT COLUMN_NAME,TABLE_NAME
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE COLUMN_NAME IN (" . $userColsStr . ")
                    AND TABLE_NAME NOT IN (" . $ignoreTablesStr . ")
                    AND TABLE_SCHEMA='" . DEPLOY_DB_DATABASE . "';";

        $result = $this->db->query($query);

        while ($table = $result->_fetch_object()) {

            if (!isset($tables[$table->TABLE_NAME])) {

                $tables[$table->TABLE_NAME] = new \stdClass();
                $tables[$table->TABLE_NAME]->name = $table->TABLE_NAME;
                $tables[$table->TABLE_NAME]->columns = array();
            }

            $tables[$table->TABLE_NAME]->columns[] = $table->COLUMN_NAME;
        }

        $tables = array_values($tables);

        //  Grab a count of the number of rows which will be affected
        for ($i = 0; $i < count($tables); $i++) {

            $columnConditional = array();
            foreach ($tables[$i]->columns as $column) {

                $columnConditional[] = $column . ' IN (' . implode(',', $mergeIds) . ')';
            }

            $query  = 'SELECT COUNT(*) as numrows FROM  ' . $tables[$i]->name . ' WHERE ' . implode(' OR ', $columnConditional);
            $result = $this->db->query($query)->row();

            if (empty($result->numrows)) {

                $tables[$i] = null;

            } else {

                $tables[$i]->numRows = $result->numrows;
            }
        }

        $tables = array_values(array_filter($tables));

        // --------------------------------------------------------------------------

        if ($preview) {

            $out = new \stdClass;
            $out->user = $this->getById($userId);
            $out->merge = array();

            foreach ($mergeIds as $mergeUserId) {

                $out->merge[] = $this->getById($mergeUserId);
            }

            $out->tables = $tables;
            $out->ignoreTables = $ignoreTables;

        } else {

            $this->db->trans_begin();

            //  For each table update the user columns
            for ($i=0; $i<count($tables); $i++) {

                foreach ($tables[$i]->columns as $column) {

                    //  Additional updates for certain tables
                    switch ($tables[$i]->name) {

                        case NAILS_DB_PREFIX . 'user_email':

                            $this->db->set('is_primary', false);
                            break;
                    }

                    $this->db->set($column, $userId);
                    $this->db->where_in($column, $mergeIds);
                    if (!$this->db->update($tables[$i]->name)) {

                        $errMsg = 'Failed to migrate column "' . $column . '" in table "' . $tables[$i]->name . '"';
                        $this->setError($errMsg);
                        $this->db->trans_rollback();
                        return false;
                    }
                }
            }

            //  Now delete each user
            for ($i=0; $i<count($mergeIds); $i++) {

                if (!$this->destroy($mergeIds[$i])) {

                    $errMsg = 'Failed to delete user "' . $mergeIds[$i] . '" ';
                    $this->setError($errMsg);
                    $this->db->trans_rollback();
                    return false;
                }
            }

            if ($this->db->trans_status() === false) {

                $this->db->trans_rollback();
                $out = false;

            } else {

                $this->db->trans_commit();
                $out = true;
            }
        }

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Format a user object
     * @param  stdClass &$user The user object to format
     * @return void
     */
    protected function formatObject(&$user)
    {
        parent::formatObject($user);

        // --------------------------------------------------------------------------

        $user->group_acl = json_decode($user->group_acl);

        //  If the user has an ACL set then we'll need to extract and merge that
        if ($user->user_acl) {

            $user->user_acl = json_decode($user->user_acl);
            $user->acl      = array_merge($user->group_acl, $user->user_acl);
            $user->acl      = array_filter($user->acl);
            $user->acl      = array_unique($user->acl);

        } else {

            $user->acl = $user->group_acl;
        }

        // --------------------------------------------------------------------------

        //  Ints
        $user->id                 = (int) $user->id;
        $user->group_id           = (int) $user->group_id;
        $user->login_count        = (int) $user->login_count;
        $user->referred_by        = (int) $user->referred_by;
        $user->failed_login_count = (int) $user->failed_login_count;

        //  Bools
        $user->temp_pw           = (bool) $user->temp_pw;
        $user->is_suspended      = (bool) $user->is_suspended;
        $user->email_is_verified = (bool) $user->email_is_verified;

        // --------------------------------------------------------------------------

        //  Tidy User meta
        unset($user->user_id);
    }
}
