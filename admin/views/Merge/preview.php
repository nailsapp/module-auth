<div class="group-accounts merge merge-preview">
    <p class="alert alert-warning">
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
            <?php echo adminHelper('loadUserCell', $mergeResult->user); ?>
        </tr>
        </tbody>
    </table>
    <p class="alert alert-warning">
        <strong>2.</strong> Data in the tables listed below will be merged into the above user.
        <?php

        if (!empty($mergeResult->ignoreTables)) {

            ?>
            Please note that the following tables will not be merged and any data in them which belongs to
            the users listed below will be deleted:
            <br/>
            <code><?=implode('</code>, <code>', $mergeResult->ignoreTables)?></code>';
            <?php
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
            ?>
            <tr>
                <td class="tableName"><?=$table->name?></td>
                <td class="tableRows"><?=$table->numRows?></td>
            </tr>
            <?php
        }

        ?>
        </tbody>
    </table>
    <p class="alert alert-warning">
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
            ?>
            <tr>
                <td class="userId"><?=number_format($mergeUser->id)?></td>
                <?=adminHelper('loadUserCell', $mergeUser)?>
            </tr>
            <?php
        }
        ?>
        </tbody>
    </table>
</div>
<?php

$oInput = \Nails\Factory::service('Input');

echo form_open();
echo form_hidden('user_id', $oInput->post('user_id'));
echo form_hidden('merge_ids', $oInput->post('merge_ids'));
echo form_hidden('do_merge', true);
echo form_submit('submit', 'Perform Merge', 'class="btn btn-success"');
echo form_close();
