<div class="group-accounts merge">
    <p>
        Use this tool to merge two or more accounts into one
    </p>
    <hr/>
    <?=form_open(null, 'id="theForm"')?>
    <fieldset>
        <legend>User to Keep</legend>
        <p>
            This user account is the one you wish to merge other user's data into.
        </p>
        <p>
            <input type="text" name="user_id" class="user-search" value="<?=set_value('user_id')?>"/>
        </p>
        <?=form_error('user_id', '<p class="alert alert-danger">', '</p>')?>
    </fieldset>
    <fieldset>
        <legend>Users to merge</legend>
        <p>
            These accounts will have their data merged into the above user and then be deleted.
        </p>
        <p>
            <input type="text" id="merge-ids" name="merge_ids" class="user-search" data-multiple="true" value="<?=set_value('merge_ids')?>"/>
        </p>
        <?=form_error('merge_ids', '<p class="alert alert-danger">', '</p>')?>
    </fieldset>
    <?=form_submit('submit', 'Preview Merge', 'class="btn btn-success"')?>
    <?=form_close()?>
</div>
