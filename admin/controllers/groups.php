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

class Groups extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        if (userHasPermission('admin:auth:groups:manage')) {

            $navGroup = new \Nails\Admin\Nav('Members', 'fa-users');
            $navGroup->addAction('Manage User Groups');
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
        $permissions['manage']     = 'Can manage user groups';
        $permissions['create']     = 'Can create user groups';
        $permissions['edit']       = 'Can edit user groups';
        $permissions['delete']     = 'Can delete user groups';
        $permissions['setDefault'] = 'Can set the default user groups';

        // --------------------------------------------------------------------------

        return $permissions;
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

        $this->data['groups'] = $this->user_group_model->get_all();

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

        // --------------------------------------------------------------------------

        $this->session->set_flashdata('message', '<strong>Coming soon!</strong> The ability to dynamically create groups is on the roadmap.');
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

        $gid = $this->uri->segment(5, null);

        $this->data['group'] = $this->user_group_model->get_by_id($gid);

        if (!$this->data['group']) {

            show_404();
        }

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            //  Load library
            $this->load->library('form_validation');

            //  Define rules
            $this->form_validation->set_rules('slug', '', 'xss_clean|unique_if_diff[' . NAILS_DB_PREFIX . 'user_group.slug.' . $this->data['group']->slug . ']');
            $this->form_validation->set_rules('label', '', 'xss_clean|required');
            $this->form_validation->set_rules('description', '', 'xss_clean|required');
            $this->form_validation->set_rules('default_homepage', '', 'xss_clean|required');
            $this->form_validation->set_rules('registration_redirect', '', 'xss_clean');

            //  Set messages
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('required', lang('fv_unique_if_diff'));

            if ($this->form_validation->run()) {

                $data                          = array();
                $data['slug']                  = $this->input->post('slug');
                $data['label']                 = $this->input->post('label');
                $data['description']           = $this->input->post('description');
                $data['default_homepage']      = $this->input->post('default_homepage');
                $data['registration_redirect'] = $this->input->post('registration_redirect');

                //  Parse ACL's and password rules
                $data['acl'] = $this->user_group_model->processPermissions($this->input->post('acl'));
                $data['password_rules'] = $this->user_password_model->processRules($this->input->post('pw'));

                if ($this->user_group_model->update($gid, $data)) {

                    $this->session->set_flashdata('success', 'Group updated successfully!');
                    redirect('admin/auth/groups');

                } else {

                    $this->data['error'] = 'I was unable to update the group. ' . $this->user_group_model->last_error();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        //  Get all available permissions
        $this->data['permissions'] = array();
        foreach ($this->data['adminControllers'] as $module => $moduleDetails) {
            foreach ($moduleDetails->controllers as $controller => $controllerDetails) {

                $temp              = new \stdClass();
                $temp->label       = ucfirst($module) . ': ' . ucfirst($controller);
                $temp->slug        = $module . ':' . $controller;
                $temp->permissions = $controllerDetails['className']::permissions();

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
        $this->asset->load('nails.admin.auth.groups.min.js', 'NAILS');
        $this->asset->inline('var _edit = new NAILS_Admin_Auth_Groups_Edit();', 'JS');

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

        // --------------------------------------------------------------------------

        $this->session->set_flashdata('message', '<strong>Coming soon!</strong> The ability to delete groups is on the roadmap.');
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

        // --------------------------------------------------------------------------

        if ($this->user_group_model->setAsDefault($this->uri->segment(5))) {

            $this->session->set_flashdata('success', 'Group set as default successfully.');

        } else {

            $this->session->set_flashdata('error', 'I could not set that group as the default user group. ' . $this->user_group_model->last_error());
        }

        redirect('admin/auth/groups');
    }
}
