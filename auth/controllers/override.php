<?php

//  Include NAILS_Auth_Controller; executes common Auth functionality.
require_once '_auth.php';

/**
 * This class allows users to "login as" other users (where permission allows)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */
class NAILS_Override extends NAILS_Auth_Controller
{
    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  If you're not a admin then you shouldn't be accessing this class
        if (!$this->user_model->wasAdmin() && !$this->user_model->isAdmin()) {

            $this->session->set_flashdata('error', lang('auth_no_access'));
            redirect('/');
        }
    }


    // --------------------------------------------------------------------------


    /**
     * Log in as another user
     * @return  void
     */
    public function login_as()
    {
        //  Perform lookup of user
        $hashId = $this->uri->segment(4);
        $hashPw = $this->uri->segment(5);
        $user   = $this->user_model->get_by_hashes($hashId, $hashPw);

        if (!$user) {

            show_error(lang('auth_override_invalid'));
        }

        // --------------------------------------------------------------------------

        /**
         * Check sign-in permissions; ignore if recovering.
         * Users cannot:
         * - Sign in as themselves
         * - Sign in as superusers (unless they are a superuser)
         */

        if (!$this->user_model->wasAdmin()) {

            $hasPermission = userHasPermission('admin.accounts:0.can_login_as');
            $isCloning     = activeUser('id') == $user->id ? true : false;
            $isSuperuser   = !userHasPermission('superuser') && userHasPermission('superuser', $user) ? true : false;

            if (!$hasPermission || $isCloning || $isSuperuser) {

                if (!$hasPermission) {

                    $this->session->set_flashdata('error', lang('auth_override_fail_nopermission'));
                    redirect('admin/dashboard');

                } elseif ($isCloning) {

                    show_error(lang('auth_override_fail_cloning'));

                } elseif ($isSuperuser) {

                    show_error(lang('auth_override_fail_superuser'));
                }
            }
        }

        // --------------------------------------------------------------------------

        //  Prep recovery data
        $recoveryData                    = new stdClass();
        $recoveryData->id                = md5(activeUser('id'));
        $recoveryData->hash              = md5(activeUser('password'));
        $recoveryData->email             = activeUser('email');
        $recoveryData->name              = activeUser('first_name');
        $recoveryData->logged_in_as      = $user->id;
        $recoveryData->now_where_was_i   = $this->input->get('return_to');
        $recoveryData->back_to_admin_url = site_url('auth/override/login_as/' . $recoveryData->id . '/' . $recoveryData->hash);

        // --------------------------------------------------------------------------

        //  Replace current user's session data
        $this->user_model->setLoginData($user->id);

        // --------------------------------------------------------------------------

        //  Unset our admin recovery session data if we're recovering
        if ($this->user_model->wasAdmin()) {

            //  Where we sending the user back to? If not set go to the group homepage
            $redirect = $this->session->userdata('admin_recovery')->now_where_was_i;
            $redirect = $redirect ? $redirect : $user->group_homepage;

            /**
             * Are we logging back in as the original admin? If so, unset the admin recovery,
             * if not, leave it as it is so they can log back in in the future.
             */

            $originalAdmin = $this->session->userdata('admin_recovery');

            if ($originalAdmin->id === $user->id_md5) {

                $this->session->unset_userdata('admin_recovery');

            } else {

                /**
                 * We're logging in as someone else, update the recovery data
                 * to reflect the new user
                 */

                $recoveryData = $this->session->userdata('admin_recovery');
                $recoveryData->logged_in_as = $user->id;
                $this->session->set_userdata('admin_recovery', $recoveryData);
            }

            //  Welcome home!
            $this->session->set_flashdata('success', lang('auth_override_return', $user->first_name));

        } else {

            //  It worked, it actually worked!They said I was crazy but it actually worked!
            $this->session->set_flashdata('success', lang('auth_override_ok', title_case($user->first_name . ' ' . $user->last_name)));

            //  Prep redirect variable
            $redirect = $user->group_homepage;

            //  Set a session variable so we can come back as admin
            $this->session->set_userdata('admin_recovery', $recoveryData);
        }

        // --------------------------------------------------------------------------

        //  Redirect our user
        redirect($redirect);
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' AUTH MODULE
 *
 * The following block of code makes it simple to extend one of the core auth
 * controllers. Some might argue it's a little hacky but it's a simple 'fix'
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
 */

if (!defined('NAILS_ALLOW_EXTENSION')) {

    class Override extends NAILS_Override
    {
    }
}
