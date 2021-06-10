<?php

/**
 * This class provides the ability to merge users
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    AdminController
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Admin\Auth;

use Nails\Admin\Factory\Nav;
use Nails\Admin\Helper;
use Nails\Auth\Constants;
use Nails\Auth\Controller\BaseAdmin;
use Nails\Auth\Model\User;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Factory\Model\Field;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\UserFeedback;
use Nails\Config;
use Nails\Factory;
use stdClass;

/**
 * Class Import
 *
 * @package Nails\Admin\Auth
 */
class Import extends BaseAdmin
{
    /**
     * Merge users
     *
     * @return void
     * @throws FactoryException
     */
    public function index(): void
    {
        if (!userHasPermission('admin:auth:accounts:create')) {
            unauthorised();
        }

        // --------------------------------------------------------------------------

        $this->data['page']->title = 'Import Users';

        // --------------------------------------------------------------------------

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        if ($oInput->post()) {
            try {

                if ($oInput->post('action') === 'preview') {
                    $this
                        ->validateUpload()
                        ->renderPreview($this->uploadCsv());

                } elseif ($oInput->post('action') === 'import') {
                    $this
                        ->validateObject()
                        ->processImport();

                } else {
                    throw new \Exception('Unrecognised action');
                }

            } catch (ValidationException $e) {
                $this->data['error'] = $e->getMessage();
            } catch (\Exception $e) {
                $this->data['error'] = $e->getMessage();
            }
        }

        // --------------------------------------------------------------------------

        Helper::loadView('index');
    }

    // --------------------------------------------------------------------------

    /**
     * Generates a CSV template to upload
     *
     * @throws FactoryException
     */
    public function template()
    {
        switch (Config::get('APP_NATIVE_LOGIN_USING')) {

            case 'EMAIL':
                $sEmail    = 'Required: user@example.com';
                $sUsername = 'Optional: user_example';
                break;

            case 'USERNAME':
                $sEmail    = 'Optional: user@example.com';
                $sUsername = 'Required: user_example';
                break;

            default:
                $sEmail    = 'Required: user@example.com';
                $sUsername = 'Required: user_example';
                break;
        }

        Helper::loadCsv(
            [
                array_combine($this->getFields(), $this->getFields()),
                [
                    $sEmail,
                    $sUsername,
                    'Required: 1 for yes, 0 for no',
                    'Required: 1 for yes, 0 for no',
                    'Optional',
                    'Optional',
                    'Optional',
                    'Optional: Blank, or one of: UNDISCLOSED, MALE, FEMALE, TRANSGENDER, or OTHER',
                    'Optional: Blank, or date in format YYYY-MM-DD',
                    'Optional: Blank, or PHP timezone (as documented https://www.php.net/manual/en/timezones.php)',
                ],
            ],
            'import-users.csv',
            true
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Returns CSV fields
     *
     * @return string[]
     */
    protected function getFields(): array
    {
        return [
            'email',
            'username',
            'temp_pw',
            'send_email',
            'salutation',
            'first_name',
            'last_name',
            'gender',
            'dob',
            'timezone',
        ];
    }

    // --------------------------------------------------------------------------

    protected function validateUpload(): self
    {
        dd(__METHOD__);
        return $this;
    }

    // --------------------------------------------------------------------------

    protected function uploadCsv(): int
    {
        dd(__METHOD__);
        return 0;
    }

    // --------------------------------------------------------------------------

    protected function renderPreview(): self
    {
        dd(__METHOD__);
        return $this;
    }

    // --------------------------------------------------------------------------

    protected function validateObject(): self
    {
        dd(__METHOD__);
        return $this;
    }

    // --------------------------------------------------------------------------

    protected function processImport(): self
    {
        dd(__METHOD__);
        return $this;
    }

    // --------------------------------------------------------------------------

}
