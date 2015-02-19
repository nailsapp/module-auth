<?php

/**
 * This class provides account management functionality to Admin
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Auth;

class Accounts extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        $navGroup = new \Nails\Admin\Nav('Members', 'fa-users');

        if (userHasPermission('admin:auth:accounts:browse')) {

            $ci =& get_instance();

            $ci->db->where('is_suspended', false);
            $numTotal = $ci->db->count_all_results(NAILS_DB_PREFIX . 'user');

            $ci->db->where('is_suspended', true);
            $numSuspended = $ci->db->count_all_results(NAILS_DB_PREFIX . 'user');

            $alerts   = array();
            $alerts[] = \Nails\Admin\Nav::alertObject($numTotal,     'info', 'Number of Users');
            $alerts[] = \Nails\Admin\Nav::alertObject($numSuspended, 'alert', 'Number of Suspended Users');
            $navGroup->addAction('View All Members', 'index', $alerts);
        }

        return $navGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @return array
     */
    public static function permissions()
    {
        $permissions = parent::permissions();

        $permissions['browse']          = 'Can browse users';
        $permissions['create']          = 'Can create users';
        $permissions['delete']          = 'Can delete users';
        $permissions['suspend']         = 'Can suspend users';
        $permissions['unsuspend']       = 'Can unsuspend users';
        $permissions['loginAs']         = 'Can log in as another user';
        $permissions['editOthers']      = 'Can edit other users';
        $permissions['changeUserGroup'] = 'Can change a user\'s group';

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Constructs the controller
     */
    public function __construct()
    {
        parent::__construct();
        $this->lang->load('admin_accounts');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse user accounts
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:auth:accounts:browse')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Set method info
        $this->data['page']->title = 'View All Members';

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $page      = $this->input->get('page')      ? $this->input->get('page')      : 0;
        $perPage   = $this->input->get('perPage')   ? $this->input->get('perPage')   : 50;
        $sortOn    = $this->input->get('sortOn')    ? $this->input->get('sortOn')    : 'u.id';
        $sortOrder = $this->input->get('sortOrder') ? $this->input->get('sortOrder') : 'desc';
        $keywords  = $this->input->get('keywords')  ? $this->input->get('keywords')  : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = array(
            'u.id'         => 'User ID',
            'u.group_id'   => 'Group ID',
            'u.first_name' => 'First Name, Surname',
            'u.last_name'  => 'Surname, First Name',
            'ue.email'     => 'Email'
        );

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = array(
            'sort' => array(
                array($sortOn, $sortOrder)
            ),
            'keywords' => $keywords
        );

        //  Get the items for the page
        $totalRows           = $this->user_model->count_all($data);
        $this->data['users'] = $this->user_model->get_all($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = \Nails\Admin\Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords);
        $this->data['pagination'] = \Nails\Admin\Helper::paginationObject($page, $perPage, $totalRows);

        //  Add a header button
        if (userHasPermission('admin:auth:accounts:create')) {

             \Nails\Admin\Helper::addHeaderButton('admin/auth/accounts/create', 'Create User');
        }

        // --------------------------------------------------------------------------

        \Nails\Admin\Helper::loadView('index');
    }

    // --------------------------------------------------------------------------


    /**
     * Create a new user account
     * @return void
     */
    public function create()
    {
        if (!userHasPermission('admin:auth:accounts:create')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Page Title
        $this->data['page']->title = lang('accounts_create_title');

        // --------------------------------------------------------------------------

        //  Attempt to create the new user account
        if ($this->input->post()) {

            $this->load->library('form_validation');

            //  Set rules
            $this->form_validation->set_rules('group_id', '', 'xss_clean|required|is_natural_no_zero');
            $this->form_validation->set_rules('password', '', 'xss_clean');
            $this->form_validation->set_rules('send_activation', '', 'xss_clean');
            $this->form_validation->set_rules('temp_pw', '', 'xss_clean');
            $this->form_validation->set_rules('first_name', '', 'xss_clean|required');
            $this->form_validation->set_rules('last_name', '', 'xss_clean|required');

            $emailRules   = array();
            $emailRules[] = 'xss_clean';
            $emailRules[] = 'required';
            $emailRules[] = 'valid_email';
            $emailRules[] = 'is_unique[' . NAILS_DB_PREFIX . 'user_email.email]';

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $this->form_validation->set_rules('email', '', implode('|', $emailRules));

                if ($this->input->post('username')) {

                    $this->form_validation->set_rules('username', '', implode('|', 'xss_clean'));
                }

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $this->form_validation->set_rules('username', '', implode('|', 'xss_clean|required'));

                if ($this->input->post('email')) {

                    $this->form_validation->set_rules('email', '', implode('|', $emailRules));
                }

            } else {

                $this->form_validation->set_rules('email', '', implode('|', $emailRules));
                $this->form_validation->set_rules('username', '', 'xss_clean|required');
            }

            //  Set messages
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('min_length', lang('fv_min_length'));
            $this->form_validation->set_message('alpha_dash_period', lang('fv_alpha_dash_period'));
            $this->form_validation->set_message('is_natural_no_zero', lang('fv_required'));
            $this->form_validation->set_message('valid_email', lang('fv_valid_email'));
            $this->form_validation->set_message('is_unique', lang('fv_email_already_registered'));

            //  Execute
            if ($this->form_validation->run()) {

                //  Success
                $data             = array();
                $data['group_id'] = (int) $this->input->post('group_id');
                $data['password'] = trim($this->input->post('password'));

                if (!$data['password']) {

                    //  Password isn't set, generate one
                    $data['password'] = $this->user_password_model->generate();
                }

                if ($this->input->post('email')) {

                    $data['email'] = $this->input->post('email');
                }

                if ($this->input->post('username')) {

                    $data['username'] = $this->input->post('username');
                }

                $data['first_name']     = $this->input->post('first_name');
                $data['last_name']      = $this->input->post('last_name');
                $data['temp_pw']        = stringToBoolean($this->input->post('temp_pw'));
                $data['inform_user_pw'] = true;

                $new_user = $this->user_model->create($data, stringToBoolean($this->input->post('send_activation')));

                if ($new_user) {

                    /**
                     * Any errors happen? While the user can be created successfully other problems
                     * might happen along the way
                     */

                    if ($this->user_model->get_errors()) {

                        $message  = '<strong>Please Note,</strong> while the user was created successfully, the ';
                        $message .= 'following issues were encountered:';
                        $message .= '<ul><li>' . implode('</li><li>', $this->user_model->get_errors()) . '</li></ul>';

                        $this->session->set_flashdata('message', $message);
                    }

                    // --------------------------------------------------------------------------

                    //  Add item to admin changelog
                    $name = '#' . number_format($new_user->id);

                    if ($new_user->first_name) {

                        $name .= ' ' . $new_user->first_name;
                    }

                    if ($new_user->last_name) {

                        $name .= ' ' . $new_user->last_name;
                    }

                    $this->admin_changelog_model->add('created', 'a', 'user', $new_user->id, $name, 'admin/auth/accounts/edit/' . $new_user->id);

                    // --------------------------------------------------------------------------

                    $status   = 'success';
                    $message  = 'A user account was created for <strong>';
                    $message .= $new_user->first_name . '</strong>, update their details now.';
                    $this->session->set_flashdata($status, $message);

                    redirect('admin/auth/accounts/edit/' . $new_user->id);

                } else {

                    $this->data['error']  = 'There was an error when creating the user ';
                    $this->data['error'] .= 'account:<br />&rsaquo; ';
                    $this->data['error'] .= implode('<br />&rsaquo; ', $this->user_model->get_errors());
                }

            } else {

                $this->data['error']  = 'There was an error when creating the user account. ';
                $this->data['error'] .= $this->user_model->last_error();
            }
        }

        // --------------------------------------------------------------------------

        //  Get data for the view
        $this->data['groups'] = $this->user_group_model->get_all();
        $this->data['passwordRulesAsString'] = $this->user_password_model->getRulesAsString();

        // --------------------------------------------------------------------------

        //  Load views
        \Nails\Admin\Helper::loadView('create/index');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit a user account
     * @return void
     */
    public function edit()
    {
        if ($this->uri->segment(5) != activeUser('id') && !userHasPermission('admin:auth:accounts:editOthers')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        /**
         * Get the user's data; loaded early because it's required for the user_meta_cols
         * (we need to know the group of the user so we can pull up the correct cols/rules)
         */

        $user = $this->user_model->get_by_id($this->uri->segment(5));

        if (!$user) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_unknown_id'));
            redirect($this->input->get('return_to'));
        }

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            $return_to = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/dashboard';
            redirect($return_to);
        }

        //  Is this user editing someone other than themselves? If so, do they have permission?
        if (activeUser('id') != $user->id && !userHasPermission('admin:auth:accounts:editOthers')) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            $return_to = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/dashboard';
            redirect($return_to);
        }

        // --------------------------------------------------------------------------

        //  Load helpers
        $this->load->helper('date');

        // --------------------------------------------------------------------------

        /**
         * Load the user_meta_cols; loaded here because it's needed for both the view
         * and the form validation
         */

        $user_meta_cols = $this->config->item('user_meta_cols');
        $group_id       = $this->input->post('group_id') ? $this->input->post('group_id') : $user->group_id;

        if (isset($user_meta_cols[$group_id])) {

            $this->data['user_meta_cols'] = $user_meta_cols[$user->group_id];

        } else {

            $this->data['user_meta_cols'] = null;
        }

        //  Set fields to ignore by default
        $this->data['ignored_fields']   = array();
        $this->data['ignored_fields'][] = 'id';
        $this->data['ignored_fields'][] = 'user_id';

        /**
         * If no cols were found, DESCRIBE the user_meta table - where possible you
         * should manually set columns, including datatypes
         */

        if (is_null($this->data['user_meta_cols'])) {

            $describe = $this->db->query('DESCRIBE `' . NAILS_DB_PREFIX . 'user_meta`')->result();
            $this->data['user_meta_cols'] = array();

            foreach ($describe as $col) {

                //  Always ignore some fields
                if (array_search($col->Field, $this->data['ignored_fields']) !== false) {

                    continue;
                }

                // --------------------------------------------------------------------------

                //  Attempt to detect datatype
                $datatype  = 'string';
                $type      = 'text';

                switch (strtolower($col->Type)) {

                    case 'text':

                        $type = 'textarea';
                        break;

                    case 'date':

                        $datatype = 'date';
                        break;

                    case 'tinyint(1) unsigned':

                        $datatype = 'bool';
                        break;
                }

                // --------------------------------------------------------------------------

                $this->data['user_meta_cols'][$col->Field] = array(
                    'datatype'      => $datatype,
                    'type'          => $type,
                    'label'         => ucwords(str_replace('_', ' ', $col->Field))
                );
            }
        }

        // --------------------------------------------------------------------------

        //  Validate if we're saving, otherwise get the data and display the edit form
        if ($this->input->post()) {

            //  Load validation library
            $this->load->library('form_validation');

            // --------------------------------------------------------------------------

            //  Define user table rules
            $this->form_validation->set_rules('username', '', 'xss-clean');
            $this->form_validation->set_rules('first_name', '', 'xss_clean|trim|required');
            $this->form_validation->set_rules('last_name', '', 'xss_clean|trim|required');
            $this->form_validation->set_rules('gender', '', 'xss_clean|required');
            $this->form_validation->set_rules('dob', '', 'xss_clean|valid_date');
            $this->form_validation->set_rules('timezone', '', 'xss_clean|required');
            $this->form_validation->set_rules('datetime_format_date', '', 'xss_clean|required');
            $this->form_validation->set_rules('datetime_format_time', '', 'xss_clean|required');
            $this->form_validation->set_rules('password', '', 'xss_clean');
            $this->form_validation->set_rules('temp_pw', '', 'xss_clean');
            $this->form_validation->set_rules('reset_mfa_question', '', 'xss_clean');
            $this->form_validation->set_rules('reset_mfa_device', '', 'xss_clean');

            // --------------------------------------------------------------------------

            //  Define user_meta table rules
            foreach ($this->data['user_meta_cols'] as $col => $value) {

                $datatype = isset($value['datatype']) ? $value['datatype'] : 'string';
                $label    = isset($value['label'])    ? $value['label'] : ucwords(str_replace('_', ' ', $col));

                //  Some data types require different handling
                switch ($datatype) {

                    case 'date':

                        //  Dates must validate
                        if (isset($value['validation'])) {

                            $this->form_validation->set_rules($col, $label, 'xss_clean|' . $value['validation'] . '|valid_date[' . $col . ']');

                        } else {

                            $this->form_validation->set_rules($col, $label, 'xss_clean|valid_date[' . $col . ']');
                        }
                        break;

                    // --------------------------------------------------------------------------

                    case 'file':
                    case 'upload':
                    case 'string':
                    default:

                        if (isset($value['validation'])) {

                            $this->form_validation->set_rules($col, $label, 'xss_clean|' . $value['validation']);

                        } else {

                            $this->form_validation->set_rules($col, $label, 'xss_clean');

                        }
                        break;

                }

            }

            // --------------------------------------------------------------------------

            //  Set messages
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('min_length', lang('fv_min_length'));
            $this->form_validation->set_message('alpha_dash_period', lang('fv_alpha_dash_period'));
            $this->form_validation->set_message('is_natural_no_zero', lang('fv_required'));
            $this->form_validation->set_message('valid_date', lang('fv_valid_date'));
            $this->form_validation->set_message('valid_datetime', lang('fv_valid_datetime'));

            // --------------------------------------------------------------------------

            //  Data is valid; ALL GOOD :]
            if ($this->form_validation->run($this)) {

                //  Define the data var
                $data = array();

                // --------------------------------------------------------------------------

                //  If we have a profile image, attempt to upload it
                if (isset($FILES['profile_img']) && $FILES['profile_img']['error'] != UPLOAD_ERR_NO_FILE) {

                    $object = $this->cdn->object_replace($user->profile_img, 'profile-images', 'profile_img');

                    if ($object) {

                        $data['profile_img'] = $object->id;

                    } else {

                        $this->data['upload_error'] = $this->cdn->get_errors();
                        $this->data['error']        = lang('accounts_edit_error_profile_img');
                    }
                }

                // --------------------------------------------------------------------------

                if (!isset($this->data['upload_error'])) {

                    //  Set basic data
                    $data['temp_pw']              = stringToBoolean($this->input->post('temp_pw'));
                    $data['reset_mfa_question']   = stringToBoolean($this->input->post('reset_mfa_question'));
                    $data['reset_mfa_device']     = stringToBoolean($this->input->post('reset_mfa_device'));
                    $data['first_name']           = $this->input->post('first_name');
                    $data['last_name']            = $this->input->post('last_name');
                    $data['username']             = $this->input->post('username');
                    $data['gender']               = $this->input->post('gender');
                    $data['dob']                  = $this->input->post('dob');
                    $data['dob']                  = !empty($data['dob']) ? $data['dob'] : null;
                    $data['timezone']             = $this->input->post('timezone');
                    $data['datetime_format_date'] = $this->input->post('datetime_format_date');
                    $data['datetime_format_time'] = $this->input->post('datetime_format_time');

                    if ($this->input->post('password')) {

                        $data['password']  = $this->input->post('password');
                    }

                    //  Set meta data
                    foreach ($this->data['user_meta_cols'] as $col => $value) {

                        switch ($value['datatype']) {

                            case 'bool':
                            case 'boolean':

                                //  Convert all to boolean from string
                                $data[$col] = stringToBoolean($this->input->post($col));
                                break;

                            default:

                                $data[$col] = $this->input->post($col);
                                break;
                        }
                    }

                    // --------------------------------------------------------------------------

                    //  Update account
                    if ($this->user_model->update($this->input->post('id'), $data)) {

                        $name = $this->input->post('first_name') . ' ' . $this->input->post('last_name');
                        $this->data['success'] = lang('accounts_edit_ok', array(title_case($name)));

                        // --------------------------------------------------------------------------

                        //  Set Admin changelogs
                        $name = '#' . number_format($this->input->post('id'));

                        if ($data['first_name']) {

                            $name .= ' ' . $data['first_name'];
                        }

                        if ($data['last_name']) {

                            $name .= ' ' . $data['last_name'];
                        }

                        foreach ($data as $field => $value) {

                            if (isset($user->$field)) {

                                $this->admin_changelog_model->add(
                                    'updated',
                                    'a',
                                    'user',
                                    $this->input->post('id'),
                                    $name,
                                    'admin/auth/accounts/edit/' . $this->input->post('id'),
                                    $field,
                                    $user->$field,
                                    $value,
                                    false
                                );
                            }
                        }

                        // --------------------------------------------------------------------------

                        //  refresh the user object
                        $user = $this->user_model->get_by_id($this->input->post('id'));

                    //  The account failed to update, feedback to user
                    } else {

                        $this->data['error'] = lang('accounts_edit_fail', implode(', ', $this->user_model->get_errors()));
                    }
                }

            //  Update failed for another reason
            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }

        }
        //  End POST() check

        // --------------------------------------------------------------------------

        //  Get the user's meta data
        if ($this->data['user_meta_cols']) {

            $this->db->select(implode(',', array_keys($this->data['user_meta_cols'])));
            $this->db->where('user_id', $user->id);
            $user_meta = $this->db->get(NAILS_DB_PREFIX . 'user_meta')->row();

        } else {

            $user_meta = array();
        }

        // --------------------------------------------------------------------------

        //  Get the user's email addresses
        $this->data['user_emails'] = $this->user_model->get_emails_for_user($user->id);

        // --------------------------------------------------------------------------

        $this->data['user_edit'] = $user;
        $this->data['user_meta'] = $user_meta;

        //  Page Title
        $this->data['page']->title = lang('accounts_edit_title', title_case($user->first_name . ' ' . $user->last_name));

        //  Get the groups, timezones and languages
        $this->data['groups']       = $this->user_group_model->get_all();
        $this->data['timezones']    = $this->datetime_model->getAllTimezone();
        $this->data['date_formats'] = $this->datetime_model->getAllDateFormat();
        $this->data['time_formats'] = $this->datetime_model->getAllTimeFormat();
        $this->data['languages']    = $this->language_model->getAllEnabledFlat();

        //  Fetch any user uploads
        if (isModuleEnabled('nailsapp/module-cdn')) {

            $this->data['user_uploads'] = $this->cdn->get_objects_for_user($user->id);
        }

        // --------------------------------------------------------------------------

        if (activeUser('id') == $user->id) {

            switch (strtolower(activeUser('gender'))) {

                case 'male':

                    $this->data['notice'] = lang('accounts_edit_editing_self_m');
                    break;

                case 'female':

                    $this->data['notice'] = lang('accounts_edit_editing_self_f');
                    break;

                default:

                    $this->data['notice'] = lang('accounts_edit_editing_self_u');
                    break;
            }
        }

        // --------------------------------------------------------------------------

        //  Assets
        $this->asset->load('nails.admin.accounts.edit.min.js', true);
        $this->asset->inline('_nailsAdminAccountsEdit = new NAILS_Admin_Accounts_Edit();', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        \Nails\Admin\Helper::loadView('edit/index');
    }

    // --------------------------------------------------------------------------

    /**
     * Change a user's group
     * @return void
     */
    public function change_group()
    {
        if (!userHasPermission('admin:auth:accounts:changeUserGroup')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $userIds             = explode(',', $this->input->get('users'));
        $this->data['users'] = $this->user_model->get_by_ids($userIds);

        if (!$this->data['users']) {

            show_404();
        }

        foreach ($this->data['users'] as $user) {

            if ($this->user_model->isSuperuser($user->id) && !$this->user_model->isSuperuser()) {

                show_404();
            }
        }

        // --------------------------------------------------------------------------

        $this->data['userGroups'] = $this->user_group_model->get_all_flat();

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            if ($this->user_group_model->changeUserGroup($userIds, $this->input->post('newGroupId'))) {

                $this->session->set_flashdata('success', 'User group was updated successfully.');
                redirect('admin/auth/accounts/index');

            } else {

                $this->data['error'] = 'Failed to update user group. ' . $this->user_group_model->last_error();
            }
        }

        // --------------------------------------------------------------------------

        //  Load views
        \Nails\Admin\Helper::loadView('changeGroup/index');
    }

    // --------------------------------------------------------------------------

    /**
     * Suspend a user
     * @return void
     */
    public function suspend()
    {
        if (!userHasPermission('admin:auth:accounts:suspend')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid       = $this->uri->segment(5);
        $user      = $this->user_model->get_by_id($uid);
        $old_value = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            redirect($this->input->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Suspend user
        $this->user_model->suspend($uid);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $user      = $this->user_model->get_by_id($uid);
        $new_value = $user->is_suspended;


        // --------------------------------------------------------------------------

        //  Define messages
        if (!$user->is_suspended) {

            $this->session->set_flashdata('error', lang('accounts_suspend_error', title_case($user->first_name . ' ' . $user->last_name)));

        } else {

            $this->session->set_flashdata('success', lang('accounts_suspend_success', title_case($user->first_name . ' ' . $user->last_name)));
        }

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->admin_changelog_model->add('suspended', 'a', 'user', $uid, '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name, 'admin/auth/accounts/edit/' . $uid, 'is_suspended', $old_value, $new_value, false);

        // --------------------------------------------------------------------------

        redirect($this->input->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Unsuspend a user
     * @return void
     */
    public function unsuspend()
    {
        if (!userHasPermission('admin:auth:accounts:unsuspend')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid       = $this->uri->segment(5);
        $user      = $this->user_model->get_by_id($uid);
        $old_value = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            redirect($this->input->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Unsuspend user
        $this->user_model->unsuspend($uid);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $user      = $this->user_model->get_by_id($uid);
        $new_value = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if ($user->is_suspended) {

            $this->session->set_flashdata('error', lang('accounts_unsuspend_error', title_case($user->first_name . ' ' . $user->last_name)));

        } else {

            $this->session->set_flashdata('success', lang('accounts_unsuspend_success', title_case($user->first_name . ' ' . $user->last_name)));

        }

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->admin_changelog_model->add('unsuspended', 'a', 'user', $uid, '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name, 'admin/auth/accounts/edit/' . $uid, 'is_suspended', $old_value, $new_value, false);

        // --------------------------------------------------------------------------

        redirect($this->input->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user
     * @return void
     */
    public function delete()
    {
        if (!userHasPermission('admin:auth:accounts:delete')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid  = $this->uri->segment(5);
        $user = $this->user_model->get_by_id($uid);

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            redirect($this->input->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Delete user
        $user = $this->user_model->get_by_id($uid);

        if (!$user) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_unknown_id'));
            redirect($this->input->get('return_to'));
        }

        if ($user->id == activeUser('id')) {

            $this->session->set_flashdata('error', lang('accounts_delete_error_selfie'));
            redirect($this->input->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Define messages
        if ($this->user_model->destroy($uid)) {

            $this->session->set_flashdata('success', lang('accounts_delete_success', title_case($user->first_name . ' ' . $user->last_name)));

            //  Update admin changelog
            $this->admin_changelog_model->add('deleted', 'a', 'user', $uid, '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name);

        } else {

            $this->session->set_flashdata('error', lang('accounts_delete_error', title_case($user->first_name . ' ' . $user->last_name)));
        }

        // --------------------------------------------------------------------------

        redirect($this->input->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user's profile image
     * @return void
     */
    public function delete_profile_img()
    {
        if ($this->uri->segment(5) != activeUser('id') && !userHasPermission('admin:auth:accounts:editOthers')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $uid       = $this->uri->segment(5);
        $user      = $this->user_model->get_by_id($uid);
        $return_to = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/auth/accounts/edit/' . $uid;

        // --------------------------------------------------------------------------

        if (!$user) {

            $this->session->set_flashdata('error', lang('accounts_delete_img_error_noid'));
            redirect('admin/auth/accounts');

        } else {

            //  Non-superusers editing superusers is not cool
            if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

                $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
                redirect($return_to);
            }

            // --------------------------------------------------------------------------

            if ($user->profile_img) {

                if ($this->cdn->object_delete($user->profile_img, 'profile-images')) {

                    //  Update the user
                    $data = array();
                    $data['profile_img'] = null;

                    $this->user_model->update($uid, $data);

                    // --------------------------------------------------------------------------

                    $this->session->set_flashdata('success', lang('accounts_delete_img_success'));

                } else {

                    $this->session->set_flashdata('error', lang('accounts_delete_img_error', implode('", "', $this->cdn->get_errors())));
                }

            } else {

                $this->session->set_flashdata('notice', lang('accounts_delete_img_error_noimg'));
            }

            // --------------------------------------------------------------------------

            redirect($return_to);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Manage a user's email address
     * @return void
     */
    public function email()
    {
        $action = $this->input->post('action');
        $email  = $this->input->post('email');
        $id     = $this->input->post('id');

        switch ($action) {

            case 'add':

                $isPrimary  = (bool) $this->input->post('isPrimary');
                $isVerified = (bool) $this->input->post('isVerified');

                if ($this->user_model->email_add($email, $id, $isPrimary, $isVerified)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was added successfully. ';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to add email "' . $email . '". ';
                    $message .= $this->user_model->last_error();
                }
                break;

            case 'delete':

                if ($this->user_model->email_delete($email, $id)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was deleted successfully. ';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to delete email "' . $email . '". ';
                    $message .= $this->user_model->last_error();
                }
                break;

            case 'makePrimary':

                if ($this->user_model->email_make_primary($email, $id)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was set as the primary email.';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as the primary address. ';
                    $message .= $this->user_model->last_error();
                }
                break;

            case 'verify':

                //  Get the code for this email
                $userEmails = $this->user_model->get_emails_for_user($id);
                $code       = '';

                foreach ($userEmails as $userEmail) {

                    if ($userEmail->email == $email) {

                        $code = $userEmail->code;
                    }
                }

                if (!empty($code) && $this->user_model->email_verify($id, $code)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was verified successfully.';

                } elseif (empty($code)) {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= 'Could not determine email\'s security code.';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= $this->user_model->last_error();
                }
                break;

            default:

                $status  = 'error';
                $message = 'Unknown action: "' . $action . '"';
                break;
        }

        $this->session->set_flashdata($status, $message);
        redirect($this->input->post('return'));
    }
}
