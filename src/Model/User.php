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

use Nails\Auth\Constants;
use Nails\Auth\Events;
use Nails\Auth\Model\User\Group;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Resource;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Factory\Model\Field;
use Nails\Common\Model\Base;
use Nails\Common\Service\Cookie;
use Nails\Common\Service\Database;
use Nails\Common\Service\DateTime;
use Nails\Common\Service\Encrypt;
use Nails\Common\Service\ErrorHandler;
use Nails\Common\Service\Event;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\Session;
use Nails\Config;
use Nails\Email;
use Nails\Environment;
use Nails\Factory;
use Nails\Testing;
use ReflectionException;
use stdClass;

/**
 * Class User
 *
 * @package Nails\Auth\Model
 */
class User extends Base
{
    /**
     * The name of the resource to use (as passed to \Nails\Factory::resource())
     *
     * @var string
     */
    const RESOURCE_NAME = 'User';

    /**
     * The provider of the resource to use (as passed to \Nails\Factory::resource())
     *
     * @var string
     */
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;

    // --------------------------------------------------------------------------

    /**
     * The ID of the active user
     *
     * @var int|null
     */
    protected $iActiveUserId;

    /**
     * The Active User object
     *
     * @var Resource\User
     */
    protected $oActiveUser;

    /**
     * The name of the "Remember me" cookie
     *
     * @var string
     */
    protected $sRememberCookie = 'nailsrememberme';

    /**
     * Whether the active user is to be remembered
     *
     * @var bool
     */
    protected $bIsRemembered = false;

    /**
     * Whether the active user is logged in or not
     *
     * @var bool
     */
    protected $bIsLoggedIn = false;

    /**
     * The name of the "Admin recovery" field
     *
     * @var string
     */
    protected $sAdminRecoveryField = 'nailsAdminRecoveryData';

    /**
     * The columns in the user table
     *
     * @var array|null
     */
    protected $aUserColumns = null;

    /**
     * The columns in the user meta table
     *
     * @var array|null
     */
    protected $aMetaColumns = null;

    /**
     * The name of the user meta table
     *
     * @var string
     */
    protected $tableMeta = NAILS_DB_PREFIX . 'user_meta_app';

    /**
     * The alias to give the user meta table
     *
     * @var string
     */
    protected $tableMetaAlias = 'um';

    /**
     * The name of the user email table
     *
     * @var string
     */
    protected $tableEmail = NAILS_DB_PREFIX . 'user_email';

    /**
     * The alias to give the user email table
     *
     * @var string
     */
    protected $tableEmailAlias = 'ue';

    /**
     * The name of the user group table
     *
     * @var string
     */
    protected $tableGroup = NAILS_DB_PREFIX . 'user_group';

    /**
     * The alias to give the user group table
     *
     * @var string
     */
    protected $tableGroupAlias = 'ug';

    // --------------------------------------------------------------------------

