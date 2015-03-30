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

        //  If not specified, generate an expiration date 6 months from now
        if (!isset($data['expires'])) {

            $expires = new DateTime();
            $expires->add(new DateInterval('P6M'));

            $data['expires'] = $expires->format('Y-m-d H:i:s');
        }

        // --------------------------------------------------------------------------

        /**
         * If a scope has been requested then check that the user has permission to
         * request such a scope.
         */

        if (!empty($data['scope'])) {

            /**
             * @todo Integrate these checks
             */
        }

        // --------------------------------------------------------------------------

        /**
         * No particular reason for this template, other than to ensure a certain
         * length and to look relatively tidy
         */

        $tokenTemplate = 'XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX/XXX-XXXX-XXX';
        $tokenChars    = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        //  Generate a new token
        do {

            $token = preg_replace_callback(
                '/[X]/',
                function ($matches) use ($tokenChars) {

                    $start = rand(0, strlen($tokenChars)-1);
                    return substr($tokenChars, $start, 1);
                },
                $tokenTemplate
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
     * Formats a single object
     *
     * The get_all() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to typecast ID's and/or organise data into objects.
     *
     * @param  object $obj  A reference to the object being formatted.
     * @param  array  $data The same data array which is passed to _getcount_common, for reference if needed
     * @return void
     */
    protected function _format_object(&$obj, $data = array())
    {
        parent::_format_object($obj, $data);
        $obj->scope = explode(',', $obj->scope);
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
