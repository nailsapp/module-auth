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
    protected $adminRecoveryField;

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
        $this->defaultSortColumn  = $this->tableIdColumn;
        $this->defaultSortOrder   = 'DESC';

        // --------------------------------------------------------------------------

        //  Define searchable fields, resetting it
        $this->searchableFields = array(
            $this->tablePrefix . '.id',
            $this->tablePrefix . '.username',
            'ue.email',
            array(
                $this->tablePrefix . '.first_name',
                $this->tablePrefix . '.last_name'
            )
        );

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
     * @param  string  $sKeys      The key to look up in activeUser
     * @param  string  $sDelimiter If multiple fields are requested they'll be joined by this string
     * @return mixed
     */
    public function activeUser($sKeys = '', $sDelimiter = ' ')
    {
        //  Only look for a value if we're logged in
        if (!$this->isLoggedIn()) {
            return false;
        }

        // --------------------------------------------------------------------------

        //  If $sKeys is false just return the user object in its entirety
        if (empty($sKeys)) {
            return $this->activeUser;
        }

        // --------------------------------------------------------------------------

        //  If only one key is being requested then don't do anything fancy
        if (strpos($sKeys, ',') === false) {

            $val = isset($this->activeUser->{trim($sKeys)}) ? $this->activeUser->{trim($sKeys)} : null;

        } else {

            //  More than one key
            $aKeys = explode(',', $sKeys);
            $aKeys = array_filter($aKeys);
            $aOut   = array();

            foreach ($aKeys as $sKey) {

                //  If something is found, use that.
                if (isset($this->activeUser->{trim($sKey)})) {

                    $aOut[] = $this->activeUser->{trim($sKey)};
                }
            }

            //  If nothing was found, just return null
            if (empty($aOut)) {

                $val = null;

            } else {

                $val = implode($sDelimiter, $aOut);
            }
        }

        return $val;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the active user
     * @param stdClass $oUser The user obect to set
     */
    public function setActiveUser($oUser)
    {
        $this->activeUser = $oUser;

        // --------------------------------------------------------------------------

        $oDateTimeModel = Factory::model('DateTime');

        //  Set the user's date/time formats
        $sFormatDate = $this->activeUser('pref_date_format');
        $sFormatDate = $sFormatDate ? $sFormatDate : $oDateTimeModel->getDateFormatDefaultSlug();

        $sFormatTime = $this->activeUser('pref_time_format');
        $sFormatTime = $sFormatTime ? $sFormatTime : $oDateTimeModel->getTimeFormatDefaultSlug();

        $oDateTimeModel->setFormats($sFormatDate, $sFormatTime);
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
     * @param mixed   $mIdEmail        The user's ID or email address
     * @param boolean $bSetSessionData Whether to set the session data or not
     */
    public function setLoginData($mIdEmail, $bSetSessionData = true)
    {
        //  Valid user?
        if (is_numeric($mIdEmail)) {

            $oUser  = $this->getById($mIdEmail);
            $sError = 'Invalid User ID.';

        } elseif (is_string($mIdEmail)) {

            if (valid_email($mIdEmail)) {

                $oUser  = $this->getByEmail($mIdEmail);
                $sError = 'Invalid User email.';

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
        if (!$oUser) {

            $this->setError($sError);
            return false;

        } elseif ($oUser->is_suspended) {

            $this->setError('User is suspended.');
            return false;

        } else {

            //  Set the flag
            $this->isLoggedIn = true;

            //  Set session variables
            if ($bSetSessionData) {

                $sessionData = array(
                    'id'       => $oUser->id,
                    'email'    => $oUser->email,
                    'group_id' => $oUser->group_id,
                );
                $this->session->set_userdata($sessionData);
            }

            //  Set the active user
            $this->setActiveUser($oUser);

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
     * @param   string  $sSearch The permission to check for
     * @param   mixed   $mUser   The user to check for; if null uses activeUser, if numeric, fetches user, if object uses that object
     * @return  boolean
     */
    public function hasPermission($sSearch, $mUser = null)
    {
        //  Fetch the correct ACL
        if (is_numeric($mUser)) {

            $oUser = $this->getById($mUser);

            if (isset($oUser->acl)) {

                $aAcl = $oUser->acl;
                unset($oUser);

            } else {

                return false;
            }

        } elseif (isset($mUser->acl)) {

            $aAcl = $mUser->acl;

        } else {

            $aAcl = $this->activeUser('acl');
        }

        if (!$aAcl) {

            return false;
        }

        // --------------------------------------------------------------------------

        // Super users or CLI users can do anything their heart's desire
        if (in_array('admin:superuser', $aAcl) || $this->input->is_cli_request()) {
            return true;
        }

        // --------------------------------------------------------------------------

        /**
         * Test the ACL
         * We're going to use regular experessions here so we can allow for some
         * flexability in the search, i.e admin:* would return true if the user has
         * access to any of admin.
         */

        $bHasPermission = false;

        /**
         * Replace :* with :.* - this is a common mistake when using the permission
         * system (i.e., assuming that star on it's own will match)
         */

        $sSearch = preg_replace('/:\*/', ':.*', $sSearch);

        foreach ($aAcl as $sPermission) {

            $sPattern = '/^' . $sSearch . '$/';
            $bMatch   = preg_match($sPattern, $sPermission);

            if ($bMatch) {
                $bHasPermission = true;
                break;
            }
        }

        return $bHasPermission;
    }

    // --------------------------------------------------------------------------

    /**
     * This method applies the conditionals which are common across the get_*()
     * methods and the count() method.
     * @param array $aData Data passed from the calling method
     * @return void
     */
    protected function getCountCommon($aData = array())
    {
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

        // --------------------------------------------------------------------------

        if (!empty($aData['keywords'])) {

            if (empty($aData['or_like'])) {
                $aData['or_like'] = array();
            }

            $aData['or_like'][] = array(
                'column' => $this->tablePrefix . '.id',
                'value'  => $aData['keywords']
            );

            $aData['or_like'][] = array(
                'column' => array($this->tablePrefix . '.first_name', $this->tablePrefix . '.last_name'),
                'value'  => $aData['keywords']
            );

            $aData['or_like'][] = array(
                'column' => 'ue.email',
                'value'  => $aData['keywords']
            );
        }

        //  Let the parent method handle sorting, etc
        parent::getCountCommon($aData);
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
    public function getEmailsForUser($id)
    {
        $this->db->where('user_id', $id);
        $this->db->order_by('date_added');
        $this->db->order_by('email', 'ASC');
        return $this->db->get(NAILS_DB_PREFIX . 'user_email')->result();
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
        $iUserId  = $this->getUserId($user_id);
        if (empty($iUserId)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $oUser = $this->getById($iUserId);
        if (empty($oUser)) {

            $this->setError('Invalid user ID');
            return false;
        }

        // --------------------------------------------------------------------------


        //  If there's some data we'll need to know the columns of `user`
        //  We also want to unset any 'dangerous' items then set it for the query

        if ($data) {

            //  Set the cols in `user` (rather than querying the DB)
            $aCols = $this->getUserColumns();

            //  Safety first, no updating of user's ID.
            unset($data->id);
            unset($data->id_md5);

            //  If we're updating the user's password we should generate a new hash
            if (array_key_exists('password', $data)) {

                $oHash = $this->user_password_model->generateHash($oUser->group_id, $data['password']);

                if (empty($oHash)) {

                    $this->setError($this->user_password_model->lastError());
                    return false;
                }

                $data['password']         = $oHash->password;
                $data['password_md5']     = $oHash->password_md5;
                $data['password_engine']  = $oHash->engine;
                $data['password_changed'] = $oDate->format('Y-m-d H:i:s');
                $data['salt']             = $oHash->salt;

                $bPasswordUpdated = true;

            } else {

                $bPasswordUpdated = false;
            }

            //  Set the data
            $aDataUser            = array();
            $aDataMeta            = array();
            $sDataEmail           = '';
            $sDataUsername        = '';
            $dataResetMfaQuestion = false;
            $dataResetMfaDevice   = false;

            foreach ($data as $key => $val) {

                //  user or user_meta?
                if (array_search($key, $aCols) !== false) {

                    //  Careful now, some items cannot be blank and must be null
                    switch ($key) {

                        case 'profile_img':

                            $aDataUser[$key] = $val ? $val : null;
                            break;

                        default:

                            $aDataUser[$key] = $val;
                            break;
                    }

                } elseif ($key == 'email') {

                    $sDataEmail = strtolower(trim($val));

                } elseif ($key == 'username') {

                    $sDataUsername = strtolower(trim($val));

                } elseif ($key == 'reset_mfa_question') {

                    $dataResetMfaQuestion = $val;

                } elseif ($key == 'reset_mfa_device') {

                    $dataResetMfaDevice = $val;

                } else {

                    $aDataMeta[$key] = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  If a username has been passed then check if it's available
            if (!empty($sDataUsername)) {

                //  Check username is valid
                if (!$this->isValidUsername($sDataUsername, true, $iUserId)) {

                    return false;

                } else {

                    $aDataUser['username'] = $sDataUsername;
                }
            }

            // --------------------------------------------------------------------------

            //  Begin transaction
            $bRollback = false;
            $this->db->trans_begin();

            // --------------------------------------------------------------------------

            //  Resetting security questions?
            $this->config->load('auth/auth');

            if ($this->config->item('authTwoFactorMode') == 'QUESTION' && $dataResetMfaQuestion) {

                $this->db->where('user_id', $iUserId);
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

                $this->db->where('user_id', $iUserId);
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
            $this->db->where('id', $iUserId);
            $this->db->set('last_update', 'NOW()', false);

            if ($aDataUser) {

                $this->db->set($aDataUser);
            }

            $this->db->update(NAILS_DB_PREFIX . 'user');

            // --------------------------------------------------------------------------

            //  Update the meta table
            if ($aDataMeta) {

                $this->db->where('user_id', $iUserId);
                $this->db->set($aDataMeta);
                $this->db->update(NAILS_DB_PREFIX . 'user_meta_app');
            }

            // --------------------------------------------------------------------------

            //  If an email has been passed then attempt to update the user's email too
            if ($sDataEmail) {

                if (valid_email($sDataEmail)) {

                    //  Check if the email is already being used
                    $this->db->where('email', $sDataEmail);
                    $oEmail = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

                    if ($oEmail) {

                        /**
                         * Email is in use, if it's in use by the ID of this user then set
                         * it as the primary email for this account. If it's in use by
                         * another user then error
                         */

                        if ($oEmail->user_id == $iUserId) {

                            $this->emailMakePrimary($oEmail->email);

                        } else {

                            $this->setError('Email is already in use.');
                            $bRollback = true;
                        }

                    } else {

                        /**
                         * Doesn't appear to be in use, add as a new email address and
                         * make it the primary one
                         */

                        $this->emailAdd($sDataEmail, $iUserId, true);
                    }

                } else {

                    //  Error, not a valid email; roll back transaction
                    $this->setError('"' . $sDataEmail . '" is not a valid email address.');
                    $bRollback = true;
                }
            }

            // --------------------------------------------------------------------------

            //  How'd we get on?
            if (!$bRollback && $this->db->trans_status() !== false) {

                $this->db->trans_commit();

                // --------------------------------------------------------------------------

                //  If the user's password was updated send them a notification
                if ($bPasswordUpdated) {

                    $oEmail                  = new \stdClass();
                    $oEmail->type            = 'password_updated';
                    $oEmail->to_id           = $iUserId;
                    $oEmail->data            = new \stdClass();
                    $oEmail->data->ipAddress = $this->input->ipAddress();
                    $oEmail->data->updatedAt = $oDate->format('Y-m-d H:i:s');

                    if ($this->activeUser('id')) {
                        $oEmail->data->updatedBy = $this->activeUser('first_name,last_name');
                    }

                    $this->emailer->send($oEmail, true);
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
            $this->db->where('id', $iUserId);
            $this->db->update(NAILS_DB_PREFIX . 'user');
        }

        // --------------------------------------------------------------------------

        //  If we just updated the active user we should probably update their session info
        if ($iUserId == $this->activeUser('id')) {

            $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

            if ($data) {

                foreach ($data as $key => $val) {

                    $this->activeUser->{$key} = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  Do we need to update any timezone/date/time preferences?
            if (isset($data['timezone'])) {

                $oDateTimeModel = Factory::model('DateTime');
                $oDateTimeModel->setUserTimezone($data['timezone']);
            }

            if (isset($data['datetime_format_date'])) {

                $oDateTimeModel = Factory::model('DateTime');
                $oDateTimeModel->setDateFormat($data['datetime_format_date']);
            }

            if (isset($data['datetime_format_time'])) {

                $oDateTimeModel = Factory::model('DateTime');
                $oDateTimeModel->setTimeFormat($data['datetime_format_time']);
            }

            // --------------------------------------------------------------------------

            //  If there's a remember me cookie then update that too, but only if the password
            //  or email address has changed

            if ((isset($data['email']) || !empty($bPasswordUpdated)) && $this->isRemembered()) {

                $this->setRememberCookie();

            }

        }

        $this->setCacheUser($iUserId);

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

            $iUid = (int) $iUserId;

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
     * @param  boolean $bIsPrimary  Whether or not the email address should be the primary email address for the user
     * @param  boolean $is_verified Whether ot not the email should be marked as verified
     * @param  boolean $send_email  If unverified, whether or not the verification email should be sent
     * @return mixed                String containing verification code on success, false on failure
     */
    public function emailAdd($email, $user_id = null, $bIsPrimary = false, $is_verified = false, $send_email = true)
    {
        $iUserId = empty($user_id) ? $this->activeUser('id') : $user_id;
        $oEmail  = trim(strtolower($email));
        $oUser   = $this->getById($iUserId);

        if (empty($oUser)) {

            $this->setError('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Make sure the email is valid
        if (!valid_email($oEmail)) {

            $this->setError('"' . $oEmail . '" is not a valid email address');
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * Test email, if it's in use and for the same user then return true. If it's
         * in use by a different user then return an error.
         */

        $this->db->select('id, user_id, is_verified, code');
        $this->db->where('email', $oEmail);
        $oTest = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

        if ($oTest) {

            if ($oTest->user_id == $oUser->id) {

                /**
                 * In use, but belongs to the same user - return the code (imitates
                 * behavior of newly added email)
                 */

                if ($bIsPrimary) {

                    $this->emailMakePrimary($oTest->id);
                }

                //  Resend verification email?
                if ($send_email && !$oTest->is_verified) {

                    $this->emailAddSendVerify($oTest->id);
                }

                //  Recache the user
                $this->setCacheUser($oUser->id);

                return $oTest->code;

            } else {

                //  In use, but belongs to another user
                $this->setError('Email in use by another user.');
                return false;
            }
        }

        // --------------------------------------------------------------------------

        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $sCode          = $oPasswordModel->salt();

        $this->db->set('user_id', $oUser->id);
        $this->db->set('email', $oEmail);
        $this->db->set('code', $sCode);
        $this->db->set('is_verified', (bool) $is_verified);
        $this->db->set('date_added', 'NOW()', false);

        if ((bool) $is_verified) {

            $this->db->set('date_verified', 'NOW()', false);
        }

        $this->db->insert(NAILS_DB_PREFIX . 'user_email');

        if ($this->db->affected_rows()) {

            //  Email ID
            $iEmailId = $this->db->insert_id();

            //  Make it the primary email address?
            if ($bIsPrimary) {

                $this->emailMakePrimary($iEmailId);
            }

            //  Send off the verification email
            if ($send_email && !$is_verified) {

                $this->emailAddSendVerify($iEmailId);
            }

            //  Recache the user
            $this->setCacheUser($oUser->id);

            //  Update the activeUser
            if ($oUser->id == $this->activeUser('id')) {

                $oDate = Factory::factory('DateTime');
                $this->activeUser->last_update = $oDate->format('Y-m-d H:i:s');

                if ($bIsPrimary) {

                    $this->activeUser->email = $oEmail;
                    $this->activeUser->email_verification_code = $sCode;
                    $this->activeUser->email_is_verified = (bool) $is_verified;
                    $this->activeUser->email_is_verified_on = (bool) $is_verified ? $oDate->format('Y-m-d H:i:s') : null;
                }
            }

            //  Return the code
            return $sCode;

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
    public function emailAddSendVerify($email_id, $user_id = null)
    {
        //  Fetch the email and the user's group
        $this->db->select(
            array(
                'ue.id',
                'ue.code',
                'ue.is_verified',
                'ue.user_id',
                $this->tablePrefix . '.group_id'
            )
        );

        if (is_numeric($email_id)) {

            $this->db->where('ue.id', $email_id);

        } else {

            $this->db->where('ue.email', $email_id);

        }

        if (!empty($user_id)) {

            $this->db->where('ue.user_id', $user_id);

        }

        $this->db->join(NAILS_DB_PREFIX . 'user u', $this->tablePrefix . '.id = ue.user_id');

        $oEmailRow = $this->db->get(NAILS_DB_PREFIX . 'user_email ue')->row();

        if (!$oEmailRow) {

            $this->setError('Invalid Email.');
            return false;

        }

        if ($oEmailRow->is_verified) {

            $this->setError('Email is already verified.');
            return false;

        }

        // --------------------------------------------------------------------------

        $oEmail                  = new \stdClass();
        $oEmail->type            = 'verify_email_' . $oEmailRow->group_id;
        $oEmail->to_id           = $oEmailRow->user_id;
        $oEmail->data            = new \stdClass();
        $oEmail->data->verifyUrl = site_url('email/verify/' . $oEmailRow->user_id . '/' . $oEmailRow->code);

        if (!$this->emailer->send($oEmail, true)) {

            //  Failed to send using the group email, try using the generic email template
            $oEmail->type = 'verify_email';

            if (!$this->emailer->send($oEmail, true)) {

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
    public function emailDelete($email_id, $user_id = null)
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
     * @param  mixed  $mIdEmail The numeric ID of the user, or the email address
     * @param  string $code     The verification code as generated by emailAdd()
     * @return boolean
     */
    public function emailVerify($mIdEmail, $code)
    {
        //  Check user exists
        if (is_numeric($mIdEmail)) {

            $user = $this->getById($mIdEmail);

        } else {

            $user = $this->getByEmail($mIdEmail);
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
            if ($user->id == $this->activeUser('id')) {

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
    public function emailMakePrimary($id_email, $user_id = null)
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

        $oEmail = $this->db->get(NAILS_DB_PREFIX . 'user_email')->row();

        if (empty($oEmail)) {
            return false;
        }

        //  Update
        $this->db->trans_begin();

        $this->db->set('is_primary', false);
        $this->db->where('user_id', $oEmail->user_id);
        $this->db->update(NAILS_DB_PREFIX . 'user_email');

        $this->db->set('is_primary', true);
        $this->db->where('id', $oEmail->id);
        $this->db->update(NAILS_DB_PREFIX . 'user_email');

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {

            $this->db->trans_rollback();
            return false;

        } else {

            $this->db->trans_commit();
            $this->setCacheUser($oEmail->user_id);

            //  Update the activeUser
            if ($oEmail->user_id == $this->activeUser('id')) {

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

        if (empty($id) || empty($password) || empty($email)) {

            if (!activeUser('id') || !activeUser('password') || !activeUser('email')) {

                return false;

            } else {

                $id       = $this->activeUser('id');
                $password = $this->activeUser('password');
                $email    = $this->activeUser('email');
            }
        }

        // --------------------------------------------------------------------------

        //  Generate a code to remember the user by and save it to the DB
        $sSalt = $this->encrypt->encode(sha1($id . $password . $email . APP_PRIVATE_KEY. time()), APP_PRIVATE_KEY);

        $this->db->set('remember_code', $sSalt);
        $this->db->where('id', $id);
        $this->db->update(NAILS_DB_PREFIX . 'user');

        // --------------------------------------------------------------------------

        //  Set the cookie
        $aData           = array();
        $aData['name']   = $this->rememberCookie;
        $aData['value']  = $email . '|' . $sSalt;
        $aData['expire'] = 1209600; //   2 weeks

        set_cookie($aData);

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
        $aUserData = array();

        // --------------------------------------------------------------------------

        //  Check that we're dealing with a valid group
        if (empty($data['group_id'])) {

            $aUserData['group_id'] = $this->user_group_model->getDefaultGroupId();

        } else {

            $aUserData['group_id'] = $data['group_id'];
        }

        $oGroup = $this->user_group_model->getById($aUserData['group_id']);

        if (empty($oGroup)) {

            $this->setError('Invalid Group ID specified.');
            return false;

        } else {

            $aUserData['group_id'] = $oGroup->id;
        }

        // --------------------------------------------------------------------------

        /**
         * If a password has been passed then generate the encrypted strings, otherwise
         * have a null password - the user won't be able to login and will be informed
         * that they need to set a password using forgotten password.
         */

        if (empty($data['password'])) {

            $oPassword = $this->user_password_model->generateNullHash();

            if (!$oPassword) {
                $this->setError($this->user_password_model->lastError());
                return false;
            }

        } else {

            $oPassword = $this->user_password_model->generateHash($aUserData['group_id'], $data['password']);

            if (!$oPassword) {
                $this->setError($this->user_password_model->lastError());
                return false;
            }
        }

        /**
         * Do we need to inform the user of their password? This might be set if an
         * admin created the account, or if the system generated a new password
         */

        $bInformUserPw = !empty($data['inform_user_pw']) ? true : false;

        // --------------------------------------------------------------------------

        if (!empty($data['username'])) {
            $aUserData['username'] = strtolower($data['username']);
        }

        if (!empty($data['email'])) {

            $sEmail           = $data['email'];
            $bEmailIsVerified = !empty($data['email_is_verified']);
        }

        $aUserData['password']        = $oPassword->password;
        $aUserData['password_md5']    = $oPassword->password_md5;
        $aUserData['password_engine'] = $oPassword->engine;
        $aUserData['salt']            = $oPassword->salt;
        $aUserData['ip_address']      = $this->input->ipAddress();
        $aUserData['last_ip']         = $aUserData['ip_address'];
        $aUserData['created']         = $oDate->format('Y-m-d H:i:s');
        $aUserData['last_update']     = $oDate->format('Y-m-d H:i:s');
        $aUserData['is_suspended']    = !empty($data['is_suspended']);
        $aUserData['temp_pw']         = !empty($data['temp_pw']);

        //  Referral code
        $aUserData['referral'] = $this->generateReferral();

        //  Other data
        $aUserData['salutation'] = !empty($data['salutation']) ? $data['salutation'] : null ;
        $aUserData['first_name'] = !empty($data['first_name']) ? $data['first_name'] : null ;
        $aUserData['last_name']  = !empty($data['last_name'])  ? $data['last_name']  : null ;

        if (isset($data['gender'])) {
            $aUserData['gender'] = $data['gender'];
        }

        if (isset($data['timezone'])) {
            $aUserData['timezone'] = $data['timezone'];
        }

        if (isset($data['datetime_format_date'])) {
            $aUserData['datetime_format_date'] = $data['datetime_format_date'];
        }

        if (isset($data['datetime_format_time'])) {
            $aUserData['datetime_format_time'] = $data['datetime_format_time'];
        }

        if (isset($data['language'])) {
            $aUserData['language'] = $data['language'];
        }

        // --------------------------------------------------------------------------

        //  Set Meta data
        $aMetaCols = $this->getMetaColumns();
        $aMetaData = array();

        foreach ($data as $key => $val) {
            if (array_search($key, $aMetaCols) !== false) {
                $aMetaData[$key] = $val;
            }
        }

        // --------------------------------------------------------------------------

        $this->db->trans_begin();

        $this->db->set($aUserData);

        if (!$this->db->insert(NAILS_DB_PREFIX . 'user')) {

            $this->setError('Failed to create base user object.');
            $this->db->trans_rollback();
            return false;
        }

        $iId = $this->db->insert_id();

        // --------------------------------------------------------------------------

        /**
         * Update the user table with an MD5 hash of the user ID; a number of functions
         * make use of looking up this hashed information; this should be quicker.
         */

        $this->db->set('id_md5', md5($iId));
        $this->db->where('id', $iId);

        if (!$this->db->update(NAILS_DB_PREFIX . 'user')) {

            $this->setError('Failed to update base user object.');
            $this->db->trans_rollback();
            return false;
        }

        // --------------------------------------------------------------------------

        //  Create the user_meta_app record, add any extra data if needed
        $this->db->set('user_id', $iId);

        if ($aMetaData) {
            $this->db->set($aMetaData);
        }

        if (!$this->db->insert(NAILS_DB_PREFIX . 'user_meta_app')) {

            $this->setError('Failed to create user meta data object.');
            $this->db->trans_rollback();
            return false;
        }

        // --------------------------------------------------------------------------

        //  Finally add the email address to the user_email table
        if (!empty($sEmail)) {

            $sCode = $this->emailAdd($sEmail, $iId, true, $bEmailIsVerified, false);

            if (!$sCode) {

                //  Error will be set by emailAdd();
                $this->db->trans_rollback();
                return false;
            }

            //  Send the user the welcome email
            if ($sendWelcome) {

                $oEmail        = new \stdClass();
                $oEmail->type  = 'new_user_' . $oGroup->id;
                $oEmail->to_id = $iId;
                $oEmail->data  = new \stdClass();

                //  If this user is created by an admin then take note of that.
                if ($this->isAdmin() && $this->activeUser('id') != $iId) {

                    $oEmail->data->admin              = new \stdClass();
                    $oEmail->data->admin->id          = $this->activeUser('id');
                    $oEmail->data->admin->first_name  = $this->activeUser('first_name');
                    $oEmail->data->admin->last_name   = $this->activeUser('last_name');
                    $oEmail->data->admin->group       = new \stdClass();
                    $oEmail->data->admin->group->id   = $oGroup->id;
                    $oEmail->data->admin->group->name = $oGroup->label;
                }

                if (!empty($data['password']) && $bInformUserPw) {

                    $oEmail->data->password = $data['password'];

                    //  Is this a temp password? We should let them know that too
                    if ($aUserData['temp_pw']) {
                        $oEmail->data->isTemp = !empty($aUserData['temp_pw']);
                    }
                }

                //  If the email isn't verified we'll want to include a note asking them to do so
                if (!$bEmailIsVerified) {
                    $oEmail->data->verifyUrl  = site_url('email/verify/' . $iId . '/' . $sCode);
                }

                if (!$this->emailer->send($oEmail, true)) {

                    //  Failed to send using the group email, try using the generic email template
                    $oEmail->type = 'new_user';

                    if (!$this->emailer->send($oEmail, true)) {

                        //  Email failed to send, musn't exist, oh well.
                        $sError  = 'Failed to send welcome email.';
                        $sError .= $bInformUserPw ? ' Inform the user their password is <strong>' . $data['password'] . '</strong>' : '';

                        $this->setError($sError);
                    }
                }
            }
        }

        // --------------------------------------------------------------------------

        //  commit the transaction and return new user object
        if ($this->db->trans_status() !== false) {

            $this->db->trans_commit();
            return $this->getById($iId);

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
