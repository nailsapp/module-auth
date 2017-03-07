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
     * @param  string  $identifier The identifier to use for the user lookup
     * @param  string  $password   The user's password
     * @param  boolean $remember   Whether to 'remember' the user or not
     *
     * @return boolean|object
     */
    public function login($identifier, $password, $remember = false)
    {
        //  Delay execution for a moment (reduces brute force efficiently)
        if (Environment::not('DEVELOPMENT')) {
            usleep($this->aBruteProtection['delay']);
        }

        // --------------------------------------------------------------------------

        if (empty($identifier) || empty($password)) {

            $error = lang('auth_login_fail_missing_field');
            $this->setError($error);
            return false;
        }

        // --------------------------------------------------------------------------

        //  Look up the user, how we do so depends on the login mode that the app is using
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        switch (APP_NATIVE_LOGIN_USING) {

            case 'EMAIL':
                $user = $oUserModel->getByEmail($identifier);
                break;

            case 'USERNAME':
                $user = $oUserModel->getByUsername($identifier);
                break;

            default:
                if (valid_email($identifier)) {
                    $user = $oUserModel->getByEmail($identifier);
                } else {
                    $user = $oUserModel->getByUsername($identifier);
                }
                break;
        }

        // --------------------------------------------------------------------------

        if ($user) {

            //  User was recognised; validate credentials
            $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
            if ($oPasswordModel->isCorrect($user->id, $password)) {

                //  Password accepted! Final checks...

                //  Exceeded login count, temporarily blocked
                if ($user->failed_login_count >= $this->aBruteProtection['limit']) {

                    //  Check if the block has expired
                    if (time() < strtotime($user->failed_login_expires)) {

                        $blockTime = ceil($this->aBruteProtection['expire'] / 60);

                        $error = lang('auth_login_fail_blocked', $blockTime);
                        $this->setError($error);
                        return false;
                    }
                }

                //  Reset user's failed login counter and allow login
                $oUserModel->resetFailedLogin($user->id);

                /**
                 * If two factor auth is enabled then don't _actually_ set login data the
                 * next process will confirm the login and set this.
                 */

                $oConfig = Factory::service('Config');

                if (!$oConfig->item('authTwoFactorMode')) {

                    //  Set login data for this user
                    $oUserModel->setLoginData($user->id);

                    //  If we're remembering this user set a cookie
                    if ($remember) {
                        $oUserModel->setRememberCookie($user->id, $user->password, $user->email);
                    }

                    //  Update their last login and increment their login count
                    $oUserModel->updateLastLogin($user->id);
                }

                return $user;

                //  Is the password null? If so it means the account was created using an API of sorts
            } elseif (is_null($user->password)) {

                switch (APP_NATIVE_LOGIN_USING) {

                    case 'EMAIL':
                        $identifier = $user->email;
                        break;

                    case 'USERNAME':
                        $identifier = $user->username;
                        break;

                    default:
                        $identifier = $user->email;
                        break;
                }

                $error = lang('auth_login_fail_social', site_url('auth/forgotten_password?identifier=' . $identifier));
                $this->setError($error);

                return false;

            } else {

                //  User was recognised but the password was wrong

                //  Increment the user's failed login count
                $oUserModel->incrementFailedLogin($user->id, $this->aBruteProtection['expire']);

                //  Are we already blocked? Let them know...
                if ($user->failed_login_count >= $this->aBruteProtection['limit']) {

                    //  Check if the block has expired
                    if (time() < strtotime($user->failed_login_expires)) {

                        $blockTime = ceil($this->aBruteProtection['expire'] / 60);
                        $error     = lang('auth_login_fail_blocked', $blockTime);
                        $this->setError($error);
                        return false;
                    }

                    //  Block has expired, reset the counter
                    $oUserModel->resetFailedLogin($user->id);
                }

                //  Check if the password was changed recently
                if ($user->password_changed) {

                    $changed = strtotime($user->password_changed);
                    $recent  = strtotime('-2 WEEKS');

                    if ($changed > $recent) {

                        $changedRecently = niceTime($changed);
                    }
                }
            }
        }

        //  Login failed
        if (empty($changedRecently)) {

            $error = lang('auth_login_fail_general');
            $this->setError($error);

        } else {

            $error = lang('auth_login_fail_general_recent', $changedRecently);
            $this->setError($error);
        }

        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Verifies a user's login credentials
     *
     * @param  string $identifier The identifier to use for the lookup
     * @param  string $password   The user's password
     *
     * @return boolean
     */
    public function verifyCredentials($identifier, $password)
    {
        //  Look up the user, how we do so depends on the login mode that the app is using
        $oUserModel     = Factory::model('User', 'nailsapp/module-auth');
        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $user           = $oUserModel->getByIdentifier($identifier);

        return !empty($user) ? $oPasswordModel->isCorrect($user->id, $password) : false;
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user out
     * @return boolean
     */
    public function logout()
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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

        //  Destory CI session
        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->sess_destroy();

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
     * @param  int $userId The user ID to generate the token for
     *
     * @return string
     */
    public function mfaTokenGenerate($userId)
    {
        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $oDate          = Factory::factory('DateTime');
        $oInput         = Factory::service('Input');
        $salt           = $oPasswordModel->salt();
        $ip             = $oInput->ipAddress();
        $created        = $oDate->format('Y-m-d H:i:s');
        $expires        = $oDate->add(new \DateInterval('PT10M'))->format('Y-m-d H:i:s');

        $token          = [];
        $token['token'] = sha1(sha1(APP_PRIVATE_KEY . $userId . $created . $expires . $ip) . $salt);
        $token['salt']  = md5($salt);

        //  Add this to the DB
        $oDb = Factory::service('Database');
        $oDb->set('user_id', $userId);
        $oDb->set('token', $token['token']);
        $oDb->set('salt', $token['salt']);
        $oDb->set('created', $created);
        $oDb->set('expires', $expires);
        $oDb->set('ip', $ip);

        if ($oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_token')) {

            $token['id'] = $oDb->insert_id();
            return $token;

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
     * @param  int    $userId The ID of the user the token belongs to
     * @param  string $salt   The token's salt
     * @param  string $token  The token's hash
     * @param  string $ip     The user's IP address
     *
     * @return boolean
     */
    public function mfaTokenValidate($userId, $salt, $token, $ip)
    {
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $userId);
        $oDb->where('salt', $salt);
        $oDb->where('token', $token);

        $token  = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_token')->row();
        $return = true;

        if (!$token) {

            $error = lang('auth_twofactor_token_invalid');
            $this->setError($error);
            return false;

        } elseif (strtotime($token->expires) <= time()) {

            $error = lang('auth_twofactor_token_expired');
            $this->setError($error);
            $return = false;

        } elseif ($token->ip != $ip) {

            $error = lang('auth_twofactor_token_bad_ip');
            $this->setError($error);
            $return = false;
        }

        //  Delete the token
        $this->mfaTokenDelete($token->id);

        return $return;
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an MFA token
     *
     * @param  int $tokenId The token's ID
     *
     * @return boolean
     */
    public function mfaTokenDelete($tokenId)
    {
        $oDb = Factory::service('Database');
        $oDb->where('id', $tokenId);
        $oDb->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_token');
        return (bool) $oDb->affected_rows();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a random MFA question for a user
     *
     * @param  int $userId The user's ID
     *
     * @return boolean|\stdClass
     */
    public function mfaQuestionGet($userId)
    {
        $oDb    = Factory::service('Database');
        $oInput = Factory::service('Input');

        $oDb->where('user_id', $userId);
        $oDb->order_by('last_requested', 'DESC');
        $questions = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->result();

        if (!$questions) {
            $this->setError('No security questions available for this user.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Choose a question to return
        if (count($questions) == 1) {

            //  No choice, just return the lonely question
            $out = $questions[0];

        } elseif (count($questions) > 1) {

            /**
             * Has the most recently asked question been asked in the last 10 minutes?
             * If so, return that one again (to make harvesting all the user's questions
             * a little more time consuming). If not randomly choose one.
             */

            if (strtotime($questions[0]->last_requested) > strtotime('-10 MINS')) {

                $out = $questions[0];

            } else {

                $out = $questions[array_rand($questions)];
            }

        } else {

            //  Derp.
            $this->setError('Could not determine security question.');
            return false;
        }

        //  Decode the question
        $oEncrypt      = Factory::service('Encrypt');
        $out->question = $oEncrypt->decode($out->question, APP_PRIVATE_KEY . $out->salt);

        //  Update the last requested details
        $oDb->set('last_requested', 'NOW()', false);
        $oDb->set('last_requested_ip', $oInput->ipAddress());
        $oDb->where('id', $out->id);
        $oDb->update(NAILS_DB_PREFIX . 'user_auth_two_factor_question');

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates the answer to an MFA Question
     *
     * @param  int    $questionId The question's ID
     * @param  int    $userId     The user's ID
     * @param  string $answer     The user's answer
     *
     * @return boolean
     */
    public function mfaQuestionValidate($questionId, $userId, $answer)
    {
        $oDb = Factory::service('Database');
        $oDb->select('answer, salt');
        $oDb->where('id', $questionId);
        $oDb->where('user_id', $userId);
        $question = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->row();

        if (!$question) {
            return false;
        }

        $hash = sha1(sha1(strtolower($answer)) . APP_PRIVATE_KEY . $question->salt);

        return $hash === $question->answer;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets MFA questions for a user
     *
     * @param  int     $userId   The user's ID
     * @param  array   $data     An array of question and answers
     * @param  boolean $clearOld Whether or not to clear old questions
     *
     * @return boolean
     */
    public function mfaQuestionSet($userId, $data, $clearOld = true)
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
            $oDb->where('user_id', $userId);
            $oDb->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question');
        }

        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $oEncrypt       = Factory::service('Encrypt');

        $questionData = [];
        $counter      = 0;
        $oDate        = Factory::factory('DateTime');
        $sDateTime    = $oDate->format('Y-m-d H:i:s');

        foreach ($data as $d) {

            $questionData[$counter]             = [];
            $questionData[$counter]['user_id']  = $userId;
            $questionData[$counter]['salt']     = $oPasswordModel->salt();
            $questionData[$counter]['question'] = $oEncrypt->encode($d->question, APP_PRIVATE_KEY . $questionData[$counter]['salt']);
            $questionData[$counter]['answer']   = sha1(sha1(strtolower($d->answer)) . APP_PRIVATE_KEY . $questionData[$counter]['salt']);
            $questionData[$counter]['created']  = $sDateTime;

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
     * @param  int $userId The user's ID
     *
     * @return mixed         \stdClass on success, false on failure
     */
    public function mfaDeviceSecretGet($userId)
    {
        $oDb      = Factory::service('Database');
        $oEncrypt = Factory::service('Encrypt');

        $oDb->where('user_id', $userId);
        $oDb->limit(1);
        $result = $oDb->get(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')->result();

        if (empty($result)) {
            return false;
        }

        $return         = $result[0];
        $return->secret = $oEncrypt->decode($return->secret, APP_PRIVATE_KEY);

        return $result[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a MFA Device Secret
     *
     * @param  int    $userId         The user ID to generate for
     * @param  string $existingSecret The existing secret to use instead of generating a new one
     *
     * @return boolean|array
     */
    public function mfaDeviceSecretGenerate($userId, $existingSecret = null)
    {
        //  Get an identifier for the user
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $user       = $oUserModel->getById($userId);

        if (!$user) {

            $this->setError('User does not exist.');
            return false;
        }

        $g = new GoogleAuthenticator();

        //  Generate the secret
        if (empty($existingSecret)) {
            $secret = $g->generateSecret();
        } else {
            $secret = $existingSecret;
        }

        //  Get the hostname
        $hostname = getDomainFromUrl(BASE_URL);

        //  User identifier
        $username = $user->username;
        $username = empty($username) ? preg_replace('/[^a-z]/', '', strtolower($user->first_name . $user->last_name)) : $username;
        $username = empty($username) ? preg_replace('/[^a-z]/', '', strtolower($user->email)) : $username;

        return [
            'secret' => $secret,
            'url'    => $g->getUrl($username, $hostname, $secret),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Validates a secret against two given codes, if valid adds as a device for
     * the user
     *
     * @param  int    $userId The user's ID
     * @param  string $secret The secret being used
     * @param  int    $code1  The first code to be generate
     * @param  int    $code2  The second code to be generated
     *
     * @return boolean
     */
    public function mfaDeviceSecretValidate($userId, $secret, $code1, $code2)
    {
        //  Tidy up codes so that they only contain digits
        $code1 = preg_replace('/[^\d]/', '', $code1);
        $code2 = preg_replace('/[^\d]/', '', $code2);

        //  New instance of the authenticator
        $g = new GoogleAuthenticator();

        //  Check the codes
        $checkCode1 = $g->checkCode($secret, $code1);
        $checkCode2 = $g->checkCode($secret, $code2);

        if ($checkCode1 && $checkCode2) {

            /**
             * Both codes are valid, which means they are sequential and recent.
             * We must now save the secret against the user and record those codes
             * so they can't be used again.
             */

            $oDb      = Factory::service('Database');
            $oEncrypt = Factory::service('Encrypt');

            $oDb->set('user_id', $userId);
            $oDb->set('secret', $oEncrypt->encode($secret, APP_PRIVATE_KEY));
            $oDb->set('created', 'NOW()', false);

            if ($oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')) {

                $secret_id = $oDb->insert_id();
                $oDate     = Factory::factory('DateTime');

                $data   = [];
                $data[] = [
                    'secret_id' => $secret_id,
                    'code'      => $code1,
                    'used'      => $oDate->format('Y-m-d H:i:s'),
                ];
                $data[] = [
                    'secret_id' => $secret_id,
                    'code'      => $code2,
                    'used'      => $oDate->format('Y-m-d H:i:s'),
                ];

                $oDb->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code', $data);

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
     * @param  int    $userId The user's ID
     * @param  string $code   The code to validate
     *
     * @return boolean
     */
    public function mfaDeviceCodeValidate($userId, $code)
    {
        //  Get the user's secret
        $secret = $this->mfaDeviceSecretGet($userId);

        if (!$secret) {
            $this->setError('Invalid User');
            return false;
        }

        //  Has the code been used before?
        $oDb = Factory::service('Database');
        $oDb->where('secret_id', $secret->id);
        $oDb->where('code', $code);

        if ($oDb->count_all_results(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code')) {
            $this->setError('Code has already been used.');
            return false;
        }

        //  Tidy up codes so that they only contain digits
        $code = preg_replace('/[^\d]/', '', $code);

        //  New instance of the authenticator
        $g = new GoogleAuthenticator();

        $checkCode = $g->checkCode($secret->secret, $code);

        if ($checkCode) {

            //  Log the code so it can't be used again
            $oDb->set('secret_id', $secret->id);
            $oDb->set('code', $code);
            $oDb->set('used', 'NOW()', false);

            $oDb->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code');

            return true;

        } else {

            return false;
        }
    }
}
