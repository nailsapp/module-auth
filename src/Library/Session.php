<?php

/**
 * The class handles the user's session
 * @todo        remove dependency on CodeIgniter's Session library
 * @todo        properly handle CLI behaviour
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
     * Whether on the CLI or not
     * @var boolean
     */
    private $bIsCli;

    // --------------------------------------------------------------------------

    /**
     * The session object
     * @var \CI_Session
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

            $this->oSession = $oCi->load->library('session');
            $this->bIsCli   = false;

        } else {
            $this->bIsCli = true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Route calls to the CodeIgniter Session class
     *
     * @param  string $sMethod    The method being called
     * @param  array  $aArguments Any arguments being passed
     *
     * @return mixed
     */
    public function __call($sMethod, $aArguments)
    {
        if (!$this->bIsCli) {
            if (method_exists($this, $sMethod)) {
                return call_user_func_array([$this, $sMethod], $aArguments);
            } else {
                return call_user_func_array([$this->oSession, $sMethod], $aArguments);
            }
        } else {
            return null;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Pass any property "gets" to the CodeIgniter Session class
     *
     * @param  string $sProperty The property to get
     *
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
     *
     * @param  string $sProperty The property to set
     * @param  mixed  $mValue    The value to set
     *
     * @return void
     */
    public function __set($sProperty, $mValue)
    {
        if (!$this->bIsCli) {
            $this->oSession->{$sProperty} = $mValue;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Keeps existing flashdata available to next request.
     * http://codeigniter.com/forums/viewthread/104392/#917834
     *
     * @param  string|array $mKey The key to keep, null will retain all flashdata
     *
     * @return void
     **/
    public function keep_flashdata($mKey = null)
    {
        /**
         * 'old' flashdata gets removed.  Here we mark all flashdata as 'new' to preserve
         * it from _flashdata_sweep(). Note the function will NOT return FALSE if the $mKey
         * provided cannot be found, it will retain ALL flashdata
         */

        if (is_null($mKey)) {

            foreach ($this->oSession->userdata as $k => $v) {

                $sOldFlashdataKey = $this->oSession->flashdata_key . ':old:';

                if (strpos($k, $sOldFlashdataKey) !== false) {
                    $sNewFlashdataKey = $this->oSession->flashdata_key . ':new:';
                    $sNewFlashdataKey = str_replace($sOldFlashdataKey, $sNewFlashdataKey, $k);
                    $this->oSession->set_userdata($sNewFlashdataKey, $v);
                }
            }

            return;

        } elseif (is_array($mKey)) {
            foreach ($mKey as $k) {
                $this->keep_flashdata($k);
            }
        }

        // --------------------------------------------------------------------------

        $sOldFlashdataKey = $this->oSession->flashdata_key . ':old:' . $mKey;
        $value            = $this->oSession->userdata($sOldFlashdataKey);

        // --------------------------------------------------------------------------

        $sNewFlashdataKey = $this->oSession->flashdata_key . ':new:' . $mKey;
        $this->oSession->set_userdata($sNewFlashdataKey, $value);
    }
}
