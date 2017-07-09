<div class="group-accounts groups overview">
    <p>
        <?=lang('accounts_groups_index_intro')?>
    </p>
    <hr/>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="label"><?=lang('accounts_groups_index_th_name')?></th>
                    <th class="homepage"><?=lang('accounts_groups_index_th_homepage')?></th>
                    <th class="default"><?=lang('accounts_groups_index_th_default')?></th>
                    <th class="actions"><?=lang('accounts_groups_index_th_actions')?></th>
                </tr>
            </thead>
            <tbody>
                <?php

                foreach ($groups as $group) {

                    ?>
                    <tr>
                        <td class="label">
                            <strong><?=$group->label?></strong>
                            <small style="display:block;"><?=$group->description?></small>
                        </td>
                        <td class="homepage">
                            <span style="color:#ccc"><?=substr(site_url(), 0, -1)?></span><?=$group->default_homepage?>
                        </td>
                        <?php

                        echo adminHelper('loadBoolCell', $group->is_default);

                        ?>
                        <td class="actions">
                            <?php

                            if (userHasPermission('admin:auth:groups:edit')) {

                                echo anchor('admin/auth/groups/edit/' . $group->id, lang('action_edit'), 'class="btn btn-xs btn-primary"');
                            }

                            if (userHasPermission('admin:auth:groups:delete')) {

                                echo anchor('admin/auth/groups/delete/' . $group->id, lang('action_delete'), 'class="btn btn-xs btn-danger confirm" data-body="This action is also not undoable." data-title="Confirm Delete"');
                            }

                            if (userHasPermission('admin:auth:groups:setDefault') && !$group->is_default) {

                                echo anchor('admin/auth/groups/set_default/' . $group->id, lang('accounts_groups_index_action_set_default'), 'class="btn btn-xs btn-success"');
                            }

                            ?>
                        </td>
                    </tr>
                    <?php

                }

                ?>
            </tbody>
        </table>
    </div>
</div>
