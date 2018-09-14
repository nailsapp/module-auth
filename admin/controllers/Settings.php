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

use Nails\Admin\Helper;
use Nails\Auth\Controller\BaseAdmin;
use Nails\Factory;

class Settings extends BaseAdmin
{
    /**
     * Announces this controller's navGroups
     * @return \stdClass
     */
    public static function announce()
    {
        $oNavGroup = Factory::factory('Nav', 'nails/module-admin');
        $oNavGroup->setLabel('Settings');
        $oNavGroup->setIcon('fa-wrench');

        if (userHasPermission('admin:auth:settings:update:.*')) {
            $oNavGroup->addAction('Authentication');
        }

        return $oNavGroup;
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

        $oSocial                 = Factory::service('SocialSignOn', 'nails/module-auth');
        $providers               = $oSocial->getProviders();
        $this->data['providers'] = $providers;

        // --------------------------------------------------------------------------

        if ($this->input->post()) {

            //  Prepare update
            $settings          = [];
            $settingsEncrypted = [];

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

                                        if (empty($label1['encrypted'])) {
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

                                    if (empty($label['encrypted'])) {
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

                    $oDb = Factory::service('Database');
                    $oDb->trans_begin();

                    $bRollback        = false;
                    $oAppSettingModel = Factory::model('AppSetting');

                    if (!empty($settings)) {
                        if (!$oAppSettingModel->set($settings, 'auth')) {
                            $error     = $oAppSettingModel->lastError();
                            $bRollback = true;
                        }
                    }

                    if (!empty($settingsEncrypted)) {
                        if (!$oAppSettingModel->set($settingsEncrypted, 'auth', null, true)) {
                            $error     = $oAppSettingModel->lastError();
                            $bRollback = true;
                        }
                    }

                    if ($bRollback) {

                        $oDb->trans_rollback();
                        $this->data['error'] = 'There was a problem saving authentication settings.';

                    } else {

                        $oDb->trans_commit();
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
        $this->data['settings'] = appSetting(null, 'auth', true);

        // --------------------------------------------------------------------------

        //  Load assets
        $oAsset = Factory::service('Asset');
        $oAsset->load('nails.admin.settings.min.js', 'NAILS');

        // --------------------------------------------------------------------------

        //  Set page title
        $this->data['page']->title = 'Settings &rsaquo; Authentication';

        // --------------------------------------------------------------------------

        //  Load view
        Helper::loadView('index');
    }
}
