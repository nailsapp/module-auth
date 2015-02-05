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
$lang['accounts_groups_edit_basic_field_label_registration']       = 'Registration Redirect';
$lang['accounts_groups_edit_basic_field_placeholder_registration'] = 'Redirect new registrants of this group here.';
$lang['accounts_groups_edit_basic_field_tip_registration']         = 'If not defined new registrants will be redirected to the group\'s homepage.';

$lang['accounts_groups_edit_permission_legend']                    = 'Permissions';
$lang['accounts_groups_edit_permission_warn']                      = '<strong>Please note:</strong> Superusers have full, unrestricted access to admin, regardless of what extra permissions are set.';
$lang['accounts_groups_edit_permission_intro']                     = 'For non-superuser groups you may also grant a access to the administration area by selecting which admin modules they have permission to access. <strong>It goes without saying that you should be careful with these options.</strong>';
$lang['accounts_groups_edit_permissions_field_label_superuser']    = 'Is Super User';
$lang['accounts_groups_edit_permissions_toggle_all']               = 'Toggle All';
$lang['accounts_groups_edit_permissions_dashboard_warn']           = 'If any admin method is selected then this must also be selected.';
