<?php

namespace Nails\Auth\Admin\Dashboard\Widget;

use Nails\Admin\Service;
use Nails\Admin\Admin\Dashboard\Widget\Base;
use Nails\Auth\Constants;
use Nails\Auth\Model\User;
use Nails\Auth\Model\User\Group;
use Nails\Factory;

/**
 * Class USers
 *
 * @package Nails\Auth\Admin\Dashboard\Widget
 */
class Users extends Base
{
    /**
     * Whether to pad the body or not
     */
    const PAD_BODY = false;

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Users';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Renders a table of the site\'s user groups with top-line numbers about the users they contain.';
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getImage(): ?string
    {
        return null;
    }

    // --------------------------------------------------------------------------

    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        /** @var Group $oGroupModel */
        $oGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);

        /** @var \DateTime $oNow */
        $oNow = Factory::factory('DateTime');

        $aBody = [];
        foreach ($oGroupModel->getAll() as $oGroup) {
            $aBody[] = sprintf(
                <<<EOT
                <tr>
                    <td>%s</td>
                    <td style="text-align: center;">%s</td>
                    <td style="text-align: center;">%s</td>
                    <td style="text-align: center;">%s</td>
                    <td style="text-align: center;">%s</td>
                </tr>
                EOT,
                sprintf(
                    '%s<small>%s</small>',
                    $oGroup->label,
                    $oGroup->description
                ),
                number_format($oUserModel->countAll([
                    'where' => [
                        ['group_id', $oGroup->id],
                    ],
                ])),
                number_format($oUserModel->countAll([
                    'where' => [
                        ['group_id', $oGroup->id],
                        ['is_suspended', true],
                    ],
                ])),
                number_format($oUserModel->countAll([
                    'where' => [
                        ['group_id', $oGroup->id],
                        ['is_suspended', false],
                        ['last_seen >', $oNow->sub(new \DateInterval('P7D'))->format('Y-m-d H:i:s')],
                    ],
                ])),
                number_format($oUserModel->countAll([
                    'where' => [
                        ['group_id', $oGroup->id],
                        ['is_suspended', false],
                        ['last_seen <', $oNow->sub(new \DateInterval('P7D'))->format('Y-m-d H:i:s')],
                    ],
                ])),
            );
        }

        $sBody = implode(PHP_EOL, $aBody);

        return <<<EOT
            <table>
                <thead>
                    <tr>
                        <th style="vertical-align: middle;">
                            Group
                        </th>
                        <th style="width: 100px; vertical-align: middle; text-align: center;">
                            Registered
                        </th>
                        <th style="width: 100px; vertical-align: middle; text-align: center;">
                            Suspended
                        </th>
                        <th style="width: 100px; vertical-align: middle; text-align: center;">
                            Seen Recently
                            <br><small>within 7 days</small>
                        </th>
                        <th style="width: 100px; vertical-align: middle; text-align: center;">
                            Not Seen Recently
                            <br><small>over 7 days</small>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    $sBody
                </tbody>
            </table>
            EOT;
    }
}
