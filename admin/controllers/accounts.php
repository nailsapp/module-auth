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

use Nails\Factory;
use Nails\Admin\Helper;
use Nails\Auth\Controller\BaseAdmin;

class Accounts extends BaseAdmin
{
    protected $oChangeLogModel;

    // --------------------------------------------------------------------------

    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
        $oNavGroup->setLabel('Members');
        $oNavGroup->setIcon('fa-users');

        if (userHasPermission('admin:auth:accounts:browse')) {

            $ci =& get_instance();

            $ci->db->where('is_suspended', false);
            $numTotal = $ci->db->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertTotal = Factory::factory('NavAlert', 'nailsapp/module-admin');
            $oAlertTotal->setValue($numTotal);
            $oAlertTotal->setLabel('Number of Users');

            $ci->db->where('is_suspended', true);
            $numSuspended = $ci->db->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertSuspended = Factory::factory('NavAlert', 'nailsapp/module-admin');
            $oAlertSuspended->setValue($numSuspended);
            $oAlertSuspended->setSeverity('danger');
            $oAlertSuspended->setLabel('Number of Suspended Users');

            $oNavGroup->addAction('View All Members', 'index', array($oAlertTotal, $oAlertSuspended), 0);
        }

        return $oNavGroup;
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
        $this->oChangeLogModel = Factory::model('ChangeLog', 'nailsapp/module-admin');
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

        $tablePrefix = $this->user_model->getTablePrefix();

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $page      = $this->input->get('page')      ? $this->input->get('page')      : 0;
        $perPage   = $this->input->get('perPage')   ? $this->input->get('perPage')   : 50;
        $sortOn    = $this->input->get('sortOn')    ? $this->input->get('sortOn')    : $tablePrefix . '.id';
        $sortOrder = $this->input->get('sortOrder') ? $this->input->get('sortOrder') : 'desc';
        $keywords  = $this->input->get('keywords')  ? $this->input->get('keywords')  : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = array(
            $tablePrefix . '.id'         => 'User ID',
            $tablePrefix . '.group_id'   => 'Group ID',
            $tablePrefix . '.first_name' => 'First Name, Surname',
            $tablePrefix . '.last_name'  => 'Surname, First Name',
            'ue.email'                   => 'Email'
        );

        // --------------------------------------------------------------------------

        $groupsFlat   = $this->user_group_model->getAllFlat();
        $groupsFilter = array();

        foreach ($groupsFlat as $id => $label) {

            $groupsFilter[] = array($label, $id, true);
        }

        //  Filter Checkboxes
        $cbFilters   = array();

        if (count($groupsFilter) > 1) {

            $cbFilters[] = Helper::searchFilterObject(
                $tablePrefix . '.group_id',
                'User Group',
                $groupsFilter
            );
        }

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = array(
            'sort' => array(
                array($sortOn, $sortOrder)
            ),
            'keywords' => $keywords,
            'cbFilters' => $cbFilters
        );

        //  Get the items for the page
        $totalRows           = $this->user_model->countAll($data);
        $this->data['users'] = $this->user_model->getAll($page, $perPage, $data);

        //  Set Search and Pagination objects for the view
        $this->data['search']     = Helper::searchObject(true, $sortColumns, $sortOn, $sortOrder, $perPage, $keywords, $cbFilters);
        $this->data['pagination'] = Helper::paginationObject($page, $perPage, $totalRows);

        //  Add a header button
        if (userHasPermission('admin:auth:accounts:create')) {

             Helper::addHeaderButton('admin/auth/accounts/create', 'Create User');
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
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

            $oFormValidation = Factory::service('FormValidation');

            //  Set rules
            $oFormValidation->set_rules('group_id', '', 'xss_clean|required|is_natural_no_zero');
            $oFormValidation->set_rules('password', '', 'xss_clean');
            $oFormValidation->set_rules('send_activation', '', 'xss_clean');
            $oFormValidation->set_rules('temp_pw', '', 'xss_clean');
            $oFormValidation->set_rules('first_name', '', 'xss_clean|required');
            $oFormValidation->set_rules('last_name', '', 'xss_clean|required');

            $emailRules   = array();
            $emailRules[] = 'xss_clean';
            $emailRules[] = 'required';
            $emailRules[] = 'valid_email';
            $emailRules[] = 'is_unique[' . NAILS_DB_PREFIX . 'user_email.email]';

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules('email', '', implode('|', $emailRules));

                if ($this->input->post('username')) {

                    $oFormValidation->set_rules('username', '', 'xss_clean');
                }

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'xss_clean|required');

