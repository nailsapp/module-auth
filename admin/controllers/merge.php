<?php

/**
 * This class provides the ability to merge users
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Auth;

class Merge extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:auth:merge:users')) {

            $navGroup = new \Nails\Admin\Nav('Members', 'fa-users');
            $navGroup->addAction('Merge Users');
            return $navGroup;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @return array
     */
    public static function permissions()
    {
        $permissions = parent::permissions();

        // --------------------------------------------------------------------------

        //  Define some basic extra permissions
        $permissions['users'] = 'Can merge users';

        // --------------------------------------------------------------------------

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Merge users
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:auth:merge:users')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Merge Users';

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            $userId   = $this->input->post('userId');
            $mergeIds = explode(',', $this->input->post('mergeIds'));
            $preview  = !$this->input->post('doMerge') ? true : false;

            if (!in_array(activeUser('id'), $mergeIds)) {

                $mergeResult = $this->user_model->merge($userId, $mergeIds, $preview);

                if ($mergeResult) {

                    if ($preview) {

                        $this->data['mergeResult'] = $mergeResult;

                        \Nails\Admin\Helper::loadView('preview');
                        return;

                    } else {

                        $status  = 'success';
                        $message = 'Users were merged successfully.';
                        $this->session->set_flashdata($status, $message);
                        redirect('admin/auth/merge');
                    }

                } else {

                    $this->data['error'] = 'Failed to merge users. ' . $this->user_model->last_error();
                }

            } else {

                $this->data['error'] = 'You cannot list yourself as a user to merge.';
            }
        }

        // --------------------------------------------------------------------------

        $this->asset->load('nails.admin.accounts.merge.min.js', 'NAILS');
        $this->asset->inline('var _accountsMerge = new NAILS_Admin_Accounts_Merge()', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        \Nails\Admin\Helper::loadView('index');
    }
}
