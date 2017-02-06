<?php

/**
 * The class handles the user's session
 * @todo remove dependency on CodeIgniter's Session library
 * @todo properly handle CLI behaviour
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Library;

class Session
{
    /**
     * Whether on the cLI or not
     * @var boolean
     */
    private $bIsCli;

    // --------------------------------------------------------------------------

    /**
     * The session object
     * @var \CI_DB_mysqli_driver
     */
    private $oSession;

    // --------------------------------------------------------------------------

    /**
     * Construct the class, set up the session
     */
    public function __construct()
    {
        if (!isCli()) {

            /**
             * STOP! Before we load the session library, we need to check if we're using
             * the database. If we are then check if `sess_table_name` is "nails_session".
             * If it is, and NAILS_DB_PREFIX != nails_ then replace 'nails_' with NAILS_DB_PREFIX
             */

            $oCi           = get_instance();
            $sSessionTable = $oCi->config->item('sess_table_name');

            if ($sSessionTable === 'nails_session' && NAILS_DB_PREFIX !== 'nails_') {

                $sSessionTable = str_replace('nails_', NAILS_DB_PREFIX, $sSessionTable);
                $oCi->config->set_item('sess_table_name', $sSessionTable);
            }

            $oCi->load->library('session/session');
            $this->oSession = $oCi->session;
            $this->bIsCli   = false;

        } else {

            $this->bIsCli = true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Route calls to the CodeIgniter Session class
     * @param  string $sMethod    The method being called
     * @param  array  $aArguments Any arguments being passed
     * @return mixed
     */
    public function __call($sMethod, $aArguments)
    {
        if (!$this->bIsCli) {

            return call_user_func_array(array($this->oSession, $sMethod), $aArguments);

        } else {

            return null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pass any property "gets" to the CodeIgniter Session class
     * @param  string $sProperty The property to get
     * @return mixed
     */
    public function __get($sProperty)
    {
        if (!$this->bIsCli) {

            return $this->oSession->{$sProperty};

        } else {

            return null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pass any property "sets" to the CodeIgniter Session class
     * @param  string $sProperty The property to set
     * @param  mixed  $mValue    The value to set
     * @return mixed
     */
    public function __set($sProperty, $mValue)
    {
        if (!$this->bIsCli) {
            $this->oSession->{$sProperty} = $mValue;
        }
    }
}
