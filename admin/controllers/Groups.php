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
    const CONFIG_MODEL_PROVIDER = 'nails/module-auth';
    const CONFIG_SIDEBAR_GROUP  = 'Users';
    const CONFIG_PERMISSION     = 'groups';
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
    protected function loadEditViewData($oItem = null): void
    {
        parent::loadEditViewData($oItem);

        //  Get all available permissions
        $this->data['aPermissions'] = [];
        foreach ($this->data['adminControllers'] as $module => $oModuleDetails) {
            foreach ($oModuleDetails->controllers as $sController => $aControllerDetails) {

                $oTemp              = new \stdClass();
                $oTemp->label       = ucfirst($module) . ': ' . ucfirst($sController);
                $oTemp->slug        = strtolower($module . ':' . $sController);
                $oTemp->permissions = $aControllerDetails['className']::permissions();

                $aKeys   = array_keys($oTemp->permissions);
                $aLabels = array_values($oTemp->permissions);

                array_walk($aKeys, function (&$sValue) {
                    $sValue = strtolower($sValue);
                });

                $oTemp->permissions = array_combine($aKeys, $aLabels);

                if (!empty($oTemp->permissions)) {
                    $this->data['aPermissions'][] = $oTemp;
                }
            }
        }

        arraySortMulti($this->data['aPermissions'], 'label');
        $this->data['aPermissions'] = array_values($this->data['aPermissions']);
    }

    // --------------------------------------------------------------------------

    /**
     * Form validation for edit/create
     *
     * @param array $aOverrides Any overrides for the fields; best to do this in the model's describeFields() method
     *
     * @throws ValidationException
     * @return void
     */
    protected function runFormValidation($aOverrides = []): void
    {
        parent::runFormValidation([
            'slug'        => [
                'required',
                'unique_if_diff[' . NAILS_DB_PREFIX . 'user_group.slug.' . $this->data['item']->slug . ']',
            ],
            'label'       => ['required'],
            'description' => ['required'],
        ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Extract data from post variable
     *
     * @return array
     */
    protected function getPostObject(): array
    {
        $oInput             = Factory::service('Input');
        $oUserGroupModel    = Factory::model('UserGroup', 'nails/module-auth');
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');

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
     *
     * @return void
     */
    public function delete(): void
    {
        $oUri       = Factory::service('Uri');
        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oItemModel = Factory::model(
            $this->aConfig['MODEL_NAME'],
            $this->aConfig['MODEL_PROVIDER']
        );
        $iItemId    = (int) $oUri->segment(5);
        $oItem      = $oItemModel->getById($iItemId);

        if (empty($oItem)) {
            show404();
        } elseif ($oItem->id === activeUser('group_id')) {
            $oSession->setFlashData('error', 'You cannot delete your own user group.');
            redirect('admin/auth/groups');
        } elseif (!isSuperuser() && groupHasPermission('admin:superuser', $oItem)) {
            $oSession->setFlashData('error', 'You cannot delete a group which has super user permissions.');
            redirect('admin/auth/groups');
        } elseif ($oItem->id === $oItemModel->getDefaultGroupId()) {
            $oSession->setFlashData('error', 'You cannot delete the default user group.');
            redirect('admin/auth/groups');
        } else {
            parent::delete();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Set the default user group
     *
     * @return void
     */
    public function set_default(): void
    {
        if (!userHasPermission('admin:auth:groups:setDefault')) {
            show404();
        }

        $oUri            = Factory::service('Uri');
        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        $oSession        = Factory::service('Session', 'nails/module-auth');

        if ($oUserGroupModel->setAsDefault($oUri->segment(5))) {
            $oSession->setFlashData(
                'success',
                'Group set as default successfully.'
            );
        } else {
            $oSession->setFlashData(
                'error',
                'Failed to set default user group. ' . $oUserGroupModel->lastError()
            );
        }

        redirect('admin/auth/groups');
    }
}
