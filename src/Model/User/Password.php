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

class Password extends \Nails\Common\Model\Base
{
    use \Nails\Common\Traits\ErrorHandling;
    use \Nails\Common\Traits\Caching;

    // --------------------------------------------------------------------------

    protected $oUserModel;
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
        $this->aCharset = array();
        $this->aCharset['symbol'] = utf8_encode('!@$^&*(){}":?<>~-=[];\'\\/.,');
        $this->aCharset['number'] = utf8_encode('0123456789');
        $this->aCharset['lower_alpha'] = utf8_encode('abcdefghijklmnopqrstuvwxyz');
        $this->aCharset['upper_alpha'] = utf8_encode('ABCDEFGHIJKLMNOPQRSTUVWXYZ');
    }

    // --------------------------------------------------------------------------

    /**
     * Inject the user object, private by convention - only really used by a few
     * core Nails classes
     * @param stdClass &$oUser The User Object
     */
    public function setUserObject(&$oUser)
    {
        $this->oUserModel = $oUser;
    }

    // --------------------------------------------------------------------------

    /**
     * Changes a password for a particular user
     * @param  int    $iUserId   The user ID whose password to change
     * @param  string $sPassword The raw, unencrypted new password
     * @return boolean
     */
    public function change($iUserId, $sPassword)
    {
        //  @todo
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a password is correct for a particular user.
     * @param  int     $iUserId   The user ID to check for
     * @param  string  $sPassword The raw, unencrypted password to check
     * @return boolean
     */
    public function isCorrect($iUserId, $sPassword)
    {
        if (empty($iUserId) || empty($sPassword)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $this->db->select('u.password, u.password_engine, u.salt');
        $this->db->where('u.id', $iUserId);
        $this->db->limit(1);
        $oResult = $this->db->get(NAILS_DB_PREFIX . 'user u');

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
     * @param  integer  $iUserId The user ID to check
     * @return boolean
     */
    public function isExpired($iUserId)
    {
        if (empty($iUserId)) {

            return false;
        }

        $this->db->select('u.password_changed,ug.password_rules');
        $this->db->where('u.id', $iUserId);
        $this->db->join(NAILS_DB_PREFIX . 'user_group ug', 'ug.id = u.group_id');
        $this->db->limit(1);
        $oResult = $this->db->get(NAILS_DB_PREFIX . 'user u');

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

            $oThen = new \DateTime($sChanged);
            $oNow  = new \DateTime();
            $oInterval = $oNow->diff($oThen);

            return $oInterval->days >= $oGroupPwRules->expiresAfter;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns how many days a password is valid for
     * @return int
     */
    public function expiresAfter($iGroupId)
    {
        if (empty($iGroupId)) {

            return null;
        }

        $this->db->select('password_rules');
        $this->db->where('id', $iGroupId);
        $this->db->limit(1);
        $oResult = $this->db->get(NAILS_DB_PREFIX . 'user_group');

        if ($oResult->num_rows() !== 1) {

            return null;
        }

        //  Decode the password rules
        $oGroupPwRules = json_decode($oResult->row()->password_rules);

        if (empty($oGroupPwRules->expiresAfter)) {

            return null;

        } else {

            return $oGroupPwRules->expiresAfter;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Create a password hash, checks to ensure a password is strong enough according
     * to the password rules defined by the app.
     * @param integer $iGroupId The group who's rules to fetch
     * @param  string $sPassword The raw, unencrypted password
     * @return mixed            stdClass on success, false on failure
     */
    public function generateHash($iGroupId, $sPassword)
    {
        if (empty($sPassword)) {

            $this->_set_error('No password to hash');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Check password satisfies password rules
        $aPwRules = $this->getRules($iGroupId);

        //  Long enough?
        if (!empty($aPwRules['min']) && strlen($sPassword) < $aPwRules['min']) {

            $this->_set_error('Password is too short.');
            return false;
        }

        //  Too long?
        if (!empty($aPwRules['max']) && strlen($sPassword) > $aPwRules['max']) {

            $this->_set_error('Password is too long.');
            return false;
        }

        //  Satisfies all the requirements
        $aFailedRequirements = array();
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

        if (!empty($aFailedRequirements)) {

            \Nails\Factory::helper('string');
            $sError = 'Password must contain ' . implode(', ', $aFailedRequirements) . '.';
            $sError = str_lreplace(', ', ' and ', $sError);
            $this->_set_error($sError);
            return false;
        }

        //  Not be a banned password?
        foreach ($aPwRules['banned'] as $sStr) {

            if (trim(strtolower($sPassword)) == strtolower($sStr)) {

                $this->_set_error('Password cannot be "' . $sStr . '"');
                return false;
            }
        }

        // --------------------------------------------------------------------------

        //  Password is valid, generate hash object
        return self::generateHashObject($sPassword);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a string contains any of the charecters from a defined charset.
     * @param  string  $sStr     The string to analyse
     * @param  string  $sCharset The charset to test against
     * @return boolean
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
     * Generates a null password hash
     * @return mixed stdClass on success, false on failure
     */
    public function generateNullHash()
    {
        return self::generateHashObject(null);
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a password hash, no strength checks
     * @param  string $sPassword The password to generate the hash for
     * @return stdClass
     */
    public static function generateHashObject($sPassword)
    {
        $sSalt = self::salt();

        // --------------------------------------------------------------------------

        $oOut               = new \stdClass();
        $oOut->password     = sha1(sha1($sPassword) . $sSalt);
        $oOut->password_md5 = md5($oOut->password);
        $oOut->salt         = $sSalt;
        $oOut->engine       = 'NAILS_1';

        return $oOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a password which is sufficiently secure according to the app's password rules
     * @param  integer $iGroupId The group who's rules to fetch
     * @return string
     */
    public function generate($iGroupId)
    {
        $aPwRules = $this->getRules($iGroupId);
        $aPwOut = array();

        // --------------------------------------------------------------------------

        /**
         * We're generating a password, define all the charsets to use, at the very
         * ;east have the lower_alpha charset.
         */

        $aCharsets = array();
        $aCharsets[] = $this->aCharset['lower_alpha'];

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

        // --------------------------------------------------------------------------

        //  Work out the min length
        $iMin = $aPwRules['min'];
        if (empty($aPwRules['min'])) {
            $iMin = 8;
        }

        //  Work out the max length
        $iMax = $aPwRules['max'];
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
                    $aPwOut[] = $sCharset[$sCharacter];
                }

            } while (count($aPwOut) < $iMax);

            //  Check password isn't a prohibited string
            foreach ($aPwRules['banned'] as $sString) {

                if (strtolower(implode('', $aPwOut)) == strtolower($sString)) {

                    $bPwValid = false;
                    break;
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
     * @param integer $iGroupId The group who's rules to fetch
     * @return array
     */
    protected function getRules($iGroupId)
    {
        $sCacheKey = 'password-rules-' . $iGroupId;
        $aCacheResult = $this->_get_cache($sCacheKey);
        if (!empty($aCacheResult)) {
            return $aCacheResult;
        }

        $this->db->select('password_rules');
        $this->db->where('id', $iGroupId);
        $oResult = $this->db->get(NAILS_DB_PREFIX . 'user_group');

        if ($oResult->num_rows() === 0) {
            return array();
        }

        $oPwRules = json_decode($oResult->row()->password_rules);

        $aOut = array();
        $aOut['min'] = !empty($oPwRules->min) ? $oPwRules->min : null;
        $aOut['max'] = !empty($oPwRules->max) ? $oPwRules->max : null;
        $aOut['expiresAfter'] = !empty($oPwRules->expiresAfter) ? $oPwRules->expiresAfter : null;
        $aOut['requirements'] = !empty($oPwRules->requirements) ? $oPwRules->requirements : array();
        $aOut['banned'] = !empty($oPwRules->banned) ? $oPwRules->banned : array();

        $this->_set_cache($sCacheKey, $aOut);

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the app's password rules as a formatted string
     * @param integer $iGroupId The group who's rules to fetch
     * @return string
     */
    public function getRulesAsString($iGroupId)
    {
        $aRules = $this->getRulesAsArray($iGroupId);

        if (empty($aRules)) {

            return '';
        }

        $sStr = 'Passwords must ' . strtolower(implode(', ', $aRules)) . '.';

        \Nails\Factory::helper('string');
        return str_lreplace(', ', ' and ', $sStr);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the app's password rules as an array of human friendly strings
     * @param integer $iGroupId The group who's rules to fetch
     * @return array
     */
    public function getRulesAsArray($iGroupId)
    {
        $aRules = $this->getRules($iGroupId);
        $aOut   = array();

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
     * @param  string $sPepper Additional data to inject into the salt
     * @return string
     */
    public static function salt($sPepper = '')
    {
        return md5(uniqid($sPepper . rand() . DEPLOY_PRIVATE_KEY . APP_PRIVATE_KEY, true));
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a forgotten password token for a user
     * @param string $identifier The identifier to use for setting the token (set by APP_NATIVE_LOGIN_USING)
     * @return boolean
     */
    public function set_token($sIdentifier)
    {
        if (empty($sIdentifier)) {

            return false;
        }

        // --------------------------------------------------------------------------

        //  Generate code
        $sKey = sha1(sha1(self::salt()) . self::salt() . APP_PRIVATE_KEY);
        $iTtl = time() + 86400; // 24 hours.

        // --------------------------------------------------------------------------

        //  Update the user
        $oUser = $this->oUserModel->getByIdentifier($sIdentifier);

        if ($oUser) {

            $aData = array(
                'forgotten_password_code' => $iTtl . ':' . $sKey
            );

            return $this->oUserModel->update($oUser->id, $aData);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a forgotten password code.
     * @param  string $sCode The token to validate
     * @param  string $bGenerateNewPw Whether or not to generate a new password (only if token is valid)
     * @return boolean
     */
    public function validate_token($sCode, $bGenerateNewPw)
    {
        if (empty($sCode)) {

            return false;
        }

        // --------------------------------------------------------------------------

        $this->db->select('id, group_id, forgotten_password_code');
        $this->db->like('forgotten_password_code', ':' . $sCode, 'before');
        $oResult = $this->db->get(NAILS_DB_PREFIX . 'user');

        // --------------------------------------------------------------------------

        if ($oResult->num_rows() != 1) {

            return false;
        }

        // --------------------------------------------------------------------------

        $oUser = $oResult->row();
        $aCode = explode(':', $oUser->forgotten_password_code);

        // --------------------------------------------------------------------------

        //  Check that the link is still valid
        if (time() > $aCode[0]) {

            return 'EXPIRED';

        } else {

            //  Valid hash and hasn't expired.
            $aOut = array();
            $aOut['user_id'] = $oUser->id;

            //  Generate a new password?
            if ($bGenerateNewPw) {

                $aOut['password'] = $this->generate($oUser->group_id);

                if (empty($aOut['password'])) {

                    //  This should never happen, but just in case.
                    return false;
                }

                $oHash = $this->generateHash($oUser->group_id, $aOut['password']);

                if (!$oHash) {

                    //  Again, this should never happen, but just in case.
                    return false;
                }

                // --------------------------------------------------------------------------

                $aData['password'] = $oHash->password;
                $aData['password_md5'] = $oHash->password_md5;
                $aData['password_engine'] = $oHash->engine;
                $aData['salt'] = $oHash->salt;
                $aData['temp_pw'] = true;
                $aData['forgotten_password_code'] = null;

                $this->db->where('forgotten_password_code', $oUser->forgotten_password_code);
                $this->db->set($aData);
                $this->db->update(NAILS_DB_PREFIX . 'user');
            }
        }

        return $aOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an array of permissions into a JSON encoded string suitable for the database
     * @param  array  $aRules An array of rules to set
     * @return string
     */
    public function processRules($aRules)
    {
        if (empty($aRules)) {
            return null;
        }

        $aOut = array();

        //  Min/max length
        $aOut['min'] = !empty($aRules['min']) ? (int) $aRules['min'] : null;
        $aOut['max'] = !empty($aRules['max']) ? (int) $aRules['max'] : null;

        //  Expiration
        $aOut['expiresAfter'] = !empty($aRules['expires_after']) ? (int) $aRules['expires_after'] : null;

        //  Requirements
        $aOut['requirements'] = array();
        if (!empty($aRules['requirements'])) {
            $aOut['requirements']['symbol'] = in_array('symbol', $aRules['requirements']);
            $aOut['requirements']['number'] = in_array('number', $aRules['requirements']);
            $aOut['requirements']['lower_alpha'] = in_array('lower_alpha', $aRules['requirements']);
            $aOut['requirements']['upper_alpha'] = in_array('upper_alpha', $aRules['requirements']);

            $aOut['requirements'] = array_filter($aOut['requirements']);
        }

        //  Banned words
        $aOut['banned'] = array();
        if (!empty($aRules['banned'])) {

            $aRules['banned'] = trim($aRules['banned']);
            $aOut['banned'] = explode(',', $aRules['banned']);
            $aOut['banned'] = array_map('trim', $aOut['banned']);
            $aOut['banned'] = array_map('strtolower', $aOut['banned']);
            $aOut['banned'] = array_filter($aOut['banned']);
        }
        $aOut = array_filter($aOut);

        if (empty($aOut)) {

            return null;

        } else {

            return json_encode($aOut);
        }
    }
}
