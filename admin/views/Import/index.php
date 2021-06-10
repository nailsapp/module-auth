<div class="module-auth import">
    <?=form_open_multipart()?>
    <fieldset>
        <legend>File</legend>
        <?php
        echo form_field_upload([
            'key'    => 'csv',
            'label'  => 'CSV',
            'info'   => anchor('admin/auth/import/template', 'Download a template CSV file', 'style="text-decoration: underline;"'),
            'accept' => 'text/csv',
        ]);
        ?>
    </fieldset>
    <p class="alert alert-warning">
        <strong>Please note:</strong> The CSV you supply should be in the correct format, as per the template which you
        can download above. Remember to include the header rows describing each column.
    </p>
    <p>
        <button type="submit" name="action" value="preview" class="btn btn-primary">
            Preview
        </button>
    </p>
    <?=form_close()?>
</div>
