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

use Nails\Factory;

class Auth extends \Nails\Common\Model\Base
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Set variables
        $this->brute_force_protection           = array();
        $this->brute_force_protection['delay']  = 1500000;
        $this->brute_force_protection['limit']  = 10;
        $this->brute_force_protection['expire'] = 900;
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user in
     * @param  string  $identifier The identifier to use for the user lookup
     * @param  string  $password   The user's password
     * @param  boolean $remember   Whether to 'remember' the user or not
     * @return object
     */
    public function login($identifier, $password, $remember = false)
    {
        //  Delay execution for a moment (reduces brute force efficiently)
        if (strtoupper(ENVIRONMENT) !== 'DEVELOPMENT') {

            usleep($this->brute_force_protection['delay']);
        }

        // --------------------------------------------------------------------------

        if (empty($identifier) || empty($password)) {

            $error = lang('auth_login_fail_missing_field');
            $this->setError($error);
            return false;
        }

        // --------------------------------------------------------------------------

        //  Look up the user, how we do so depends on the login mode that the app is using
        switch (APP_NATIVE_LOGIN_USING) {

            case 'EMAIL':

                $user = $this->user_model->getByEmail($identifier);
                break;

            case 'USERNAME':

                $user = $this->user_model->getByUsername($identifier);
                break;

            default:

                if (valid_email($identifier)) {

                    $user = $this->user_model->getByEmail($identifier);

                } else {

                    $user = $this->user_model->getByUsername($identifier);
                }
                break;
        }

        // --------------------------------------------------------------------------

        if ($user) {

            //  User was recognised; validate credentials
            if ($this->user_password_model->isCorrect($user->id, $password)) {

                //  Password accepted! Final checks...

                //  Exceeded login count, temporarily blocked
                if ($user->failed_login_count >= $this->brute_force_protection['limit']) {

                    //  Check if the block has expired
                    if (time() < strtotime($user->failed_login_expires)) {

                        $blockTime = ceil($this->brute_force_protection['expire']/60);

                        $error = lang('auth_login_fail_blocked', $blockTime);
                        $this->setError($error);
                        return false;
                    }
                }

                //  Reset user's failed login counter and allow login
                $this->user_model->resetFailedLogin($user->id);

                /**
                 * If two factor auth is enabled then don't _actually_ set login data the
                 * next process will confirm the login and set this.
                 */

                if (!$this->config->item('authTwoFactorMode')) {

                    //  Set login data for this user
                    $this->user_model->setLoginData($user->id);

                    //  If we're remembering this user set a cookie
                    if ($remember) {

                        $this->user_model->setRememberCookie($user->id, $user->password, $user->email);
                    }

                    //  Update their last login and increment their login count
                    $this->user_model->updateLastLogin($user->id);
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
                $this->user_model->incrementFailedLogin($user->id, $this->brute_force_protection['expire']);

                //  Are we already blocked? Let them know...
                if ($user->failed_login_count >= $this->brute_force_protection['limit']) {

                    //  Check if the block has expired
                    if (time() < strtotime($user->failed_login_expires)) {

                        $blockTime = ceil($this->brute_force_protection['expire']/60);
                        $error     = lang('auth_login_fail_blocked', $blockTime);
                        $this->setError($error);
                        return false;
                    }

                    //  Block has expired, reset the counter
                    $this->user_model->resetFailedLogin($user->id);
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
     * @param  string $identifier The identifier to use for the lookup
     * @param  string $password   The user's password
     * @return boolean
     */
    public function verifyCredentials($identifier, $password)
    {
        //  Look up the user, how we do so depends on the login mode that the app is using
        $user = $this->user_model->getByIdentifier($identifier);

        if (!empty($user)) {

            return $this->user_password_model->isCorrect($user->id, $password);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Log a user out
     * @return boolean
     */
    public function logout()
    {
        // Delete the remember me cookies if they exist
        $this->user_model->clearRememberCookie();

        // --------------------------------------------------------------------------

        //  null the remember_code so that auto-logins stop
        $this->db->set('remember_code', null);
        $this->db->where('id', activeUser('id'));
        $this->db->update(NAILS_DB_PREFIX . 'user');

        // --------------------------------------------------------------------------

        //  Destroy key parts of the session (enough for user_model to report user as logged out)
        $this->user_model->clearLoginData();

        // --------------------------------------------------------------------------

        //  Destory CI session
        $this->session->sess_destroy();

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
     * @param  int    $userId The user ID to generate the token for
     * @return string
     */
    public function mfaTokenGenerate($userId)
    {
        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $oDate          = Factory::factory('DateTime');
        $salt           = $oPasswordModel->salt();
        $ip             = $this->input->ipAddress();
        $created        = $oDate->format('Y-m-d H:i:s');
        $expires        = $oDate->add(new \DateInterval('PT10M'))->format('Y-m-d H:i:s');

        $token          = array();
        $token['token'] = sha1(sha1(APP_PRIVATE_KEY . $userId . $created . $expires . $ip) . $salt);
        $token['salt']  = md5($salt);

        //  Add this to the DB
        $this->db->set('user_id', $userId);
        $this->db->set('token', $token['token']);
        $this->db->set('salt', $token['salt']);
        $this->db->set('created', $created);
        $this->db->set('expires', $expires);
        $this->db->set('ip', $ip);

        if ($this->db->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_token')) {

            $token['id'] = $this->db->insert_id();
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
     * @param  int    $userId The ID of the user the token belongs to
     * @param  string $salt   The token's salt
     * @param  string $token  The token's hash
     * @param  string $ip     The user's IP address
     * @return boolean
     */
    public function mfaTokenValidate($userId, $salt, $token, $ip)
    {
        $this->db->where('user_id', $userId);
        $this->db->where('salt', $salt);
        $this->db->where('token', $token);

        $token  = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_token')->row();
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
     * @param  int     $tokenId The token's ID
     * @return boolean
     */
    public function mfaTokenDelete($tokenId)
    {
        $this->db->where('id', $tokenId);
        $this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_token');
        return (bool) $this->db->affected_rows();
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches a random MFA question for a user
     * @param  int      $userId The user's ID
     * @return stdClass
     */
    public function mfaQuestionGet($userId)
    {
        $this->db->where('user_id', $userId);
        $this->db->order_by('last_requested', 'DESC');
        $questions = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->result();

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
        $out->question = $this->encrypt->decode($out->question, APP_PRIVATE_KEY . $out->salt);

        //  Update the last requested details
        $this->db->set('last_requested', 'NOW()', false);
        $this->db->set('last_requested_ip', $this->input->ipAddress());
        $this->db->where('id', $out->id);
        $this->db->update(NAILS_DB_PREFIX . 'user_auth_two_factor_question');

        return $out;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates the answer to an MFA Question
     * @param  int    $questionId The question's ID
     * @param  int    $userId     The user's ID
     * @param  string $answer     The user's answer
     * @return boolean
     */
    public function mfaQuestionValidate($questionId, $userId, $answer)
    {
        $this->db->select('answer, salt');
        $this->db->where('id', $questionId);
        $this->db->where('user_id', $userId);
        $question = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_question')->row();

        if (!$question) {

            return false;
        }

        $hash = sha1(sha1(strtolower($answer)) . APP_PRIVATE_KEY . $question->salt);

        return $hash === $question->answer;
    }

    // --------------------------------------------------------------------------

    /**
     * Sets MFA questions for a user
     * @param  int     $userId   The user's ID
     * @param  array   $data     An array of question and answers
     * @param  boolean $clearOld Whether or not to clear old questions
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
        $this->db->trans_begin();

        //  Delete old questions?
        if ($clearOld) {

            $this->db->where('user_id', $userId);
            $this->db->delete(NAILS_DB_PREFIX . 'user_auth_two_factor_question');
        }

        $oPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $questionData   = array();
        $counter        = 0;
        $oDate          = Factory::factory('DateTime');
        $sDateTime      = $oDate->format('Y-m-d H:i:s');

        foreach ($data as $d) {

            $questionData[$counter]             = array();
            $questionData[$counter]['user_id']  = $userId;
            $questionData[$counter]['salt']     = $oPasswordModel->salt();
            $questionData[$counter]['question'] = $this->encrypt->encode($d->question, APP_PRIVATE_KEY . $questionData[$counter]['salt']);
            $questionData[$counter]['answer']   = sha1(sha1(strtolower($d->answer)) . APP_PRIVATE_KEY . $questionData[$counter]['salt']);
            $questionData[$counter]['created']  = $sDateTime;

            $counter++;
        }

        if ($questionData) {

            $this->db->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_question', $questionData);

            if ($this->db->trans_status() !== false) {

                $this->db->trans_commit();
                return true;

            } else {

                $this->db->trans_rollback();
                return false;
            }

        } else {

            $this->db->trans_rollback();
            $this->setError('No data to save.');
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Gets the user's MFA device if there is one
     * @param  int   $userId The user's ID
     * @return mixed         stdClass on success, false on failure
     */
    public function mfaDeviceSecretGet($userId)
    {
        $this->db->where('user_id', $userId);
        $this->db->limit(1);
        $result = $this->db->get(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')->result();

        if (empty($result)) {

            return false;
        }

        $return = $result[0];
        $return->secret = $this->encrypt->decode($return->secret, APP_PRIVATE_KEY);

        return $result[0];
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a MFA Device Secret
     * @param  int    $userId         The user ID tog enerate for
     * @param  string $existingSecret The existing secret to use instead of generating a new one
     * @return array
     */
    public function mfaDeviceSecretGenerate($userId, $existingSecret = null)
    {
        //  Get an identifier for the user
        $user = $this->user_model->getById($userId);

        if (!$user) {

            $this->setError('User does not exist.');
            return false;
        }

        $g = new \Google\Authenticator\GoogleAuthenticator();

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
        $username = empty($username) ? preg_replace('/[^a-z]/', strtolower($user->first_name . $user->last_name)) : $username;
        $username = empty($username) ? preg_replace('/[^a-z]/', strtolower($user->email)) : $username;

        return array(
            'secret' => $secret,
            'url'    => $g->getURL($username, $hostname, $secret)
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Validates a secret against two given codes, if valid adds as a device for
     * the user
     * @param  int     $userId The user's ID
     * @param  string  $secret The secret being used
     * @param  int     $code1  The first code to be generate
     * @param  int     $code2  The second code to be generated
     * @return boolean
     */
    public function mfaDeviceSecretValidate($userId, $secret, $code1, $code2)
    {
        //  Tidy up codes so that they only contain digits
        $code1 = preg_replace('/[^\d]/', '', $code1);
        $code2 = preg_replace('/[^\d]/', '', $code2);

        //  New instance of the authenticator
        $g = new \Google\Authenticator\GoogleAuthenticator();

        //  Check the codes
        $checkCode1 = $g->checkCode($secret, $code1);
        $checkCode2 = $g->checkCode($secret, $code2);

        if ($checkCode1 && $checkCode2) {

            /**
             * Both codes are valid, which means they are sequential and recent.
             * We must now save the secret against the user and record those codes
             * so they can't be used again.
             */

            $this->db->set('user_id', $userId);
            $this->db->set('secret', $this->encrypt->encode($secret, APP_PRIVATE_KEY));
            $this->db->set('created', 'NOW()', false);

            if ($this->db->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_secret')) {

                $secret_id = $this->db->insert_id();
                $oDate     = Factory::factory('DateTime');

                $data   = array();
                $data[] = array(
                    'secret_id' => $secret_id,
                    'code'      => $code1,
                    'used'      => $oDate->format('Y-m-d H:i:s')
                );
                $data[] = array(
                    'secret_id' => $secret_id,
                    'code'      => $code2,
                    'used'      => $oDate->format('Y-m-d H:i:s')
                );

                $this->db->insert_batch(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code', $data);

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
     * @param  int     $userId The user's ID
     * @param  string  $code   The code to validate
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
        $this->db->where('secret_id', $secret->id);
        $this->db->where('code', $code);

        if ($this->db->count_all_results(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code')) {

            $this->setError('Code has already been used.');
            return false;
        }

        //  Tidy up codes so that they only contain digits
        $code = preg_replace('/[^\d]/', '', $code);

        //  New instance of the authenticator
        $g = new \Google\Authenticator\GoogleAuthenticator();

        $checkCode = $g->checkCode($secret->secret, $code);

        if ($checkCode) {

            //  Log the code so it can't be used again
            $this->db->set('secret_id', $secret->id);
            $this->db->set('code', $code);
            $this->db->set('used', 'NOW()', false);

            $this->db->insert(NAILS_DB_PREFIX . 'user_auth_two_factor_device_code');

            return true;

        } else {

            return false;
        }
    }
}
