<?php

/**
 * This class provides authentication functionality
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model;

use Google\Authenticator\GoogleAuthenticator;
use Nails\Common\Model\Base;
use Nails\Environment;
use Nails\Factory;

class Auth extends Base
{
    protected $aBruteProtection;

    // --------------------------------------------------------------------------

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->aBruteProtection = [
            'delay'  => 1500000,
            'limit'  => 10,
            'expire' => 900,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user in
     *
     * @param  string  $sIdentifier The identifier to use for the user lookup
     * @param  string  $sPassword   The user's password
     * @param  boolean $bRemember   Whether to 'remember' the user or not
     *
     * @return boolean|object
     */
    public function login($sIdentifier, $sPassword, $bRemember = false)
    {
        //  Delay execution for a moment (reduces brute force efficiently)
        if (Environment::not('DEVELOPMENT')) {
            usleep($this->aBruteProtection['delay']);
        }

        // --------------------------------------------------------------------------

        if (empty($sIdentifier) || empty($sPassword)) {
            $this->setError(lang('auth_login_fail_missing_field'));
            return false;
        }

        // --------------------------------------------------------------------------

        //  Look up the user, how we do so depends on the login mode that the app is using
        $oUserModel = Factory::model('User', 'nails/module-auth');
        switch (APP_NATIVE_LOGIN_USING) {

            case 'EMAIL':
                $oUser = $oUserModel->getByEmail($sIdentifier);
                break;

            case 'USERNAME':
                $oUser = $oUserModel->getByUsername($sIdentifier);
                break;

            default:
                if (valid_email($sIdentifier)) {
                    $oUser = $oUserModel->getByEmail($sIdentifier);
                } else {
                    $oUser = $oUserModel->getByUsername($sIdentifier);
                }
                break;
        }

        // --------------------------------------------------------------------------

        if ($oUser) {

            //  User was recognised; validate credentials
            $oPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
            if ($oPasswordModel->isCorrect($oUser->id, $sPassword)) {

                //  Password accepted! Final checks...

                //  Exceeded login count, temporarily blocked
                if ($oUser->failed_login_count >= $this->aBruteProtection['limit']) {
                    //  Check if the block has expired
                    if (time() < strtotime($oUser->failed_login_expires)) {
                        $iBlockTime = ceil($this->aBruteProtection['expire'] / 60);
                        $this->setError(lang('auth_login_fail_blocked', $iBlockTime));
                        return false;
                    }
                }

                //  Reset user's failed login counter and allow login
                $oUserModel->resetFailedLogin($oUser->id);

                /**
                 * If two factor auth is enabled then don't _actually_ set login data the
                 * next process will confirm the login and set this.
                 */

                $oConfig = Factory::service('Config');

                if (!$oConfig->item('authTwoFactorMode')) {

                    //  Set login data for this user
                    $oUserModel->setLoginData($oUser->id);

                    //  If we're remembering this user set a cookie
                    if ($bRemember) {
                        $oUserModel->setRememberCookie($oUser->id, $oUser->password, $oUser->email);
                    }

                    //  Update their last login and increment their login count
                    $oUserModel->updateLastLogin($oUser->id);
                }

                return $oUser;

                //  Is the password null? If so it means the account was created using an API of sorts
            } elseif (is_null($oUser->password)) {

                switch (APP_NATIVE_LOGIN_USING) {

                    case 'EMAIL':
                        $sIdentifier = $oUser->email;
                        break;

                    case 'USERNAME':
                        $sIdentifier = $oUser->username;
                        break;

                    default:
                        $sIdentifier = $oUser->email;
                        break;
                }

                $error = lang('auth_login_fail_social', site_url('auth/password/forgotten?identifier=' . $sIdentifier));
                $this->setError($error);

                return false;

            } else {

                //  User was recognised but the password was wrong
                //  Increment the user's failed login count
                $oUserModel->incrementFailedLogin($oUser->id, $this->aBruteProtection['expire']);

                //  Are we already blocked? Let them know...
                if ($oUser->failed_login_count >= $this->aBruteProtection['limit']) {

                    //  Check if the block has expired
                    if (time() < strtotime($oUser->failed_login_expires)) {
                        $iBlockTime = ceil($this->aBruteProtection['expire'] / 60);
                        $this->setError(lang('auth_login_fail_blocked', $iBlockTime));
                        return false;
                    }

                    //  Block has expired, reset the counter
                    $oUserModel->resetFailedLogin($oUser->id);
                }

                //  Check if the password was changed recently
                if ($oUser->password_changed) {

                    $iChanged = strtotime($oUser->password_changed);
                    $iRecent  = strtotime('-2 WEEKS');

                    if ($iChanged > $iRecent) {
                        $sChangedRecently = niceTime($iChanged);
                    }
                }
            }
        }

        //  Login failed
        if (empty($sChangedRecently)) {
            $this->setError(lang('auth_login_fail_general'));
        } else {
            $this->setError(lang('auth_login_fail_general_recent', $sChangedRecently));
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Verifies a user's login credentials
     *
     * @param  string $sIdentifier The identifier to use for the lookup
     * @param  string $sPassword   The user's password
     *
     * @return boolean
     */
    public function verifyCredentials($sIdentifier, $sPassword)
    {
        //  Look up the user, how we do so depends on the login mode that the app is using
        $oUserModel     = Factory::model('User', 'nails/module-auth');
        $oPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
        $oUser          = $oUserModel->getByIdentifier($sIdentifier);

        return !empty($oUser) ? $oPasswordModel->isCorrect($oUser->id, $sPassword) : false;
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user out
     * @return boolean
     */
    public function logout()
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oUserModel->clearRememberCookie();

        // --------------------------------------------------------------------------

        //  null the remember_code so that auto-logins stop
        $oDb = Factory::service('Database');
        $oDb->set('remember_code', null);
        $oDb->where('id', activeUser('id'));
        $oDb->update(NAILS_DB_PREFIX . 'user');

        // --------------------------------------------------------------------------

        //  Destroy key parts of the session (enough for user_model to report user as logged out)
        $oUserModel->clearLoginData();

        // --------------------------------------------------------------------------

        //  Destroy CI session
        $oSession = Factory::service('Session', 'nails/module-auth');
        $oSession->destroy();

        // --------------------------------------------------------------------------

        //  Destroy PHP session if it exists
        if (session_id()) {
            session_destroy();
        }

        // --------------------------------------------------------------------------

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Generate an MFA token
     *
     * @param  int $iUserId The user ID to generate the token for
     *
     * @return array|false
     */
    public function mfaTokenGenerate($iUserId)
    {
        $oPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
        $oNow           = Factory::factory('DateTime');
        $oInput         = Factory::service('Input');
        $oDb            = Factory::service('Database');

        $sSalt    = $oPasswordModel->salt();
        $sIp      = $oInput->ipAddress();
        $sCreated = $oNow->format('Y-m-d H:i:s');
        $sExpires = $oNow->add(new \DateInterval('PT10M'))->format('Y-m-d H:i:s');
        $aToken   = [
            'token' => sha1(sha1(APP_PRIVATE_KEY . $iUserId . $sCreated . $sExpires . $sIp) . $sSalt),
            'salt'  => md5($sSalt),
        ];

        //  Add this to the DB
        $oDb->set('user_id', $iUserId);
        $oDb->set('token', $aToken['token']);
        $oDb->set('salt', $aToken['salt']);
        $oDb->set('created', $sCreated);
        $oDb->set('expires', $sExpires);
        $oDb->set('ip', $sIp);

        if ($oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_token')) {
            $aToken['id'] = $oDb->insert_id();
            return $aToken;
        } else {
            $error = lang('auth_twofactor_token_could_not_generate');
            $this->setError($error);
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a MFA token
     *
     * @param  int    $iUserId The ID of the user the token belongs to
     * @param  string $sSalt   The token's salt
     * @param  string $sToken  The token's hash
     * @param  string $sIp     The user's IP address
     *
     * @return boolean
     */
    public function mfaTokenValidate($iUserId, $sSalt, $sToken, $sIp)
    {
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $iUserId);
        $oDb->where('salt', $sSalt);
        $oDb->where('token', $sToken);

        $oToken  = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_token')->row();
        $bReturn = true;

        if (!$oToken) {

            $this->setError(lang('auth_twofactor_token_invalid'));
            return false;

        } elseif (strtotime($oToken->expires) <= time()) {

            $this->setError(lang('auth_twofactor_token_expired'));
            $bReturn = false;

        } elseif ($oToken->ip != $sIp) {

            $this->setError(lang('auth_twofactor_token_bad_ip'));
            $bReturn = false;
        }

        //  Delete the token
        $this->mfaTokenDelete($oToken->id);

        return $bReturn;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an MFA token
     *
     * @param  int $iTokenId The token's ID
     *
     * @return boolean
     */
    public function mfaTokenDelete($iTokenId)
    {
        $oDb = Factory::service('Database');
        $oDb->where('id', $iTokenId);
        $oDb->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_token');
        return (bool) $oDb->affected_rows();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a random MFA question for a user
     *
     * @param  int $iUserId The user's ID
     *
     * @return boolean|\stdClass
     */
    public function mfaQuestionGet($iUserId)
    {
        $oDb    = Factory::service('Database');
        $oInput = Factory::service('Input');

        $oDb->where('user_id', $iUserId);
        $oDb->order_by('last_requested', 'DESC');
        $aQuestions = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->result();

        if (!$aQuestions) {
            $this->setError('No security questions available for this user.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Choose a question to return
        if (count($aQuestions) == 1) {

            //  No choice, just return the lonely question
            $oOut = reset($aQuestions);

        } elseif (count($aQuestions) > 1) {

            /**
             * Has the most recently asked question been asked in the last 10 minutes?
             * If so, return that one again (to make harvesting all the user's questions
             * a little more time consuming). If not randomly choose one.
             */

            $oOut = reset($aQuestions);
            if (strtotime($oOut->last_requested) < strtotime('-10 MINS')) {
                $oOut = $aQuestions[array_rand($aQuestions)];
            }

        } else {
            $this->setError('Could not determine security question.');
            return false;
        }

        //  Decode the question
        $oEncrypt       = Factory::service('Encrypt');
        $oOut->question = $oEncrypt->decode($oOut->question, APP_PRIVATE_KEY . $oOut->salt);

        //  Update the last requested details
        $oDb->set('last_requested', 'NOW()', false);
        $oDb->set('last_requested_ip', $oInput->ipAddress());
        $oDb->where('id', $oOut->id);
        $oDb->update(NAILS_DB_PREFIX . 'user_auth_two_factor_question');

        return $oOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates the answer to an MFA Question
     *
     * @param  int    $iQuestionId The question's ID
     * @param  int    $iUserId     The user's ID
     * @param  string $answer      The user's answer
     *
     * @return boolean
     */
    public function mfaQuestionValidate($iQuestionId, $iUserId, $answer)
    {
        $oDb = Factory::service('Database');
        $oDb->select('answer, salt');
        $oDb->where('id', $iQuestionId);
        $oDb->where('user_id', $iUserId);
        $oQuestion = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->row();

        if (!$oQuestion) {
            return false;
        }

        $hash = sha1(sha1(strtolower($answer)) . APP_PRIVATE_KEY . $oQuestion->salt);

        return $hash === $oQuestion->answer;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets MFA questions for a user
     *
     * @param  int     $iUserId  The user's ID
     * @param  array   $data     An array of question and answers
     * @param  boolean $clearOld Whether or not to clear old questions
     *
     * @return boolean
     */
    public function mfaQuestionSet($iUserId, $data, $clearOld = true)
    {
        //  Check input
        foreach ($data as $d) {
            if (empty($d->question) || empty($d->answer)) {
                $this->setError('Malformed question/answer data.');
                return false;
            }
        }

        //  Begin transaction
        $oDb = Factory::service('Database');
        $oDb->trans_begin();

        //  Delete old questions?
        if ($clearOld) {
            $oDb->where('user_id', $iUserId);
            $oDb->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question');
        }

        $oPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
        $oEncrypt       = Factory::service('Encrypt');

        $questionData = [];
        $counter      = 0;
        $oNow         = Factory::factory('DateTime');
        $sDateTime    = $oNow->format('Y-m-d H:i:s');

        foreach ($data as $d) {
            $sSalt                  = $oPasswordModel->salt();
            $questionData[$counter] = [
                'user_id'        => $iUserId,
                'salt'           => $sSalt,
                'question'       => $oEncrypt->encode($d->question, APP_PRIVATE_KEY . $sSalt),
                'answer'         => sha1(sha1(strtolower($d->answer)) . APP_PRIVATE_KEY . $sSalt),
                'created'        => $sDateTime,
                'last_requested' => null,
            ];
            $counter++;
        }

        if ($questionData) {

            $oDb->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_question', $questionData);

            if ($oDb->trans_status() !== false) {
                $oDb->trans_commit();
                return true;
            } else {
                $oDb->trans_rollback();
                return false;
            }

        } else {
            $oDb->trans_rollback();
            $this->setError('No data to save.');
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the user's MFA device if there is one
     *
     * @param  int $iUserId The user's ID
     *
     * @return mixed         \stdClass on success, false on failure
     */
    public function mfaDeviceSecretGet($iUserId)
    {
        $oDb      = Factory::service('Database');
        $oEncrypt = Factory::service('Encrypt');

        $oDb->where('user_id', $iUserId);
        $oDb->limit(1);
        $aResult = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')->result();

        if (empty($aResult)) {
            return false;
        }

        $oReturn         = reset($aResult);
        $oReturn->secret = $oEncrypt->decode($oReturn->secret, APP_PRIVATE_KEY);

        return $oReturn;
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a MFA Device Secret
     *
     * @param  int    $iUserId         The user ID to generate for
     * @param  string $sExistingSecret The existing secret to use instead of generating a new one
     *
     * @return boolean|array
     */
    public function mfaDeviceSecretGenerate($iUserId, $sExistingSecret = null)
    {
        //  Get an identifier for the user
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oUser      = $oUserModel->getById($iUserId);

        if (!$oUser) {
            $this->setError('User does not exist.');
            return false;
        }

        $oGoogleAuth = new GoogleAuthenticator();

        //  Generate the secret
        if (empty($sExistingSecret)) {
            $sSecret = $oGoogleAuth->generateSecret();
        } else {
            $sSecret = $sExistingSecret;
        }

        //  Get the hostname
        $sHostname = getDomainFromUrl(BASE_URL);

        //  User identifier
        $sUsername = $oUser->username;
        $sUsername = empty($sUsername) ? preg_replace('/[^a-z]/', '', strtolower($oUser->first_name . $oUser->last_name)) : $sUsername;
        $sUsername = empty($sUsername) ? preg_replace('/[^a-z]/', '', strtolower($oUser->email)) : $sUsername;

        return [
            'secret' => $sSecret,
            'url'    => $oGoogleAuth->getUrl($sUsername, $sHostname, $sSecret),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Validates a secret against two given codes, if valid adds as a device for
     * the user
     *
     * @param  int    $iUserId The user's ID
     * @param  string $sSecret The secret being used
     * @param  int    $iCode1  The first code to be generate
     * @param  int    $iCode2  The second code to be generated
     *
     * @return boolean
     */
    public function mfaDeviceSecretValidate($iUserId, $sSecret, $iCode1, $iCode2)
    {
        //  Tidy up codes so that they only contain digits
        $sCode1 = preg_replace('/[^\d]/', '', $iCode1);
        $sCode2 = preg_replace('/[^\d]/', '', $iCode2);

        //  New instance of the authenticator
        $oGoogleAuth = new GoogleAuthenticator();

        //  Check the codes
        $bCheckCode1 = $oGoogleAuth->checkCode($sSecret, $sCode1);
        $bCheckCode2 = $oGoogleAuth->checkCode($sSecret, $sCode2);

        if ($bCheckCode1 && $bCheckCode2) {

            /**
             * Both codes are valid, which means they are sequential and recent.
             * We must now save the secret against the user and record those codes
             * so they can't be used again.
             */

            $oDb      = Factory::service('Database');
            $oEncrypt = Factory::service('Encrypt');

            $oDb->set('user_id', $iUserId);
            $oDb->set('secret', $oEncrypt->encode($sSecret, APP_PRIVATE_KEY));
            $oDb->set('created', 'NOW()', false);

            if ($oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')) {

                $iSecretId = $oDb->insert_id();
                $oNow      = Factory::factory('DateTime');

                $aData = [
                    [
                        'secret_id' => $iSecretId,
                        'code'      => $sCode1,
                        'used'      => $oNow->format('Y-m-d H:i:s'),
                    ],
                    [
                        'secret_id' => $iSecretId,
                        'code'      => $sCode2,
                        'used'      => $oNow->format('Y-m-d H:i:s'),
                    ],
                ];

                $oDb->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code', $aData);

                return true;

            } else {
                $this->setError('Could not save secret.');
                return false;
            }

        } else {
            $this->setError('Codes did not validate.');
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validates an MFA Device code
     *
     * @param  int    $iUserId The user's ID
     * @param  string $sCode   The code to validate
     *
     * @return boolean
     */
    public function mfaDeviceCodeValidate($iUserId, $sCode)
    {
        //  Get the user's secret
        $oSecret = $this->mfaDeviceSecretGet($iUserId);

        if (!$oSecret) {
            $this->setError('Invalid User');
            return false;
        }

        //  Has the code been used before?
        $oDb = Factory::service('Database');
        $oDb->where('secret_id', $oSecret->id);
        $oDb->where('code', $sCode);

        if ($oDb->count_all_results(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code')) {
            $this->setError('Code has already been used.');
            return false;
        }

        //  Tidy up codes so that they only contain digits
        $sCode = preg_replace('/[^\d]/', '', $sCode);

        //  New instance of the authenticator
        $oGoogleAuth = new GoogleAuthenticator();
        $checkCode   = $oGoogleAuth->checkCode($oSecret->secret, $sCode);

        if ($checkCode) {

            //  Log the code so it can't be used again
            $oDb->set('secret_id', $oSecret->id);
            $oDb->set('code', $sCode);
            $oDb->set('used', 'NOW()', false);

            $oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code');

            return true;

        } else {
            return false;
        }
    }
}
