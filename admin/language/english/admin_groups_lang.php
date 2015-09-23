<?php

/**
 * English language strings for Auth/Admin/Groups Controller
 *
 * @package     Nails
 * @subpackage  module-admin
 * @category    Language
 * @author      Nails Dev Team
 * @link
 */

//  Manage User Groups
$lang['accounts_groups_index_title']                               = 'Manage User Access';
$lang['accounts_groups_index_intro']                               = 'Manage how groups of user\'s can interact with the site.';
$lang['accounts_groups_index_th_name']                             = 'Name and Description';
$lang['accounts_groups_index_th_homepage']                         = 'Homepage';
$lang['accounts_groups_index_th_default']                          = 'Default';
$lang['accounts_groups_index_th_actions']                          = 'Actions';
$lang['accounts_groups_index_action_set_default']                  = 'Set As Default Group';

//  Edit group
$lang['accounts_groups_edit_title']                                = 'Edit Group &rsaquo; %s';
$lang['accounts_groups_edit_warning']                              = '<strong>Please note:</strong> while the system will do its best to validate the content you set ' .
                                                                      'sometimes a valid combination can render an entire group useless (including your own). Please be ' .
                                                                      'extra careful and only change things when you know what you\'re doing. Remember that you won\'t see ' .
                                                                      'the effect of changing the permissions of a group other than your own, check that your changes ' .
                                                                      'have worked before considering the job done!';

$lang['accounts_groups_edit_basic_legend']                         = 'Basics';
$lang['accounts_groups_edit_basic_field_label_label']              = 'Label';
$lang['accounts_groups_edit_basic_field_placeholder_label']        = 'Type the group\'s label name here.';
$lang['accounts_groups_edit_basic_field_label_slug']               = 'Slug';
$lang['accounts_groups_edit_basic_field_placeholder_slug']         = 'Type the group\'s slug here.';
$lang['accounts_groups_edit_basic_field_label_description']        = 'Description';
$lang['accounts_groups_edit_basic_field_placeholder_description']  = 'Type the group\'s description here.';
$lang['accounts_groups_edit_basic_field_label_homepage']           = 'Default Homepage';
$lang['accounts_groups_edit_basic_field_placeholder_homepage']     = 'Type the group\'s homepage here.';
$lang['accounts_groups_edit_basic_field_tip_homepage']             = 'This is where users are sent after login, unless a specific redirect is already in place. If not specified the user will be sent to the homepage.';
$lang['accounts_groups_edit_basic_field_label_registration']       = 'Registration Redirect';
$lang['accounts_groups_edit_basic_field_placeholder_registration'] = 'Redirect new registrants of this group here.';
$lang['accounts_groups_edit_basic_field_tip_registration']         = 'If not defined new registrants will be redirected to the group\'s homepage.';

$lang['accounts_groups_edit_password_legend']                       = 'Password Properties';
$lang['accounts_groups_edit_password_field_label_min_length']       = 'Min. Length';
$lang['accounts_groups_edit_password_field_placeholder_min_length'] = 'The minimum number of characters a password must contain.';
$lang['accounts_groups_edit_password_field_tip_min_length']         = 'If this is undefined, or set to 0 then there is no minimum length';

$lang['accounts_groups_edit_password_field_label_max_length']       = 'Max. Length';
$lang['accounts_groups_edit_password_field_placeholder_max_length'] = 'The maximum number of characters a password must contain.';
$lang['accounts_groups_edit_password_field_tip_max_length']         = 'If this is undefined, or set to 0 then there is no maximum length';

$lang['accounts_groups_edit_password_field_label_expires_after']       = 'Expires After';
$lang['accounts_groups_edit_password_field_placeholder_expires_after'] = 'The expiration policy for passwords, expressed in days';
$lang['accounts_groups_edit_password_field_tip_expires_after']         = 'If this is undefined, or set to 0 then there is no expiration policy';

$lang['accounts_groups_edit_password_field_label_requirements']              = 'Requirements';
$lang['accounts_groups_edit_password_field_label_requirements_symbol']       = 'Must contain a symbol';
$lang['accounts_groups_edit_password_field_label_requirements_number']       = 'Must contain a number';
$lang['accounts_groups_edit_password_field_label_requirements_lower']        = 'Must contain a lowercase letter';
$lang['accounts_groups_edit_password_field_label_requirements_upper']        = 'Must contain an uppercase letter';
$lang['accounts_groups_edit_password_field_label_requirements_not_username'] = 'Must not contain the user\'s username <em>(coming soon)</em>';
$lang['accounts_groups_edit_password_field_label_requirements_not_name']     = 'Must not contain the user\'s name <em>(coming soon)</em>';
$lang['accounts_groups_edit_password_field_label_requirements_not_dob']      = 'Must not contain the user\'s date of birth <em>(coming soon)</em>';

$lang['accounts_groups_edit_password_field_label_banned']       = 'Banned Words';
$lang['accounts_groups_edit_password_field_placeholder_banned'] = 'A comma separated list of words which cannot be used as a password';

$lang['accounts_groups_edit_permission_legend']                    = 'Permissions';
$lang['accounts_groups_edit_permission_warn']                      = '<strong>Please note:</strong> Superusers have full, unrestricted access to admin, regardless of what extra permissions are set.';
$lang['accounts_groups_edit_permission_intro']                     = 'For non-superuser groups you may also grant a access to the administration area by selecting which admin modules they have permission to access. <strong>It goes without saying that you should be careful with these options.</strong>';
$lang['accounts_groups_edit_permissions_field_label_superuser']    = 'Is Super User';
$lang['accounts_groups_edit_permissions_toggle_all']               = 'Toggle All';
$lang['accounts_groups_edit_permissions_dashboard_warn']           = 'If any admin method is selected then this must also be selected.';
