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

use Nails\Admin\Controller\DefaultController;
use Nails\Admin\Helper;
use Nails\Admin\Model\ChangeLog;
use Nails\Admin\Nav;
use Nails\Auth\Controller\BaseAdmin;
use Nails\Components;
use Nails\Factory;

class Accounts extends DefaultController
{
    const CONFIG_MODEL_NAME     = 'User';
    const CONFIG_MODEL_PROVIDER = 'nails/module-auth';
    const CONFIG_PERMISSION     = 'auth:accounts';
    const CONFIG_SORT_DIRECTION = 'desc';
    const CONFIG_INDEX_DATA     = [
        'expand' => ['group'],
    ];
    const CONFIG_INDEX_FIELDS   = [
        'ID'          => 'id',
        'User'        => 'id',
        'Group'       => 'group_name',
        'Login Count' => 'login_count',
        'Registered'  => 'created',
        'Last Login'  => 'last_login',
        'Last Seen'   => 'last_seen',
    ];
    const CONFIG_SORT_OPTIONS   = [
        'ID'            => 'id',
        'First name'    => 'first_name',
        'Surname'       => 'last_name',
        'Primary Email' => 'email',
        'Login Count'   => 'login_count',
        'Registered'    => 'created',
        'Last Seen'     => 'last_seen',
        'Last Login'    => 'last_login',
    ];

    // --------------------------------------------------------------------------

    /**
     * The ChangeLog model
     *
     * @var ChangeLog
     */
    protected $oChangeLogModel;

    // --------------------------------------------------------------------------

