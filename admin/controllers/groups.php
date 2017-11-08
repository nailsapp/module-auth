<?php

/**
 * This class provides group management functionality to Admin
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

class Groups extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:auth:groups:manage')) {
            $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
            $oNavGroup->setLabel('Members');
            $oNavGroup->setIcon('fa-users');
            $oNavGroup->addAction('Manage User Groups');
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
        $aPermissions               = parent::permissions();
        $aPermissions['manage']     = 'Can manage user groups';
        $aPermissions['create']     = 'Can create user groups';
        $aPermissions['edit']       = 'Can edit user groups';
        $aPermissions['delete']     = 'Can delete user groups';
        $aPermissions['setDefault'] = 'Can set the default user groups';
        return $aPermissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();
        $this->lang->load('admin_groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse user groups
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:auth:groups:manage')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Manage User Groups';

        // --------------------------------------------------------------------------

        $oUserGroupModel      = Factory::model('UserGroup', 'nailsapp/module-auth');
        $this->data['groups'] = $oUserGroupModel->getAll();

        // --------------------------------------------------------------------------

        if (userHasPermission('admin:auth:groups:create')) {
            Helper::addHeaderButton('admin/auth/groups/create', 'Create Group');
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Create a user group
     * @return void
     */
    public function create()
    {
        if (!userHasPermission('admin:auth:groups:create')) {
            show_404();
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata(
            'message',
            '<strong>Coming soon!</strong> The ability to dynamically create groups is on the roadmap.'
        );
        redirect('admin/auth/groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit a user group
     * @return void
     */
    public function edit()
    {
        if (!userHasPermission('admin:auth:groups:edit')) {
            show_404();
        }

        // --------------------------------------------------------------------------

        $oUri               = Factory::service('Uri');
        $oInput             = Factory::service('Input');
        $oUserGroupModel    = Factory::model('UserGroup', 'nailsapp/module-auth');
        $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $sGroupId           = $oUri->segment(5, null);

        $this->data['group'] = $oUserGroupModel->getById($sGroupId);

        if (!$this->data['group']) {
            show_404();
        }

        // --------------------------------------------------------------------------

        if ($oInput->post()) {

            //  Load library
            $oFormValidation = Factory::service('FormValidation');

            //  Define rules
            $oFormValidation->set_rules('slug', '', 'xss_clean|unique_if_diff[' . NAILS_DB_PREFIX . 'user_group.slug.' . $this->data['group']->slug . ']');
            $oFormValidation->set_rules('label', '', 'xss_clean|required');
            $oFormValidation->set_rules('description', '', 'xss_clean|required');
            $oFormValidation->set_rules('default_homepage', '', 'xss_clean|required');
            $oFormValidation->set_rules('registration_redirect', '', 'xss_clean');

            //  Set messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('required', lang('fv_unique_if_diff'));

            if ($oFormValidation->run()) {

                $data                          = [];
                $data['slug']                  = $oInput->post('slug');
                $data['label']                 = $oInput->post('label');
                $data['description']           = $oInput->post('description');
                $data['default_homepage']      = $oInput->post('default_homepage');
                $data['registration_redirect'] = $oInput->post('registration_redirect');

                //  Parse ACL's and password rules
                $data['acl']            = $oUserGroupModel->processPermissions($oInput->post('acl'));
                $data['password_rules'] = $oUserPasswordModel->processRules($oInput->post('pw'));

                if ($oUserGroupModel->update($sGroupId, $data)) {

                    $oSession = Factory::service('Session', 'nailsapp/module-auth');
                    $oSession->set_flashdata('success', 'Group updated successfully!');
                    redirect('admin/auth/groups');

                } else {
                    $this->data['error'] = 'I was unable to update the group. ' . $oUserGroupModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        //  Get all available permissions
        $this->data['permissions'] = [];
        foreach ($this->data['adminControllers'] as $module => $oModuleDetails) {
            foreach ($oModuleDetails->controllers as $sController => $aControllerDetails) {

                $temp              = new \stdClass();
                $temp->label       = ucfirst($module) . ': ' . ucfirst($sController);
                $temp->slug        = $module . ':' . $sController;
                $temp->permissions = $aControllerDetails['className']::permissions();

                if (!empty($temp->permissions)) {
                    $this->data['permissions'][] = $temp;
                }
            }
        }

        array_sort_multi($this->data['permissions'], 'label');
        $this->data['permissions'] = array_values($this->data['permissions']);

        // --------------------------------------------------------------------------

        //  Page title
        $this->data['page']->title = lang('accounts_groups_edit_title', $this->data['group']->label);

        // --------------------------------------------------------------------------

        //  Assets
        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.groups.min.js', 'nailsapp/module-auth');
        $oAsset->inline('var _edit = new NAILS_Admin_Auth_Groups_Edit();', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('edit');
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user group
     * @return void
     */
    public function delete()
    {
        if (!userHasPermission('admin:auth:groups:delete')) {
            show_404();
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata(
            'message',
            '<strong>Coming soon!</strong> The ability to delete groups is on the roadmap.'
        );
        redirect('admin/auth/groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the default user group
     * @return void
     */
    public function set_default()
    {
        if (!userHasPermission('admin:auth:groups:setDefault')) {
            show_404();
        }

        $oUri            = Factory::service('Uri');
        $oUserGroupModel = Factory::model('UserGroup', 'nailsapp/module-auth');
        $oSession        = Factory::service('Session', 'nailsapp/module-auth');

        if ($oUserGroupModel->setAsDefault($oUri->segment(5))) {
            $oSession->set_flashdata(
                'success',
                'Group set as default successfully.'
            );
        } else {
            $oSession->set_flashdata(
                'error',
                'I could not set that group as the default user group. ' . $oUserGroupModel->lastError()
            );
        }

        redirect('admin/auth/groups');
    }
}
