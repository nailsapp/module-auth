<div class="group-accounts change-group">
    <p>
        Use the following tool to change the group a user belongs to.
    </p>
    <hr />
    <?php

        $formUrl = uri_string() . '?users=' . $this->input->get('users');
        echo form_open($formUrl);

    ?>
    <fieldset>
        <legend>Users to Update</legend>
        <table>
            <thead>
                <tr>
                    <th class="userId">ID</th>
                    <th>Name</th>
                    <th>Current Group</th>
                </tr>
            </thead>
            <tbody>
            <?php

                foreach ($users as $theUser) {

                echo '<tr>';
                    echo '<td class="userId">' . number_format($theUser->id) . '</td>';
                    $this->load->view('admin/_utilities/table-cell-user', $theUser);
                    echo '<td>' . $theUser->group_name . '</td>';
                echo '</tr>';

                }

            ?>
            </tbody>
        </table>
    </fieldset>
    <fieldset>
        <legend>New Group</legend>
        <select name="newGroupId" class="select2">
        <?php

        foreach ($userGroups as $id => $label) {

            echo '<option value="' . $id. '">';
                echo $label;
            echo '</option>';
        }

        ?>
        </select>
    </fieldset>
    <?php

        echo form_submit('submit', 'Update User Groups', 'class="awesome green"');
        echo form_close();

    ?>
</div>