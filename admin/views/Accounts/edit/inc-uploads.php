<?php

if (\Nails\Components::exists('nails/module-cdn')) {

    ?>
    <fieldset id="edit-user-uploads" class="uploads">
        <legend><?=lang('accounts_edit_upload_legend')?></legend>
        <ul>
            <?php

            if ($user_uploads) {

                foreach ($user_uploads as $file) {

                    echo '<li class="file">';

                    switch ($file->file->mime) {

                        case 'image/jpg':
                        case 'image/jpeg':
                        case 'image/gif':
                        case 'image/png':

                            echo '<a href="' . cdnServe($file->id) . '" class="fancybox image">';
                            echo img(cdnCrop($file->id, 35, 35));
                            echo $file->file->name->human;
                            echo '<small>Bucket: ' . $file->bucket->slug . '</small>';
                            echo '</a>';
                            break;

                        default:

                            echo anchor(cdnServe($file->id) . '?dl=1', $file->file->name->human . '<small>Bucket: ' . $file->bucket->slug . '</small>');
                            break;
                    }

                    echo '</li>';
                }

            } else {

                echo '<li class="no-data">' . lang('accounts_edit_upload_nofile') . '</li>';
            }

            ?>
        </ul>
    </fieldset>
    <?php

}