    /**
     * Announces this controller's navGroups
     *
     * @return Nav
     * @throws \Nails\Common\Exception\FactoryException
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
        $oNavGroup->setLabel('Users');
        $oNavGroup->setIcon('fa-users');

        if (userHasPermission('admin:auth:accounts:browse')) {

            $oDb = Factory::service('Database');
            $oDb->where('is_suspended', false);
            $numTotal    = $oDb->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertTotal = Factory::factory('NavAlert', 'nails/module-admin');
            $oAlertTotal->setValue($numTotal);
            $oAlertTotal->setLabel('Number of Users');

            $oDb->where('is_suspended', true);
            $numSuspended    = $oDb->count_all_results(NAILS_DB_PREFIX . 'user');
            $oAlertSuspended = Factory::factory('NavAlert', 'nails/module-admin');
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
     *
     * @return array
     */
    public static function permissions(): array
    {
        return array_merge(
            parent::permissions(),
            [
                'browse'             => 'Can browse users',
                'create'             => 'Can create users',
                'delete'             => 'Can delete users',
                'suspend'            => 'Can suspend users',
                'unsuspend'          => 'Can unsuspend users',
                'loginAs'            => 'Can log in as another user',
                'editOthers'         => 'Can edit other users',
                'changeUserGroup'    => 'Can change a user\'s group',
                'changeOwnUserGroup' => 'Can change their own user group',
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Accounts constructor.
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\NailsException
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        $oInput  = Factory::service('Input');
        $sReturn = $oInput->server('request_uri');

        // --------------------------------------------------------------------------

        //  Remove isModal parameter
        $aUrl   = parse_url($sReturn);
        $sPath  = getFromArray('path', $aUrl);
        $sQuery = getFromArray('query', $aUrl);
        parse_str($sQuery, $aQuery);
        unset($aQuery['isModal']);
        $sQuery  = http_build_query($aQuery);
        $sReturn = $sQuery ? $sPath . '?' . $sQuery : $sPath;
        $sReturn = urlencode($sReturn);

        // --------------------------------------------------------------------------

        $this->aConfig['INDEX_ROW_BUTTONS'] = array_merge(
            $this->aConfig['INDEX_ROW_BUTTONS'],
            [
                [
                    'url'     => site_url('auth/override/login_as/{{id_md5}}/{{password_md5}}') . '?return_to=' . $sReturn,
                    'label'   => 'Login As',
                    'class'   => 'btn-warning',
                    'enabled' => function ($oUser) {
                        /**
                         * Requirements
                         * - target user is not active user
                         * - active user has loginAs permission
                         * - target user is not suspended
                         * - if target user is a superuser active user must also be a superuser
                         */
                        return $oUser->id !== activeUser('id')
                            && userHasPermission('admin:auth:accounts:loginAs')
                            && !$oUser->is_suspended
                            && $this->activeUserCanEditSuperUser($oUser);
                    },
                ],
                [
                    'url'     => 'suspend/{{id}}?return_to=' . $sReturn,
                    'label'   => 'Suspend',
                    'class'   => 'btn-danger',
                    'enabled' => function ($oUser) {
                        /**
                         * Requirements
                         * - target user is not active user
                         * - active user has suspend permission
                         * - target user is not suspended
                         * - if target user is a superuser active user must also be a superuser
                         */
                        return $oUser->id !== activeUser('id')
                            && userHasPermission('admin:auth:accounts:suspend')
                            && !$oUser->is_suspended
                            && $this->activeUserCanEditSuperUser($oUser);
                    },
                ],
                [
                    'url'     => 'unsuspend/{{id}}?return_to=' . $sReturn,
                    'label'   => 'Unsuspend',
                    'class'   => 'btn-success',
                    'enabled' => function ($oUser) {
                        /**
                         * Requirements
                         * - target user is not active user
                         * - active user has unsuspend permission
                         * - target user is suspended
                         * - if target user is a superuser active user must also be a superuser
                         */
                        return $oUser->id !== activeUser('id')
                            && userHasPermission('admin:auth:accounts:unsuspend')
                            && $oUser->is_suspended
                            && $this->activeUserCanEditSuperUser($oUser);
                    },
                ],
                [
                    'url'     => site_url('admin/auth/accounts/change_group?users={{id}}'),
                    'label'   => 'Change Group',
                    'class'   => 'btn-default',
                    'enabled' => function ($oUser) {
                        /**
                         * Requirements
                         * - target user is not active user
                         * - active user has changeUserGroup permission
                         * - if target user is a superuser active user must also be a superuser
                         */
                        return (
                                ($oUser->id === activeUser('id') && userHasPermission('admin:auth:accounts:changeOwnUserGroup'))
                                || ($oUser->id !== activeUser('id') && userHasPermission('admin:auth:accounts:changeUserGroup'))
                            )
                            && $this->activeUserCanEditSuperUser($oUser);
                    },
                ],
            ]
        );

        // --------------------------------------------------------------------------

        //  Override the edit and delete `enabled` behavour to add additional checks
        foreach ($this->aConfig['INDEX_ROW_BUTTONS'] as &$aButton) {
            if ($aButton['label'] === lang('action_edit')) {

                $aButton['enabled'] = function ($oUser) {
                    return ($oUser->id === activeUser('id') || userHasPermission('admin:auth:accounts:editOthers')) &&
                        !(!isSuperuser() && isSuperuser($oUser));
                };

            } elseif ($aButton['label'] === lang('action_delete')) {

                $aButton['enabled'] = function ($oUser) {
                    return static::isDeleteButtonEnabled($oUser) &&
                        $oUser->id !== activeUser('id') &&
                        !(!isSuperuser() && isSuperuser($oUser));
                };
            }
        }

        // --------------------------------------------------------------------------

        $this->aConfig['INDEX_USER_FIELDS'][]     = 'user';
        $this->aConfig['INDEX_CENTERED_FIELDS'][] = 'login_count';
        $this->aConfig['INDEX_CENTERED_FIELDS'][] = 'group_name';
        $this->aConfig['INDEX_NUMERIC_FIELDS'][]  = 'login_count';

        // --------------------------------------------------------------------------

        $this->lang->load('admin_accounts');
        $this->oChangeLogModel = Factory::model('ChangeLog', 'nails/module-admin');
    }

    // --------------------------------------------------------------------------

    /**
     * Determins whether the active user can edit the target superuser
     *
     * @param \stdClass $oUser The user to check
     *
     * @return bool
     */
    protected function activeUserCanEditSuperUser($oUser)
    {
        return !(!isSuperuser() && isSuperuser($oUser));
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the available checkbox filters
     *
     * @return array
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function indexCheckboxFilters()
    {
        $oGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        $aGroups     = $oGroupModel->getAll();

        return array_merge(
            parent::indexCheckboxFilters(),
            [
                Factory::factory('IndexFilter', 'nails/module-admin')
                    ->setLabel('Group')
                    ->setColumn('group_id')
                    ->addOptions(array_map(function ($oGroup) {
                        return Factory::factory('IndexFilterOption', 'nails/module-admin')
                            ->setLabel($oGroup->label)
                            ->setValue($oGroup->id)
                            ->setIsSelected(true);
                    }, $aGroups)),
                Factory::factory('IndexFilter', 'nails/module-admin')
                    ->setLabel('Suspended')
                    ->setColumn('is_suspended')
                    ->addOptions([
                        Factory::factory('IndexFilterOption', 'nails/module-admin')
                            ->setLabel('Yes')
                            ->setValue(true),
                        Factory::factory('IndexFilterOption', 'nails/module-admin')
                            ->setLabel('No')
                            ->setValue(false)
                            ->setIsSelected(true),
                    ]),
            ]
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Create a new user account
     *
     * @todo (Pablo - 2019-01-22) - Use the DefaultController create() method
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\ModelException
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

            $aEmailRules = [
                'required',
                'valid_email',
                'is_unique[' . NAILS_DB_PREFIX . 'user_email.email]',
            ];

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules('email', '', implode('|', $aEmailRules));

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'required');
                if ($oInput->post('email')) {
                    $oFormValidation->set_rules('email', '', implode('|', $aEmailRules));
                }

            } else {
                $oFormValidation->set_rules('email', '', implode('|', $aEmailRules));
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
                $aData = [
                    'group_id' => (int) $this->input->post('group_id', true),
                    'password' => trim($this->input->post('password', true)),
                ];

                if (!$aData['password']) {
                    //  Password isn't set, generate one
                    $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
                    $aData['password']  = $oUserPasswordModel->generate($aData['group_id']);
                }

                if ($oInput->post('email')) {
                    $aData['email'] = $oInput->post('email', true);
                }

                if ($oInput->post('username')) {
                    $aData['username'] = $oInput->post('username', true);
                }

                $aData['first_name']     = $oInput->post('first_name', true);
                $aData['last_name']      = $oInput->post('last_name', true);
                $aData['temp_pw']        = stringToBoolean($oInput->post('temp_pw', true));
                $aData['inform_user_pw'] = true;

                $oUserModel = Factory::model('User', 'nails/module-auth');
                $new_user   = $oUserModel->create($aData, stringToBoolean($oInput->post('send_activation', true)));

                if ($new_user) {

                    /**
                     * Any errors happen? While the user can be created successfully other problems
                     * might happen along the way
                     */

                    $oSession = Factory::service('Session', 'nails/module-auth');

                    if ($oUserModel->getErrors()) {

                        $sMessage = '<strong>Please Note,</strong> while the user was created successfully, the ';
                        $sMessage .= 'following issues were encountered:';
                        $sMessage .= '<ul><li>' . implode('</li><li>', $oUserModel->getErrors()) . '</li></ul>';

                        $oSession->setFlashData('message', $sMessage);
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

                    $sStatus  = 'success';
                    $sMessage = 'A user account was created for <strong>';
                    $sMessage .= $new_user->first_name . '</strong>, update their details now.';
                    $oSession->setFlashData($sStatus, $sMessage);

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
        $oUserGroupModel             = Factory::model('UserGroup', 'nails/module-auth');
        $oUserPasswordModel          = Factory::model('UserPassword', 'nails/module-auth');
        $this->data['groups']        = $oUserGroupModel->getAll();
        $this->data['passwordRules'] = [];

        foreach ($this->data['groups'] as $oGroup) {
            $this->data['passwordRules'][$oGroup->id] = $oUserPasswordModel->getRulesAsString($oGroup->id);
        }

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('create');
    }

    // --------------------------------------------------------------------------

    /**
     * Edit a user account
     *
     * @todo (Pablo - 2019-01-22) - Use the DefaultController edit() method
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\ModelException
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

        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oUser      = $oUserModel->getById($oUri->segment(5));

        if (!$oUser) {
            $oSession->setFlashData('error', lang('accounts_edit_error_unknown_id'));
            redirect($oInput->get('return_to'));
        }

        //  Non-superusers editing superusers is not cool
        if (!$oUserModel->isSuperuser() && userHasPermission('superuser', $oUser)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            $sReturnTo = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/dashboard';
            redirect($sReturnTo);
        }

        //  Is this user editing someone other than themselves? If so, do they have permission?
        if (activeUser('id') != $oUser->id && !userHasPermission('admin:auth:accounts:editOthers')) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            $sReturnTo = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/dashboard';
            redirect($sReturnTo);
        }

        // --------------------------------------------------------------------------

        /**
         * Load the user_meta_cols; loaded here because it's needed for both the view
         * and the form validation
         */

        $oDb           = Factory::service('Database');
        $oConfig       = Factory::service('Config');
        $aUserMetaCols = $oConfig->item('user_meta_cols');
        $iGroupId      = (int) $oInput->post('group_id') ?: $oUser->group_id;

        if (isset($aUserMetaCols[$iGroupId])) {
            $this->data['user_meta_cols'] = $aUserMetaCols[$oUser->group_id];
        } else {
            $this->data['user_meta_cols'] = null;
        }

        //  Set fields to ignore by default
        $this->data['ignored_fields'] = [
            'id',
            'user_id',
        ];

        /**
         * If no cols were found, DESCRIBE the user_meta_app table - where possible you
         * should manually set columns, including datatypes
         */

        if (is_null($this->data['user_meta_cols'])) {

            $aResult                      = $oDb->query('DESCRIBE `' . NAILS_DB_PREFIX . 'user_meta_app`')->result();
            $this->data['user_meta_cols'] = [];

            foreach ($aResult as $col) {

                //  Always ignore some fields
                if (array_search($col->Field, $this->data['ignored_fields']) !== false) {
                    continue;
                }

                // --------------------------------------------------------------------------

                //  Attempt to detect datatype
                $sDataType = 'string';
                $type      = 'text';

                switch (strtolower($col->Type)) {

                    case 'text':
                        $type = 'textarea';
                        break;

                    case 'date':
                        $sDataType = 'date';
                        break;

                    case 'tinyint(1) unsigned':
                        $sDataType = 'bool';
                        break;
                }

                // --------------------------------------------------------------------------

                $this->data['user_meta_cols'][$col->Field] = [
                    'datatype' => $sDataType,
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

                $sDataType = isset($value['datatype']) ? $value['datatype'] : 'string';
                $label     = isset($value['label']) ? $value['label'] : ucwords(str_replace('_', ' ', $col));

                //  Some data types require different handling
                switch ($sDataType) {

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
                $aData = [];

                // --------------------------------------------------------------------------

                //  If we have a profile image, attempt to upload it
                if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] != UPLOAD_ERR_NO_FILE) {

                    $oCdn   = Factory::service('Cdn', 'nails/module-cdn');
                    $object = $oCdn->objectReplace($oUser->profile_img, 'profile-images', 'profile_img');

                    if ($object) {

                        $aData['profile_img'] = $object->id;

                    } else {

                        $this->data['upload_error'] = $oCdn->getErrors();
                        $this->data['error']        = lang('accounts_edit_error_profile_img');
                    }
                }

                // --------------------------------------------------------------------------

                if (!isset($this->data['upload_error'])) {

                    //  Set basic data

                    $aData['temp_pw']              = stringToBoolean($oInput->post('temp_pw', true));
                    $aData['reset_mfa_question']   = stringToBoolean($oInput->post('reset_mfa_question', true));
                    $aData['reset_mfa_device']     = stringToBoolean($oInput->post('reset_mfa_device', true));
                    $aData['first_name']           = $oInput->post('first_name', true);
                    $aData['last_name']            = $oInput->post('last_name', true);
                    $aData['username']             = $oInput->post('username', true);
                    $aData['gender']               = $oInput->post('gender', true);
                    $aData['dob']                  = $oInput->post('dob', true);
                    $aData['dob']                  = !empty($aData['dob']) ? $aData['dob'] : null;
                    $aData['timezone']             = $oInput->post('timezone', true);
                    $aData['datetime_format_date'] = $oInput->post('datetime_format_date', true);
                    $aData['datetime_format_time'] = $oInput->post('datetime_format_time', true);

                    if ($oInput->post('password', true)) {
                        $aData['password'] = $oInput->post('password', true);
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
                                $aData[$col] = stringToBoolean($mValue);
                                break;

                            case 'file':
                            case 'upload':
                                //  File uploads should be an integer, or if empty, null
                                $aData[$col] = (int) $mValue ?: null;
                                break;

                            default:
                                $aData[$col] = $mValue;
                                break;
                        }
                    }

                    // --------------------------------------------------------------------------

                    //  Update account
                    if ($oUserModel->update($oInput->post('id'), $aData)) {

                        $name                  = $oInput->post('first_name', true) . ' ' . $oInput->post('last_name', true);
                        $this->data['success'] = lang('accounts_edit_ok', [title_case($name)]);
                        // --------------------------------------------------------------------------

                        //  Set Admin changelogs
                        $name = '#' . number_format($oInput->post('id'));

                        if ($aData['first_name']) {
                            $name .= ' ' . $aData['first_name'];
                        }

                        if ($aData['last_name']) {
                            $name .= ' ' . $aData['last_name'];
                        }

                        foreach ($aData as $field => $value) {
                            if (isset($oUser->$field)) {
                                $this->oChangeLogModel->add(
                                    'updated',
                                    'a',
                                    'user',
                                    $oInput->post('id'),
                                    $name,
                                    'admin/auth/accounts/edit/' . $oInput->post('id'),
                                    $field,
                                    $oUser->$field,
                                    $value,
                                    false
                                );
                            }
                        }

                        // --------------------------------------------------------------------------

                        //  refresh the user object
                        $oUser = $oUserModel->getById($oInput->post('id'));

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
            $oDb->where('user_id', $oUser->id);
            $aUserMeta = (array) $oDb->get(NAILS_DB_PREFIX . 'user_meta_app')->row();

        } else {
            $aUserMeta = [];
        }

        // --------------------------------------------------------------------------

        //  Get the user's email addresses
        $this->data['user_emails'] = $oUserModel->getEmailsForUser($oUser->id);

        // --------------------------------------------------------------------------

        $this->data['user_edit'] = $oUser;
        $this->data['user_meta'] = $aUserMeta;

        //  Page Title
        $this->data['page']->title = lang(
            'accounts_edit_title',
            title_case($oUser->first_name . ' ' . $oUser->last_name)
        );

        //  Get the groups, timezones and languages
        $oUserGroupModel      = Factory::model('UserGroup', 'nails/module-auth');
        $this->data['groups'] = $oUserGroupModel->getAll();

        $oLanguageModel          = Factory::model('Language');
        $this->data['languages'] = $oLanguageModel->getAllEnabledFlat();

        $oDateTimeModel                 = Factory::model('DateTime');
        $this->data['timezones']        = $oDateTimeModel->getAllTimezone();
        $this->data['date_formats']     = $oDateTimeModel->getAllDateFormat();
        $this->data['time_formats']     = $oDateTimeModel->getAllTimeFormat();
        $this->data['default_timezone'] = $oDateTimeModel->getTimezoneDefault();

        //  Fetch any user uploads
        if (Components::exists('nails/module-cdn')) {
            $oCdn                       = Factory::service('Cdn', 'nails/module-cdn');
            $this->data['user_uploads'] = $oCdn->getObjectsForUser($oUser->id);
        }

        // --------------------------------------------------------------------------

        if (activeUser('id') == $oUser->id) {

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

        $oUserPasswordModel          = Factory::model('UserPassword', 'nails/module-auth');
        $this->data['passwordRules'] = $oUserPasswordModel->getRulesAsString($oUser->group_id);

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('edit');
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user
     *
     * @todo (Pablo - 2019-01-22) - Use the DefaultController edit() method
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\ModelException
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
        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $iUserId    = $oUri->segment(5);
        $oUser      = $oUserModel->getById($iUserId);

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $oUser)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Delete user
        $oUser = $oUserModel->getById($iUserId);

        if (!$oUser) {
            $oSession->setFlashData('error', lang('accounts_edit_error_unknown_id'));
            redirect($oInput->get('return_to'));
        } elseif ($oUser->id == activeUser('id')) {
            $oSession->setFlashData('error', lang('accounts_delete_error_selfie'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Define messages
        if ($oUserModel->destroy($iUserId)) {

            $sStatus  = 'success';
            $sMessage = lang('accounts_delete_success', title_case($oUser->first_name . ' ' . $oUser->last_name));

            //  Update admin changelog
            $this->oChangeLogModel->add(
                'deleted',
                'a',
                'user',
                $iUserId,
                '#' . number_format($iUserId) . ' ' . $oUser->first_name . ' ' . $oUser->last_name
            );

        } else {
            $sStatus  = 'error';
            $sMessage = lang('accounts_delete_error', title_case($oUser->first_name . ' ' . $oUser->last_name));
        }

        $oSession->setFlashData($sStatus, $sMessage);

        // --------------------------------------------------------------------------

        redirect($oInput->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Change a user's group
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function change_group()
    {
        if (!userHasPermission('admin:auth:accounts:changeUserGroup') && !userHasPermission('admin:auth:accounts:changeOwnUserGroup')) {
            show404();
        }

        // --------------------------------------------------------------------------

        $oInput     = Factory::service('Input');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $aUserIds   = explode(',', $oInput->get('users'));
        $aUsers     = $oUserModel->getByIds($aUserIds);

        if (empty($aUsers)) {
            show404();
        }

        $aRemovedUsers = [];
        foreach ($aUsers as &$oUser) {
            if (
                ($oUserModel->isSuperuser($oUser) && !$oUserModel->isSuperuser())
                || ($oUser->id === activeUser('id') && !userHasPermission('admin:auth:accounts:changeOwnUserGroup'))
                || ($oUser->id !== activeUser('id') && !userHasPermission('admin:auth:accounts:changeUserGroup'))
            ) {
                $aRemovedUsers[] = $oUser;
                $oUser           = null;
            }
        }

        $aUsers = array_filter($aUsers);
        $aUsers = array_values($aUsers);

        if (!empty($aRemovedUsers)) {
            $this->data['warning'] = 'You do not have permission to change the group of the following users: ' .
                implode(', ', array_map(function ($oUser) {
                    return '<br><strong>#' . $oUser->id . ' ' . $oUser->first_name . ' ' . $oUser->last_name . '</strong>';
                }, $aRemovedUsers));
        }

        if (empty($aUsers)) {
            $this->data['error'] = 'No users selected';
        }

        // --------------------------------------------------------------------------

        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        $aGroups         = $oUserGroupModel->getAll();

        if (!isSuperuser()) {
            foreach ($aGroups as &$oGroup) {
                if (!empty($oGroup->acl) && in_array('admin:superuser', $oGroup->acl)) {
                    $oGroup = null;
                }
            }
        }

        $aGroups     = array_filter($aGroups);
        $aGroups     = array_values($aGroups);
        $aUserGroups = [];
        foreach ($aGroups as $oGroup) {
            $aUserGroups[$oGroup->id] = $oGroup->label;
        }

        // --------------------------------------------------------------------------

        if ($oInput->post()) {
            if ($oUserGroupModel->changeUserGroup(arrayExtractProperty($aUsers, 'id'), (int) $oInput->post('group_id'))) {
                $oSession = Factory::service('Session', 'nails/module-auth');
                $oSession->setFlashData('success', 'User group was updated successfully.');
                redirect('admin/auth/accounts');
            } else {
                $this->data['error'] = 'Failed to update user group. ' . $oUserGroupModel->lastError();
            }
        }


        $this->data['aUsers']      = $aUsers;
        $this->data['aUserGroups'] = $aUserGroups;
        $this->data['page']->title = 'Change a user\'s group';

        // --------------------------------------------------------------------------

        //  Load views
        Helper::loadView('changeGroup');
    }

    // --------------------------------------------------------------------------

    /**
     * Suspend a user
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\ModelException
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
        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $iUserId    = $oUri->segment(5);
        $oUser      = $oUserModel->getById($iUserId);
        $bOldValue  = $oUser->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $oUser)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Suspend user
        $oUserModel->suspend($iUserId);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $oUser     = $oUserModel->getById($iUserId);
        $bNewValue = $oUser->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if (!$oUser->is_suspended) {
            $oSession->setFlashData(
                'error',
                lang('accounts_suspend_error', title_case($oUser->first_name . ' ' . $oUser->last_name))
            );
        } else {
            $oSession->setFlashData(
                'success',
                lang('accounts_suspend_success', title_case($oUser->first_name . ' ' . $oUser->last_name))
            );
        }

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->oChangeLogModel->add(
            'suspended',
            'a',
            'user',
            $iUserId,
            '#' . number_format($iUserId) . ' ' . $oUser->first_name . ' ' . $oUser->last_name,
            'admin/auth/accounts/edit/' . $iUserId,
            'is_suspended',
            $bOldValue,
            $bNewValue,
            false
        );

        // --------------------------------------------------------------------------

        redirect($oInput->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Unsuspend a user
     *
     * @throws \Nails\Common\Exception\FactoryException
     * @throws \Nails\Common\Exception\ModelException
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
        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $iUserId    = $oUri->segment(5);
        $oUser      = $oUserModel->getById($iUserId);
        $bOldValue  = $oUser->is_suspended;

        // --------------------------------------------------------------------------

        //  Non-superusers editing superusers is not cool
        if (!isSuperuser() && userHasPermission('superuser', $oUser)) {
            $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
            redirect($oInput->get('return_to'));
        }

        // --------------------------------------------------------------------------

        //  Unsuspend user
        $oUserModel->unsuspend($iUserId);

        // --------------------------------------------------------------------------

        //  Get the user's details, again
        $oUser     = $oUserModel->getById($iUserId);
        $bNewValue = $oUser->is_suspended;

        // --------------------------------------------------------------------------

        //  Define messages
        if ($oUser->is_suspended) {
            $sStatus  = 'error';
            $sMessage = lang('accounts_unsuspend_error', title_case($oUser->first_name . ' ' . $oUser->last_name));
        } else {
            $sStatus  = 'success';
            $sMessage = lang('accounts_unsuspend_success', title_case($oUser->first_name . ' ' . $oUser->last_name));
        }

        $oSession->setFlashData($sStatus, $sMessage);

        // --------------------------------------------------------------------------

        //  Update admin changelog
        $this->oChangeLogModel->add(
            'unsuspended',
            'a',
            'user',
            $iUserId,
            '#' . number_format($iUserId) . ' ' . $oUser->first_name . ' ' . $oUser->last_name,
            'admin/auth/accounts/edit/' . $iUserId,
            'is_suspended',
            $bOldValue,
            $bNewValue,
            false
        );

        // --------------------------------------------------------------------------

        redirect($oInput->get('return_to'));
    }

    // --------------------------------------------------------------------------

    /**
     * Delete a user's profile image
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function delete_profile_img()
    {
        $oUri = Factory::service('Uri');
        if ($oUri->segment(5) != activeUser('id') && !userHasPermission('admin:auth:accounts:editOthers')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $oInput     = Factory::service('Input');
        $oSession   = Factory::service('Session', 'nails/module-auth');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $iUserId    = $oUri->segment(5);
        $oUser      = $oUserModel->getById($iUserId);
        $sReturnTo  = $oInput->get('return_to') ? $oInput->get('return_to') : 'admin/auth/accounts/edit/' . $iUserId;

        // --------------------------------------------------------------------------

        if (!$oUser) {

            $oSession->setFlashData('error', lang('accounts_delete_img_error_noid'));
            redirect('admin/auth/accounts');

        } else {

            //  Non-superusers editing superusers is not cool
            if (!isSuperuser() && userHasPermission('superuser', $oUser)) {
                $oSession->setFlashData('error', lang('accounts_edit_error_noteditable'));
                redirect($sReturnTo);
            }

            // --------------------------------------------------------------------------

            if ($oUser->profile_img) {

                $oCdn = Factory::service('Cdn', 'nails/module-cdn');

                if ($oCdn->objectDelete($oUser->profile_img, 'profile-images')) {

                    //  Update the user
                    $oUserModel->update($iUserId, ['profile_img' => null]);

                    $sStatus  = 'notice';
                    $sMessage = lang('accounts_delete_img_success');

                } else {
                    $sStatus  = 'error';
                    $sMessage = lang('accounts_delete_img_error', implode('", "', $oCdn->getErrors()));
                }

            } else {
                $sStatus  = 'notice';
                $sMessage = lang('accounts_delete_img_error_noimg');
            }

            $oSession->setFlashData($sStatus, $sMessage);

            // --------------------------------------------------------------------------

            redirect($sReturnTo);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Manage a user's email address
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function email()
    {
        $oInput     = Factory::service('Input');
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $action     = $oInput->post('action');
        $sEmail     = trim($oInput->post('email'));
        $iId        = (int) $oInput->post('id') ?: null;

        switch ($action) {

            case 'add':
                $bIsPrimary  = (bool) $oInput->post('isPrimary');
                $bIsVerified = (bool) $oInput->post('isVerified');

                if ($oUserModel->emailAdd($sEmail, $iId, $bIsPrimary, $bIsVerified)) {
                    $sStatus  = 'success';
                    $sMessage = '"' . $sEmail . '" was added successfully. ';
                } else {
                    $sStatus  = 'error';
                    $sMessage = 'Failed to add email. ';
                    $sMessage .= $oUserModel->lastError();
                }
                break;

            case 'delete':
                if ($oUserModel->emailDelete($sEmail, $iId)) {
                    $sStatus  = 'success';
                    $sMessage = '"' . $sEmail . '" was deleted successfully. ';
                } else {
                    $sStatus  = 'error';
                    $sMessage = 'Failed to delete email "' . $sEmail . '". ';
                    $sMessage .= $oUserModel->lastError();
                }
                break;

            case 'makePrimary':
                if ($oUserModel->emailMakePrimary($sEmail, $iId)) {
                    $sStatus  = 'success';
                    $sMessage = '"' . $sEmail . '" was set as the primary email.';
                } else {
                    $sStatus  = 'error';
                    $sMessage = 'Failed to mark "' . $sEmail . '" as the primary address. ';
                    $sMessage .= $oUserModel->lastError();
                }
                break;

            case 'verify':
                //  Get the code for this email
                $aUserEmails = $oUserModel->getEmailsForUser($iId);
                $sCode       = '';

                foreach ($aUserEmails as $oUserEmail) {
                    if ($oUserEmail->email == $sEmail) {
                        $sCode = $oUserEmail->code;
                    }
                }

                if (!empty($sCode) && $oUserModel->emailVerify($iId, $sCode)) {
                    $sStatus  = 'success';
                    $sMessage = '"' . $sEmail . '" was verified successfully.';
                } elseif (empty($sCode)) {
                    $sStatus  = 'error';
                    $sMessage = 'Failed to mark "' . $sEmail . '" as verified. ';
                    $sMessage .= 'Could not determine email\'s security code.';
                } else {
                    $sStatus  = 'error';
                    $sMessage = 'Failed to mark "' . $sEmail . '" as verified. ';
                    $sMessage .= $oUserModel->lastError();
                }
                break;

            default:
                $sStatus  = 'error';
                $sMessage = 'Unknown action: "' . $action . '"';
                break;
        }

        $oSession = Factory::service('Session', 'nails/module-auth');
        $oSession->setFlashData($sStatus, $sMessage);
        redirect($oInput->post('return'));
    }
}
