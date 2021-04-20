<div class="group-accounts change-group">
    <?php

    use Nails\Factory;

    if (!empty($aUsers)) {
        $oInput   = Factory::service('Input');
        $sFormUrl = uri_string() . '?users=' . $oInput->get('users');
        echo form_open($sFormUrl);
        ?>
        <p>
            Use the following tool to change the group a user belongs to.
        </p>
        <hr/>
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
                    foreach ($aUsers as $oUser) {
                        ?>
                        <tr>
                            <td class="userId"><?=number_format($oUser->id)?></td>
                            <?=adminHelper('loadUserCell', $oUser)?>
                            <td><?=$oUser->group_name?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </fieldset>
        <fieldset>
            <legend>New Group</legend>
            <?=form_dropdown('group_id', $aUserGroups, null, 'class=select2')?>
        </fieldset>
        <?php
        echo \Nails\Admin\Helper::floatingControls([
            'save' => [
                'text' => 'Update User Groups'
            ]
        ]);
        echo \Nails\Factory::service('View')
            ->load(
                'admin/_components/floating-controls',
                [
                    ''
                ],
                true
            );
    }
    ?>
</div>
