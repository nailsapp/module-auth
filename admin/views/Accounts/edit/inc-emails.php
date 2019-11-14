<fieldset id="edit-user-emails" class="emails">
    <legend>
        <?=lang('accounts_edit_emails_legend')?>
    </legend>
    <div class="box-container">
        <table>
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
                foreach ($user_emails as $email) {
                    ?>
                    <tr data-email="<?=$email->email?>" class="existing-email">
                        <td class="email">
                            <?=mailto($email->email)?>
                        </td>
                        <?php

                        if ($email->is_primary) {
                            ?>
                            <td class="is-primary success">
                                <b class="fa fa-check-circle fa-lg"></b>
                            </td>
                            <?php
                        } else {
                            ?>
                            <td class="is-primary error">
                                <b class="fa fa-times-circle fa-lg"></b>
                            </td>
                            <?php
                        }

                        if ($email->is_verified) {
                            ?>
                            <td class="is-verified success">
                                <b class="fa fa-check-circle fa-lg"></b>
                            </td>
                            <?php
                        } else {
                            ?>
                            <td class="is-verified error">
                                <b class="fa fa-times-circle fa-lg"></b>
                            </td>
                            <?php
                        }
                        ?>
                        <td class="date-added">
                            <?=toUserDatetime($email->date_added)?>
                        </td>
                        <td class="date-verified">
                            <?php
                            if ($email->is_verified) {
                                echo toUserDatetime($email->date_added);
                            } else {
                                ?>
                                <span class="text-muted">
                                    <?=lang('accounts_edit_emails_td_not_verified')?>
                                </span>
                                <?php
                            }
                            ?>
                        </td>
                        <td class="actions">
                            <?php
                            if (!$email->is_primary) {
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

                            if (!$email->is_verified) {
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
                        <input type="email" name="email" placeholder="Type an email address to add to the user here" />
                    </td>
                    <td class="is-primary">
                        <input type="checkbox" name="isPrimary" value="1" />
                    </td>
                    <td class="is-verified">
                        <input type="checkbox" name="isVerified" value="1" />
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
    </div>
</fieldset>

