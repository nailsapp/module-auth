<fieldset id="edit-user-emails" class="emails">
    <legend>
        <?=lang('accounts_edit_emails_legend')?>
    </legend>
    <div class="box-container">
        <table>
            <thead>
                <tr>
                    <th class="email"><?=lang('accounts_edit_emails_th_email')?></th>
                    <th class="isPrimary"><?=lang('accounts_edit_emails_th_primary')?></th>
                    <th class="isVerified"><?=lang('accounts_edit_emails_th_verified')?></th>
                    <th class="dateAdded"><?=lang('accounts_edit_emails_th_date_added')?></th>
                    <th class="dateVerified"><?=lang('accounts_edit_emails_th_date_verified')?></th>
                    <th class="actions"><?=lang('accounts_edit_emails_th_actions')?></th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($user_emails as $email) {

                    ?>
                    <tr data-email="<?=$email->email?>" class="existingEmail">
                        <td class="email">
                            <?=mailto($email->email)?>
                        </td>
                        <?php

                        if ($email->is_primary) {

                            ?>
                            <td class="isPrimary success">
                                <b class="fa fa-check-circle fa-lg"></b>
                            </td>
                            <?php

                        } else {

                            ?>
                            <td class="isPrimary error">
                                <b class="fa fa-times-circle fa-lg"></b>
                            </td>
                            <?php

                        }
                        if ($email->is_verified) {

                            ?>
                            <td class="isVerified success">
                                <b class="fa fa-check-circle fa-lg"></b>
                            </td>
                            <?php

                        } else {

                            ?>
                            <td class="isVerified error">
                                <b class="fa fa-times-circle fa-lg"></b>
                            </td>
                            <?php

                        }

                        ?>
                        <td class="dateAdded">
                            <?=toUserDatetime($email->date_added)?>
                        </td>
                        <td class="dateVerified">
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
                                    'data-action="makePrimary" class="btn btn-xs btn-primary"'
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
                <tr id="addEmailForm">
                    <td class="email">
                        <input type="email" name="email" placeholder="Type an email address to add to the user here"/>
                    </td>
                    <td class="isPrimary">
                        <input type="checkbox" name="isPrimary" value="1"/>
                    </td>
                    <td class="isVerified">
                        <input type="checkbox" name="isVerified" value="1"/>
                    </td>
                    <td class="dateAdded">
                        <span class="text-muted">&mdash;</span>
                    </td>
                    <td class="dateVerified">
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