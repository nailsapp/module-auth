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

class NAILS_User_access_token_model extends NAILS_Model
{
    /**
     * Construct the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->table       = NAILS_DB_PREFIX . 'user_auth_access_token';
        $this->tablePrefix = 'uaat';
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new access token
     * @param  array   $data         The data to create the access token with
     * @param  boolean $returnObject Whether to return just the new ID or the full access token
     * @return mixed
     */
    public function create($data = array(), $returnObject = false)
    {
        //§User Id is a required field
        if (empty($data['user_id'])) {

            $this->_set_error('A user ID must be supplied.');
            return false;
        }

        // --------------------------------------------------------------------------

        /**
         * No aprticular reason for this template, other than to ensure a certain
         * length and to look relatively tidy
         */

        $tokenTemplate = 'XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX/XXX-XXXX-XXX';
        $tokenChars    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        //  Generate a new token
        do {

            $data['token'] = preg_replace_callback(
                '/[X]/',
                function ($matches) use ($tokenChars) {

                    $start = rand(0, strlen($tokenChars)-1);
                    return substr($tokenChars, $start, 1);
                },
                $tokenTemplate
            );

            $this->db->where('token', $data['token']);

        } while ($this->db->count_all_results($this->table));

        // --------------------------------------------------------------------------

        return parent::create($data, $returnObject);
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
     * Prevent deletion of access tokens
     * @param  int $id The accessToken's ID
     * @return boolean
     */
    public function delete($id)
    {
        $this->_set_error('Access tokens cannot be deleted.');
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Prevent deletion of access tokens
     * @param  int $id The accessToken's ID
     * @return boolean
     */
    public function destroy($id)
    {
        $this->_set_error('Access tokens cannot be deleted.');
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a token by it's token
     * @param  string $token The token to return
     * @return mixed         false on failure, stdClass on success
     */
    public function getByToken($token)
    {
        $data = array(
            'where' => array(
                array(
                    $this->tablePrefix . '.token',
                    $token
                )
            )
        );

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
        $token = $this->getByToken($token);

        if ($token) {

            if (!is_null($token->expires)) {

                //  Expired?
                if (time() < strtotime($token->expires)) {

                    return $token;

                } else {

                    return false;
                }

            } else {

                //  No expirey date
                return $token;
            }
            dumpanddie($token);

        } else {

            return false;
        }
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' MODELS
 *
 * The following block of code makes it simple to extend one of the core
 * models. Some might argue it's a little hacky but it's a simple 'fix'
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

if (!defined('NAILS_ALLOW_EXTENSION_USER_ACCESS_TOKEN_MODEL')) {

    class User_access_token_model extends NAILS_User_access_token_model
    {
    }
}
