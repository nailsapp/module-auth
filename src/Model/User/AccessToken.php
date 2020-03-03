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

use DateInterval;
use Nails\Common\Model\Base;
use Nails\Config;
use Nails\Factory;

class AccessToken extends Base
{
    /**
     * Define how long the default expiration should be for access tokens; this should
     * be a value accepted by the DateInterval constructor.
     * @var string
     */
    const TOKEN_EXPIRE = 'P6M';

    /**
     * Token template, defines the structure of the token, in a way acceptable to the
     * generateToken() function
     * @var string
     */
    const TOKEN_MASK = 'AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA-AAAA/AAA-AAAA-AAA';

    /**
     * The characters which will make up the token; replace the X's in authAccessTokenTemplate.
     * @var string
     */
    const TOKEN_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    // --------------------------------------------------------------------------

    /**
     * Define a key => value array of scope handlers for checking whether a specified
     * user can request a certain scope. This should be an array of callables.
     *
     * @var array
     */
    protected $aScopeHandler = [];

    // --------------------------------------------------------------------------

    /**
     * Construct the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->table = Config::get('NAILS_DB_PREFIX') . 'user_auth_access_token';
    }

    // --------------------------------------------------------------------------

    /**
     * Creates a new access token
     *
     * @param  array  $aData         The data to create the access token with
     * @param boolean $bReturnObject Whether to return the object, or the ID
     *
     * @return mixed false on failure, stdClass on success
     */
    public function create(array $aData = [], $bReturnObject = false)
    {
        //  User ID is a required field
        if (empty($aData['user_id'])) {
            $this->setError('A user ID must be supplied.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  Scope is a required field
        if (!empty($this->aScopeHandler) && empty($aData['scope'])) {
            $this->setError('A scope must be supplied.');
            return false;
        }

        // --------------------------------------------------------------------------

        //  If not specified, generate an expiration date based on the defaults
        if (!isset($aData['expires']) && !empty(static::TOKEN_EXPIRE)) {
            $oNow = Factory::factory('DateTime');
            $oNow->add(new DateInterval(static::TOKEN_EXPIRE));
            $aData['expires'] = $oNow->format('Y-m-d H:i:s');
        }

        // --------------------------------------------------------------------------

        if (!empty($this->aScopeHandler)) {

            /**
             * For each scope requested, test the user is permitted to request it.
             */
            $aData['scope'] = explode(',', $aData['scope']);
            $aData['scope'] = array_map('trim', $aData['scope']);
            $aData['scope'] = array_unique($aData['scope']);

            asort($aData['scope']);

            foreach ($aData['scope'] as $sScope) {

                if (empty($this->aScopeHandler[$sScope])) {

                    $this->setError('"' . $sScope . '" is not a valid token scope.');
                    return false;

                } elseif (!s_callable($this->aScopeHandler[$sScope])) {

                    $this->setError('Handler for "' . $sScope . '" is not a valid token scope callback.');
                    return false;

                } elseif (!call_user_func($this->aScopeHandler[$sScope], $aData['user_id'], $sScope)) {

                    $this->setError('No permission to request a token with scope "' . $sScope . '".');
                    return false;
                }
            }

            $aData['scope'] = implode(',', $aData['scope']);
        }

        // --------------------------------------------------------------------------

        //  Generate a new token
        $oDb = Factory::service('Database');
        do {

            $sToken         = strtoupper(generateToken(static::TOKEN_MASK));
            $aData['token'] = hash('sha256', $sToken . APP_PRIVATE_KEY);
            $oDb->where('token', $aData['token']);

        } while ($oDb->count_all_results($this->table));

        // --------------------------------------------------------------------------

        if (parent::create($aData)) {
            return (object) [
                'token'   => $sToken,
                'expires' => $aData['expires'],
            ];
        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Revoke an access token for a user
     *
     * @param  integer $iUserId The ID of the user the token belongs to
     * @param  mixed   $mToken  The token object, or a token ID
     *
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
                $this->setError('Not authorised to revoke that token.');
                return false;
            }

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Prevent update of access tokens
     *
     * @param  int   $iId   The accessToken's ID
     * @param  array $aData Data to update the access token with
     *
     * @return boolean
     */
    public function update($iId, array $aData = []): bool
    {
        $this->setError('Access tokens cannot be amended once created.');
        return false;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a token by it's token
     *
     * @param  string $sToken The token to return
     * @param  array  $aData  Data to pass to getAll()
     *
     * @return mixed         false on failure, stdClass on success
     */
    public function getByToken($sToken, array $aData = [])
    {
        return parent::getByToken(hash('sha256', $sToken . APP_PRIVATE_KEY), $aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Returns a token by it's token, but only if valid (i.e not expired)
     *
     * @param  string $sToken The token to return
     *
     * @return mixed         false on failure, stdClass on success
     */
    public function getByValidToken($sToken, array $aData = [])
    {
        if (!isset($aData['where'])) {
            $aData['where'] = [];
        }

        $aData['where'][] = '(expires IS NULL OR expires > NOW())';

        return $this->getByToken($sToken, $aData);
    }

    // --------------------------------------------------------------------------

    /**
     * Determines whether a token has a given scope
     *
     * @param  mixed  $mToken The token object, or a token ID
     * @param  string $sScope The scope(s) to check for
     *
     * @return boolean
     */
    public function hasScope($mToken, $sScope)
    {
        if (is_numeric($mToken)) {
            $oToken = $this->getById($mToken);
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
     * The getAll() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to cast integers and booleans and/or organise data into objects.
     *
     * @param  object $oObj      A reference to the object being formatted.
     * @param  array  $aData     The same data array which is passed to _getcount_common, for reference if needed
     * @param  array  $aIntegers Fields which should be cast as integers if numerical and not null
     * @param  array  $aBools    Fields which should be cast as booleans if not null
     * @param  array  $aFloats   Fields which should be cast as floats if not null
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
        $oObj->scope = explode(',', $oObj->scope);
    }
}
