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

use Nails\Admin\Controller\DefaultController;
use Nails\Factory;

class Groups extends DefaultController
{
    const CONFIG_MODEL_NAME     = 'UserGroup';
    const CONFIG_MODEL_PROVIDER = 'nailsapp/module-auth';
    const CONFIG_SIDEBAR_GROUP  = 'Members';
    const CONFIG_SORT_OPTIONS   = [
        'id'       => 'ID',
        'label'    => 'Label',
        'created'  => 'Created',
        'modified' => 'Modified',
    ];

    // --------------------------------------------------------------------------

    /**
     * Load data for the edit/create view
     *
     * @param  \stdClass $oItem The main item object
     *
     * @return void
     */
    protected function loadEditViewData($oItem = null)
    {
        parent::loadEditViewData($oItem);

        //  Assets
        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.groups.min.js', 'nailsapp/module-auth');
        $oAsset->inline('var _edit = new NAILS_Admin_Auth_Groups_Edit();', 'JS');

        //  Get all available permissions
        $this->data['aPermissions'] = [];
        foreach ($this->data['adminControllers'] as $module => $oModuleDetails) {
            foreach ($oModuleDetails->controllers as $sController => $aControllerDetails) {

                $temp              = new \stdClass();
                $temp->label       = ucfirst($module) . ': ' . ucfirst($sController);
                $temp->slug        = $module . ':' . $sController;
                $temp->permissions = $aControllerDetails['className']::permissions();

                if (!empty($temp->permissions)) {
                    $this->data['aPermissions'][] = $temp;
                }
            }
        }

        array_sort_multi($this->data['aPermissions'], 'label');
        $this->data['aPermissions'] = array_values($this->data['aPermissions']);
    }

    // --------------------------------------------------------------------------

    /**
     * Form validation for edit/create
     *
     * @param array $aOverrides Any overrides for the fields; best to do this in the model's describeFields() method
     *
     * @return mixed
     */
    protected function runFormValidation($aOverrides = [])
    {
        parent::runFormValidation([
            'slug'                  => [
                'xss_clean',
                'required',
                'unique_if_diff[' . NAILS_DB_PREFIX . 'user_group.slug.' . $this->data['item']->slug . ']'
            ],
            'label'                 => ['xss_clean', 'required'],
            'description'           => ['xss_clean', 'required'],
            'default_homepage'      => ['xss_clean'],
            'registration_redirect' => ['xss_clean'],
        ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Extract data from post variable
     * @return array
     */
    protected function getPostObject()
    {
        $oInput             = Factory::service('Input');
        $oUserGroupModel    = Factory::model('UserGroup', 'nailsapp/module-auth');
        $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');

        return [
            'slug'                  => $oInput->post('slug'),
            'label'                 => $oInput->post('label'),
            'description'           => $oInput->post('description'),
            'default_homepage'      => $oInput->post('default_homepage'),
            'registration_redirect' => $oInput->post('registration_redirect'),
            'acl'                   => $oUserGroupModel->processPermissions($oInput->post('acl')),
            'password_rules'        => $oUserPasswordModel->processRules($oInput->post('pw')),
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Delete an item
     * @return void
     */
    public function delete()
    {
        $oUri       = Factory::service('Uri');
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oItemModel = Factory::model(
            $this->aConfig['MODEL_NAME'],
            $this->aConfig['MODEL_PROVIDER']
        );
        $iItemId    = (int) $oUri->segment(5);
        $oItem      = $oItemModel->getById($iItemId);

        if (empty($oItem)) {
            show_404();
        } elseif ($oItem->id === activeUser('group_id')) {
            $oSession->set_flashdata('error', 'You cannot delete your own user group.');
            redirect('admin/auth/groups');
        } elseif (!isSuperuser() && groupHasPermission('admin:superuser', $oItem)) {
            $oSession->set_flashdata('error', 'You cannot delete a group which has super user permissions.');
            redirect('admin/auth/groups');
        } elseif ($oItem->id === $oItemModel->getDefaultGroupId()) {
            $oSession->set_flashdata('error', 'You cannot delete the default user group.');
            redirect('admin/auth/groups');
        } else {
            parent::delete();
        }
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
                'Failed to set default user group. ' . $oUserGroupModel->lastError()
            );
        }

        redirect('admin/auth/groups');
    }
}
