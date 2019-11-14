<?php

namespace Nails\Auth\Auth\Admin\User\Tab;

use Nails\Auth\Interfaces\Admin\User\Tab;
use Nails\Auth\Resource\User;
use Nails\Common\Service\DateTime;
use Nails\Common\Service\View;
use Nails\Factory;

/**
 * Class Details
 *
 * @package Nails\Auth\Auth\Admin\User\Tab
 */
class Details implements Tab
{
    /**
     * Return the tab's label
     *
     * @return string
     */
    public function getLabel(): string
    {
        return 'Details';
    }

    // --------------------------------------------------------------------------

    /**
     * Return the order in which the tabs should render
     *
     * @return int|null
     */
    public function getOrder(): ?int
    {
        return 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Return the tab's body
     *
     * @return string
     */
    public function getBody(User $oUser): string
    {
        /** @var View $oView */
        $oView = Factory::service('View');
        /** @var DateTime $oDateTimeService */
        $oDateTimeService = Factory::service('DateTime');

        return $oView
            ->load(
                [
                    'Accounts/edit/inc-profile-img',
                    'Accounts/edit/inc-basic',
                ],
                [
                    'oUser'            => $oUser,
                    'sDefaultTimezone' => $oDateTimeService->getTimezoneDefault(),
                    'aTimezones'       => $oDateTimeService->getAllTimezone(),
                    'aDateFormats'     => $oDateTimeService->getAllDateFormat(),
                    'aTimeFormats'     => $oDateTimeService->getAllTimeFormat(),
                    //  @todo (Pablo - 2019-11-14) - Handle these
                    'aUploadErrors'    => [],
                ],
                true
            );
    }
}