    /**
     * Construct the user model
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Set defaults
        $this->table             = Config::get('NAILS_DB_PREFIX') . 'user';
        $this->tableAlias        = 'u';
        $this->tableSlugColumn   = 'username';
        $this->defaultSortColumn = $this->tableIdColumn;
        $this->defaultSortOrder  = 'DESC';

        // --------------------------------------------------------------------------

        //  Define searchable fields, resetting it
        $this->searchableFields = [
            $this->tableAlias . '.id',
            $this->tableAlias . '.username',
            $this->tableEmailAlias . '.email',
            [
                $this->tableAlias . '.first_name',
                $this->tableAlias . '.last_name',
            ],
        ];

        // --------------------------------------------------------------------------

        //  Clear the activeUser
        $this->clearActiveUser();
    }

    // --------------------------------------------------------------------------

    /**
     * Initialise the generic user model
     *
     * @return void
     * @throws FactoryException
     * @throws ModelException
     */
    public function init()
    {
        /** @var Input $oInput */
        $oInput         = Factory::service('Input');
        $iTestingAsUser = $oInput->header(Testing::TEST_HEADER_USER_NAME);

        if (Environment::not(Environment::ENV_PROD) && $iTestingAsUser) {

            $oUser = $this->getById($iTestingAsUser);
            if (empty($oUser)) {
                set_status_header(500);
                ErrorHandler::halt('Not a valid user ID');
            }
            $this->setLoginData($oUser->id);

        } else {

            //  Refresh user's session
            $this->refreshSession();

            //  If no user is logged in, see if there's a remembered user to be logged in
            if (!$this->isLoggedIn()) {
                $this->loginRememberedUser();
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Log in a previously logged in user
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    protected function loginRememberedUser()
    {
        //  Is remember me functionality enabled?
        /** @var \Nails\Common\Service\Config $oConfig */
        $oConfig = Factory::service('Config');
        $oConfig->load('auth/auth');

        if (!$oConfig->item('authEnableRememberMe')) {
            return false;
        }

        // --------------------------------------------------------------------------

        //  Get the credentials from the cookie set earlier
        /** @var Cookie $oCookie */
        $oCookie  = Factory::service('Cookie');
        $remember = $oCookie->read($this->sRememberCookie);

        if ($remember) {

            $remember = explode('|', $remember);
            $email    = isset($remember[0]) ? $remember[0] : null;
            $code     = isset($remember[1]) ? $remember[1] : null;

            if ($email && $code) {

                //  Look up the user so we can cross-check the codes
                $user = $this->getByEmail($email);

                if ($user && $code === $user->remember_code) {
                    //  User was validated, log them in!
                    $this->setLoginData($user->id);
                    $this->iActiveUserId = $user->id;
                }
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a value from the active user's session data
     *
     * @param string $sKeys      The key to look up in activeUser
     * @param string $sDelimiter If multiple fields are requested they'll be joined by this string
     *
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
            return $this->oActiveUser;
        }

        // --------------------------------------------------------------------------

        //  If only one key is being requested then don't do anything fancy
        if (strpos($sKeys, ',') === false) {

            $val = isset($this->oActiveUser->{trim($sKeys)}) ? $this->oActiveUser->{trim($sKeys)} : null;

        } else {

            //  More than one key
            $aKeys = explode(',', $sKeys);
            $aKeys = array_filter($aKeys);
            $aOut  = [];

            foreach ($aKeys as $sKey) {

                //  If something is found, use that.
                if (isset($this->oActiveUser->{trim($sKey)})) {
                    $aOut[] = $this->oActiveUser->{trim($sKey)};
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
     *
     * @param Resource\User $oUser The user object to set
     *
     * @throws FactoryException
     */
    public function setActiveUser(Resource\User $oUser)
    {
        $this->oActiveUser = $oUser;
        /** @var DateTime $oDateTimeService */
        $oDateTimeService = Factory::service('DateTime');

        //  Set the user's date/time formats
        $sFormatDate = $this->activeUser('pref_date_format');
        $sFormatDate = $sFormatDate ? $sFormatDate : $oDateTimeService->getDateFormatDefaultSlug();

        $sFormatTime = $this->activeUser('pref_time_format');
        $sFormatTime = $sFormatTime ? $sFormatTime : $oDateTimeService->getTimeFormatDefaultSlug();

        $oDateTimeService->setUserFormats($sFormatDate, $sFormatTime);
    }

    // --------------------------------------------------------------------------

    /**
     * Clear the active user
     *
     * @return void
     * @throws FactoryException
     */
    public function clearActiveUser()
    {
        $this->oActiveUser = Factory::resource(static::RESOURCE_NAME, static::RESOURCE_PROVIDER, (object) []);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the user's login data
     *
     * @param Resource\User|string|int $mUser           The user's Resource, ID, or identifier
     * @param bool                     $bSetSessionData Whether to set the session data or not
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function setLoginData($mUser, bool $bSetSessionData = true): bool
    {
        //  Valid user?
        if ($mUser instanceof Resource\User) {

            $oUser = $mUser;

        } elseif (is_numeric($mUser)) {

            $oUser  = $this->getById($mUser);
            $sError = 'Invalid User ID.';

        } elseif (is_string($mUser)) {

            Factory::helper('email');
            if (valid_email($mUser)) {
                $oUser  = $this->getByEmail($mUser);
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
            $this->bIsLoggedIn = true;

            //  Set session variables
            if ($bSetSessionData) {
                /** @var Session $oSession */
                $oSession = Factory::service('Session');
                $oSession->setUserData([
                    'id'       => $oUser->id,
                    'email'    => $oUser->email,
                    'group_id' => $oUser->group_id,
                ]);
            }

            //  Set the active user
            $this->setActiveUser($oUser);

            /** @var Event $oEventService */
            $oEventService = Factory::service('Event');
            $oEventService->trigger(
                Events::USER_LOG_IN,
                Events::getEventNamespace(),
                [$oUser->id]
            );

            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Clears the login data for a user
     *
     * @return  void
     * @throws FactoryException
     * @throws NailsException
     * @throws ReflectionException
     */
    public function clearLoginData()
    {
        $iUserId = $this->activeUser('id');

        //  Clear the session
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        $oSession->unsetUserData('id');
        $oSession->unsetUserData('email');
        $oSession->unsetUserData('group_id');

        //  Set the flag
        $this->bIsLoggedIn = false;

        //  Reset the activeUser
        $this->clearActiveUser();

        //  Remove any remember me cookie
        $this->clearRememberCookie();

        /** @var Event $oEventService */
        $oEventService = Factory::service('Event');
        $oEventService->trigger(
            Events::USER_LOG_OUT,
            Events::getEventNamespace(),
            [$iUserId]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is logged in or not.
     *
     * @return  bool
     */
    public function isLoggedIn(): bool
    {
        return $this->bIsLoggedIn;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is to be remembered
     *
     * @return  bool
     */
    public function bIsRemembered(): bool
    {
        //  Deja vu?
        if (!is_null($this->bIsRemembered)) {
            return $this->bIsRemembered;
        }

        // --------------------------------------------------------------------------

        /**
         * Look for the remember me cookie and explode it, if we're landed with a 2
         * part array then it's likely this is a valid cookie - however, this test
         * is, obviously, not gonna detect a spoof.
         */

        $cookie = get_cookie($this->sRememberCookie);
        $cookie = explode('|', $cookie);

        $this->bIsRemembered = count($cookie) == 2;

        return $this->bIsRemembered;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user group has admin permissions.
     *
     * @param mixed $user The user to check, uses activeUser if null
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
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
     *
     * @return bool
     * @throws FactoryException
     */
    public function wasAdmin()
    {
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        return (bool) $oSession->getUserData($this->sAdminRecoveryField);
    }

    // --------------------------------------------------------------------------

    /**
     * Adds to the admin recovery array, allowing suers to login as other users multiple times, and come back
     *
     * @param int    $loggingInAs The ID of the user who is being imitated
     * @param string $returnTo    Where to redirect the user when they log back in
     *
     * @throws FactoryException
     */
    public function setAdminRecoveryData($loggingInAs, $returnTo = '')
    {
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        //  Look for existing Recovery Data
        $existingRecoveryData = $oSession->getUserData($this->sAdminRecoveryField);

        if (empty($existingRecoveryData)) {

            $existingRecoveryData = [];
        }

        //  Prepare the new element
        $adminRecoveryData = (object) [
            'oldUserId' => activeUser('id'),
            'newUserId' => $loggingInAs,
            'hash'      => activeUser('password_md5'),
            'name'      => activeUser('first_name,last_name'),
            'email'     => activeUser('email'),
            'returnTo'  => empty($returnTo) ? $oInput->server('REQUEST_URI') : $returnTo,
            'loginUrl'  => siteUrl(
                sprintf(
                    'auth/override/login_as/%s/%s?retruningAdmin=1',
                    activeUser('id_md5'),
                    activeUser('password_md5')
                )
            ),
        ];

        //  Put the new session onto the stack and save to the session
        $existingRecoveryData[] = $adminRecoveryData;

        $oSession->setUserData($this->sAdminRecoveryField, $existingRecoveryData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the recovery data at the bottom of the stack, i.e the most recently added
     *
     * @return array|stdClass
     * @throws FactoryException
     */
    public function getAdminRecoveryData()
    {
        /** @var Session $oSession */
        $oSession             = Factory::service('Session');
        $existingRecoveryData = $oSession->getUserData($this->sAdminRecoveryField);

        if (empty($existingRecoveryData)) {
            return [];
        } else {
            return end($existingRecoveryData);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Removes the most recently added recovery data from the stack
     *
     * @return void
     * @throws FactoryException
     */
    public function unsetAdminRecoveryData()
    {
        /** @var Session $oSession */
        $oSession              = Factory::service('Session');
        $aExistingRecoveryData = $oSession->getUserData($this->sAdminRecoveryField);

        if (empty($aExistingRecoveryData)) {
            $aExistingRecoveryData = [];
        } else {
            array_pop($aExistingRecoveryData);
        }

        $oSession->setUserData($this->sAdminRecoveryField, $aExistingRecoveryData);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the active user is a superuser. Extend this method to
     * alter it's response.
     *
     * @param mixed $user The user to check, uses activeUser if null
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function isSuperuser($user = null)
    {
        return $this->hasPermission('admin:superuser', $user);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether the specified user has a certain ACL permission
     *
     * @param string $sSearch The permission to check for
     * @param mixed  $mUser   The user to check for; if null uses activeUser, if numeric, fetches user, if object uses that object
     *
     * @return  bool
     * @throws FactoryException
     * @throws ModelException
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
            $aAcl = (array) $this->activeUser('acl');
        }

        // --------------------------------------------------------------------------

        // Super users or CLI users can do anything their heart desires
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        if (in_array('admin:superuser', $aAcl) || $oInput::isCli()) {
            return true;
        }

        // --------------------------------------------------------------------------

        /**
         * Test the ACL
         * We're going to use regular expressions here so we can allow for some
         * flexibility in the search, i.e admin:* would return true if the user has
         * access to any of admin.
         */

        $bHasPermission = false;

        /**
         * Replace :* with :.* - this is a common mistake when using the permission
         * system (i.e., assuming that star on it's own will match)
         */

        $sSearch = strtolower(preg_replace('/:\*/', ':.*', $sSearch));

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
     *
     * @param array $aData Data passed from the calling method
     *
     * @return void
     */
    protected function getCountCommon(array $aData = []): void
    {
        //  Define the selects
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select($this->tableAlias . '.*');
        $oDb->select([
            $this->tableEmailAlias . '.email',
            $this->tableEmailAlias . '.code email_verification_code',
            $this->tableEmailAlias . '.is_verified email_is_verified',
            $this->tableEmailAlias . '.date_verified email_is_verified_on',
        ]);
        $oDb->select(
            array_values(
                array_map(
                    function (Field $oField) {
                        return $this->tableMetaAlias . '.' . $oField->key;
                    },
                    $this->describeMetaFields()
                )
            )
        );
        $oDb->select([
            $this->tableGroupAlias . '.slug group_slug',
            $this->tableGroupAlias . '.label group_name',
            $this->tableGroupAlias . '.default_homepage group_homepage',
            $this->tableGroupAlias . '.acl group_acl',
        ]);

        // --------------------------------------------------------------------------

        //  Define the joins
        $oDb->join(
            $this->tableEmail . ' ' . $this->tableEmailAlias,
            $this->tableAlias . '.id = ' . $this->tableEmailAlias . '.user_id AND ' . $this->tableEmailAlias . '.is_primary = 1',
            'LEFT'
        );

        $oDb->join(
            $this->tableMeta . ' ' . $this->tableMetaAlias,
            $this->tableAlias . '.id = ' . $this->tableMetaAlias . '.user_id',
            'LEFT'
        );

        $oDb->join(
            $this->tableGroup . ' ' . $this->tableGroupAlias,
            $this->tableAlias . '.group_id = ' . $this->tableGroupAlias . '.id',
            'LEFT'
        );

        //  Let the parent method handle sorting, etc
        parent::getCountCommon($aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Filter out duplicates and prefix column names if necessary
     *
     * @param string $sPrefix
     * @param array  $aCols
     *
     * @return array
     */
    protected function prepareDbColumns($sPrefix = '', $aCols = [])
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
     *
     * @param string $sIdentifier The user's identifier, either an email address or a username
     *
     * @return Resource\User|null
     * @throws FactoryException
     * @throws ModelException
     */
    public function getByIdentifier(string $sIdentifier): ?Resource\User
    {
        switch (Config::get('APP_NATIVE_LOGIN_USING')) {

            case 'EMAIL':
                return $this->getByEmail($sIdentifier);
                break;

            case 'USERNAME':
                return $this->getByUsername($sIdentifier);
                break;

            default:
                Factory::helper('email');
                if (valid_email($sIdentifier)) {
                    return $this->getByEmail($sIdentifier);
                } else {
                    return $this->getByUsername($sIdentifier);
                }
                break;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their email address
     *
     * @param string $sEmail The user's email address
     *
     * @return Resource\User|null
     * @throws FactoryException
     * @throws ModelException
     */
    public function getByEmail(string $sEmail): ?Resource\User
    {
        //  Look up the email, and if we find an ID then fetch that user
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select('user_id');
        $oDb->where('email', trim($sEmail));
        $user = $oDb->get($this->tableEmail)->row();

        return $user ? $this->getById($user->user_id) : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their username
     *
     * @param string $sUsername The user's username
     *
     * @return Resource\User|null
     * @throws ModelException
     */
    public function getByUsername(string $sUsername): ?Resource\User
    {
        $aUsers = $this->getAll([
            'where' => [
                [
                    'column' => $this->tableAlias . '.username',
                    'value'  => $sUsername,
                ],
            ],
        ]);

        return empty($aUsers) ? null : reset($aUsers);
    }

    // --------------------------------------------------------------------------

    /**
     * Get a specific user by a MD5 hash of their ID and password
     *
     * @param string $md5Id The MD5 hash of their ID
     * @param string $md5Pw The MD5 hash of their password
     *
     * @return Resource\User|null
     * @throws ModelException
     */
    public function getByHashes(string $md5Id, string $md5Pw): ?Resource\User
    {
        if (empty($md5Id) || empty($md5Pw)) {
            return null;
        }

        $aUsers = $this->getAll([
            'where' => [
                [
                    'column' => $this->tableAlias . '.id_md5',
                    'value'  => $md5Id,
                ],
                [
                    'column' => $this->tableAlias . '.password_md5',
                    'value'  => $md5Pw,
                ],
            ],
        ]);

        return empty($aUsers) ? null : reset($aUsers);
    }

    // --------------------------------------------------------------------------

    /**
     * Get a user by their referral code
     *
     * @param string $sReferralCode The user's referral code
     *
     * @return Resource\User|null
     * @throws ModelException
     */
    public function getByReferral(string $sReferralCode): ?Resource\User
    {
        $aUsers = $this->getAll([
            'where' => [
                [
                    'column' => $this->tableAlias . '.referral',
                    'value'  => $sReferralCode,
                ],
            ],
        ]);

        return empty($aUsers) ? null : reset($aUsers);
    }

    // --------------------------------------------------------------------------

    /**
     * Get all the email addresses which are registered to a particular user ID
     *
     * @param int $id The user's ID
     *
     * @return array
     * @throws FactoryException
     */
    public function getEmailsForUser($id)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $id);
        $oDb->order_by('date_added');
        $oDb->order_by('email', 'ASC');
        return $oDb->get($this->tableEmail)->result();
    }

    // --------------------------------------------------------------------------

    /**
     * Update a user, if $iUserId is not set method will attempt to update the
     * active user. If $data is passed then the method will attempt to update
     * the user and/or user_meta_* tables
     *
     * @param int   $iUserId The ID of the user to update
     * @param array $aData   Any data to be updated
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function update($iUserId = null, array $aData = null): bool
    {
        /** @var \DateTime $oDate */
        $oDate   = Factory::factory('DateTime');
        $aData   = (array) $aData;
        $iUserId = $this->getUserId($iUserId);
        if (empty($iUserId)) {
            return false;
        }

        // --------------------------------------------------------------------------

        $oOldUser = $this->getById($iUserId);
        if (empty($oOldUser)) {
            $this->setError('Invalid user ID');
            return false;
        }

        //  Deep clone so we're sure it isn't indvertantly updated
        $oOldUser = unserialize(serialize($oOldUser));

        // --------------------------------------------------------------------------

        //  If there's some data we'll need to know the columns of `user`
        //  We also want to unset any 'dangerous' items then set it for the query

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        if ($aData) {

            //  Set the cols in `user` (rather than querying the DB)
            $aCols = array_keys($this->describeFields());

            //  Safety first, no updating of sensitive fields
            unset($aData['id']);
            unset($aData['id_md5']);
            unset($aData['password_md5']);
            unset($aData['password_engine']);
            unset($aData['password_changed']);
            unset($aData['salt']);

            $sNewPassword = getFromArray('password', $aData);
            unset($aData['password']);

            //  Set the data
            $aDataUser         = [];
            $aDataMeta         = [];
            $sDataEmail        = '';
            $sDataUsername     = '';
            $bResetMfaQuestion = false;
            $bResetMfaDevice   = false;

            foreach ($aData as $key => $val) {

                //  user or user_meta?
                if (array_search($key, $aCols) !== false) {

                    //  Careful now, some items cannot be blank and must be null
                    switch ($key) {

                        //  Int or null
                        case 'profile_img':
                            $aDataUser[$key] = (int) $val ?: null;
                            break;

                        //  Null if empty
                        case 'dob':
                            $aDataUser[$key] = $val ?: null;
                            break;

                        //  Boolean
                        case 'temp_pw':
                            $aDataUser[$key] = (bool) $val;
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
                    $bResetMfaQuestion = $val;
                } elseif ($key == 'reset_mfa_device') {
                    $bResetMfaDevice = $val;
                } else {
                    $aDataMeta[$key] = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  If a username has been passed then check if it's available
            if (!empty($sDataUsername)) {
                //  Check username is valid
                if (!$this->isValidUsername($sDataUsername, true, $iUserId)) {
                    //  isValidUsername will set the error message
                    return false;
                } else {
                    $aDataUser['username'] = $sDataUsername;
                }
            }

            // --------------------------------------------------------------------------

            if (!empty($sDataEmail)) {
                Factory::helper('email');
                if (!valid_email($sDataEmail)) {
                    $this->setError('"' . $sDataEmail . '" is not a valid email address.');
                    return false;
                }
            }

            // --------------------------------------------------------------------------

            //  Begin transaction
            try {

                $oDb->trans_begin();

                // --------------------------------------------------------------------------

                //  Resetting 2FA?
                if ($bResetMfaQuestion || $bResetMfaDevice) {

                    /** @var \Nails\Common\Service\Config $oConfig */
                    $oConfig = Factory::service('Config');
                    $oConfig->load('auth/auth');
                    $sTwoFactorMode = $oConfig->item('authTwoFactorMode');

                    if ($sTwoFactorMode == 'QUESTION' && $bResetMfaQuestion) {

                        $oDb->where('user_id', $iUserId);
                        if (!$oDb->delete(Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_question')) {
                            $oDb->trans_rollback();
                            $this->setError('Could not reset user\'s Multi Factor Authentication questions.');
                            return false;
                        }

                    } elseif ($sTwoFactorMode == 'DEVICE' && $bResetMfaDevice) {

                        $oDb->where('user_id', $iUserId);
                        if (!$oDb->delete(Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_device_secret')) {
                            $oDb->trans_rollback();
                            $this->setError('Could not reset user\'s Multi Factor Authentication device.');
                            return false;
                        }
                    }
                }

                // --------------------------------------------------------------------------

                //  Update the user table
                $oDb->where('id', $iUserId);
                $oDb->set('last_update', 'NOW()', false);

                if ($aDataUser) {
                    $oDb->set($aDataUser);
                }

                if (!$oDb->update($this->table)) {
                    throw new NailsException('Failed to update user table.');
                }

                // --------------------------------------------------------------------------

                //  Update the meta table
                if ($aDataMeta) {
                    $oDb->where('user_id', $iUserId);
                    $oDb->set($aDataMeta);
                    if (!$oDb->update($this->tableMeta)) {
                        throw new NailsException('Failed to update user meta table.');
                    }
                }

                // --------------------------------------------------------------------------

                //  Update the password if it has been supplied
                if (!empty($sNewPassword)) {
                    $bIsTemp = (bool) getFromArray('temp_pw', $aData);
                    if (!$oUserPasswordModel->change($iUserId, $sNewPassword, $bIsTemp)) {
                        throw new NailsException(
                            'Failed to change password. ' .
                            $oUserPasswordModel->lastError()
                        );
                    }
                }

                // --------------------------------------------------------------------------

                //  If an email has been passed then attempt to update the user's email too
                if ($sDataEmail) {

                    //  Check if the email is already being used
                    $oDb->where('email', $sDataEmail);
                    $oEmail = $oDb->get($this->tableEmail)->row();

                    if ($oEmail) {

                        /**
                         * Email is in use, if it's in use by the ID of this user then set
                         * it as the primary email for this account. If it's in use by
                         * another user then error
                         */

                        if ($oEmail->user_id == $iUserId) {
                            $this->emailMakePrimary($oEmail->email);
                        } else {
                            throw new NailsException('Email is already in use.');
                        }

                    } else {

                        /**
                         * Doesn't appear to be in use, add as a new email address and
                         * make it the primary one
                         */
                        $this->emailAdd($sDataEmail, $iUserId, true, false, true, false);
                    }
                }

                $oDb->trans_commit();

            } catch (\Exception $e) {
                $oDb->trans_rollback();
                $this->setError($e->getMessage());
                return false;
            }

        } else {

            /**
             * If there was no data then run an update anyway on just user table. We need to
             * do this as some methods will use $oDb->set() before calling update(); not
             * sure if this is a bad design or not... sorry.
             */

            $oDb->set('last_update', 'NOW()', false);
            $oDb->where('id', $iUserId);
            $oDb->update($this->table);
        }

        // --------------------------------------------------------------------------

        //  If we just updated the active user we should probably update their session info
        if ($iUserId == $this->activeUser('id')) {
            $this->oActiveUser->last_update = $oDate->format('Y-m-d H:i:s');
            if ($aData) {
                foreach ($aData as $key => $val) {
                    $this->oActiveUser->{$key} = $val;
                }
            }

            // --------------------------------------------------------------------------

            //  Do we need to update any timezone/date/time preferences?
            if (isset($aData['timezone'])) {
                /** @var DateTime $oDateTimeService */
                $oDateTimeService = Factory::service('DateTime');
                $oDateTimeService->setUserTimezone($aData['timezone']);
            }

            if (isset($aData['datetime_format_date'])) {
                /** @var DateTime $oDateTimeService */
                $oDateTimeService = Factory::service('DateTime');
                $oDateTimeService->setUserDateFormat($aData['datetime_format_date']);
            }

            if (isset($aData['datetime_format_time'])) {
                /** @var DateTime $oDateTimeService */
                $oDateTimeService = Factory::service('DateTime');
                $oDateTimeService->setUserTimeFormat($aData['datetime_format_time']);
            }

            // --------------------------------------------------------------------------

            //  If there's a remember me cookie then update that too, but only if the password
            //  or email address has changed

            if ((isset($aData['email']) || !empty($bPasswordUpdated)) && $this->bIsRemembered()) {
                $this->setRememberCookie();
            }
        }

        //  Clear the caches for this user
        $this->unsetCacheUser($iUserId);

        $this->triggerEvent(
            Events::USER_MODIFIED,
            [$iUserId, $oOldUser]
        );

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Works out the correct user ID, falls back to activeUser()
     *
     * @param int $iUserId The user ID to use
     *
     * @return int
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
     * Saves a user object to the persistent cache
     *
     * @param int   $iUserId The user ID to cache
     * @param array $aData   The data array
     *
     * @return bool
     * @throws ModelException
     */
    public function setCacheUser($iUserId, $aData = [])
    {
        $this->unsetCacheUser($iUserId);
        $oUser = $this->getById($iUserId);

        if (empty($oUser)) {
            return false;
        }

        $this->setCache(
            $this->prepareCacheKey($this->tableIdColumn, $oUser->id, $aData),
            $oUser
        );

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Removes a user from the cache
     *
     * @param int $iUserId The User ID to remove
     */
    protected function unsetCacheUser($iUserId)
    {
        $this->unsetCachePrefix(
            $this->prepareCacheKey($this->tableIdColumn, $iUserId)
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Adds a new email to the user email table. Will optionally send the verification email, too.
     *
     * @param string $sEmail        The email address to add
     * @param int    $iUserId       The ID of the user to add for, defaults to $this->activeUser('id')
     * @param bool   $bIsPrimary    Whether or not the email address should be the primary email address for the user
     * @param bool   $bIsVerified   Whether or not the email should be marked as verified
     * @param bool   $bSendEmail    If unverified, whether or not the verification email should be sent
     * @param bool   $bTriggerEvent Whether to trigger the user modified event
     *
     * @return bool|string          String containing verification code on success, false on failure
     * @throws FactoryException
     * @throws ModelException
     */
    public function emailAdd(
        string $sEmail,
        int $iUserId = null,
        bool $bIsPrimary = false,
        bool $bIsVerified = false,
        bool $bSendEmail = true,
        bool $bTriggerEvent = true
    ) {

        $iUserId = empty($iUserId) ? $this->activeUser('id') : $iUserId;
        $sEmail  = trim(strtolower($sEmail));
        $oUser   = $this->getById($iUserId);

        if (empty($oUser)) {
            $this->setError('Invalid User ID');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Make sure the email is valid
        Factory::helper('email');
        if (!valid_email($sEmail)) {
            $this->setError('"' . $sEmail . '" is not a valid email address');
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * Test email, if it's in use and for the same user then return true. If it's
         * in use by a different user then return an error.
         */

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select('id, user_id, is_verified, code');
        $oDb->where('email', $sEmail);
        $oTest = $oDb->get($this->tableEmail)->row();

        if ($oTest) {

            if ($oTest->user_id == $oUser->id) {

                /**
                 * In use, but belongs to the same user - return the code (imitates
                 * behavior of newly added email)
                 */

                if ($bIsPrimary) {
                    $this->emailMakePrimary($oTest->id, $oUser->id, false);
                }

                //  Resend verification email?
                if ($bSendEmail && !$oTest->is_verified) {
                    $this->emailAddSendVerify($oTest->id);
                }

                $this->unsetCacheUser($oUser->id);

                if ($bTriggerEvent) {
                    $this->triggerEvent(
                        Events::USER_MODIFIED,
                        [$oUser->id, $oUser]
                    );
                }

                return $oTest->code;

            } else {

                //  In use, but belongs to another user
                $this->setError('Email in use by another user.');
                return false;
            }
        }

        // --------------------------------------------------------------------------

        /** @var Password $oPasswordModel */
        $oPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);
        $sCode          = $oPasswordModel->salt();

        $oDb->set('user_id', $oUser->id);
        $oDb->set('email', $sEmail);
        $oDb->set('code', $sCode);
        $oDb->set('is_verified', (bool) $bIsVerified);
        $oDb->set('date_added', 'NOW()', false);

        if ((bool) $bIsVerified) {
            $oDb->set('date_verified', 'NOW()', false);
        }

        $oDb->insert($this->tableEmail);

        if ($oDb->affected_rows()) {

            //  Email ID
            $iEmailId = $oDb->insert_id();

            //  Make it the primary email address?
            if ($bIsPrimary) {
                $this->emailMakePrimary($iEmailId, $oUser->id, false);
            }

            //  Send off the verification email
            if ($bSendEmail && !$bIsVerified) {
                $this->emailAddSendVerify($iEmailId);
            }

            //  Cache the user
            $this->unsetCacheUser($oUser->id);

            //  Update the activeUser
            if ($oUser->id == $this->activeUser('id')) {

                /** @var \DateTime $oDate */
                $oDate                          = Factory::factory('DateTime');
                $this->oActiveUser->last_update = $oDate->format('Y-m-d H:i:s');

                if ($bIsPrimary) {
                    $this->oActiveUser->email                   = $sEmail;
                    $this->oActiveUser->email_verification_code = $sCode;
                    $this->oActiveUser->email_is_verified       = (bool) $bIsVerified;
                    $this->oActiveUser->email_is_verified_on    = (bool) $bIsVerified ? $oDate->format('Y-m-d H:i:s') : null;
                }
            }

            if ($bTriggerEvent) {
                $this->triggerEvent(
                    Events::USER_MODIFIED,
                    [$oUser->id, $oUser]
                );
            }

            return $sCode;

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Send, or resend, the verify email for a particular email address
     *
     * @param int $email_id The email's ID
     * @param int $iUserId  The user's ID
     *
     * @return bool
     * @throws FactoryException
     */
    public function emailAddSendVerify($email_id, $iUserId = null)
    {
        if (!Config::get('NAILS_AUTH_EMAIL_VERIFY_ON_ADD', true)) {
            return true;
        }

        //  Fetch the email and the user's group
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select(
            [
                $this->tableEmailAlias . '.id',
                $this->tableEmailAlias . '.code',
                $this->tableEmailAlias . '.is_verified',
                $this->tableEmailAlias . '.user_id',
                $this->tableAlias . '.group_id',
            ]
        );

        if (is_numeric($email_id)) {
            $oDb->where($this->tableEmailAlias . '.id', $email_id);
        } else {
            $oDb->where($this->tableEmailAlias . '.email', $email_id);
        }

        if (!empty($iUserId)) {
            $oDb->where($this->tableEmailAlias . '.user_id', $iUserId);
        }

        $oDb->join(
            $this->table . ' ' . $this->tableAlias,
            $this->tableAlias . '.id = ' . $this->tableEmailAlias . '.user_id'
        );

        $oEmailRow = $oDb->get($this->tableEmail . ' ' . $this->tableEmailAlias)->row();

        if (!$oEmailRow) {
            $this->setError('Invalid Email.');
            return false;
        }

        if ($oEmailRow->is_verified) {
            $this->setError('Email is already verified.');
            return false;
        }

        // --------------------------------------------------------------------------

        /** @var Email\Service\Emailer $oEmailer */
        $oEmailer = Factory::service('Emailer', Email\Constants::MODULE_SLUG);
        $oEmail   = (object) [
            'type'  => 'verify_email_' . $oEmailRow->group_id,
            'to_id' => $oEmailRow->user_id,
            'data'  => (object) [
                'verifyUrl' => siteUrl('email/verify/' . $oEmailRow->user_id . '/' . $oEmailRow->code),
            ],
        ];

        if (!$oEmailer->send($oEmail, true)) {

            //  Failed to send using the group email, try using the generic email template
            $oEmail->type = 'verify_email';

            if (!$oEmailer->send($oEmail, true)) {
                //  Email failed to send, for now, do nothing.
                $this->setError('The verification email failed to send.');
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes a non-primary email from the user email table, optionally filtering
     * by $iUserId
     *
     * @param int|string $mEmailId      The email address, or the ID of the email address to remove
     * @param int        $iUserId       The ID of the user to restrict to
     * @param bool       $bTriggerEvent Whether to trigger the user modified event
     *
     * @return bool
     * @throws FactoryException
     */
    public function emailDelete($mEmailId, int $iUserId = null, bool $bTriggerEvent = true)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        if (is_numeric($mEmailId)) {
            $oDb->where('id', $mEmailId);
        } else {
            $oDb->where('email', $mEmailId);
        }

        if (!empty($iUserId)) {
            $oDb->where('user_id', $iUserId);
        }

        $oDb->where('is_primary', false);
        $oRow = $oDb->get($this->tableEmail)->row();

        if (empty($oRow)) {
            $this->setError('"' . $mEmailId . '" is not a known email or ID');
            return false;
        }

        $oUser = $this->getById($oRow->user_id);

        $oDb->where('id', $oRow->id);

        if ((bool) $oDb->affected_rows()) {

            $this->unsetCacheUser($oUser->id);
            if ($bTriggerEvent) {
                $this->triggerEvent(
                    Events::USER_MODIFIED,
                    [$oUser->id, $oUser]
                );
            }

            return true;

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Verifies whether the supplied $code is valid for the requested user ID or email
     * address. If it is then the email is marked as verified.
     *
     * @param int|string $mIdEmail      The numeric ID of the user, or the email address
     * @param string     $sCode         The verification code as generated by emailAdd()
     * @param bool       $bTriggerEvent Whether to trigger the user modified event
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function emailVerify($mIdEmail, string $sCode, bool $bTriggerEvent = true)
    {
        //  Check user exists
        if (is_numeric($mIdEmail)) {
            $oUser = $this->getById($mIdEmail);
        } else {
            $oUser = $this->getByEmail($mIdEmail);
        }

        if (!$oUser) {
            $this->setError('User does not exist.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Check if email has already been verified
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $oUser->id);
        $oDb->where('is_verified', true);
        $oDb->where('code', $sCode);

        if ($oDb->count_all_results($this->tableEmail)) {
            $this->setError('Email has already been verified.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Go ahead and set as verified
        $oDb->set('is_verified', true);
        $oDb->set('date_verified', 'NOW()', false);
        $oDb->where('user_id', $oUser->id);
        $oDb->where('is_verified', false);
        $oDb->where('code', $sCode);

        $oDb->update($this->tableEmail);

        if ((bool) $oDb->affected_rows()) {

            $this->unsetCacheUser($oUser->id);

            //  Update the activeUser
            if ($oUser->id == $this->activeUser('id')) {

                /** @var \DateTime $oDate */
                $oDate                          = Factory::factory('DateTime');
                $this->oActiveUser->last_update = $oDate->format('Y-m-d H:i:s');

                //  @todo: update the rest of the activeUser
            }

            if ($bTriggerEvent) {
                $this->triggerEvent(
                    Events::USER_MODIFIED,
                    [$oUser->id, $oUser]
                );
            }

            return true;

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets an email address as the primary email address for that user.
     *
     * @param int|string $mIdEmail      The numeric  ID of the email address, or the email address itself
     * @param int        $iUserId       Specify the user ID which this should apply to
     * @param bool       $bTriggerEvent Whether to trigger the user modified event
     *
     * @return bool
     * @throws FactoryException
     */
    public function emailMakePrimary($mIdEmail, int $iUserId = null, bool $bTriggerEvent = true): bool
    {
        //  Fetch email
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->select('id,user_id,email');

        if (is_numeric($mIdEmail)) {
            $oDb->where('id', $mIdEmail);
        } else {
            $oDb->where('email', $mIdEmail);
        }

        if (!is_null($iUserId)) {
            $oDb->where('user_id', $iUserId);
        }

        $oEmail = $oDb->get($this->tableEmail)->row();

        if (empty($oEmail)) {
            return false;
        }

        $oUser = $this->getById($oEmail->user_id);

        //  Update
        $oDb->trans_begin();
        try {
            $oDb->set('is_primary', false);
            $oDb->where('user_id', $oEmail->user_id);
            $oDb->update($this->tableEmail);

            $oDb->set('is_primary', true);
            $oDb->where('id', $oEmail->id);
            $oDb->update($this->tableEmail);

            $this->unsetCacheUser($oEmail->user_id);

            //  Update the activeUser
            if ($oEmail->user_id == $this->activeUser('id')) {

                $oDate                          = Factory::factory('DateTime');
                $this->oActiveUser->last_update = $oDate->format('Y-m-d H:i:s');

                //  @todo: update the rest of the activeUser
            }

            $oDb->trans_commit();

            if ($bTriggerEvent) {
                $this->triggerEvent(
                    Events::USER_MODIFIED,
                    [$oUser->id, $oUser]
                );
            }

            return true;

        } catch (\Exception $e) {
            $this->setError('Failed to set primary email. ' . $e->getMessage());
            $oDb->trans_rollback();
            return false;
        }

    }

    // --------------------------------------------------------------------------

    /**
     * Increment the user's failed logins
     *
     * @param int $iUserId  The user ID to increment
     * @param int $iExpires How long till the block, if the threshold is reached, expires.
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function incrementFailedLogin(int $iUserId, int $iExpires = 300): bool
    {
        /** @var \DateTime $oDate */
        $oDate = Factory::factory('DateTime');
        $oDate->add(new \DateInterval('PT' . $iExpires . 'S'));

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->set('failed_login_count', '`failed_login_count`+1', false);
        $oDb->set('failed_login_expires', $oDate->format('Y-m-d H:i:s'));
        return $this->update($iUserId);
    }

    // --------------------------------------------------------------------------

    /**
     * Reset a user's failed login
     *
     * @param int $iUserId The user ID to reset
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function resetFailedLogin(int $iUserId): bool
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->set('failed_login_count', 0);
        $oDb->set('failed_login_expires', 'null', false);
        return $this->update($iUserId);
    }

    // --------------------------------------------------------------------------

    /**
     * Update a user's `last_login` field
     *
     * @param int $iUserId The user ID to update
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function updateLastLogin($iUserId)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->set('last_login', 'NOW()', false);
        $oDb->set('login_count', 'login_count+1', false);
        return $this->update($iUserId);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the user's 'rememberMe' cookie, nom nom nom
     *
     * @param int    $iId       The User's ID
     * @param string $sPassword The user's password, hashed
     * @param string $sEmail    The user's email\
     *
     * @return bool
     * @throws FactoryException
     */
    public function setRememberCookie($iId = null, $sPassword = null, $sEmail = null)
    {
        //  Is remember me functionality enabled?
        /** @var \Nails\Common\Service\Config $oConfig */
        $oConfig = Factory::service('Config');
        $oConfig->load('auth/auth');

        if (!$oConfig->item('authEnableRememberMe')) {
            return false;
        }

        // --------------------------------------------------------------------------

        if (empty($iId) || empty($sPassword) || empty($sEmail)) {

            if (!activeUser('id') || !activeUser('password') || !activeUser('email')) {

                return false;

            } else {
                $iId       = $this->activeUser('id');
                $sPassword = $this->activeUser('password');
                $sEmail    = $this->activeUser('email');
            }
        }

        // --------------------------------------------------------------------------

        //  Generate a code to remember the user by and save it to the DB
        /** @var Encrypt $oEncrypt */
        $oEncrypt = Factory::service('Encrypt');
        $sSalt    = $oEncrypt->encode(sha1($iId . $sPassword . $sEmail . Config::get('APP_PRIVATE_KEY') . time()));

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        $oDb->set('remember_code', $sSalt);
        $oDb->where('id', $iId);
        $oDb->update($this->table);

        // --------------------------------------------------------------------------

        //  Set the cookie
        set_cookie([
            'name'   => $this->sRememberCookie,
            'value'  => $sEmail . '|' . $sSalt,
            'expire' => 1209600, //   2 weeks
        ]);

        // --------------------------------------------------------------------------

        //  Update the flag
        $this->bIsRemembered = true;

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Clear the active user's 'rememberMe' cookie
     *
     * @return void
     */
    public function clearRememberCookie()
    {
        delete_cookie($this->sRememberCookie);

        //  Update the flag
        $this->bIsRemembered = false;
    }

    // --------------------------------------------------------------------------

    /**
     * Refresh the user's session from the database
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     * @throws NailsException
     * @throws ReflectionException
     */
    protected function refreshSession()
    {
        //  Get the user; be wary of admin's logged in as other people
        /** @var Session $oSession */
        $oSession = Factory::service('Session');
        if ($this->wasAdmin()) {

            $recoveryData = $this->getAdminRecoveryData();

            if (!empty($recoveryData->newUserId)) {
                $me = $recoveryData->newUserId;
            } else {
                $me = $oSession->getUserData('id');
            }

        } else {
            $me = $oSession->getUserData('id');
        }

        //  Is anybody home? Hello...?
        if (!$me) {
            $me = $this->iActiveUserId;
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
            $this->bIsLoggedIn = false;
            return false;
        }

        // --------------------------------------------------------------------------

        //  Store this entire user in memory
        $this->setActiveUser($me);

        // --------------------------------------------------------------------------

        //  Set the user's logged in flag
        $this->bIsLoggedIn = true;

        // --------------------------------------------------------------------------

        //  Update user's `last_seen` and `last_ip` properties
        if (!wasAdmin()) {
            /** @var Database $oDb */
            $oDb = Factory::service('Database');
            /** @var Input $oInput */
            $oInput = Factory::service('Input');

            $oDb->set('last_seen', 'NOW()', false);
            if (Config::get('NAILS_AUTH_LOG_IP', true)) {
                $oDb->set('last_ip', $oInput->ipAddress());
            }
            $oDb->where('id', $me->id);
            $oDb->update($this->table);
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new user
     *
     * @param array $data         An array of data to create the user with
     * @param bool  $bSendWelcome Whether to send the welcome email
     *
     * @return mixed                StdClass on success, false on failure
     * @throws FactoryException
     * @throws ModelException
     */
    public function create(array $data = [], $bSendWelcome = true)
    {
        /** @var \DateTime $oDate */
        $oDate = Factory::factory('DateTime');
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Group $oUserGroupModel */
        $oUserGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        //  Has an email or a username been submitted?
        if (Config::get('APP_NATIVE_LOGIN_USING') == 'EMAIL') {

            //  Email defined?
            if (empty($data['email'])) {
                $this->setError('An email address must be supplied.');
                return false;
            }

            //  Check email against DB
            $oDb->where('email', $data['email']);
            if ($oDb->count_all_results($this->tableEmail)) {
                $this->setError('This email is already in use.');
                return false;
            }

        } elseif (Config::get('APP_NATIVE_LOGIN_USING') == 'USERNAME') {

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
            $oDb->where('email', $data['email']);
            if ($oDb->count_all_results($this->tableEmail)) {
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
        $aUserData = [];

        // --------------------------------------------------------------------------

        //  Check that we're dealing with a valid group
        if (empty($data['group_id'])) {
            $aUserData['group_id'] = $oUserGroupModel->getDefaultGroupId();
        } else {
            $aUserData['group_id'] = $data['group_id'];
        }

        $oGroup = $oUserGroupModel->getById($aUserData['group_id']);

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

        try {

            if (empty($data['password'])) {
                $oPassword = $oUserPasswordModel->generateNullHash();
            } else {
                $oPassword = $oUserPasswordModel->generateHash($aUserData['group_id'], $data['password']);
            }

        } catch (NailsException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        /**
         * Do we need to inform the user of their password? This might be set if an
         * admin created the account, or if the system generated a new password
         */

        $bInformUserPw = !empty($data['inform_user_pw']);

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
        $aUserData['created']         = $oDate->format('Y-m-d H:i:s');
        $aUserData['last_update']     = $oDate->format('Y-m-d H:i:s');
        $aUserData['is_suspended']    = !empty($data['is_suspended']);
        $aUserData['temp_pw']         = !empty($data['temp_pw']);

        if (Config::get('NAILS_AUTH_LOG_IP', true)) {
            $aUserData['ip_address'] = $oInput->ipAddress();
            $aUserData['last_ip']    = $oInput->ipAddress();
        }

        //  Referral code
        $aUserData['referral']    = $this->generateReferral();
        $aUserData['referred_by'] = !empty($data['referred_by']) ? $data['referred_by'] : null;

        //  Other data
        $aUserData['salutation'] = !empty($data['salutation']) ? $data['salutation'] : null;
        $aUserData['first_name'] = !empty($data['first_name']) ? $data['first_name'] : null;
        $aUserData['last_name']  = !empty($data['last_name']) ? $data['last_name'] : null;

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

        if (isset($data['profile_img'])) {
            $aUserData['profile_img'] = $data['profile_img'];
        }

        if (isset($data['dob'])) {
            $aUserData['dob'] = $data['dob'];
        }

        // --------------------------------------------------------------------------

        //  Set Meta data
        $aMetaCols = array_keys($this->describeMetaFields());
        $aMetaData = [];

        foreach ($data as $key => $val) {
            if (array_search($key, $aMetaCols) !== false) {
                $aMetaData[$key] = $val;
            }
        }

        // --------------------------------------------------------------------------

        try {

            $oDb->trans_begin();

            $oDb->set($aUserData);

            if (!$oDb->insert($this->table)) {
                throw new NailsException('Failed to create base user object.');
            }

            $iId = $oDb->insert_id();

            // --------------------------------------------------------------------------

            /**
             * Update the user table with an MD5 hash of the user ID; a number of functions
             * make use of looking up this hashed information; this should be quicker.
             */

            $oDb->set('id_md5', md5($iId));
            $oDb->where('id', $iId);

            if (!$oDb->update($this->table)) {
                throw new NailsException('Failed to update base user object.');
            }

            // --------------------------------------------------------------------------

            //  Create the meta record, add any extra data if needed
            $oDb->set('user_id', $iId);

            if ($aMetaData) {
                $oDb->set($aMetaData);
            }

            if (!$oDb->insert($this->tableMeta)) {
                throw new NailsException('Failed to create user meta data object.');
            }

            // --------------------------------------------------------------------------

            //  Finally add the email address to the user email table
            if (!empty($sEmail)) {

                $sCode = $this->emailAdd($sEmail, $iId, true, !empty($bEmailIsVerified), false, false);

                if (!$sCode) {
                    //  Error will be set by emailAdd();
                    throw new NailsException($this->lastError());
                }

                //  Send the user the welcome email
                if ($bSendWelcome) {

                    /** @var Email\Service\Emailer $oEmailer */
                    $oEmailer = Factory::service('Emailer', Email\Constants::MODULE_SLUG);

                    $oEmail = (object) [
                        'type'  => 'new_user_' . $oGroup->id,
                        'to_id' => $iId,
                        'data'  => (object) [],
                    ];

                    //  If this user is created by an admin then take note of that.
                    if ($this->isAdmin() && $this->activeUser('id') != $iId) {
                        $oEmail->data->admin = (object) [
                            'id'         => $this->activeUser('id'),
                            'first_name' => $this->activeUser('first_name'),
                            'last_name'  => $this->activeUser('last_name'),
                            'group'      => (object) [
                                'id'   => $oGroup->id,
                                'name' => $oGroup->label,
                            ],
                        ];
                    }

                    if (!empty($data['password']) && $bInformUserPw) {

                        $oEmail->data->password = $data['password'];

                        //  Is this a temp password? We should let them know that too
                        if ($aUserData['temp_pw']) {
                            $oEmail->data->isTemp = !empty($aUserData['temp_pw']);
                        }
                    }

                    //  If the email isn't verified we'll want to include a note asking them to do so
                    if (empty($bEmailIsVerified)) {
                        $oEmail->data->verifyUrl = siteUrl('email/verify/' . $iId . '/' . $sCode);
                    }

                    if (!$oEmailer->send($oEmail, true)) {

                        //  Failed to send using the group email, try using the generic email template
                        $oEmail->type = 'new_user';

                        if (!$oEmailer->send($oEmail, true)) {

                            //  Email failed to send, must not exist, oh well.
                            $sError = 'Failed to send welcome email.';
                            $sError .= $bInformUserPw ? ' Inform the user their password is <strong>' . $data['password'] . '</strong>' : '';
                            throw new NailsException($sError);
                        }
                    }
                }
            }

            // --------------------------------------------------------------------------

            $oDb->trans_commit();

            $this->triggerEvent(
                Events::USER_CREATED,
                [$iId]
            );

            return $this->getById($iId);

        } catch (\Exception $e) {
            $oDb->trans_rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user
     *
     * @param int $iUserId The ID of the user to delete
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function destroy($iUserId): bool
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        /**
         * Delete the meta table first as it is the most likely to have FK's on it
         * which might fail as part of the delete.
         */

        $oDb->where('user_id', $iUserId);
        $oDb->delete($this->tableMeta);

        if ((bool) $oDb->affected_rows()) {

            $oDb->where('id', $iUserId);
            $oDb->delete($this->table);

            if ((bool) $oDb->affected_rows()) {
                $this->unsetCacheUser($iUserId);
                $this->triggerEvent(
                    Events::USER_DESTROYED,
                    [$iUserId]
                );
                return true;
            }
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Alias to destroy()
     *
     * @param int $iUserId The ID of the user to delete
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function delete($iUserId): bool
    {
        $this->triggerEvent(
            Events::USER_DELETED,
            [$iUserId]
        );
        return $this->destroy($iUserId);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a valid referral code
     *
     * @return string
     * @throws FactoryException
     */
    protected function generateReferral()
    {
        Factory::helper('string');
        /** @var Database $oDb */
        $oDb       = Factory::service('Database');
        $sReferral = '';

        while (1 > 0) {

            $sReferral = random_string('alnum', 8);
            $oQuery    = $oDb->get_where($this->table, ['referral' => $sReferral]);

            if ($oQuery->num_rows() == 0) {
                break;
            }
        }

        return $sReferral;
    }

    // --------------------------------------------------------------------------

    /**
     * Rewards a user for their referral
     *
     * @param int $iUserId    The ID of the user who signed up
     * @param int $referrerId The ID of the user who made the referral
     *
     * @return void
     */
    public function rewardReferral($iUserId, $referrerId)
    {
        // @todo Implement this method where appropriate
    }

    // --------------------------------------------------------------------------

    /**
     * Suspend a user
     *
     * @param int $iUserId The ID of the user to suspend
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function suspend($iUserId)
    {
        return $this->update($iUserId, ['is_suspended' => true]);
    }

    // --------------------------------------------------------------------------

    /**
     * Unsuspend a user
     *
     * @param int $iUserId The ID of the user to unsuspend
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function unsuspend($iUserId)
    {
        return $this->update($iUserId, ['is_suspended' => false]);
    }

    // --------------------------------------------------------------------------

    /**
     * Checks whether a username is valid
     *
     * @param string $sUsername     The username to check
     * @param bool   $bCheckDb      Whether to test against the database
     * @param int    $iIgnoreUserId The ID of a user to ignore when checking the database
     *
     * @return bool
     * @throws FactoryException
     */
    public function isValidUsername($sUsername, $bCheckDb = false, $iIgnoreUserId = null): bool
    {
        /**
         * Check username doesn't contain invalid characters - we're actively looking
         * for characters which are invalid so we can say "Hey! The following
         * characters are invalid" rather than making the user guess, y'know, 'cause
         * we're good guys.
         */

        $sInvalidChars = '/[^a-zA-Z0-9\-_\.]/';

        //  Minimum length of the username
        $iMinLength = 2;

        // --------------------------------------------------------------------------

        if (preg_match($sInvalidChars, $sUsername)) {

            $this->setError(
                'Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).'
            );
            return false;

        } elseif (strlen($sUsername) < $iMinLength) {

            $this->setError(
                'Usernames must be at least ' . $iMinLength . ' characters long.'
            );
            return false;
        }

        // --------------------------------------------------------------------------

        if ($bCheckDb) {

            /** @var Database $oDb */
            $oDb = Factory::service('Database');
            $oDb->where('username', $sUsername);

            if (!empty($iIgnoreUserId)) {
                $oDb->where('id !=', $iIgnoreUserId);
            }

            if ($oDb->count_all_results($this->table)) {
                $this->setError('Username is already in use.');
                return false;
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Merges users with ID in $mergeIds into $iUserId
     *
     * @param int   $iUserId    The user ID to keep
     * @param array $aMergeIds  An array of user ID's to merge into $iUserId
     * @param bool  $bIsPreview Whether we're generating a preview or not
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function merge($iUserId, $aMergeIds, $bIsPreview = false)
    {
        /** @var Database $oDb */
        $oDb = Factory::service('Database');

        if (!is_numeric($iUserId)) {
            $this->setError('"userId" must be numeric.');
            return false;
        }

        if (!is_array($aMergeIds)) {
            $this->setError('"mergeIDs" must be an array.');
            return false;
        }

        for ($i = 0; $i < count($aMergeIds); $i++) {
            if (!is_numeric($aMergeIds[$i])) {
                $this->setError('"mergeIDs" must contain only numerical values.');
                return false;
            }
            $aMergeIds[$i] = $oDb->escape((int) $aMergeIds[$i]);
        }

        if (in_array($iUserId, $aMergeIds)) {
            $this->setError('"userId" cannot be listed as a merge user.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Look for tables which contain a user ID column in them.
        $aUserCols = [
            'user_id',
            'created_by',
            'modified_by',
            'author_id',
            'authorId',
        ];

        $sUserColsStr = "'" . implode("','", $aUserCols) . "'";

        $aIgnoreTables = [
            $this->table,
            $this->tableMeta,
            Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_device_code',
            Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_device_secret',
            Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_question',
            Config::get('NAILS_DB_PREFIX') . 'user_auth_two_factor_token',
            Config::get('NAILS_DB_PREFIX') . 'user_social',
        ];

        $sIgnoreTablesStr = "'" . implode("','", $aIgnoreTables) . "'";

        $aTables = [];
        $sQuery  = "SELECT COLUMN_NAME,TABLE_NAME
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE COLUMN_NAME IN (" . $sUserColsStr . ")
                    AND (TABLE_NAME LIKE '" . Config::get('NAILS_DB_PREFIX') . "%' OR TABLE_NAME LIKE '" . Config::get('APP_DB_PREFIX') . "%')
                    AND TABLE_NAME NOT IN (" . $sIgnoreTablesStr . ")
                    AND TABLE_SCHEMA='" . Config::get('DB_DATABASE') . "';";

        /** @var CI_DB_result $result */
        $result = $oDb->query($sQuery);

        while ($oTable = $result->unbuffered_row()) {

            if (!isset($aTables[$oTable->TABLE_NAME])) {
                $aTables[$oTable->TABLE_NAME] = (object) [
                    'name'    => $oTable->TABLE_NAME,
                    'columns' => [],
                ];
            }

            $aTables[$oTable->TABLE_NAME]->columns[] = $oTable->COLUMN_NAME;
        }

        $aTables = array_values($aTables);

        //  Grab a count of the number of rows which will be affected
        for ($i = 0; $i < count($aTables); $i++) {

            $aColumnConditional = [];
            foreach ($aTables[$i]->columns as $column) {
                $aColumnConditional[] = $column . ' IN (' . implode(',', $aMergeIds) . ')';
            }

            $sQuery  = 'SELECT COUNT(*) AS numrows FROM  ' . $aTables[$i]->name . ' WHERE ' . implode(' OR ', $aColumnConditional);
            $oResult = $oDb->query($sQuery)->row();

            if (empty($oResult->numrows)) {
                $aTables[$i] = null;
            } else {
                $aTables[$i]->numRows = $oResult->numrows;
            }
        }

        $aTables = array_values(array_filter($aTables));

        // --------------------------------------------------------------------------

        if ($bIsPreview) {

            $out = (object) [
                'user'  => $this->getById($iUserId),
                'merge' => [],
            ];

            foreach ($aMergeIds as $iMergeUserId) {
                $out->merge[] = $this->getById($iMergeUserId);
            }

            $out->tables       = $aTables;
            $out->ignoreTables = $aIgnoreTables;

        } else {

            $oDb->trans_begin();

            //  For each table update the user columns
            for ($i = 0; $i < count($aTables); $i++) {

                foreach ($aTables[$i]->columns as $column) {

                    //  Additional updates for certain tables
                    switch ($aTables[$i]->name) {
                        case $this->tableEmail:
                            $oDb->set('is_primary', false);
                            break;
                    }

                    $oDb->set($column, $iUserId);
                    $oDb->where_in($column, $aMergeIds);
                    if (!$oDb->update($aTables[$i]->name)) {
                        $this->setError(
                            'Failed to migrate column "' . $column . '" in table "' . $aTables[$i]->name . '"'
                        );
                        $oDb->trans_rollback();
                        return false;
                    }
                }
            }

            //  Now delete each user
            for ($i = 0; $i < count($aMergeIds); $i++) {
                if (!$this->destroy($aMergeIds[$i])) {
                    $this->setError('Failed to delete user "' . $aMergeIds[$i] . '" ');
                    $oDb->trans_rollback();
                    return false;
                }
            }

            if ($oDb->trans_status() === false) {
                $oDb->trans_rollback();
                $out = false;
            } else {
                $oDb->trans_commit();
                $out = true;
            }
        }

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Describe the model's fields
     *
     * @param string|null $sTable
     *
     * @return Field[]
     * @throws FactoryException
     */
    public function describeFields($sTable = null)
    {
        $aFields = parent::describeFields($sTable);

        //  Data types
        $aFields['profile_img']->type          = 'cdn_object_picker';
        $aFields['timezone']->type             = 'dropdown';
        $aFields['datetime_format_date']->type = 'dropdown';
        $aFields['datetime_format_time']->type = 'dropdown';

        //  Labels
        $aFields['first_name']->label           = 'First Name';
        $aFields['last_name']->label            = 'Surname';
        $aFields['profile_img']->label          = 'Profile Image';
        $aFields['dob']->label                  = 'Date of Birth';
        $aFields['datetime_format_date']->label = 'Date Format';
        $aFields['datetime_format_time']->label = 'Time Format';
        $aFields['ip_address']->label           = 'Registration IP';
        $aFields['last_ip']->label              = 'Last IP';
        $aFields['last_update']->label          = 'Modified';
        $aFields['referral']->label             = 'Referral Code';

        //  Validation rules
        $aRules = [
            'required' => [
                'first_name',
                'last_name',
            ],
        ];

        foreach ($aRules as $sRule => $aProperties) {
            foreach ($aProperties as $sProperty) {
                $aFields[$sProperty]->validation[] = $sRule;
            }
        }

        //  Notes
        $aFields['username']->info = 'Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).';

        //  Dropdown values
        /** @var DateTime $oDateTimeService */
        $oDateTimeService                         = Factory::service('DateTime');
        $aFields['timezone']->options             = $oDateTimeService->getAllTimezoneFlat();
        $aFields['datetime_format_date']->options = $oDateTimeService->getAllDateFormatFlat();
        $aFields['datetime_format_time']->options = $oDateTimeService->getAllTimeFormatFlat();

        //  Dropdown validation
        $aFields['timezone']->validation[]             = 'in_list[' . implode(',', array_keys($aFields['timezone']->options)) . ']';
        $aFields['datetime_format_date']->validation[] = 'in_list[' . implode(',', array_keys($aFields['datetime_format_date']->options)) . ']';
        $aFields['datetime_format_time']->validation[] = 'in_list[' . implode(',', array_keys($aFields['datetime_format_time']->options)) . ']';

        //  Defaults
        $aFields['timezone']->default             = $oDateTimeService->getTimezoneDefault();
        $aFields['datetime_format_date']->default = $oDateTimeService->getDateFormatDefaultSlug();
        $aFields['datetime_format_time']->default = $oDateTimeService->gettimeFormatDefaultSlug();

        //  Misc
        $aFields['timezone']->class             = 'select2';
        $aFields['datetime_format_date']->class = 'select2';
        $aFields['datetime_format_time']->class = 'select2';

        return $aFields;
    }

    // --------------------------------------------------------------------------

    /**
     * Describes the meta fields of the user object
     *
     * @return array
     */
    public function describeMetaFields(): array
    {
        $aFields = parent::describeFields($this->tableMeta);
        unset($aFields['user_id']);
        return $aFields;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the name of the user meta table
     *
     * @return string
     */
    public function getMetaTableName(): string
    {
        return $this->tableMeta;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the name of the user email table
     *
     * @return string
     */
    public function getEmailTableName(): string
    {
        return $this->tableEmail;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the name of the user group table
     *
     * @return string
     */
    public function getGroupTableName(): string
    {
        return $this->tableGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a single object
     *
     * The getAll() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to cast ints and bools and/or organise data into objects.
     *
     * @param object $oObj      A reference to the object being formatted.
     * @param array  $aData     The same data array which is passed to getCountCommon(), for reference if needed
     * @param array  $aIntegers Fields which should be cast as ints if numerical and not null
     * @param array  $aBools    Fields which should be cast as bools if not null
     * @param array  $aFloats   Fields which should be cast as floats if not null
     *
     * @return void
     */
    protected function formatObject(
        &$oObj,
        array $aData = [],
        array $aIntegers = [],
        array $aBools = [],
        array $aFloats = []
    ) {

        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);

        // --------------------------------------------------------------------------

        $oObj->group_acl = json_decode($oObj->group_acl);

        //  If the user has an ACL set then we'll need to extract and merge that
        if ($oObj->user_acl) {

            $oObj->user_acl = json_decode($oObj->user_acl);
            $oObj->acl      = array_merge($oObj->group_acl, $oObj->user_acl);
            $oObj->acl      = array_filter($oObj->acl);
            $oObj->acl      = array_unique($oObj->acl);

        } else {

            $oObj->acl = $oObj->group_acl;
        }

        // --------------------------------------------------------------------------

        //  Ints
        $oObj->id                 = (int) $oObj->id;
        $oObj->group_id           = (int) $oObj->group_id;
        $oObj->login_count        = (int) $oObj->login_count;
        $oObj->referred_by        = (int) $oObj->referred_by;
        $oObj->failed_login_count = (int) $oObj->failed_login_count;

        //  Bools
        $oObj->temp_pw           = (bool) $oObj->temp_pw;
        $oObj->is_suspended      = (bool) $oObj->is_suspended;
        $oObj->email_is_verified = (bool) $oObj->email_is_verified;

        // --------------------------------------------------------------------------

        //  Tidy User meta
        unset($oObj->user_id);
    }
}
