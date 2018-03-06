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

use Nails\Factory;
use Nails\Admin\Helper;
use Nails\Auth\Controller\BaseAdmin;

class Merge extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:auth:merge:users')) {

            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup->setLabel('Members');
            $oNavGroup->setIcon('fa-users');
            $oNavGroup->addAction('Merge Users');
            return $oNavGroup;
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

                $oUserModel  = Factory::model('User', 'nailsapp/module-auth');
                $mergeResult = $oUserModel->merge($userId, $mergeIds, $preview);

                if ($mergeResult) {

                    if ($preview) {

                        $this->data['mergeResult'] = $mergeResult;
                        Helper::loadView('preview');
                        return;

                    } else {

                        $status   = 'success';
                        $message  = 'Users were merged successfully.';
                        $oSession = Factory::service('Session', 'nailsapp/module-auth');
                        $oSession->set_flashdata($status, $message);
                        redirect('admin/auth/merge');
                    }

                } else {
                    $this->data['error'] = 'Failed to merge users. ' . $oUserModel->lastError();
                }

            } else {
                $this->data['error'] = 'You cannot list yourself as a user to merge.';
            }
        }

        // --------------------------------------------------------------------------

        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.accounts.merge.min.js', 'nailsapp/module-auth');
        $oAsset->inline('var _accountsMerge = new NAILS_Admin_Accounts_Merge()', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('index');
    }
}
