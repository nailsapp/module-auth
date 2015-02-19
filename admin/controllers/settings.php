<?php

/**
 * This class handles setting of notification recipients
 *
 * @package     Nails
 * @subpackage  module-admin
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Auth;

class Settings extends \AdminController
{
    /**
     * Announces this controller's navGroups
     * @return stdClass
     */
    public static function announce()
    {
        $navGroup = new \Nails\Admin\Nav('Settings', 'fa-wrench');

        if (userHasPermission('admin:auth:settings:update:.*')) {

            $navGroup->addAction('Authentication');
        }

        return $navGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of permissions which can be configured for the user
     * @return array
     */
    public static function permissions()
    {
        $permissions = parent::permissions();

        $permissions['update:registration'] = 'Can configure registration';
        $permissions['update:password']     = 'Can configure password';
        $permissions['update:social']       = 'Can social integrations';

        return $permissions;
    }

    // --------------------------------------------------------------------------

    /**
     * Set Site Auth settings
     * @return void
     */
    public function index()
    {
        if (!userHasPermission('admin:auth:settings:update:.*')) {

            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->load->library('auth/social_signon');
        $providers = $this->social_signon->get_providers();
        $this->data['providers'] = $providers;

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            //  Prepare update
            $settings          = array();
            $settingsEncrypted = array();

            if (userHasPermission('admin:auth:settings:update:registration')) {

                $settings['user_registration_enabled'] = $this->input->post('user_registration_enabled');
            }

            if (userHasPermission('admin:auth:settings:update:password')) {

                //  @todo
            }

            if (userHasPermission('admin:auth:settings:update:social')) {

                /**
                 * Disable social signon, if any providers are proeprly enabled it'll
                 * turn itself on again.
                 */

                $settings['auth_social_signon_enabled'] = false;

                foreach ($providers as $provider) {

                    $settings['auth_social_signon_' . $provider['slug'] . '_enabled'] = (bool) $this->input->post('auth_social_signon_' . $provider['slug'] . '_enabled');

                    if ($settings['auth_social_signon_' . $provider['slug'] . '_enabled']) {

                        //  null out each key
                        if ($provider['fields']) {

                            foreach ($provider['fields'] as $key => $label) {

                                if (is_array($label) && !isset($label['label'])) {

                                    foreach ($label as $key1 => $label1) {

                                        $value = $this->input->post('auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1);

                                        if (!empty($label1['required']) && empty($value)) {

                                            $error = 'Provider "' . $provider['label'] . '" was enabled, but was missing required field "' . $label1['label'] . '".';
                                            break 3;

                                        }

                                        if ( empty($label1['encrypted'])) {

                                            $settings['auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1] = $value;

                                        } else {

                                            $settingsEncrypted['auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1] = $value;
                                        }
                                    }

                                } else {

                                    $value = $this->input->post('auth_social_signon_' . $provider['slug'] . '_' . $key);

                                    if (!empty($label['required']) && empty($value)) {

                                        $error = 'Provider "' . $provider['label'] . '" was enabled, but was missing required field "' . $label['label'] . '".';
                                        break 2;
                                    }

                                    if ( empty($label['encrypted'])) {

                                        $settings['auth_social_signon_' . $provider['slug'] . '_' . $key] = $value;

                                    } else {

                                        $settingsEncrypted['auth_social_signon_' . $provider['slug'] . '_' . $key] = $value;
                                    }
                                }
                            }
                        }

                        //  Turn on social signon
                        $settings['auth_social_signon_enabled'] = true;

                    } else {

                        //  null out each key
                        if ($provider['fields']) {

                            foreach ($provider['fields'] as $key => $label) {

                                /**
                                 * Secondary conditional detects an actual array fo fields rather than
                                 * just the label/required array. Design could probably be improved...
                                 **/

                                if (is_array($label) && !isset($label['label'])) {

                                    foreach ($label as $key1 => $label1) {

                                        $settings['auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1] = null;
                                    }

                                } else {

                                    $settings['auth_social_signon_' . $provider['slug'] . '_' . $key] = null;
                                }
                            }
                        }
                    }
                }
            }

            //  Save
            if (!empty($settings)) {

                if (empty($error)) {

                    $this->db->trans_begin();
                    $rollback = false;

                    if (!empty($settings)) {

                        if (!$this->app_setting_model->set($settings, 'auth')) {

                            $error    = $this->app_setting_model->last_error();
                            $rollback = true;
                        }
                    }

                    if (!empty($settingsEncrypted)) {

                        if (!$this->app_setting_model->set($settingsEncrypted, 'auth', null, true)) {

                            $error    = $this->app_setting_model->last_error();
                            $rollback = true;
                        }
                    }

                    if ($rollback) {

                        $this->db->trans_rollback();
                        $this->data['error'] = 'There was a problem saving authentication settings. ' . $error;

                    } else {

                        $this->db->trans_commit();
                        $this->data['success'] = 'Authentication settings were saved.';

                    }

                } else {

                    $this->data['error'] = 'There was a problem saving authentication settings. ' . $error;
                }

            } else {

                $this->data['message'] = 'No settings to save.';
            }
        }

        // --------------------------------------------------------------------------

        //  Existing settings
        $this->data['settings'] = app_setting(null, 'auth', true);

        // --------------------------------------------------------------------------

        //  Load assets
        $this->asset->load('nails.admin.settings.min.js', 'NAILS');

        // --------------------------------------------------------------------------

        //  Set page title
        $this->data['page']->title = 'Settings &rsaquo; Authentication';

        // --------------------------------------------------------------------------

        //  Load view
        \Nails\Admin\Helper::loadView('index');
    }
}