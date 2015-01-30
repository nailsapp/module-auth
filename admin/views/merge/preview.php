<div class="group-accounts merge merge-preview">
    <p class="system-alert message">
        <strong>1.</strong> The following user will be kept.
    </p>
    <table>
        <thead>
            <tr>
                <th class="userId">ID</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="userId"><?=number_format($mergeResult->user->id)?></td>
                <?php $this->load->view('admin/_utilities/table-cell-user', $mergeResult->user); ?>
            </tr>
        </tbody>
    </table>
    <p class="system-alert message">
        <strong>2.</strong> Data in the tables listed below will be merged into the above user.
        <?php

            if (!empty($mergeResult->ignoreTables)) {

                echo 'Please note that the following tables will not be merged and any data in them which belongs to ';
                echo 'the users listed below will be deleted: <br />';
                echo '<code>' . implode('</code>, <code>', $mergeResult->ignoreTables) . '</code>';
            }

        ?>
    </p>
    <table>
        <thead>
            <tr>
                <th>Table</th>
                <th>Rows to merge</th>
            </tr>
        </thead>
        <tbody>
        <?php

            foreach ($mergeResult->tables as $table) {

                echo '<tr>';
                    echo '<td class="tableName">' . $table->name . '</td>';
                    echo '<td class="tableRows">' . $table->numRows . '</td>';
                echo '</tr>';
            }

        ?>
        </tbody>
    </table>
    <p class="system-alert message">
        <strong>3.</strong> The following users will be deleted after data is merged.
    </p>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
            </tr>
        </thead>
        <tbody>
        <?php

            foreach ($mergeResult->merge as $mergeUser) {

                echo '<tr>';
                    echo '<td class="userId">' . number_format($mergeUser->id) . '</td>';
                    $this->load->view('admin/_utilities/table-cell-user', $mergeUser);
                echo '</tr>';
            }

        ?>
        </tbody>
    </table>
</div>
<?php

    echo form_open();
    echo form_hidden('userId', $this->input->post('userId'));
    echo form_hidden('mergeIds', $this->input->post('mergeIds'));
    echo form_hidden('doMerge', true);
    echo form_submit('submit', 'Perform Merge', 'class="awesome green"');
    echo form_close();
