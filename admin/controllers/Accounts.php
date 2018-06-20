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

use Nails\Admin\Helper;
use Nails\Auth\Controller\BaseAdmin;
use Nails\Factory;

class Accounts extends BaseAdmin
{
    protected $oChangeLogModel;

    // --------------------------------------------------------------------------

    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nailsapp/module-admin');
        $oNavGroup->setLabel('Users');
        $oNavGroup->setIcon('fa-users');

        if (userHasPermission('admin:auth:accounts:browse')) {

            $oDb = Factory::service('Database');
            $oDb->where('is_suspended', false);
            $numTotal    = $oDb->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertTotal = Factory::factory('NavAlert', 'nailsapp/module-admin');
            $oAlertTotal->setValue($numTotal);
            $oAlertTotal->setLabel('Number of Users');

            $oDb->where('is_suspended', true);
            $numSuspended    = $oDb->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertSuspended = Factory::factory('NavAlert', 'nailsapp/module-admin');
            $oAlertSuspended->setValue($numSuspended);
            $oAlertSuspended->setSeverity('danger');
            $oAlertSuspended->setLabel('Number of Suspended Users');

            $oNavGroup->addAction('View All Users', 'index', [$oAlertTotal, $oAlertSuspended], 0);
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
        $this->data['page']->title = 'View All Users';

        // --------------------------------------------------------------------------

        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $tableAlias = $oUserModel->getTableAlias();

        // --------------------------------------------------------------------------

        //  Get pagination and search/sort variables
        $oInput    = Factory::service('Input');
        $page      = $oInput->get('page') ? $oInput->get('page') : 0;
        $perPage   = $oInput->get('perPage') ? $oInput->get('perPage') : 50;
        $sortOn    = $oInput->get('sortOn') ? $oInput->get('sortOn') : $tableAlias . '.id';
        $sortOrder = $oInput->get('sortOrder') ? $oInput->get('sortOrder') : 'desc';
        $keywords  = $oInput->get('keywords') ? $oInput->get('keywords') : '';

        // --------------------------------------------------------------------------

        //  Define the sortable columns
        $sortColumns = [
            $tableAlias . '.id'         => 'User ID',
            $tableAlias . '.group_id'   => 'Group ID',
            $tableAlias . '.first_name' => 'First Name, Surname',
            $tableAlias . '.last_name'  => 'Surname, First Name',
            'ue.email'                  => 'Email',
            $tableAlias . '.last_seen'  => 'Last Seen',
            $tableAlias . '.last_login' => 'Last Login',
        ];

        // --------------------------------------------------------------------------

        $oUserGroupModel = Factory::model('UserGroup', 'nailsapp/module-auth');
        $groupsFlat      = $oUserGroupModel->getAllFlat();
        $groupsFilter    = [];

        foreach ($groupsFlat as $id => $label) {
            $groupsFilter[] = [$label, $id, true];
        }

        //  Filter Checkboxes
        $cbFilters = [];

        if (count($groupsFilter) > 1) {
            $cbFilters[] = Helper::searchFilterObject(
                $tableAlias . '.group_id',
                'User Group',
                $groupsFilter
            );
        }

        $cbFilters[] = Helper::searchFilterObject(
            $tableAlias . '.is_suspended',
            'Suspended',
            [
                ['Yes', true, false],
                ['No', false, true]
            ]
        );

        // --------------------------------------------------------------------------

        //  Define the $data variable for the queries
        $data = [
            'sort'      => [
                [$sortOn, $sortOrder],
            ],
            'keywords'  => $keywords,
            'cbFilters' => $cbFilters,
        ];

        //  Get the items for the page
        $oUserModel          = Factory::model('User', 'nailsapp/module-auth');
        $totalRows           = $oUserModel->countAll($data);
        $this->data['users'] = $oUserModel->getAll($page, $perPage, $data);

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
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            $oFormValidation = Factory::service('FormValidation');

            //  Set rules
            $oFormValidation->set_rules('group_id', '', 'required|is_natural_no_zero');
            $oFormValidation->set_rules('first_name', '', 'required');
            $oFormValidation->set_rules('last_name', '', 'required');

            $emailRules   = [];
            $emailRules[] = 'required';
            $emailRules[] = 'valid_email';
            $emailRules[] = 'is_unique[' . NAILS_DB_PREFIX . 'user_email.email]';

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules('email', '', implode('|', $emailRules));

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'required');

