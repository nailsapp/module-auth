<?php

if (isModuleEnabled('nailsapp/module-cdn')) {

    ?>
    <fieldset  id="edit-user-uploads" class="uploads">
        <legend><?=lang('accounts_edit_upload_legend')?></legend>
        <p>
        <?php

            echo '<ul>';

            if ($user_uploads) {

                foreach ($user_uploads as $file) {

                    echo '<li class="file">';

                    switch ($file->mime) {

                        case 'image/jpg':
                        case 'image/jpeg':
                        case 'image/gif':
                        case 'image/png':

                            echo '<a href="' . cdnServe($file->id) . '" class="fancybox image">';
                            echo img(cdnCrop($file->id, 35, 35));
                            echo $file->filename_display;
                            echo '<small>Bucket: ' . $file->bucket->slug . '</small>';
                            echo '</a>';
                            break;

                        default:

                            echo anchor(cdnServe($file->id) . '?dl=1', $file->filename_display . '<small>Bucket: ' . $file->bucket->slug . '</small>');
                            break;
                    }

                    echo '</li>';
                }

            } else {

                echo '<li class="no-data">' . lang('accounts_edit_upload_nofile') . '</li>';
            }

            echo '</ul>';

        ?>
        </p>
    </fieldset>
    <?php

}