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
    protected $accounts_group;
    protected $accounts_where;
    protected $accounts_columns;
    protected $accounts_actions;
    protected $accounts_sortfields;

    // --------------------------------------------------------------------------

    /**
     * Announces this controllers details
     * @return stdClass
     */
    public static function announce()
    {
        $d = parent::announce();

        // --------------------------------------------------------------------------

        //  Load the laguage file
        get_instance()->lang->load('admin_accounts');

        // --------------------------------------------------------------------------

        //  Configurations
        $d->name = lang('accounts_module_name');
        $d->icon = 'fa-users';

        // --------------------------------------------------------------------------

        //  Navigation options
        $d->funcs          = array();
        $d->funcs['index'] = lang('accounts_nav_index');

        if (user_has_permission('admin.accounts:0.can_manage_groups')) {

            $d->funcs['groups'] = 'Manage User Groups';
        }

        if (user_has_permission('admin.accounts:0.can_merge_users')) {

            $d->funcs['merge']  = 'Merge Users';
        }

        // --------------------------------------------------------------------------

        return $d;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of notifications
     * @param  string $classIndex The class_index value, used when multiple admin instances are available
     * @return array
     */
    public static function notifications($classIndex = null)
    {
        $ci =& get_instance();
        $notifications = array();

        // --------------------------------------------------------------------------

        $notifications['index']            = array();
        $notifications['index']['type']    = 'split';
        $notifications['index']['options'] = array();

        $ci->db->where('is_suspended', true);
        $notifications['index']['options'][] = array(
            'title' => 'Suspended',
            'type' => 'alert',
            'value' => $ci->db->count_all_results(NAILS_DB_PREFIX . 'user')
        );

        $ci->db->where('is_suspended', false);
        $notifications['index']['options'][] = array(
            'title' => 'Active',
            'type' => 'info',
            'value' => $ci->db->count_all_results(NAILS_DB_PREFIX . 'user')
        );

        // --------------------------------------------------------------------------

        return $notifications;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of extra permissions for this controller
     * @param  string $classIndex The class_index value, used when multiple admin instances are available
     * @return array
     */
    public static function permissions($classIndex = null)
    {
        $permissions = parent::permissions($classIndex);

        // --------------------------------------------------------------------------

        //  Define some basic extra permissions
        $permissions['can_create_user']       = 'Can create users';
        $permissions['can_suspend_user']      = 'Can suspend/unsuspend users';
        $permissions['can_login_as']          = 'Can log in as another user';
        $permissions['can_edit_others']       = 'Can edit other users';
        $permissions['can_change_user_group'] = 'Can change a user\'s group';
        $permissions['can_delete_others']     = 'Can delete other users';
        $permissions['can_merge_users']       = 'Can merge users';
        $permissions['can_manage_groups']     = 'Can manage user groups';
        $permissions['can_create_group']      = 'Can create user groups';
        $permissions['can_edit_group']        = 'Can edit user groups';
        $permissions['can_delete_group']      = 'Can delete user groups';
        $permissions['can_set_default_group'] = 'Can set the default user groups';

        // --------------------------------------------------------------------------

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Constructs the controller
     */
    public function __construct($adminControllers)
    {
        parent::__construct($adminControllers);

        // --------------------------------------------------------------------------

        //  Defaults defaults
        $this->accounts_group      = false;
        $this->accounts_where      = array();
        $this->accounts_columns    = array();
        $this->accounts_actions    = array();
        $this->accounts_sortfields = array();

        // --------------------------------------------------------------------------

        $this->accounts_sortfields[] = array('label' => lang('accounts_sort_id'), 'col' => 'u.id');
        $this->accounts_sortfields[] = array('label' => lang('accounts_sort_group_id'), 'col' => 'u.group_id');
        $this->accounts_sortfields[] = array('label' => lang('accounts_sort_first'), 'col' => 'u.first_name');
        $this->accounts_sortfields[] = array('label' => lang('accounts_sort_last'), 'col' => 'u.last_name');
        $this->accounts_sortfields[] = array('label' => lang('accounts_sort_email'), 'col' => 'ue.email');
    }

    // --------------------------------------------------------------------------

    /**
     * Browse user accounts
     * @return void
     */
    public function index()
    {
        //  Searching, sorting, ordering and paginating.
        $hash = 'search_' . md5(uri_string()) . '_';

        if ($this->input->get('reset')) {

            $this->session->unset_userdata($hash . 'per_page');
            $this->session->unset_userdata($hash . 'sort');
            $this->session->unset_userdata($hash . 'order');
        }

        $default_per_page = $this->session->userdata($hash . 'per_page') ? $this->session->userdata($hash . 'per_page') : 50;
        $default_sort     = $this->session->userdata($hash . 'sort') ?   $this->session->userdata($hash . 'sort') : 'u.id';
        $default_order    = $this->session->userdata($hash . 'order') ?  $this->session->userdata($hash . 'order') : 'ASC';

        //  Define vars
        $searchTerm = $this->input->get('search');

        $limit = array(
                    $this->input->get('per_page') ? $this->input->get('per_page') : $default_per_page,
                    $this->input->get('offset') ? $this->input->get('offset') : 0
                );

        $order = array(
                    $this->input->get('sort') ? $this->input->get('sort') : $default_sort,
                    $this->input->get('order') ? $this->input->get('order') : $default_order
                );

        $this->accounts_group = !empty($this->accounts_group) ? $this->accounts_group : $this->input->get('filter');

        //  Set sorting and ordering info in session data so it's remembered for when user returns
        $this->session->set_userdata($hash . 'per_page', $limit[0]);
        $this->session->set_userdata($hash . 'sort', $order[0]);
        $this->session->set_userdata($hash . 'order', $order[1]);

        //  Set values for the page
        $this->data['search']           = new \stdClass();
        $this->data['search']->per_page = $limit[0];
        $this->data['search']->sort     = $order[0];
        $this->data['search']->order    = $order[1];

        // --------------------------------------------------------------------------

        //  Is a group set?
        if ($this->accounts_group) {

            $this->accounts_where['u.group_id'] = $this->accounts_group;
        }

        // --------------------------------------------------------------------------

        //  Get the accounts
        $this->data['users']       = new \stdClass();
        $this->data['users']->data = $this->user_model->get_all(false, $order, $limit, $this->accounts_where, $searchTerm);

        //  Work out pagination
        $this->data['users']->pagination                = new \stdClass();
        $this->data['users']->pagination->total_results = $this->user_model->count_all($this->accounts_where, $searchTerm);

        // --------------------------------------------------------------------------

        //  Override the title (used when loading this method from one of the other methods)
        $this->data['page']->title = !empty($this->data['page']->title) ? $this->data['page']->title : lang('accounts_index_title');

        if ($searchTerm) {

            $this->data['page']->title  .= ' (' . lang('accounts_index_search_results', array($searchTerm, number_format($this->data['users']->pagination->total_results))) . ')';

        } else {

            $this->data['page']->title  .= ' (' . number_format($this->data['users']->pagination->total_results) . ')';
        }

        // --------------------------------------------------------------------------

        //  Pass any columns and actions to the view
        $this->data['columns']    = $this->accounts_columns;
        $this->data['actions']    = $this->accounts_actions;
        $this->data['sortfields'] = $this->accounts_sortfields;

        // --------------------------------------------------------------------------

        //  Load views
        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------


    /**
     * Create a new user account
     * @return void
     */
    public function create()
    {
        if (!user_has_permission('admin.accounts:0.can_create_user')) {

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
                    $message  = '<strong>Success!</strong> A user account was created for <strong>';
                    $message .= $new_user->first_name . '</strong>, update their details now.';
                    $this->session->set_flashdata($status, $message);

                    redirect('admin/auth/accounts/edit/' . $new_user->id);

                } else {

                    $this->data['error']  = '<strong>Sorry,</strong> there was an error when creating the user ';
                    $this->data['error'] .= 'account:<br />&rsaquo; ';
                    $this->data['error'] .= implode('<br />&rsaquo; ', $this->user_model->get_errors());
                }

            } else {

                $this->data['error']  = '<strong>Sorry,</strong> there was an error when creating the user account. ';
                $this->data['error'] .= $this->user_model->last_error();
            }
        }

        // --------------------------------------------------------------------------

        //  Get the groups
        $this->data['groups'] = $this->user_group_model->get_all();

        // --------------------------------------------------------------------------

        //  Load views
        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/create/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Edit a user account
     * @return void
     */
    public function edit()
    {
        if ($this->uri->segment(5) != active_user('id') && !user_has_permission('admin.accounts:0.can_edit_others')) {

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
        if (!$this->user_model->is_superuser() && user_has_permission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            $return_to = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/dashboard';
            redirect($return_to);
        }

        //  Is this user editing someone other than themselves? If so, do they have permission?
        if (active_user('id') != $user->id && !user_has_permission('admin.accounts:0.can_edit_others')) {

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
        $this->data['timezones']    = $this->datetime_model->get_all_timezone();
        $this->data['date_formats'] = $this->datetime_model->get_all_date_format();
        $this->data['time_formats'] = $this->datetime_model->get_all_time_format();
        $this->data['languages']    = $this->language_model->get_all_enabled_flat();

        //  Fetch any user uploads
        if (isModuleEnabled('nailsapp/module-cdn')) {

            $this->data['user_uploads'] = $this->cdn->get_objects_for_user($user->id);
        }

        // --------------------------------------------------------------------------

        if (active_user('id') == $user->id) {

            switch (strtolower(active_user('gender'))) {

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
        if ($this->input->get('inline') || $this->input->get('is_fancybox')) {

            $this->data['headerOverride'] = 'structure/header/nails-admin-blank';
            $this->data['footerOverride'] = 'structure/footer/nails-admin-blank';
        }

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/edit/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Change a user's group
     * @return void
     */
    public function change_group()
    {
        if (!user_has_permission('admin.accounts:0.can_change_user_group')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $userIds             = explode(',', $this->input->get('users'));
        $this->data['users'] = $this->user_model->get_by_ids($userIds);

        if (!$this->data['users']) {

            show_404();
        }

        foreach ($this->data['users'] as $user) {

            if ($this->user_model->is_superuser($user->id) && !$this->user_model->is_superuser()) {

                show_404();
            }
        }

        // --------------------------------------------------------------------------

        $this->data['userGroups'] = $this->user_group_model->get_all_flat();

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            if ($this->user_group_model->changeUserGroup($userIds, $this->input->post('newGroupId'))) {

                $this->session->set_flashdata('success', '<strong>Success!</strong> User group was updated successfully.');
                redirect('admin/auth/accounts/index');

            } else {

                $this->data['error'] = '<strong>Sorry,</strong> failed to update user group. ' . $this->user_group_model->last_error();
            }
        }

        // --------------------------------------------------------------------------

        //  Load views
        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/change_group/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Suspend a user
     * @return void
     */
    public function suspend()
    {
        if (!user_has_permission('admin.accounts:0.can_suspend_user')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid       = $this->uri->segment(5);
        $user      = $this->user_model->get_by_id($uid);
        $old_value = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->is_superuser() && user_has_permission('superuser', $user)) {

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
        if (!user_has_permission('admin.accounts:0.can_suspend_user')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid       = $this->uri->segment(5);
        $user      = $this->user_model->get_by_id($uid);
        $old_value = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->is_superuser() && user_has_permission('superuser', $user)) {

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
        if (!user_has_permission('admin.accounts:0.can_delete_others')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        //  Get the user's details
        $uid  = $this->uri->segment(5);
        $user = $this->user_model->get_by_id($uid);

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->is_superuser() && user_has_permission('superuser', $user)) {

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

        if ($user->id == active_user('id')) {

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
        if ($this->uri->segment(5) != active_user('id') && !user_has_permission('admin.accounts:0.can_edit_others')) {

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
            if (!$this->user_model->is_superuser() && user_has_permission('superuser', $user)) {

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
                    $message = '<strong>Success!</strong> "' . $email . '" was added successfully. ';

                } else {

                    $status   = 'error';
                    $message  = '<strong>Sorry,</strong> failed to add email "' . $email . '". ';
                    $message .= $this->user_model->last_error();
                }
                break;

            case 'delete':

                if ($this->user_model->email_delete($email, $id)) {

                    $status  = 'success';
                    $message = '<strong>Success!</strong> "' . $email . '" was deleted successfully. ';

                } else {

                    $status   = 'error';
                    $message  = '<strong>Sorry,</strong> failed to delete email "' . $email . '". ';
                    $message .= $this->user_model->last_error();
                }
                break;

            case 'makePrimary':

                if ($this->user_model->email_make_primary($email, $id)) {

                    $status  = 'success';
                    $message = '<strong>Success!</strong> "' . $email . '" was set as the primary email.';

                } else {

                    $status   = 'error';
                    $message  = '<strong>Sorry,</strong> failed to mark "' . $email . '" as the primary address. ';
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
                    $message = '<strong>Success!</strong> "' . $email . '" was verified successfully.';

                } elseif (empty($code)) {

                    $status   = 'error';
                    $message  = '<strong>Sorry,</strong> failed to mark "' . $email . '" as verified. ';
                    $message .= 'Could not determine email\'s security code.';

                } else {

                    $status   = 'error';
                    $message  = '<strong>Sorry,</strong> failed to mark "' . $email . '" as verified. ';
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

    // --------------------------------------------------------------------------

    /**
     * Manage user groups
     * @return void
     */
    public function groups()
    {
        if (!user_has_permission('admin.accounts:0.can_manage_groups')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $method = $this->uri->segment(5) ? $this->uri->segment(5) : 'index';
        $method = ucfirst(strtolower($method));

        if (method_exists($this, 'groups' . $method)) {

            $this->{'groups' . $method}();

        } else {

            show_404();
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Browse user groups
     * @return void
     */
    protected function groupsIndex()
    {
        $this->data['page']->title = 'Manage User Groups';

        // --------------------------------------------------------------------------

        $this->data['groups'] = $this->user_group_model->get_all();

        // --------------------------------------------------------------------------

        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/groups/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Create a user group
     * @return void
     */
    protected function groupsCreate()
    {
        if (!user_has_permission('admin.accounts:0.can_create_group')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $this->session->set_flashdata('message', '<strong>Coming soon!</strong> The ability to dynamically create groups is on the roadmap.');
        redirect('admin/auth/accounts/groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit a user group
     * @return void
     */
    protected function groupsEdit()
    {
        if (!user_has_permission('admin.accounts:0.can_edit_group')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $gid = $this->uri->segment(6, null);

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
            $this->form_validation->set_rules('acl[]', '', 'xss_clean');
            $this->form_validation->set_rules('acl[superuser]', '', 'xss_clean');
            $this->form_validation->set_rules('acl[admin]', '', 'xss_clean');
            $this->form_validation->set_rules('acl[admin][]', '', 'xss_clean');

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

                //  Parse ACL's
                $acl         = $this->input->post('acl');
                $data['acl'] = serialize($acl);

                if ($this->user_group_model->update($gid, $data)) {

                    $this->session->set_flashdata('success', '<strong>Huzzah!</strong> Group updated successfully!');
                    redirect('admin/auth/accounts/groups');

                } else {

                    $this->data['error'] = '<strong>Sorry,</strong> I was unable to update the group. ' . $this->user_group_model->last_error();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        //  Page title
        $this->data['page']->title = lang('accounts_groups_edit_title', $this->data['group']->label);

        // --------------------------------------------------------------------------

        //  Load views
        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/groups/edit', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user group
     * @return void
     */
    protected function groupsDelete()
    {
        if (!user_has_permission('admin.accounts:0.can_delete_group')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $this->session->set_flashdata('message', '<strong>Coming soon!</strong> The ability to delete groups is on the roadmap.');
        redirect('admin/auth/accounts/groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the default user group
     * @return void
     */
    protected function groupsSet_default()
    {
        if (!user_has_permission('admin.accounts:0.can_set_default_group')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        if ($this->user_group_model->setAsDefault($this->uri->segment(6))) {

            $this->session->set_flashdata('success', '<strong>Success!</strong> Group set as default successfully.');

        } else {

            $this->session->set_flashdata('error', '<strong>Sorry,</strong> I could not set that group as the default user group. ' . $this->user_group_model->last_error());
        }

        redirect('admin/auth/accounts/groups');
    }

    // --------------------------------------------------------------------------

    /**
     * Merge users
     * @return void
     */
    public function merge()
    {
        if (!user_has_permission('admin.accounts:0.can_merge_users')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Merge Users';

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            $userId   = $this->input->post('userId');
            $mergeIds = explode(',', $this->input->post('mergeIds'));
            $preview  = !$this->input->post('doMerge') ? true : false;

            if (!in_array(active_user('id'), $mergeIds)) {

                $mergeResult = $this->user_model->merge($userId, $mergeIds, $preview);

                if ($mergeResult) {

                    if ($preview) {

                        $this->data['mergeResult'] = $mergeResult;

                        $this->load->view('structure/header', $this->data);
                        $this->load->view('admin/accounts/merge/preview', $this->data);
                        $this->load->view('structure/footer', $this->data);
                        return;

                    } else {

                        $this->session->set_flashdata('success', '<strong>Success!</strong> Users were merged successfully.');
                        redirect('admin/auth/accounts/merge');
                    }

                } else {

                    $this->data['error'] = 'Failed to merge users. ' . $this->user_model->last_error();
                }

            } else {

                $this->data['error'] = '<strong>Sorry,</strong> you cannot list yourself as a user to merge.';
            }
        }

        // --------------------------------------------------------------------------

        $this->asset->load('nails.admin.accounts.merge.min.js', 'NAILS');
        $this->asset->inline('var _accountsMerge = new NAILS_Admin_Accounts_Merge()', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        $this->load->view('structure/header', $this->data);
        $this->load->view('admin/accounts/merge/index', $this->data);
        $this->load->view('structure/footer', $this->data);
    }
}
