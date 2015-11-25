<div class="group-accounts merge">
    <p>
        Use this tool to merge two or more accounts into one
    </p>
    <hr />
    <?=form_open(null, 'id="theForm"')?>
    <fieldset>
        <legend>User to Keep</legend>
        <p>
            This user account is the one you wish to merge other user's data into.
        </p>
        <p>
            <input type="text" id="userId" name="userId" />
        </p>
    </fieldset>
    <fieldset>
        <legend>Users to merge</legend>
        <p>
            These accounts will have their data merged into the above user and then be deleted.
        </p>
        <p>
            <input type="text" id="mergeIds" name="mergeIds" />
        </p>
    </fieldset>
    <?=form_submit('submit', 'Preview Merge', 'class="btn btn-success"')?>
    <?=form_close()?>
</div>