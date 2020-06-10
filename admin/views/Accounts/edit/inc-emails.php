<?php

use Nails\Admin\Helper;
use Nails\Auth\Resource\User;

/**
 * @var User       $oUser
 * @var stdClass[] $aEmails
 */

?>
<table id="edit-user-emails" class="emails">
    <thead>
        <tr>
            <th class="email"><?=lang('accounts_edit_emails_th_email')?></th>
            <th class="is-primary"><?=lang('accounts_edit_emails_th_primary')?></th>
            <th class="is-verified"><?=lang('accounts_edit_emails_th_verified')?></th>
            <th class="date-added"><?=lang('accounts_edit_emails_th_date_added')?></th>
            <th class="date-verified"><?=lang('accounts_edit_emails_th_date_verified')?></th>
            <th class="actions"><?=lang('accounts_edit_emails_th_actions')?></th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($aEmails as $oEmail) {
            ?>
            <tr data-email="<?=$oEmail->email?>" class="existing-email">
                <td class="email">
                    <?=mailto($oEmail->email)?>
                </td>
                <?=Helper::loadBoolCell($oEmail->is_primary)?>
                <?=Helper::loadBoolCell($oEmail->is_verified)?>
                <td class="date-added">
                    <?=toUserDatetime($oEmail->date_added)?>
                </td>
                <?=Helper::loadDateTimeCell($oEmail->date_verified, lang('accounts_edit_emails_td_not_verified'))?>
                <td class="actions">
                    <?php
                    if (!$oEmail->is_primary) {
                        echo anchor(
                            '',
                            'Make Primary',
                            'data-action="make-primary" class="btn btn-xs btn-primary"'
                        );
                        echo anchor(
                            '',
                            'Delete',
                            'data-action="delete" class="btn btn-xs btn-danger"'
                        );
                    }

                    if (!$oEmail->is_verified) {
                        echo anchor(
                            '',
                            'Verify',
                            'data-action="verify" class="btn btn-xs btn-success"'
                        );
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
        ?>
        <tr id="add-email-form">
            <td class="email">
                <input type="email" name="email" placeholder="Type an email address to add to the user here"/>
            </td>
            <td class="is-primary">
                <input type="checkbox" name="is_primary" value="1"/>
            </td>
            <td class="is-verified">
                <input type="checkbox" name="is_verified" value="1"/>
            </td>
            <td class="date-added">
                <span class="text-muted">&mdash;</span>
            </td>
            <td class="date-verified">
                <span class="text-muted">&mdash;</span>
            </td>
            <td class="actions">
                <a href="#" class="submit btn btn-xs btn-success">Add Email</a>
            </td>
        </tr>
    </tbody>
</table>


