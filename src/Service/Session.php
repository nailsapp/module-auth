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

namespace Nails\Auth\Service;

use Nails\Factory;

class Session
{
    /**
     * The session object
     * @var \CI_Session
     */
    protected $oSession;

    // --------------------------------------------------------------------------

    /**
     * Attempts to restore or set up a session
     *
     * @param bool $bForceSetup Whether to force session set up
     */
    protected function setup($bForceSetup = false)
    {
        if (!empty($this->oSession)) {
            return;
        }

        $oInput = Factory::service('Input');
        if (!$oInput::isCli()) {
            /**
             * Look for the session cookie, if it exists, then a session exists
             * and the whole service should be loaded up.
             */
            $oConfig     = Factory::service('Config');
            $sCookieName = $oConfig->item('sess_cookie_name');

            if ($bForceSetup || $oInput::cookie($sCookieName)) {
                //  @todo (Pablo - 2018-03-19) - Remove dependency on CI Sessions
                $oCi = get_instance();
                $oCi->load->library('session');
                $this->oSession = $oCi->session;
            }
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets session flashdata
     *
     * @param mixed $mKey   The key to set, or an associative array of key=>value pairs
     * @param mixed $mValue The value to store
     *
     * @return $this
     */
    public function setFlashData($mKey, $mValue = null)
    {
        $this->setup(true);
        if (empty($this->oSession)) {
            return $this;
        }
        $this->oSession->set_flashdata($mKey, $mValue);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieves flash data from the session
     *
     * @param string $sKey The key to retrieve
     *
     * @return mixed
     */
    public function getFlashData($sKey = null)
    {
        $this->setup();
        if (empty($this->oSession)) {
            return null;
        }
        return $this->oSession->flashdata($sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Keeps existing flashdata available to next request.
     * http://codeigniter.com/forums/viewthread/104392/#917834
     *
     * @param  string|array $mKey The key to keep, null will retain all flashdata
     *
     * @return $this
     **/
    public function keepFlashData($mKey = null)
    {
        if (empty($this->oSession)) {
            return $this;
        }

        /**
         * 'old' flashdata gets removed.  Here we mark all flashdata as 'new' to preserve
         * it from _flashdata_sweep(). Note the function will NOT return FALSE if the $mKey
         * provided cannot be found, it will retain ALL flashdata
         */

        if (is_null($mKey)) {

            foreach ($this->oSession->userdata as $k => $v) {

                $sOldFlashDataKey = $this->oSession->flashdata_key . ':old:';

                if (strpos($k, $sOldFlashDataKey) !== false) {
                    $sNewFlashDataKey = $this->oSession->flashdata_key . ':new:';
                    $sNewFlashDataKey = str_replace($sOldFlashDataKey, $sNewFlashDataKey, $k);
                    $this->oSession->set_userdata($sNewFlashDataKey, $v);
                }
            }

            return $this;

        } elseif (is_array($mKey)) {
            foreach ($mKey as $k) {
                $this->keepFlashData($k);
            }
        }

        // --------------------------------------------------------------------------

        $sOldFlashDataKey = $this->oSession->flashdata_key . ':old:' . $mKey;
        $value            = $this->oSession->userdata($sOldFlashDataKey);

        // --------------------------------------------------------------------------

        $sNewFlashDataKey = $this->oSession->flashdata_key . ':new:' . $mKey;
        $this->oSession->set_userdata($sNewFlashDataKey, $value);

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Writes data to the user's session
     *
     * @param mixed $mKey   The key to set, or an associative array of key=>value pairs
     * @param mixed $mValue The value to store
     *
     * @return $this
     */
    public function setUserData($mKey, $mValue = null)
    {
        $this->setup(true);
        if (empty($this->oSession)) {
            return $this;
        }

        $this->oSession->set_userdata($mKey, $mValue);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieves data from the session
     *
     * @param string $sKey The key to retrieve
     *
     * @return mixed
     */
    public function getUserData($sKey = null)
    {
        $this->setup();
        if (empty($this->oSession)) {
            return null;
        }
        return $this->oSession->userdata($sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Removes data from the session
     *
     * @param mixed $mKey The key to set, or an associative array of key=>value pairs
     *
     * @return $this
     */
    public function unsetUserData($mKey)
    {
        if (empty($this->oSession)) {
            return $this;
        }

        $this->oSession->unset_userdata($mKey);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Destroy the user's session
     * @return $this
     */
    public function destroy()
    {
        if (empty($this->oSession)) {
            return $this;
        }

        $oConfig     = Factory::service('Config');
        $sCookieName = $oConfig->item('sess_cookie_name');

        $this->oSession->sess_destroy();

        if (isset($_COOKIE) && array_key_exists($sCookieName, $_COOKIE)) {
            delete_cookie($sCookieName);
            unset($_COOKIE[$sCookieName]);
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Regenerate the user's session
     *
     * @param bool $bDestroy Whether to destroy the old session
     */
    public function regenerate($bDestroy = false)
    {
        if (empty($this->oSession)) {
            return $this;
        }

        $this->oSession->sess_regenerate($bDestroy);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * ALIASES; maintained for backwards compatability with old code and CodeIgniter
     */

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::setFlashData
     * @see Session::setFlashData()
     * @deprecated
     */
    public function set_flashdata($mKey, $mValue)
    {
        return $this->setFlashData($mKey, $mValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::getFlashData
     * @see Session::getFlashData()
     * @deprecated
     */
    public function flashdata($sKey)
    {
        return $this->getFlashData($sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::getFlashData
     * @see Session::getFlashData()
     * @deprecated
     */
    public function get_flashdata($sKey)
    {
        return $this->getFlashData($sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::keepFlashData()
     * @see Session::keepFlashData()
     * @deprecated
     */
    public function keep_flashdata($mKey = null)
    {
        $this->keepFlashData($mKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::setUserData
     * @see Session::setUserData()
     * @deprecated
     */
    public function set_userdata($mKey, $mValue = null)
    {
        return $this->setUserData($mKey, $mValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::getUserData
     * @see Session::getUserData()
     * @deprecated
     */
    public function userdata($sKey)
    {
        return $this->getUserData($sKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::unsetUserData
     * @see Session::unsetUserData()
     * @deprecated
     */
    public function unset_userdata($mKey)
    {
        return $this->unsetUserData($mKey);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::destroy
     * @see Session::destroy()
     * @deprecated
     */
    public function sess_destroy()
    {
        return $this->destroy();
    }

    // --------------------------------------------------------------------------

    /**
     * Alias of Session::regenerate
     * @see Session::regenerate()
     * @deprecated
     */
    public function sess_regenerate($bDestroy = false)
    {
        return $this->destroy($bDestroy);
    }
}
