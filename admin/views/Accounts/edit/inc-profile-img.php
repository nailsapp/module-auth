<?php

use Nails\Auth\Resource\User;
use Nails\Common\Service\Input;
use Nails\Factory;

/**
 * @var User     $oUser
 * @var string[] $aUploadErrors
 */

/** @var Input $oInput */
$oInput = Factory::service('Input');

?>
<fieldset id="edit-user-profile-img">
    <legend>
        <?=lang('accounts_edit_img_legend')?>
    </legend>
    <div class="field <?=!empty($aUploadErrors) ? 'error' : ''?>">
        <?php

        if (empty($oUser->profile_img)) {

            echo img([
                'src'   => cdnBlankAvatar(100, 125, $oUser->gender),
                'id'    => 'preview_image',
                'class' => 'left img-thumbnail',
                'style' => 'margin-right:10px;',
            ]);
            echo form_upload('profile_img');

        } else {

            $aImg = [
                'src'   => cdnCrop($oUser->profile_img, 100, 125),
                'id'    => 'preview_image',
                'style' => 'border:1px solid #CCC;padding:0;margin-right:10px;',
                'class' => 'img-thumbnail',
            ];

            echo anchor(
                cdnServe($oUser->profile_img),
                img($aImg),
                'class="fancybox left"'
            );
            echo '<p>';
            echo form_upload('profile_img', null, 'style="float:none;"') . '<br />';
            $sReturn = '?return_to=' . urlencode(uri_string() . '?' . $oInput->server('QUERY_STRING'));
            echo anchor(
                'admin/auth/accounts/delete_profile_img/' . $oUser->id . $sReturn,
                lang('action_delete'),
                'class="btn btn-xs btn-danger confirm" data-body="This action is not undoable."'
            );
            echo '</p>';
        }

        if (!empty($aUploadErrors)) {

            echo '<span class="error">';
            foreach ($aUploadErrors as $err) {
                echo $err . '<br />';
            }
            echo '</span>';
        }

        ?>
        <div class="clear"></div>
    </div>
    <div class="clear"></div>
</fieldset>
