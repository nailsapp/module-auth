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

use Nails\Admin\Helper;
use Nails\Auth\Controller\BaseAdmin;
use Nails\Factory;

class Merge extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:auth:merge:users')) {

            $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
            $oNavGroup->setLabel('Users');
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

                $oUserModel  = Factory::model('User', 'nails/module-auth');
                $mergeResult = $oUserModel->merge($userId, $mergeIds, $preview);

                if ($mergeResult) {

                    if ($preview) {

                        $this->data['mergeResult'] = $mergeResult;
                        Helper::loadView('preview');
                        return;

                    } else {

                        $status   = 'success';
                        $message  = 'Users were merged successfully.';
                        $oSession = Factory::service('Session', 'nails/module-auth');
                        $oSession->setFlashData($status, $message);
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

        //  Load views
        Helper::loadView('index');
    }
}
