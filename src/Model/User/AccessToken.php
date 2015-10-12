<?php

/**
 * This model contains all methods for interacting with user groups.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model\User;

class AccessToken extends \Nails\Common\Model\Base
{
    protected $defaultExpiration;
    protected $tokenTemplate;
    protected $tokenCharacters;

    // --------------------------------------------------------------------------

    /**
     * Construct the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->table       = NAILS_DB_PREFIX . 'user_auth_access_token';
        $this->tablePrefix = 'uaat';

        //  Load the config file and specify values
        $this->config->load('auth/auth');

        $this->defaultExpiration = $this->config->item('authAccessTokenDefaultExpiration');
        $this->tokenTemplate     = $this->config->item('authAccessTokenTemplate');
        $this->tokenCharacters   = $this->config->item('authAccessTokenCharacters');

        /**
         * Define an array of handlers for checking whether a specified user can
         * request a certain scope. This should be an array of callables.
         */

        $this->scopeHandler = array();
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new access token
     * @param  array $data The data to create the access token with
     * @return mixed false on failure, stdClass on success
     */
    public function create($data = array())
    {
        //  User ID is a required field
        if (empty($data['user_id'])) {

            $this->_set_error('A user ID must be supplied.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Scope is a required field
        if (empty($data['scope'])) {

            $this->_set_error('A scope must be supplied.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  If not specified, generate an expiration date 6 months from now
        if (!isset($data['expires']) && !empty($this->defaultExpiration)) {

            $expires = \Nails\Factory::factory('DateTime');
            $expires->add(new DateInterval($this->defaultExpiration));

            $data['expires'] = $expires->format('Y-m-d H:i:s');
        }

        // --------------------------------------------------------------------------

        /**
         * For each scope requested, test the user is permitted to request it.
         */
        $data['scope'] = explode(',', $data['scope']);
        $data['scope'] = array_map('trim', $data['scope']);
        $data['scope'] = array_unique($data['scope']);

        asort($data['scope']);

        foreach ($data['scope'] as $sScope) {


            if (empty($this->scopeHandler[$sScope])) {

                $this->_set_error('"' . $sScope . '" is not a valid token scope.');
                return false;

            } else {

                $sScopeHandler = 'scopeHandler' . $this->scopeHandler[$sScope];

                if (!is_callable(array($this, $sScopeHandler))) {

                    $this->_set_error('"' . $this->scopeHandler[$sScope] . '" is not a valid token scope callback.');
                    return false;

                } elseif (!$this->{$sScopeHandler}($data['user_id'], $sScope)) {

                    $this->_set_error('No permission to request a token with scope "' . $sScope . '".');
                    return false;
                }
            }
        }

        $data['scope'] = implode(',', $data['scope']);

        // --------------------------------------------------------------------------

        //  Generate a new token
        do {

            $token = preg_replace_callback(
                '/[X]/',
                function ($matches) {

                    $start = rand(0, strlen($this->tokenCharacters)-1);
                    return substr($this->tokenCharacters, $start, 1);
                },
                $this->tokenTemplate
            );

            $data['token'] = hash('sha256', $token . APP_PRIVATE_KEY);

            $this->db->where('token', $data['token']);

        } while ($this->db->count_all_results($this->table));

        // --------------------------------------------------------------------------

        if (parent::create($data)) {

            $out          = new \stdClass();
            $out->token   = $token;
            $out->expires = $data['expires'];

            return $out;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Revoke an access token for a user
     * @param  integer $iUserId The ID of the suer the token belongs to
     * @param  mixed   $mToken  The token object, or a token ID
     * @return boolean
     */
    public function revoke($iUserId, $mToken)
    {
        if (is_string($mToken)) {

            $oToken = $this->getByToken($mToken);

        } else {

            $oToken = $mToken;
        }

        if ($oToken) {

            if ($oToken->user_id === $iUserId) {

                return $this->delete($oToken->id);

            } else {

                $this->_set_error('Not authorised to revoke that token.');
                return false;
            }

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Prevent update of access tokens
     * @param  int   $id   The accessToken's ID
     * @param  array $data Data to update the access token with
     * @return boolean
     */
    public function update($id, $data)
    {
        $this->_set_error('Access tokens cannot be amended once created.');
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a token by it's token
     * @param  string $token The token to return
     * @param  array  $data  Data to pass to get_all()
     * @return mixed         false on failure, stdClass on success
     */
    public function getByToken($token, $data = array())
    {
        if (empty($data['where'])) {

            $data['where'] = array();
        }

        $data['where'][] = array($this->tablePrefix . '.token', hash('sha256', $token . APP_PRIVATE_KEY));

        $token = $this->get_all(null, null, $data);

        if ($token) {

            return $token[0];

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a token by it's token, but only if valid (i.e not expired)
     * @param  string $token The token to return
     * @return mixed         false on failure, stdClass on success
     */
    public function getByValidToken($token)
    {
        $data = array(
            'where' => array(
                '(' . $this->tablePrefix . '.expires IS NULL OR ' . $this->tablePrefix . '.expires > NOW())'
            )
        );

        return $this->getByToken($token, $data);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a token has a given scope
     * @param  mixed   $mToken   The token object, or a token ID
     * @param  string  $sScope   The scope(s) to check for
     * @return boolean
     */
    public function hasScope($mToken, $sScope)
    {
        if (is_numeric($mToken)) {

            $oToken = $this->get_by_id($mToken);

        } else {

            $oToken = $mToken;
        }

        if ($oToken) {

            return in_array($sScope, $oToken->scope);

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats a single object
     *
     * The get_all() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to typecast ID's and/or organise data into objects.
     *
     * @param  object $obj  A reference to the object being formatted.
     * @param  array  $data The same data array which is passed to _getcount_common, for reference if needed
     * @return void
     */
    protected function _format_object(&$obj, $data = array(), $integers = array(), $bools = array(), $floats = array())
    {
        parent::_format_object($obj, $data, $integers, $bools, $floats);
        $obj->scope = explode(',', $obj->scope);
    }
}
