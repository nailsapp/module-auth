<fieldset id="edit-user-emails" class="emails">
    <legend><?=lang('accounts_edit_emails_legend')?></legend>
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

                    echo '<tr data-email="' . $email->email . '" class="existingEmail">';
                    echo '<td class="email">';
                        echo mailto($email->email);
                    echo '</td>';
                    if ($email->is_primary) {

                        echo '<td class="isPrimary success">';
                            echo  '<b class="fa fa-check-circle fa-lg"></b>';
                        echo '</td>';

                    } else {

                        echo '<td class="isPrimary error">';
                            echo  '<b class="fa fa-times-circle fa-lg"></b>';
                        echo '</td>';
                    }
                    if ($email->is_verified) {

                        echo '<td class="isVerified success">';
                            echo  '<b class="fa fa-check-circle fa-lg"></b>';
                        echo '</td>';

                    } else {

                        echo '<td class="isVerified error">';
                            echo  '<b class="fa fa-times-circle fa-lg"></b>';
                        echo '</td>';
                    }
                    echo '<td class="dateAdded">';
                        echo user_datetime($email->date_added);
                    echo '</td>';
                    echo '<td class="dateVerified">';
                        if ($email->is_verified) {

                            echo user_datetime($email->date_added);

                        } else {

                            echo '<span class="text-muted">';
                                echo lang('accounts_edit_emails_td_not_verified');
                            echo '</span>';
                        }
                    echo '</td>';
                    echo '<td class="actions">';

                        if (!$email->is_primary) {

                            echo anchor('', 'Make Primary', 'data-action="makePrimary" class="awesome small green"');
                            echo anchor('', 'Delete', 'data-action="delete" class="awesome small red"');

                        }

                        if (!$email->is_verified) {

                            echo anchor('', 'Verify', 'data-action="verify" class="awesome small green"');
                        }

                    echo '</td>';
                    echo '</tr>';
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
                        <a href="#" class="submit awesome small green">Add Email</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</fieldset>