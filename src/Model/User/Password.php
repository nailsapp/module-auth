<?php

/**
 * This model contains all methods for interacting with user's passwords.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model\User;

use Nails\Auth\Constants;
use Nails\Auth\Model\User;
use Nails\Auth\Resource;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ModelException;
use Nails\Common\Exception\NailsException;
use Nails\Common\Model\Base;
use Nails\Common\Service\Database;
use Nails\Common\Service\Input;
use Nails\Config;
use Nails\Email;
use Nails\Email\Service\Emailer;
use Nails\Factory;

/**
 * Class Password
 *
 * @package                   Nails\Auth\Model\User
 * @todo (Pablo - 2019-12-10) - Should be a service
 */
class Password extends Base
{
    protected $aCharset;

    // --------------------------------------------------------------------------

    /**
     * Constructs the model
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Set defaults
        $this->aCharset                = [];
        $this->aCharset['symbol']      = '!@$^&*(){}":?<>~-=[];\'\\/.,';
        $this->aCharset['number']      = '0123456789';
        $this->aCharset['lower_alpha'] = 'abcdefghijklmnopqrstuvwxyz';
        $this->aCharset['upper_alpha'] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }

    // --------------------------------------------------------------------------

    /**
     * Changes a password for a particular user
     *
     * @param int    $iUserId   The user ID whose password to change
     * @param string $sPassword The raw, unencrypted new password
     * @param bool   $bIsTemp   Whether the password is temporary
     *
     * @return bool
     * @throws FactoryException
     * @throws ModelException
     */
    public function change(int $iUserId, string $sPassword, bool $bIsTemp = false)
    {
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Emailer $oEmailer */
        $oEmailer = Factory::service('Emailer', Email\Constants::MODULE_SLUG);

        // --------------------------------------------------------------------------

        $oUser = $oUserModel->getById($iUserId);
        if (empty($oUser)) {
            $this->setError('Invalid user ID.');
            return false;
        }

        try {
            $oHash = $this->generateHash($oUser->group_id, $sPassword);
        } catch (NailsException $e) {
            $this->setError($e->getMessage());
            return false;
        }

        // --------------------------------------------------------------------------

        $sNow = Factory::factory('DateTime')->format('Y-m-d H:i:s');

        $oDb->set('password', $oHash->password);
        $oDb->set('password_md5', $oHash->password_md5);
        $oDb->set('password_engine', $oHash->engine);
        $oDb->set('password_changed', $sNow);
        $oDb->set('salt', $oHash->salt);
        $oDb->set('temp_pw', $bIsTemp);
        $oDb->set('last_update', $sNow);
        $oDb->set('failed_login_count', 0);
        $oDb->set('failed_login_expires', null);

        $oDb->where('id', $oUser->id);

        if (!$oDb->update($oUserModel->getTableName())) {
            $this->setError('Failed to update user record.');
            return false;
        }

        // --------------------------------------------------------------------------

        $oEmail = (object) [
            'type'  => 'password_updated',
            'to_id' => $iUserId,
            'data'  => (object) [
                'ipAddress' => $oInput->ipAddress(),
                'updatedAt' => $sNow,
            ],
        ];

        if (activeUser('id') && activeUser('id') !== $iUserId) {
            $oEmail->data->updatedBy = activeUser('first_name,last_name');
        }

        $oEmailer->send($oEmail);

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a password is correct for a particular user.
     *
     * @param Resource\User|int|string $mUser     The user's Resource, ID, or identifier
     * @param string                   $sPassword The raw, unencrypted password to check
     *
     * @return bool
     */
    public function isCorrect($mUser, $sPassword): bool
    {
        $oUser = $this->getUser($mUser);

        if (empty($oUser) || empty($sPassword)) {
            return false;
        }

        // --------------------------------------------------------------------------

        $oDb = Factory::service('Database');
        $oDb->select('u.password, u.password_engine, u.salt');
        $oDb->where('u.id', $oUser->id);
        $oDb->limit(1);
        $oResult = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'user u');

        // --------------------------------------------------------------------------

        if ($oResult->num_rows() !== 1) {
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * @todo: use the appropriate driver to determine password correctness, but
         * for now, do it the old way
         */

        $sHash = sha1(sha1($sPassword) . $oResult->row()->salt);

        return $oResult->row()->password === $sHash;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a user's password has expired
     *
     * @param Resource\User|int|string $mUser The user's Resource, ID, or identifier
     *
     * @return bool
     */
    public function isExpired($mUser): bool
    {
        $oUser = $this->getUser($mUser);

        if (empty($oUser)) {
            return false;
        }

        $oDb = Factory::service('Database');
        $oDb->select('u.password_changed,ug.password_rules');
        $oDb->where('u.id', $oUser->id);
        $oDb->join(Config::get('NAILS_DB_PREFIX') . 'user_group ug', 'ug.id = u.group_id');
        $oDb->limit(1);
        $oResult = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'user u');

        if ($oResult->num_rows() !== 1) {
            return false;
        }

        //  Decode the password rules
        $oGroupPwRules = json_decode($oResult->row()->password_rules);

        if (empty($oGroupPwRules->expiresAfter)) {
            return false;
        }

        $sChanged = $oResult->row()->password_changed;

        if (is_null($sChanged)) {

            return true;

        } else {

            $oThen     = new \DateTime($sChanged);
            $oNow      = new \DateTime();
            $oInterval = $oNow->diff($oThen);

            return $oInterval->days >= $oGroupPwRules->expiresAfter;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a user's password is temporary
     *
     * @param Resource\User|int|string $mUser The user's Resource, ID, or identifier
     *
     * @return bool
     */
    public function isTemporary($mUser): bool
    {
        $oUser = $this->getUser($mUser);
        if (empty($oUser)) {
            return false;
        }

        return $oUser->temp_pw;
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a password is acceptable for a user group
     *
     * @param int    $iGroupId  The user group
     * @param string $sPassword The raw, unencrypted password
     *
     * @return bool
     */
    public function isAcceptable(int $iGroupId, string $sPassword): bool
    {
        //  Check password satisfies password rules
        $aPwRules = $this->getRules($iGroupId);

        //  Long enough?
        if (!empty($aPwRules['min']) && strlen($sPassword) < $aPwRules['min']) {
            $this->setError('Password is too short.');
            return false;
        }

        //  Too long?
        if (!empty($aPwRules['max']) && strlen($sPassword) > $aPwRules['max']) {
            $this->setError('Password is too long.');
            return false;
        }

        //  Satisfies all the requirements
        $aFailedRequirements = [];
        if (!empty($aPwRules['requirements'])) {
            foreach ($aPwRules['requirements'] as $sRequirement => $bValue) {
                switch ($sRequirement) {
                    case 'symbol':
                        if (!$this->strContainsFromCharset($sPassword, 'symbol')) {
                            $aFailedRequirements[] = 'a symbol';
                        }
                        break;

                    case 'number':
                        if (!$this->strContainsFromCharset($sPassword, 'number')) {
                            $aFailedRequirements[] = 'a number';
                        }
                        break;

                    case 'lower_alpha':
                        if (!$this->strContainsFromCharset($sPassword, 'lower_alpha')) {
                            $aFailedRequirements[] = 'a lowercase letter';
                        }
                        break;

                    case 'upper_alpha':
                        if (!$this->strContainsFromCharset($sPassword, 'upper_alpha')) {
                            $aFailedRequirements[] = 'an uppercase letter';
                        }
                        break;
                }
            }
        }

        if (!empty($aFailedRequirements)) {
            $sError = 'Password must contain ' . implode(', ', $aFailedRequirements) . '.';
            $sError = str_lreplace(', ', ' and ', $sError);
            $this->setError($sError);
            return false;
        }

        //  Not be a banned password?
        if (!empty($aPwRules['banned'])) {
            foreach ($aPwRules['banned'] as $sStr) {
                if (trim(strtolower($sPassword)) == strtolower($sStr)) {
                    $this->setError('Password cannot be "' . $sStr . '"');
                    return false;
                }
            }
        }

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns how many days a password is valid for
     *
     * @param $iGroupId
     *
     * @return null
     */
    public function expiresAfter($iGroupId)
    {
        if (empty($iGroupId)) {
            return null;
        }

        $oDb = Factory::service('Database');
        $oDb->select('password_rules');
        $oDb->where('id', $iGroupId);
        $oDb->limit(1);
        $oResult = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'user_group');

        if ($oResult->num_rows() !== 1) {
            return null;
        }

        //  Decode the password rules
        $oGroupPwRules = json_decode($oResult->row()->password_rules);

        return empty($oGroupPwRules->expiresAfter) ? null : $oGroupPwRules->expiresAfter;
    }

    // --------------------------------------------------------------------------

    /**
     * Create a password hash, checks to ensure a password is strong enough according
     * to the password rules defined by the app.
     *
     * @param int    $iGroupId  The group who's rules to fetch
     * @param string $sPassword The raw, unencrypted password
     *
     * @return \stdClass
     * @throws NailsException
     */
    public function generateHash($iGroupId, $sPassword): \stdClass
    {
        if (empty($sPassword)) {
            throw new NailsException('No password to hash.');
        } elseif (!$this->isAcceptable($iGroupId, $sPassword)) {
            throw new NailsException('Password does not meet requirements.');
        } else {
            return $this->generateHashObject($sPassword);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a null password hash
     *
     * @return \stdClass
     */
    public function generateNullHash(): \stdClass
    {
        return $this->generateHashObject(null);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a password hash, no strength checks
     *
     * @param string $sPassword The password to generate the hash for
     *
     * @return \stdClass
     */
    public function generateHashObject($sPassword): \stdClass
    {
        $sSalt = $this->salt();
        $sHash = sha1(sha1($sPassword) . $sSalt);

        return (object) [
            'password'     => $sHash,
            'password_md5' => md5($sHash),
            'salt'         => $sSalt,
            'engine'       => 'NAILS_1',
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a string contains any of the characters from a defined charset.
     *
     * @param string $sStr     The string to analyse
     * @param string $sCharset The charset to test against
     *
     * @return bool
     */
    private function strContainsFromCharset($sStr, $sCharset)
    {
        if (empty($this->aCharset[$sCharset])) {
            return true;
        }

        return preg_match('/[' . preg_quote($this->aCharset[$sCharset], '/') . ']/', $sStr);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a password which is sufficiently secure according to the app's password rules
     *
     * @param int $iGroupId The group who's rules to fetch
     *
     * @return string
     */
    public function generate($iGroupId)
    {
        $aPwRules = $this->getRules($iGroupId);
        $aPwOut   = [];

        // --------------------------------------------------------------------------

        /**
         * We're generating a password, define all the charsets to use, at the very
         * ;east have the lower_alpha charset.
         */

        $aCharsets   = [];
        $aCharsets[] = $this->aCharset['lower_alpha'];

        if (!empty($aPwRules['requirements'])) {
            foreach ($aPwRules['requirements'] as $sRequirement => $bValue) {

                switch ($sRequirement) {
                    case 'symbol':
                        $aCharsets[] = $this->aCharset['symbol'];
                        break;

                    case 'number':
                        $aCharsets[] = $this->aCharset['number'];
                        break;

                    case 'upper_alpha':
                        $aCharsets[] = $this->aCharset['upper_alpha'];
                        break;
                }
            }
        }

        // --------------------------------------------------------------------------

        //  Work out the min length
        $iMin = getFromArray('min', $aPwRules);
        if (empty($iMin)) {
            $iMin = 8;
        }

        //  Work out the max length
        $iMax = getFromArray('max', $aPwRules);
        if (empty($iMax) || $iMin > $iMax) {
            $iMax = $iMin + count($aCharsets) * 2;
        }

        // --------------------------------------------------------------------------

        //  We now have a max_length and all our chars, generate password!
        $bPwValid = true;
        do {
            do {
                foreach ($aCharsets as $sCharset) {
                    $sCharacter = rand(0, strlen($sCharset) - 1);
                    $aPwOut[]   = $sCharset[$sCharacter];
                }
            } while (count($aPwOut) < $iMax);

            //  Check password isn't a prohibited string
            if (!empty($aPwRules['banned'])) {
                foreach ($aPwRules['banned'] as $sString) {
                    if (strtolower(implode('', $aPwOut)) == strtolower($sString)) {
                        $bPwValid = false;
                        break;
                    }
                }
            }

        } while (!$bPwValid);

        // --------------------------------------------------------------------------

        //  Shuffle the string and return
        shuffle($aPwOut);
        return implode('', $aPwOut);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the app's raw password rules as an array
     *
     * @param int $iGroupId The group who's rules to fetch
     *
     * @return array
     */
    protected function getRules($iGroupId)
    {
        $sCacheKey    = 'password-rules-' . $iGroupId;
        $aCacheResult = $this->getCache($sCacheKey);
        if (!empty($aCacheResult)) {
            return $aCacheResult;
        }

        $oDb = Factory::service('Database');
        $oDb->select('password_rules');
        $oDb->where('id', $iGroupId);
        $oResult = $oDb->get(Config::get('NAILS_DB_PREFIX') . 'user_group');

        if ($oResult->num_rows() === 0) {
            return [];
        }

        $oPwRules = json_decode($oResult->row()->password_rules);

        $aOut                 = [];
        $aOut['min']          = !empty($oPwRules->min) ? $oPwRules->min : null;
        $aOut['max']          = !empty($oPwRules->max) ? $oPwRules->max : null;
        $aOut['expiresAfter'] = !empty($oPwRules->expiresAfter) ? $oPwRules->expiresAfter : null;
        $aOut['requirements'] = !empty($oPwRules->requirements) ? $oPwRules->requirements : [];
        $aOut['banned']       = !empty($oPwRules->banned) ? $oPwRules->banned : [];

        $this->setCache($sCacheKey, $aOut);

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the app's password rules as a formatted string
     *
     * @param int $iGroupId The group who's rules to fetch
     *
     * @return string
     */
    public function getRulesAsString($iGroupId)
    {
        $aRules = $this->getRulesAsArray($iGroupId);

        if (empty($aRules)) {
            return '';
        }

        $sStr = 'Passwords must ' . strtolower(implode(', ', $aRules)) . '.';
        return str_lreplace(', ', ' and ', $sStr);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the app's password rules as an array of human friendly strings
     *
     * @param int $iGroupId The group who's rules to fetch
     *
     * @return array
     */
    public function getRulesAsArray($iGroupId)
    {
        $aRules = $this->getRules($iGroupId);
        $aOut   = [];

        if (!empty($aRules['min'])) {
            $aOut[] = 'Have at least ' . $aRules['min'] . ' characters';
        }

        if (!empty($aRules['max'])) {
            $aOut[] = 'Have at most ' . $aRules['max'] . ' characters';
        }

        if (!empty($aRules['requirements'])) {
            foreach ($aRules['requirements'] as $sKey => $bValue) {
                switch ($sKey) {
                    case 'symbol':
                        $aOut[] = 'Contain a symbol';
                        break;

                    case 'lower_alpha':
                        $aOut[] = 'Contain a lowercase letter';
                        break;

                    case 'upper_alpha':
                        $aOut[] = 'Contain an upper case letter';
                        break;

                    case 'number':
                        $aOut[] = 'Contain a number';
                        break;
                }
            }
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a random salt
     *
     * @param string $sPepper Additional data to inject into the salt
     *
     * @return string
     */
    public function salt($sPepper = '')
    {
        return md5(
            uniqid(
                $sPepper . rand() . Config::get('DEPLOY_PRIVATE_KEY') . Config::get('APP_PRIVATE_KEY'),
                true
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a forgotten password token for a user
     *
     * @param Resource\User|int|string $mUser The user Resource, ID, or identifier
     *
     * @return bool
     */
    public function setToken($mUser): bool
    {
        $oUser = $this->getUser($mUser);

        if (empty($oUser)) {
            return false;
        }

        // --------------------------------------------------------------------------

        //  Generate code
        $sCode = implode(':', [
            //  TTL (24 hrs)
            time() + 86400,
            //  Key
            sha1(sha1($this->salt()) . $this->salt() . Config::get('APP_PRIVATE_KEY')),
        ]);

        // --------------------------------------------------------------------------

        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);

        return $oUserModel->update($oUser->id, ['forgotten_password_code' => $sCode]);
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a forgotten password code.
     *
     * @param string $sCode          The token to validate
     * @param bool   $bGenerateNewPw Whether or not to generate a new password (only if token is valid)
     *
     * @return bool|string|array
     */
    public function validateToken($sCode, $bGenerateNewPw)
    {
        if (empty($sCode)) {
            return false;
        }

        // --------------------------------------------------------------------------

        /** @var Database $oDb */
        $oDb = Factory::service('Database');
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);

        $oDb->select('u.id, u.group_id, u.forgotten_password_code, e.email, u.username');
        $oDb->join($oUserModel->getEmailTableName() . ' e', 'e.user_id = u.id AND e.is_primary = 1');
        $oDb->like('forgotten_password_code', ':' . $sCode, 'before');
        $oResult = $oDb->get($oUserModel->getTableName() . ' u');

        // --------------------------------------------------------------------------

        if ($oResult->num_rows() != 1) {
            return false;
        }

        // --------------------------------------------------------------------------

        $oUser = $oResult->row();
        [$sTTL, $sKey] = array_pad(explode(':', $oUser->forgotten_password_code), 2, null);
        $iTTL = (int) $sTTL;

        // --------------------------------------------------------------------------

        //  Check that the link is still valid
        if (time() > $iTTL) {

            return 'EXPIRED';

        } else {

            //  Valid hash and hasn't expired.
            $aOut = [
                'user_id' => $oUser->id,
            ];

            switch (Config::get('APP_NATIVE_LOGIN_USING')) {

                case 'EMAIL':
                    $aOut['user_identity'] = $oUser->email;
                    break;

                case 'USERNAME':
                    $aOut['user_identity'] = $oUser->username;
                    break;

                default:
                    $aOut['user_identity'] = $oUser->email ?? $oUser->username;
                    break;
            }

            //  Generate a new password?
            if ($bGenerateNewPw) {

                try {

                    $aOut['password'] = $this->generate($oUser->group_id);
                    if (empty($aOut['password'])) {
                        throw new NailsException('Generated password was empty.');
                    }

                    $oHash = $this->generateHash($oUser->group_id, $aOut['password']);

                } catch (NailsException $e) {
                    $this->setError($e->getMessage());
                    return false;
                }

                // --------------------------------------------------------------------------

                $aData = [
                    'password'                => $oHash->password,
                    'password_md5'            => $oHash->password_md5,
                    'password_engine'         => $oHash->engine,
                    'salt'                    => $oHash->salt,
                    'temp_pw'                 => true,
                    'forgotten_password_code' => null,
                    'failed_login_count'      => 0,
                    'failed_login_expires'    => null,
                ];

                $oDb->where('forgotten_password_code', $oUser->forgotten_password_code);
                $oDb->set($aData);
                $oDb->update(Config::get('NAILS_DB_PREFIX') . 'user');
            }
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an array of permissions into a JSON encoded string suitable for the database
     *
     * @param array $aRules An array of rules to set
     *
     * @return string
     */
    public function processRules($aRules)
    {
        if (empty($aRules)) {
            return null;
        }

        $aOut = [];

        //  Min/max length
        $aOut['min'] = !empty($aRules['min']) ? (int) $aRules['min'] : null;
        $aOut['max'] = !empty($aRules['max']) ? (int) $aRules['max'] : null;

        //  Expiration
        $aOut['expiresAfter'] = !empty($aRules['expires_after']) ? (int) $aRules['expires_after'] : null;

        //  Requirements
        $aOut['requirements'] = [];
        if (!empty($aRules['requirements'])) {
            $aOut['requirements']['symbol']      = in_array('symbol', $aRules['requirements']);
            $aOut['requirements']['number']      = in_array('number', $aRules['requirements']);
            $aOut['requirements']['lower_alpha'] = in_array('lower_alpha', $aRules['requirements']);
            $aOut['requirements']['upper_alpha'] = in_array('upper_alpha', $aRules['requirements']);
            $aOut['requirements']                = array_filter($aOut['requirements']);
        }

        //  Banned words
        $aOut['banned'] = [];
        if (!empty($aRules['banned'])) {
            $aRules['banned'] = trim($aRules['banned']);
            $aOut['banned']   = explode(',', $aRules['banned']);
            $aOut['banned']   = array_map('trim', $aOut['banned']);
            $aOut['banned']   = array_map('strtolower', $aOut['banned']);
            $aOut['banned']   = array_filter($aOut['banned']);
        }
        $aOut = array_filter($aOut);

        return empty($aOut) ? null : json_encode($aOut);
    }

    // --------------------------------------------------------------------------

    /**
     * Calculates how long ago a user's pssword was changed, in seconds
     *
     * @param Resource\User|string|int $mUser The user's Resource, ID, or identifier
     *
     * @return int|null
     * @throws FactoryException
     * @throws ModelException
     */
    public function timeSinceChange($mUser): ?int
    {
        $oUser = $this->getUser($mUser);

        if (!empty($oUser) && $oUser->password_changed) {
            return time() - strtotime($oUser->password_changed);
        }

        return null;
    }

    // --------------------------------------------------------------------------

    protected function getUser($mUser): ?Resource\User
    {
        /** @var \App\Auth\Model\User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);

        if ($mUser instanceof Resource\User) {
            return $mUser;
        } elseif (is_numeric($mUser)) {
            return $oUserModel->getById($mUser);
        } elseif (is_string($mUser)) {
            return $oUserModel->getByIdentifier($mUser);
        }

        return null;
    }
}
