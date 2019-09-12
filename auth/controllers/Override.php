<?php

/**
 * This class allows users to "login as" other users (where permission allows)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Constants;
use Nails\Factory;
use Nails\Auth\Controller\Base;

class Override extends Base
{
    /**
     * Override constructor.
     */
    public function __construct()
    {
        parent::__construct();

        //  If you're not a admin then you shouldn't be accessing this class
        if (!wasAdmin() && !isAdmin()) {
            $oSession = Factory::service('Session', Constants::MODULE_SLUG);
            $oSession->setFlashData('error', lang('auth_no_access'));
            redirect('/');
        }
    }


    // --------------------------------------------------------------------------

    /**
     * Log in as another user
     *
     * @return  void
     */
    public function login_as()
    {
        //  Perform lookup of user
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        $oUri       = Factory::service('Uri');

        $sHashId = $oUri->segment(4);
        $sHashPw = $oUri->segment(5);
        $oUser   = $oUserModel->getByHashes($sHashId, $sHashPw);

        if (!$oUser) {
            show404();
        }

        // --------------------------------------------------------------------------

        /**
         * Check sign-in permissions; ignore if recovering.
         * Users cannot:
         * - Sign in as themselves
         * - Sign in as superusers (unless they are a superuser)
         */

        $oSession = Factory::service('Session', Constants::MODULE_SLUG);

        if (!wasAdmin()) {

            $bHasPermission = userHasPermission('admin:auth:accounts:loginAs');
            $bIsCloning     = activeUser('id') == $oUser->id;
            $bIsSuperuser   = !isSuperuser() && isSuperuser($oUser) ? true : false;

            if (!$bHasPermission || $bIsCloning || $bIsSuperuser) {
                if (!$bHasPermission) {
                    $oSession->setFlashData('error', lang('auth_override_fail_nopermission'));
                    redirect('admin/dashboard');
                } elseif ($bIsCloning) {
                    show404();
                } elseif ($bIsSuperuser) {
                    show404();
                }
            }
        }

        // --------------------------------------------------------------------------

        $oInput = Factory::service('Input');
        if (!$oInput->get('returningAdmin') && isAdmin()) {

            /**
             * The current user is an admin, we should set our Admin Recovery Data so
             * that they can come back.
             */

            $oUserModel->setAdminRecoveryData($oUser->id, $oInput->get('return_to'));
            $sRedirectUrl = $oUser->group_homepage;

            //  A bit of feedback
            $sStatus  = 'success';
            $sMessage = lang('auth_override_ok', $oUser->first_name . ' ' . $oUser->last_name);

        } elseif (wasAdmin()) {

            /**
             * This user is a recovering adminaholic. Work out where we're sending
             * them back to then remove the adminRecovery data.
             */

            $oRecoveryData = getAdminRecoveryData();
            $sRedirectUrl  = !empty($oRecoveryData->returnTo) ? $oRecoveryData->returnTo : $oUser->group_homepage;

            unsetAdminRecoveryData();

            //  Some feedback
            $sStatus  = 'success';
            $sMessage = lang('auth_override_return', $oUser->first_name . ' ' . $oUser->last_name);

        } else {

            /**
             * This user is simply logging in as someone else and has passed the hash
             * verification.
             */

            $sRedirectUrl = $oUser->group_homepage;

            //  Some feedback
            $sStatus  = 'success';
            $sMessage = lang('auth_override_ok', $oUser->first_name . ' ' . $oUser->last_name);
        }

        // --------------------------------------------------------------------------

        //  Replace current user's session data
        $oUserModel->setLoginData($oUser->id);

        // --------------------------------------------------------------------------

        //  Any feedback?
        if (!empty($sMessage)) {
            $oSession->setFlashData($sStatus, $sMessage);
        }

        // --------------------------------------------------------------------------

        redirect($sRedirectUrl);
    }
}
