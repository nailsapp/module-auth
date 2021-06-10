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
use Nails\Cdn;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Factory\Model\Field;
use Nails\Common\Service\DateTime;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\UserFeedback;
use Nails\Common\Service\View;
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

        /** @var Input $oInput */
        $oInput = Factory::service('Input');

        if ($oInput->post()) {
            try {

                if ($oInput->post('action') === 'preview') {
                    $this
                        ->validateUpload()
                        ->renderPreview($this->uploadCsv());
                    return;

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

        $this->data['page']->title = 'Import Users';
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
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        $aFields    = $this->getFields();

        Helper::loadCsv(
            [
                array_combine(
                    array_keys($aFields),
                    array_keys($aFields)
                ),
                array_combine(
                    array_keys($aFields),
                    [
                        sprintf(
                            '%s: user@example.com',
                            $aFields['email'] ? 'Required' : 'Optional'
                        ),
                        sprintf(
                            '%s: user_example',
                            $aFields['username'] ? 'Required' : 'Optional'
                        ),
                        'Optional: if not set, default user group is used',
                        'Optional: automatically generated if not set',
                        'Required: 1 for yes, 0 for no',
                        'Required: 1 for yes, 0 for no',
                        'Optional',
                        'Optional',
                        'Optional',
                        'Optional: Blank, or one of: ' . array_keys($oUserModel->getGenders()),
                        'Optional: Blank, or date in format YYYY-MM-DD',
                        'Optional: Blank, or PHP timezone (as documented https://www.php.net/manual/en/timezones.php)',
                    ]
                ),
            ],
            'import-users.csv',
            true
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Returns CSV fields and whether they are required or not
     *
     * @return bool[string]
     */
    protected function getFields(): array
    {
        return [
            'email'      => in_array(Config::get('APP_NATIVE_LOGIN_USING'), ['EMAIL', 'BOTH']),
            'username'   => in_array(Config::get('APP_NATIVE_LOGIN_USING'), ['USERNAME', 'BOTH']),
            'group_id'   => false,
            'password'   => false,
            'temp_pw'    => true,
            'send_email' => true,
            'salutation' => false,
            'first_name' => false,
            'last_name'  => false,
            'gender'     => false,
            'dob'        => false,
            'timezone'   => false,
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Validates the CSV file upload
     *
     * @return $this
     * @throws FactoryException
     * @throws ValidationException
     * @throws \Nails\Common\Exception\ModelException
     */
    protected function validateUpload(): self
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);

        $aFile = $oInput::file('csv');
        if (empty($aFile)) {
            throw new ValidationException('No file selected for upload');
        }

        if ($aFile['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(
                $oCdn::getUploadError($aFile['error'])
            );
        }

        $sMime = $oCdn->getMimeFromFile($aFile['tmp_name']);
        if ($aFile['type'] !== 'text/csv' && $sMime !== 'text/plain') {
            throw new ValidationException(
                'Uploaded file is not a CSV'
            );
        }

        $this->validateData(
            $this->parseCsv($aFile['tmp_name'])
        );

        return $this;
    }

    // --------------------------------------------------------------------------

    protected function validateObject(): self
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);
        /** @var Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Cdn\Constants::MODULE_SLUG);

        /** @var Cdn\Resource\CdnObject $oObject */
        $oObject = $oObjectModel->getById((int) $oInput->post('object_id'));
        if (empty($oObject)) {
            throw new ValidationException(
                'CDN Object does not exist'
            );
        } elseif ($oObject->file->mime !== 'text/csv') {
            throw new ValidationException(
                'Object is not a CSV'
            );
        }

        $sPath = $oCdn->objectLocalPath($oObject->id);
        if (empty($sPath)) {
            throw new \RuntimeException(
                'Failed to get a local path for CSV file.'
            );
        }

        $this->validateData(
            $this->parseCsv($sPath)
        );

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Validates the CSV data
     *
     * @param array $aData The data to validate
     *
     * @return $this
     * @throws FactoryException
     * @throws ValidationException
     * @throws \Nails\Common\Exception\ModelException
     */
    protected function validateData(array $aData): self
    {
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var User\Password $oPasswordModel */
        $oPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);
        /** @var User\Group $oGroupModel */
        $oGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);
        /** @var DateTime $oDateTimeService */
        $oDateTimeService = Factory::service('DateTime');

        $aGroups = $oGroupModel->getAllFlat();
        $aHeader = array_splice($aData, 0, 1);
        $aHeader = reset($aHeader);

        if (empty($aHeader)) {
            throw new ValidationException(
                'Missing header row'
            );
        }

        $aFields         = $this->getFields();
        $aRequiredFields = array_keys(array_filter($aFields));

        $aDiff = array_diff($aHeader, array_keys($aFields));
        if (!empty($aDiff)) {
            throw new ValidationException(sprintf(
                'Header row contains the following invalid values: %s',
                implode(', ', $aDiff)
            ));
        }

        foreach ($aData as $iIndex => $aDatum) {

            $aDatum = array_combine($aHeader, $aDatum);

            try {

                foreach ($aRequiredFields as $sRequiredField) {
                    if (($aDatum[$sRequiredField] ?? '') === '') {
                        throw new ValidationException(
                            sprintf(
                                'Required field `%s` not set.',
                                $sRequiredField
                            )
                        );
                    }
                }

                if (!empty($aDatum['email'])) {
                    if (!filter_var($aDatum['email'], FILTER_VALIDATE_EMAIL)) {
                        throw new ValidationException(sprintf(
                            '"%s" is not a valid email address.',
                            $aDatum['email']
                        ));
                    }

                    if ($oUserModel->getByEmail($aDatum['email'])) {
                        throw new ValidationException(sprintf(
                            '"%s" is already a registered email.',
                            $aDatum['email']
                        ));
                    }
                }

                if (!empty($aDatum['username'])) {
                    if (!$oUserModel->isValidUsername($aDatum['username'], true)) {
                        throw new ValidationException(sprintf(
                            '"%s" is not a valid username; %s',
                            $aDatum['username'],
                            $oUserModel->lastError()
                        ));
                    }
                }

                if (!empty($aDatum['group_id'])) {
                    if (!array_key_exists($aDatum['group_id'], $aGroups)) {
                        throw new ValidationException(
                            'Invalid user group ID.',
                        );
                    }
                }

                if (!empty($aDatum['password'])) {
                    if (!$oPasswordModel->isAcceptable($aDatum['group_id'] ?: $oGroupModel->getDefaultGroupId(), $aDatum['password'])) {
                        throw new ValidationException(
                            'Password is not acceptable.',
                        );
                    }
                }

                if (!empty($aDatum['temp_pw'])) {
                    if (!in_array($aDatum['temp_pw'], ['0', '1'])) {
                        throw new ValidationException(sprintf(
                            'Invalid value "%s" for field `temp_pw`; must be 0 or 1.',
                            $aDatum['temp_pw']
                        ));
                    }
                }

                if (!empty($aDatum['send_email'])) {
                    if (!in_array($aDatum['send_email'], ['0', '1'])) {
                        throw new ValidationException(sprintf(
                            'Invalid value "%s" for field `send_temp`; must be 0 or 1.',
                            $aDatum['send_email']
                        ));
                    }
                }

                if (!empty($aDatum['gender'])) {
                    if (!in_array($aDatum['gender'], array_keys($oUserModel->getGenders()))) {
                        throw new ValidationException(sprintf(
                            'Invalid value "%s" for field `gender`; must be %s.',
                            $aDatum['gender'],
                            implode(', ', array_keys($oUserModel->getGenders()))
                        ));
                    }
                }

                if (!empty($aDatum['dob'])) {
                    try {
                        $oDate = new \DateTime($aDatum['dob']);
                        if (empty($oDate) || $oDate->format('Y-m-d') !== $aDatum['dob']) {
                            throw new \Exception();
                        }
                    } catch (\Exception $e) {
                        throw new ValidationException(sprintf(
                            'Invalid value "%s" for field `dob`; must be a date in the format YYYY-MM-DD',
                            $aDatum['dob']
                        ));
                    }
                }

                if (!empty($aDatum['timezone'])) {
                    if (!in_array($aDatum['timezone'], array_keys($oDateTimeService->getAllTimezoneFlat()))) {
                        throw new ValidationException(sprintf(
                            'Invalid value "%s" for field `timezone`; must be a valid PHP timezone.',
                            $aDatum['timezone'],
                        ));
                    }
                }

            } catch (\Exception $e) {
                throw new ValidationException(
                    sprintf(
                        'Error at row %s: %s <pre style="padding: 1rem;margin-top:0.5rem;">%s</pre>',
                        $iIndex + 1,
                        $e->getMessage(),
                        implode(', ', $aDatum)
                    )
                );
            }
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Parses the CSV file into an array
     *
     * @param string $sPath The path to the CSV
     *
     * @return array
     */
    protected function parseCsv(string $sPath): array
    {
        return array_map('str_getcsv', file($sPath));
    }

    // --------------------------------------------------------------------------

    protected function uploadCsv(): Cdn\Resource\CdnObject
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);
        /** @var Cdn\Model\CdnObject $oObjectModel */
        $oObjectModel = Factory::model('Object', Cdn\Constants::MODULE_SLUG);

        $aFile = $oInput::file('csv');

        $oObject = $oCdn->objectCreate(
            'csv',
            [
                'slug'      => 'import-user',
                'is_hidden' => true,
            ],
            [
                'Content-Type' => 'text/csv',
            ]
        );

        if (!$oObject) {
            throw new ValidationException(sprintf(
                'Failed to upload CSV; %s',
                $oCdn->lastError()
            ));
        }

        return $oObjectModel->getById($oObject->id);
    }

    // --------------------------------------------------------------------------

    protected function renderPreview(Cdn\Resource\CdnObject $oObject): void
    {
        /** @var Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);

        $sPath = $oCdn->objectLocalPath($oObject->id);
        if (empty($sPath)) {
            throw new \RuntimeException(
                'Failed to get a local path for CSV file'
            );
        }

        $aData = $this->parseCsv($sPath);

        $aFields = $this->getFields();
        $aHeader = array_splice($aData, 0, 1);
        $aHeader = reset($aHeader);

        $this->data['aFields']     = array_keys($aFields);
        $this->data['aHeader']     = $aHeader;
        $this->data['aData']       = $aData;
        $this->data['oObject']     = $oObject;
        $this->data['page']->title = 'Import Users: Preview';

        Helper::loadView('preview');
    }

    // --------------------------------------------------------------------------

    /**
     * Process the import
     *
     * @throws FactoryException
     */
    protected function processImport(): void
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Cdn\Service\Cdn $oCdn */
        $oCdn = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var User\Group $oGroupModel */
        $oGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);

        $iObjectId = (int) $oInput->post('object_id');
        $sPath     = $oCdn->objectLocalPath((int) $oInput->post('object_id'));
        if (empty($sPath)) {
            throw new \RuntimeException(
                'Failed to get a local path for CSV file.'
            );
        }

        $aData    = $this->parseCsv($sPath);
        $aHeader  = array_splice($aData, 0, 1);
        $aHeader  = reset($aHeader);
        $iSuccess = 0;
        $aError   = [];

        foreach ($aData as $iIndex => $aDatum) {

            $aDatum     = array_combine($aHeader, $aDatum);
            $bSendEmail = (bool) $aDatum['send_email'];
            $aUserData  = array_filter([
                'group_id'   => ($aDatum['group_id'] ?? null) ?: $oGroupModel->getDefaultGroupId(),
                'email'      => trim($aDatum['email']) ?: null,
                'username'   => trim($aDatum['username']) ?: null,
                'temp_pw'    => (bool) $aDatum['temp_pw'],
                'salutation' => trim($aDatum['salutation']) ?: null,
                'first_name' => trim($aDatum['first_name']) ?: null,
                'last_name'  => trim($aDatum['last_name']) ?: null,
                'gender'     => trim($aDatum['gender']) ?: null,
                'dob'        => trim($aDatum['dob']) ?: null,
                'timezone'   => trim($aDatum['timezone']) ?: null,
            ]);

            try {

                if ($oUserModel->create($aUserData, $bSendEmail)) {
                    $iSuccess++;
                } else {
                    $aError[] = sprintf(
                        'Row at index %s: %s',
                        $iIndex + 1,
                        $oUserModel->lastError()
                    );
                }

            } catch (\Exception $e) {
                $aError[] = sprintf(
                    'Row at index %s: %s',
                    $iIndex + 1,
                    $e->getMessage()
                );
            } catch (\Error $e) {
                $aError[] = sprintf(
                    'Row at index %s: %s',
                    $iIndex + 1,
                    $e->getMessage()
                );
            }
        }

        /** @var UserFeedback $oUserFeedback */
        $oUserFeedback = Factory::service('UserFeedback');

        if (!empty($iSuccess)) {
            $oUserFeedback->success(sprintf(
                '%s user accounts created successfully.',
                $iSuccess
            ));
        }

        if (!empty($aError)) {
            $oUserFeedback->success(sprintf(
                '%s user accounts failed to create: <br>&mdash;%s',
                count($aError),
                implode('<br>&mdash; ', $aError)
            ));
        }

        $oCdn->objectDestroy($iObjectId);

        redirect('admin/auth/import');
    }
}