                if ($this->input->post('email')) {

                    $oFormValidation->set_rules('email', '', implode('|', $emailRules));
                }

            } else {

                $oFormValidation->set_rules('email', '', implode('|', $emailRules));
                $oFormValidation->set_rules('username', '', 'xss_clean|required');
            }

            //  Set messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('min_length', lang('fv_min_length'));
            $oFormValidation->set_message('alpha_dash_period', lang('fv_alpha_dash_period'));
            $oFormValidation->set_message('is_natural_no_zero', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));
            $oFormValidation->set_message('is_unique', lang('fv_email_already_registered'));

            //  Execute
            if ($oFormValidation->run()) {

                //  Success
                $data             = array();
                $data['group_id'] = (int) $this->input->post('group_id');
                $data['password'] = trim($this->input->post('password'));

                if (!$data['password']) {

                    //  Password isn't set, generate one
                    $data['password'] = $this->user_password_model->generate($data['group_id']);
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

                    if ($this->user_model->getErrors()) {

                        $message  = '<strong>Please Note,</strong> while the user was created successfully, the ';
                        $message .= 'following issues were encountered:';
                        $message .= '<ul><li>' . implode('</li><li>', $this->user_model->getErrors()) . '</li></ul>';

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

                    $this->oChangeLogModel->add('created', 'a', 'user', $new_user->id, $name, 'admin/auth/accounts/edit/' . $new_user->id);

                    // --------------------------------------------------------------------------

                    $status   = 'success';
                    $message  = 'A user account was created for <strong>';
                    $message .= $new_user->first_name . '</strong>, update their details now.';
                    $this->session->set_flashdata($status, $message);

                    redirect('admin/auth/accounts/edit/' . $new_user->id);

                } else {

                    $this->data['error']  = 'There was an error when creating the user ';
                    $this->data['error'] .= 'account:<br />&rsaquo; ';
                    $this->data['error'] .= implode('<br />&rsaquo; ', $this->user_model->getErrors());
                }

            } else {

                $this->data['error']  = 'There was an error when creating the user account. ';
                $this->data['error'] .= $this->user_model->lastError();
            }
        }

        // --------------------------------------------------------------------------

        //  Get data for the view
        $this->data['groups'] = $this->user_group_model->getAll();
        $this->data['passwordRules'] = array();

        foreach ($this->data['groups'] as $oGroup) {

            $this->data['passwordRules'][$oGroup->id] = $this->user_password_model->getRulesAsString($oGroup->id);
        }

        // --------------------------------------------------------------------------

        //  Assets
        $this->asset->load('admin.accounts.create.min.js', 'nailsapp/module-auth');
        $this->asset->inline('_nailsAdminAccountsCreate = new NAILS_Admin_Accounts_Create();', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('create/index');
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

        $user = $this->user_model->getById($this->uri->segment(5));

        if (!$user) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_unknown_id'));
            redirect($this->input->get('return_to'));
        }

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            $returnTo = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/dashboard';
            redirect($returnTo);
        }

        //  Is this user editing someone other than themselves? If so, do they have permission?
        if (activeUser('id') != $user->id && !userHasPermission('admin:auth:accounts:editOthers')) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            $returnTo = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/dashboard';
            redirect($returnTo);
        }

        // --------------------------------------------------------------------------

        /**
         * Load the user_meta_cols; loaded here because it's needed for both the view
         * and the form validation
         */
        $oConfig = Factory::service('Config');

        $user_meta_cols = $oConfig->item('user_meta_cols');
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
         * If no cols were found, DESCRIBE the user_meta_app table - where possible you
         * should manually set columns, including datatypes
         */

        if (is_null($this->data['user_meta_cols'])) {

            $describe = $this->db->query('DESCRIBE `' . NAILS_DB_PREFIX . 'user_meta_app`')->result();
            $this->data['user_meta_cols'] = array();

            foreach ($describe as $col) {

                //  Always ignore some fields
                if (array_search($col->Field, $this->data['ignored_fields']) !== false) {

                    continue;
                }

                // --------------------------------------------------------------------------

                //  Attempt to detect datatype
                $datatype = 'string';
                $type     = 'text';

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
                    'datatype' => $datatype,
                    'type'     => $type,
                    'label'    => ucwords(str_replace('_', ' ', $col->Field))
                );
            }
        }

        // --------------------------------------------------------------------------

        //  Validate if we're saving, otherwise get the data and display the edit form
        if ($this->input->post()) {

            //  Load validation library
            $oFormValidation = Factory::service('FormValidation');

            // --------------------------------------------------------------------------

            //  Define user table rules
            $oFormValidation->set_rules('username', '', 'xss-clean');
            $oFormValidation->set_rules('first_name', '', 'xss_clean|trim|required');
            $oFormValidation->set_rules('last_name', '', 'xss_clean|trim|required');
            $oFormValidation->set_rules('gender', '', 'xss_clean|required');
            $oFormValidation->set_rules('dob', '', 'xss_clean|valid_date');
            $oFormValidation->set_rules('timezone', '', 'xss_clean|required');
            $oFormValidation->set_rules('datetime_format_date', '', 'xss_clean|required');
            $oFormValidation->set_rules('datetime_format_time', '', 'xss_clean|required');
            $oFormValidation->set_rules('password', '', 'xss_clean');
            $oFormValidation->set_rules('temp_pw', '', 'xss_clean');
            $oFormValidation->set_rules('reset_mfa_question', '', 'xss_clean');
            $oFormValidation->set_rules('reset_mfa_device', '', 'xss_clean');

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

                            $oFormValidation->set_rules($col, $label, 'xss_clean|' . $value['validation'] . '|valid_date[' . $col . ']');

                        } else {

                            $oFormValidation->set_rules($col, $label, 'xss_clean|valid_date[' . $col . ']');
                        }
                        break;

                    // --------------------------------------------------------------------------

                    case 'file':
                    case 'upload':
                    case 'string':
                    default:

                        if (isset($value['validation'])) {

                            $oFormValidation->set_rules($col, $label, 'xss_clean|' . $value['validation']);

                        } else {

                            $oFormValidation->set_rules($col, $label, 'xss_clean');
                        }
                        break;
                }
            }

            // --------------------------------------------------------------------------

            //  Set messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('min_length', lang('fv_min_length'));
            $oFormValidation->set_message('alpha_dash_period', lang('fv_alpha_dash_period'));
            $oFormValidation->set_message('is_natural_no_zero', lang('fv_required'));
            $oFormValidation->set_message('valid_date', lang('fv_valid_date'));
            $oFormValidation->set_message('valid_datetime', lang('fv_valid_datetime'));

            // --------------------------------------------------------------------------

            //  Data is valid; ALL GOOD :]
            if ($oFormValidation->run($this)) {

                //  Define the data var
                $data = array();

                // --------------------------------------------------------------------------

                //  If we have a profile image, attempt to upload it
                if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] != UPLOAD_ERR_NO_FILE) {

                    $object = $this->cdn->objectReplace($user->profile_img, 'profile-images', 'profile_img');

                    if ($object) {

                        $data['profile_img'] = $object->id;

                    } else {

                        $this->data['upload_error'] = $this->cdn->getErrors();
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

                        $mValue = $this->input->post($col);

                        //  Should the field be made null on empty?
                        if (!empty($value['nullOnEmpty']) && empty($mValue)) {
                            $mValue = null;
                        }

                        switch ($value['datatype']) {

                            case 'bool':
                            case 'boolean':
                                //  Convert all to boolean from string
                                $data[$col] = stringToBoolean($mValue);
                                break;

                            case 'file':
                            case 'upload':
                                //  File uploads should be an integer, or if empty, null
                                $data[$col] = (int) $mValue ?: null;
                                break;

                            default:
                                $data[$col] = $mValue;
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

                                $this->oChangeLogModel->add(
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
                        $user = $this->user_model->getById($this->input->post('id'));

                    //  The account failed to update, feedback to user
                    } else {

                        $this->data['error'] = lang(
                            'accounts_edit_fail',
                            implode(', ', $this->user_model->getErrors())
                        );
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
            $user_meta = $this->db->get(NAILS_DB_PREFIX . 'user_meta_app')->row();

        } else {

            $user_meta = array();
        }

        // --------------------------------------------------------------------------

        //  Get the user's email addresses
        $this->data['user_emails'] = $this->user_model->getEmailsForUser($user->id);

        // --------------------------------------------------------------------------

        $this->data['user_edit'] = $user;
        $this->data['user_meta'] = $user_meta;

        //  Page Title
        $this->data['page']->title = lang(
            'accounts_edit_title',
            title_case($user->first_name . ' ' . $user->last_name)
        );

        //  Get the groups, timezones and languages
        $this->data['groups']       = $this->user_group_model->getAll();

        $oLanguageModel = Factory::model('Language');
        $this->data['languages'] = $oLanguageModel->getAllEnabledFlat();

        $oDateTimeModel = Factory::model('DateTime');
        $this->data['timezones']        = $oDateTimeModel->getAllTimezone();
        $this->data['date_formats']     = $oDateTimeModel->getAllDateFormat();
        $this->data['time_formats']     = $oDateTimeModel->getAllTimeFormat();
        $this->data['default_timezone'] = $oDateTimeModel->getTimezoneDefault();

        //  Fetch any user uploads
        if (isModuleEnabled('nailsapp/module-cdn')) {

            $this->data['user_uploads'] = $this->cdn->getObjectsForUser($user->id);
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

        $this->data['passwordRules'] = $this->user_password_model->getRulesAsString($user->group_id);

        // --------------------------------------------------------------------------

        //  Assets
        $this->asset->load('admin.accounts.edit.min.js', 'nailsapp/module-auth');
        $this->asset->inline('_nailsAdminAccountsEdit = new NAILS_Admin_Accounts_Edit();', 'JS');

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('edit/index');
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
        $this->data['users'] = $this->user_model->getByIds($userIds);

        if (!$this->data['users']) {

            show_404();
        }

        foreach ($this->data['users'] as $user) {

            if ($this->user_model->isSuperuser($user->id) && !$this->user_model->isSuperuser()) {

                show_404();
            }
        }

        // --------------------------------------------------------------------------

        $this->data['userGroups'] = $this->user_group_model->getAllFlat();

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            if ($this->user_group_model->changeUserGroup($userIds, $this->input->post('newGroupId'))) {

                $this->session->set_flashdata('success', 'User group was updated successfully.');
                redirect('admin/auth/accounts/index');

            } else {

                $this->data['error'] = 'Failed to update user group. ' . $this->user_group_model->lastError();
            }
        }

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('changeGroup/index');
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
        $user      = $this->user_model->getById($uid);
        $oldValue = $user->is_suspended;

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
        $user      = $this->user_model->getById($uid);
        $newValue = $user->is_suspended;


        // --------------------------------------------------------------------------

        //  Define messages
        if (!$user->is_suspended) {

            $this->session->set_flashdata(
                'error',
                lang('accounts_suspend_error', title_case($user->first_name . ' ' . $user->last_name))
            );

        } else {

            $this->session->set_flashdata(
                'success',
                lang('accounts_suspend_success', title_case($user->first_name . ' ' . $user->last_name))
            );
        }

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->oChangeLogModel->add(
            'suspended',
            'a',
            'user',
            $uid,
            '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name,
            'admin/auth/accounts/edit/' . $uid,
            'is_suspended',
            $oldValue,
            $newValue,
            false
        );

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
        $user      = $this->user_model->getById($uid);
        $oldValue = $user->is_suspended;

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
        $user     = $this->user_model->getById($uid);
        $newValue = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if ($user->is_suspended) {

            $this->session->set_flashdata(
                'error',
                lang(
                    'accounts_unsuspend_error',
                    title_case($user->first_name . ' ' . $user->last_name)
                )
            );

        } else {

            $this->session->set_flashdata(
                'success',
                lang(
                    'accounts_unsuspend_success',
                    title_case($user->first_name . ' ' . $user->last_name)
                )
            );
        }

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->oChangeLogModel->add(
            'unsuspended',
            'a',
            'user',
            $uid,
            '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name,
            'admin/auth/accounts/edit/' . $uid,
            'is_suspended',
            $oldValue,
            $newValue,
            false
        );

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
        $user = $this->user_model->getById($uid);

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

            $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
            redirect($this->input->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Delete user
        $user = $this->user_model->getById($uid);

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

            $this->session->set_flashdata(
                'success',
                lang('accounts_delete_success', title_case($user->first_name . ' ' . $user->last_name))
            );

            //  Update admin changelog
            $this->oChangeLogModel->add(
                'deleted',
                'a',
                'user',
                $uid,
                '#' . number_format($uid) . ' ' . $user->first_name . ' ' . $user->last_name
            );

        } else {

            $this->session->set_flashdata(
                'error',
                lang('accounts_delete_error', title_case($user->first_name . ' ' . $user->last_name))
            );
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

        $uid      = $this->uri->segment(5);
        $user     = $this->user_model->getById($uid);
        $returnTo = $this->input->get('return_to') ? $this->input->get('return_to') : 'admin/auth/accounts/edit/' . $uid;

        // --------------------------------------------------------------------------

        if (!$user) {

            $this->session->set_flashdata('error', lang('accounts_delete_img_error_noid'));
            redirect('admin/auth/accounts');

        } else {

            //  Non-superusers editing superusers is not cool
            if (!$this->user_model->isSuperuser() && userHasPermission('superuser', $user)) {

                $this->session->set_flashdata('error', lang('accounts_edit_error_noteditable'));
                redirect($returnTo);
            }

            // --------------------------------------------------------------------------

            if ($user->profile_img) {

                if ($this->cdn->objectDelete($user->profile_img, 'profile-images')) {

                    //  Update the user
                    $data = array();
                    $data['profile_img'] = null;

                    $this->user_model->update($uid, $data);

                    // --------------------------------------------------------------------------

                    $this->session->set_flashdata('success', lang('accounts_delete_img_success'));

                } else {

                    $this->session->set_flashdata('error', lang('accounts_delete_img_error', implode('", "', $this->cdn->getErrors())));
                }

            } else {

                $this->session->set_flashdata('notice', lang('accounts_delete_img_error_noimg'));
            }

            // --------------------------------------------------------------------------

            redirect($returnTo);
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

                if ($this->user_model->emailAdd($email, $id, $isPrimary, $isVerified)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was added successfully. ';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to add email. ';
                    $message .= $this->user_model->lastError();
                }
                break;

            case 'delete':
                if ($this->user_model->emailDelete($email, $id)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was deleted successfully. ';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to delete email "' . $email . '". ';
                    $message .= $this->user_model->lastError();
                }
                break;

            case 'makePrimary':
                if ($this->user_model->emailMakePrimary($email, $id)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was set as the primary email.';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as the primary address. ';
                    $message .= $this->user_model->lastError();
                }
                break;

            case 'verify':
                //  Get the code for this email
                $userEmails = $this->user_model->getEmailsForUser($id);
                $code       = '';

                foreach ($userEmails as $userEmail) {

                    if ($userEmail->email == $email) {

                        $code = $userEmail->code;
                    }
                }

                if (!empty($code) && $this->user_model->emailVerify($id, $code)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was verified successfully.';

                } elseif (empty($code)) {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= 'Could not determine email\'s security code.';

                } else {

                    $status   = 'error';
                    $message  = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= $this->user_model->lastError();
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
