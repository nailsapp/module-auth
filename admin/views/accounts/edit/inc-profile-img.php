<fieldset id="edit-user-profile-img">
    <legend><?=lang('accounts_edit_img_legend')?></legend>
    <?php

        $hasError = isset($upload_error) ? 'error' : NULL;

        echo '<div class="field ' . $hasError . '">';

        if (empty($user_edit->profile_img)) {

            echo img(array(
                'src' => cdn_blank_avatar(100, 125, $user_edit->gender),
                'id' => 'preview_image',
                'class' => 'left img-thumbnail',
                'style' => 'margin-right:10px;'
            ));
            echo form_upload('profile_img');

        } else {

            $img = array(
                'src'   => cdn_thumb($user_edit->profile_img, 100, 125),
                'id'    => 'preview_image',
                'style' => 'border:1px solid #CCC;padding:0;margin-right:10px;',
                'class' => 'img-thumbnail'
           );

            echo anchor(cdn_serve($user_edit->profile_img), img($img), 'class="fancybox left"');
            echo '<p>';
                echo form_upload('profile_img', NULL, 'style="float:none;"') . '<br />';
                $return = '?return_to=' . urlencode(uri_string() . '?' . $_SERVER['QUERY_STRING']);
                echo anchor(
                    'admin/auth/accounts/delete_profile_img/' . $user_edit->id . $return,
                    lang('action_delete'),
                    'class="awesome small red confirm" data-body="This action is not undoable."'
                );
            echo '</p>';
        }

        if ($hasError) {

            echo '<span class="error">';

            foreach ($upload_error as $err) {

                echo $err . '<br />';
            }

            echo '</span>';
        }

        echo '<div class="clear"></div>';
        echo '</div>';
    ?>

    <!--    CLEARFIX    -->
    <div class="clear"></div>
</fieldset>