                if ($oInput->post('email')) {
                    $oFormValidation->set_rules('email', '', implode('|', $emailRules));
                }

            } else {
                $oFormValidation->set_rules('email', '', implode('|', $emailRules));
                $oFormValidation->set_rules('username', '', 'required');
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
                $data             = [];
                $data['group_id'] = (int) $this->input->post('group_id', true);
                $data['password'] = trim($this->input->post('password', true));

                if (!$data['password']) {
                    //  Password isn't set, generate one
                    $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
                    $data['password']   = $oUserPasswordModel->generate($data['group_id']);
                }

                if ($oInput->post('email')) {
                    $data['email'] = $oInput->post('email', true);
                }

                if ($oInput->post('username')) {
                    $data['username'] = $oInput->post('username', true);
                }

                $data['first_name']     = $oInput->post('first_name', true);
                $data['last_name']      = $oInput->post('last_name', true);
                $data['temp_pw']        = stringToBoolean($oInput->post('temp_pw', true));
                $data['inform_user_pw'] = true;

                $oUserModel = Factory::model('User', 'nailsapp/module-auth');
                $new_user   = $oUserModel->create($data, stringToBoolean($oInput->post('send_activation', true)));

                if ($new_user) {

                    /**
                     * Any errors happen? While the user can be created successfully other problems
                     * might happen along the way
                     */

                    $oSession = Factory::service('Session', 'nailsapp/module-auth');

                    if ($oUserModel->getErrors()) {

                        $message = '<strong>Please Note,</strong> while the user was created successfully, the ';
                        $message .= 'following issues were encountered:';
                        $message .= '<ul><li>' . implode('</li><li>', $oUserModel->getErrors()) . '</li></ul>';

                        $oSession->setFlashData('message', $message);
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

                    $this->oChangeLogModel->add(
                        'created',
                        'a',
                        'user',
                        $new_user->id,
                        $name,
                        'admin/auth/accounts/edit/' . $new_user->id
                    );

                    // --------------------------------------------------------------------------

                    $status  = 'success';
                    $message = 'A user account was created for <strong>';
                    $message .= $new_user->first_name . '</strong>, update their details now.';
                    $oSession->setFlashData($status, $message);

                    redirect('admin/auth/accounts/edit/' . $new_user->id);

                } else {
                    $this->data['error'] = 'There was an error when creating the user ';
                    $this->data['error'] .= 'account:<br />&rsaquo; ';
                    $this->data['error'] .= implode('<br />&rsaquo; ', $oUserModel->getErrors());
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        //  Get data for the view
        $oUserGroupModel             = Factory::model('UserGroup', 'nailsapp/module-auth');
        $oUserPasswordModel          = Factory::model('UserPassword', 'nailsapp/module-auth');
        $this->data['groups']        = $oUserGroupModel->getAll();
        $this->data['passwordRules'] = [];

        foreach ($this->data['groups'] as $oGroup) {
            $this->data['passwordRules'][$oGroup->id] = $oUserPasswordModel->getRulesAsString($oGroup->id);
        }

        // --------------------------------------------------------------------------

        //  Assets
        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.accounts.create.min.js', 'nailsapp/module-auth');
        $oAsset->inline('_nailsAdminAccountsCreate = new NAILS_Admin_Accounts_Create();', 'JS');

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
        $oUri   = Factory::service('Uri');
        $oInput = Factory::service('Input');

        if ($oUri->segment(5) != activeUser('id') && !userHasPermission('admin:auth:accounts:editOthers')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        /**
         * Get the user's data; loaded early because it's required for the user_meta_cols
         * (we need to know the group of the user so we can pull up the correct cols/rules)
         */

        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $user       = $oUserModel->getById($oUri->segment(5));

        if (!$user) {
            $oSession->setFlashData('error', lang('accounts_edit_error_unknown_id'));
            redirect($oInput->get('return_to'));
        }

        //  Non-superusers editing superusers is not cool
        if (!$oUserModel->isSuperuser() && userHasPermission('superuser', $user)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            $returnTo = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/dashboard';
            redirect($returnTo);
        }

        //  Is this user editing someone other than themselves? If so, do they have permission?
        if (activeUser('id') != $user->id && !userHasPermission('admin:auth:accounts:editOthers')) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            $returnTo = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/dashboard';
            redirect($returnTo);
        }

        // --------------------------------------------------------------------------

        /**
         * Load the user_meta_cols; loaded here because it's needed for both the view
         * and the form validation
         */

        $oDb     = Factory::service('Database');
        $oConfig = Factory::service('Config');

        $user_meta_cols = $oConfig->item('user_meta_cols');

        $group_id = $oInput->post('group_id') ? $oInput->post('group_id', true) : $user->group_id;

        if (isset($user_meta_cols[$group_id])) {
            $this->data['user_meta_cols'] = $user_meta_cols[$user->group_id];
        } else {
            $this->data['user_meta_cols'] = null;
        }

        //  Set fields to ignore by default
        $this->data['ignored_fields']   = [];
        $this->data['ignored_fields'][] = 'id';
        $this->data['ignored_fields'][] = 'user_id';

        /**
         * If no cols were found, DESCRIBE the user_meta_app table - where possible you
         * should manually set columns, including datatypes
         */

        if (is_null($this->data['user_meta_cols'])) {

            $describe                     = $oDb->query('DESCRIBE `' . NAILS_DB_PREFIX . 'user_meta_app`')->result();
            $this->data['user_meta_cols'] = [];

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

                $this->data['user_meta_cols'][$col->Field] = [
                    'datatype' => $datatype,
                    'type'     => $type,
                    'label'    => ucwords(str_replace('_', ' ', $col->Field)),
                ];
            }
        }

        // --------------------------------------------------------------------------

        //  Validate if we're saving, otherwise get the data and display the edit form
        if ($oInput->post()) {

            //  Load validation library
            $oFormValidation = Factory::service('FormValidation');

            // --------------------------------------------------------------------------

            //  Define user table rules
            $oFormValidation->set_rules('first_name', '', 'trim|required');
            $oFormValidation->set_rules('last_name', '', 'trim|required');
            $oFormValidation->set_rules('gender', '', 'required');
            $oFormValidation->set_rules('dob', '', 'valid_date');
            $oFormValidation->set_rules('timezone', '', 'required');
            $oFormValidation->set_rules('datetime_format_date', '', 'required');
            $oFormValidation->set_rules('datetime_format_time', '', 'required');

            // --------------------------------------------------------------------------

            //  Define user_meta table rules
            foreach ($this->data['user_meta_cols'] as $col => $value) {

                $datatype = isset($value['datatype']) ? $value['datatype'] : 'string';
                $label    = isset($value['label']) ? $value['label'] : ucwords(str_replace('_', ' ', $col));

                //  Some data types require different handling
                switch ($datatype) {

                    case 'date':
                        //  Dates must validate
                        if (isset($value['validation'])) {
                            $oFormValidation->set_rules($col, $label, $value['validation'] . '|valid_date[' . $col . ']');
                        } else {
                            $oFormValidation->set_rules($col, $label, 'valid_date[' . $col . ']');
                        }
                        break;

                    // --------------------------------------------------------------------------

                    case 'file':
                    case 'upload':
                    case 'string':
                    default:
                        if (isset($value['validation'])) {
                            $oFormValidation->set_rules($col, $label, $value['validation']);
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
            if ($oFormValidation->run()) {

                //  Define the data var
                $data = [];

                // --------------------------------------------------------------------------

                //  If we have a profile image, attempt to upload it
                if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] != UPLOAD_ERR_NO_FILE) {

                    $oCdn   = Factory::service('Cdn', 'nailsapp/module-cdn');
                    $object = $oCdn->objectReplace($user->profile_img, 'profile-images', 'profile_img');

                    if ($object) {

                        $data['profile_img'] = $object->id;

                    } else {

                        $this->data['upload_error'] = $oCdn->getErrors();
                        $this->data['error']        = lang('accounts_edit_error_profile_img');
                    }
                }

                // --------------------------------------------------------------------------

                if (!isset($this->data['upload_error'])) {

                    //  Set basic data

                    $data['temp_pw']              = stringToBoolean($oInput->post('temp_pw', true));
                    $data['reset_mfa_question']   = stringToBoolean($oInput->post('reset_mfa_question', true));
                    $data['reset_mfa_device']     = stringToBoolean($oInput->post('reset_mfa_device', true));
                    $data['first_name']           = $oInput->post('first_name', true);
                    $data['last_name']            = $oInput->post('last_name', true);
                    $data['username']             = $oInput->post('username', true);
                    $data['gender']               = $oInput->post('gender', true);
                    $data['dob']                  = $oInput->post('dob', true);
                    $data['dob']                  = !empty($data['dob']) ? $data['dob'] : null;
                    $data['timezone']             = $oInput->post('timezone', true);
                    $data['datetime_format_date'] = $oInput->post('datetime_format_date', true);
                    $data['datetime_format_time'] = $oInput->post('datetime_format_time', true);

                    if ($oInput->post('password', true)) {
                        $data['password'] = $oInput->post('password', true);
                    }

                    //  Set meta data
                    foreach ($this->data['user_meta_cols'] as $col => $value) {

                        $mValue = $oInput->post($col, true);

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
                    if ($oUserModel->update($oInput->post('id'), $data)) {

                        $name                  = $oInput->post('first_name', true) . ' ' . $oInput->post('last_name', true);
                        $this->data['success'] = lang('accounts_edit_ok', [title_case($name)]);
                        // --------------------------------------------------------------------------

                        //  Set Admin changelogs
                        $name = '#' . number_format($oInput->post('id'));

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
                                    $oInput->post('id'),
                                    $name,
                                    'admin/auth/accounts/edit/' . $oInput->post('id'),
                                    $field,
                                    $user->$field,
                                    $value,
                                    false
                                );
                            }
                        }

                        // --------------------------------------------------------------------------

                        //  refresh the user object
                        $user = $oUserModel->getById($oInput->post('id'));

                        //  The account failed to update, feedback to user
                    } else {

                        $this->data['error'] = lang(
                            'accounts_edit_fail',
                            implode(', ', $oUserModel->getErrors())
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

            $oDb->select(implode(',', array_keys($this->data['user_meta_cols'])));
            $oDb->where('user_id', $user->id);
            $user_meta = $oDb->get(NAILS_DB_PREFIX . 'user_meta_app')->row();

        } else {
            $user_meta = [];
        }

        // --------------------------------------------------------------------------

        //  Get the user's email addresses
        $this->data['user_emails'] = $oUserModel->getEmailsForUser($user->id);

        // --------------------------------------------------------------------------

        $this->data['user_edit'] = $user;
        $this->data['user_meta'] = $user_meta;

        //  Page Title
        $this->data['page']->title = lang(
            'accounts_edit_title',
            title_case($user->first_name . ' ' . $user->last_name)
        );

        //  Get the groups, timezones and languages
        $oUserGroupModel      = Factory::model('UserGroup', 'nailsapp/module-auth');
        $this->data['groups'] = $oUserGroupModel->getAll();

        $oLanguageModel          = Factory::model('Language');
        $this->data['languages'] = $oLanguageModel->getAllEnabledFlat();

        $oDateTimeModel                 = Factory::model('DateTime');
        $this->data['timezones']        = $oDateTimeModel->getAllTimezone();
        $this->data['date_formats']     = $oDateTimeModel->getAllDateFormat();
        $this->data['time_formats']     = $oDateTimeModel->getAllTimeFormat();
        $this->data['default_timezone'] = $oDateTimeModel->getTimezoneDefault();

        //  Fetch any user uploads
        if (isModuleEnabled('nailsapp/module-cdn')) {
            $oCdn                       = Factory::service('Cdn', 'nailsapp/module-cdn');
            $this->data['user_uploads'] = $oCdn->getObjectsForUser($user->id);
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

        $oUserPasswordModel          = Factory::model('UserPassword', 'nailsapp/module-auth');
        $this->data['passwordRules'] = $oUserPasswordModel->getRulesAsString($user->group_id);

        // --------------------------------------------------------------------------

        //  Assets
        $oAsset = Factory::service('Asset');
        $oAsset->load('admin.accounts.edit.min.js', 'nailsapp/module-auth');
        $oAsset->inline('_nailsAdminAccountsEdit = new NAILS_Admin_Accounts_Edit();', 'JS');

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

        $oInput              = Factory::service('Input');
        $oUserModel          = Factory::model('User', 'nailsapp/module-auth');
        $userIds             = explode(',', $oInput->get('users'));
        $this->data['users'] = $oUserModel->getByIds($userIds);

        if (!$this->data['users']) {
            show_404();
        }

        foreach ($this->data['users'] as $user) {
            if ($oUserModel->isSuperuser($user->id) && !$oUserModel->isSuperuser()) {
                show_404();
            }
        }

        // --------------------------------------------------------------------------

        $oUserGroupModel          = Factory::model('UserGroup', 'nailsapp/module-auth');
        $this->data['userGroups'] = $oUserGroupModel->getAllFlat();

        // --------------------------------------------------------------------------

        if ($oInput->post()) {

            if ($oUserGroupModel->changeUserGroup($userIds, $oInput->post('newGroupId'))) {

                $oSession = Factory::service('Session', 'nailsapp/module-auth');
                $oSession->setFlashData('success', 'User group was updated successfully.');
                redirect('admin/auth/accounts/index');

            } else {
                $this->data['error'] = 'Failed to update user group. ' . $oUserGroupModel->lastError();
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
        $oUri       = Factory::service('Uri');
        $oInput     = Factory::service('Input');
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $uid        = $oUri->segment(5);
        $user       = $oUserModel->getById($uid);
        $oldValue   = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $user)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Suspend user
        $oUserModel->suspend($uid);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $user     = $oUserModel->getById($uid);
        $newValue = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if (!$user->is_suspended) {
            $oSession->setFlashData(
                'error',
                lang('accounts_suspend_error', title_case($user->first_name . ' ' . $user->last_name))
            );
        } else {
            $oSession->setFlashData(
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

        redirect($oInput->get('return_to'));
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
        $oUri       = Factory::service('Uri');
        $oInput     = Factory::service('Input');
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $uid        = $oUri->segment(5);
        $user       = $oUserModel->getById($uid);
        $oldValue   = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $user)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Unsuspend user
        $oUserModel->unsuspend($uid);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $user     = $oUserModel->getById($uid);
        $newValue = $user->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if ($user->is_suspended) {
            $oSession->setFlashData(
                'error',
                lang(
                    'accounts_unsuspend_error',
                    title_case($user->first_name . ' ' . $user->last_name)
                )
            );
        } else {
            $oSession->setFlashData(
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

        redirect($oInput->get('return_to'));
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
        $oUri       = Factory::service('Uri');
        $oInput     = Factory::service('Input');
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $uid        = $oUri->segment(5);
        $user       = $oUserModel->getById($uid);

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $user)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Delete user
        $user = $oUserModel->getById($uid);

        if (!$user) {
            $oSession->setFlashData('error', lang('accounts_edit_error_unknown_id'));
            redirect($oInput->get('return_to'));
        } elseif ($user->id == activeUser('id')) {
            $oSession->setFlashData('error', lang('accounts_delete_error_selfie'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Define messages
        if ($oUserModel->destroy($uid)) {

            $oSession->setFlashData(
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

            $oSession->setFlashData(
                'error',
                lang('accounts_delete_error', title_case($user->first_name . ' ' . $user->last_name))
            );
        }

        // --------------------------------------------------------------------------

        redirect($oInput->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user's profile image
     * @return void
     */
    public function delete_profile_img()
    {
        $oUri = Factory::service('Uri');
        if ($oUri->segment(5) != activeUser('id') && !userHasPermission('admin:auth:accounts:editOthers')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $oInput     = Factory::service('Input');
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $uid        = $oUri->segment(5);
        $user       = $oUserModel->getById($uid);
        $returnTo   = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/auth/accounts/edit/' . $uid;

        // --------------------------------------------------------------------------

        if (!$user) {

            $oSession->setFlashData('error', lang('accounts_delete_img_error_noid'));
            redirect('admin/auth/accounts');

        } else {

            //  Non-superusers editing superusers is not cool
            if (!isSuperuser() && userHasPermission('superuser', $user)) {
                $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
                redirect($returnTo);
            }

            // --------------------------------------------------------------------------

            if ($user->profile_img) {

                $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');

                if ($oCdn->objectDelete($user->profile_img, 'profile-images')) {

                    //  Update the user
                    $data                = [];
                    $data['profile_img'] = null;

                    $oUserModel->update($uid, $data);

                    // --------------------------------------------------------------------------

                    $oSession->setFlashData(
                        'success',
                        lang('accounts_delete_img_success')
                    );

                } else {
                    $oSession->setFlashData(
                        'error',
                        lang('accounts_delete_img_error', implode('", "', $oCdn->getErrors()))
                    );
                }

            } else {
                $oSession->setFlashData(
                    'notice',
                    lang('accounts_delete_img_error_noimg')
                );
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
        $oInput     = Factory::service('Input');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $action     = $oInput->post('action');
        $email      = $oInput->post('email');
        $id         = $oInput->post('id');

        switch ($action) {

            case 'add':
                $isPrimary  = (bool) $oInput->post('isPrimary');
                $isVerified = (bool) $oInput->post('isVerified');

                if ($oUserModel->emailAdd($email, $id, $isPrimary, $isVerified)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was added successfully. ';

                } else {

                    $status  = 'error';
                    $message = 'Failed to add email. ';
                    $message .= $oUserModel->lastError();
                }
                break;

            case 'delete':
                if ($oUserModel->emailDelete($email, $id)) {
                    $status  = 'success';
                    $message = '"' . $email . '" was deleted successfully. ';
                } else {
                    $status  = 'error';
                    $message = 'Failed to delete email "' . $email . '". ';
                    $message .= $oUserModel->lastError();
                }
                break;

            case 'makePrimary':
                if ($oUserModel->emailMakePrimary($email, $id)) {
                    $status  = 'success';
                    $message = '"' . $email . '" was set as the primary email.';
                } else {
                    $status  = 'error';
                    $message = 'Failed to mark "' . $email . '" as the primary address. ';
                    $message .= $oUserModel->lastError();
                }
                break;

            case 'verify':
                //  Get the code for this email
                $userEmails = $oUserModel->getEmailsForUser($id);
                $code       = '';

                foreach ($userEmails as $userEmail) {
                    if ($userEmail->email == $email) {
                        $code = $userEmail->code;
                    }
                }

                if (!empty($code) && $oUserModel->emailVerify($id, $code)) {

                    $status  = 'success';
                    $message = '"' . $email . '" was verified successfully.';

                } elseif (empty($code)) {

                    $status  = 'error';
                    $message = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= 'Could not determine email\'s security code.';

                } else {

                    $status  = 'error';
                    $message = 'Failed to mark "' . $email . '" as verified. ';
                    $message .= $oUserModel->lastError();
                }
                break;

            default:
                $status  = 'error';
                $message = 'Unknown action: "' . $action . '"';
                break;
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->setFlashData($status, $message);
        redirect($oInput->post('return'));
    }
}
