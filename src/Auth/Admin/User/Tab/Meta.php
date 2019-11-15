<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Constants;
use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Resource\User;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Exception\ViewNotFoundException;
use Nails\Common\Factory\Model\Field;
use Nails\Common\Service\Config;
use Nails\Common\Service\View;
use Nails\Factory;

/**
 * Class Meta
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Meta implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Meta';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return float|null
     */
    public function getOrder(): ?float
    {
        return 1;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the tab's body
     *
     * @param User $oUser The user being edited
     *
     * @return string
     * @throws FactoryException
     * @throws ViewNotFoundException
     */
    public function getBody(User $oUser): string
    {
        /** @var View $oView */
        $oView = Factory::service('View');

        return $oView
            ->load(
                [
                    'Accounts/edit/inc-meta',
                ],
                [
                    'oUser'     => $oUser,
                    'aMetaCols' => $this->getFieldsForGroup($oUser->group_id),
                ],
                true
            );
    }

    // --------------------------------------------------------------------------

    /**
     * Returns additional markup, outside of the main <form> element
     *
     * @param User $oUser The user being edited
     *
     * @return string
     */
    public function getAdditionalMarkup(User $oUser): string
    {
        return '';
    }

    // --------------------------------------------------------------------------

    /**
     * Returns an array of validation rules compatible with Validator objects
     *
     * @param User $oUser The user being edited
     *
     * @return array
     */
    public function getValidationRules(User $oUser): array
    {
        /** @var Field[] $aMetaCols */
        $aMetaCols = $this->getFieldsForGroup($oUser->group_id);
        $aRules    = [];

        foreach ($aMetaCols as $oField) {
            $aRules[$oField->key] = $oField->validation;
        }

        return $aRules;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the appropriate meta fields for the given user group
     *
     * @param int|null $iGroupId
     *
     * @return Field[]
     * @throws FactoryException
     */
    protected function getFieldsForGroup(int $iGroupId = null): array
    {
        /** @var \Nails\Auth\Model\User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');

        /** @var array|null $aDefinedCols */
        $aDefinedCols = $oConfig->item('user_meta_cols');
        /** @var Field[] $aMetaCols */
        $aMetaCols = $oUserModel->describeMetaFields();

        if ($iGroupId && is_array($aDefinedCols)) {

            $aGroupFields = getFromArray($iGroupId, $aDefinedCols);

            //  @todo (Pablo - 2019-11-15) - Remove backwards compatability
            $oFirst = reset($aGroupFields);
            if (!is_string($oFirst)) {
                $aGroupFields = array_keys($aGroupFields);
            }

            return array_filter($aMetaCols, function (Field $oField) use ($aGroupFields) {
                return in_array($oField->key, $aGroupFields);
            });
        } else {
            return $aMetaCols;
        }
    }
}
