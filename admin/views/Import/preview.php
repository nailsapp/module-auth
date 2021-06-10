<div class="module-auth import import--preview">
    <div class="alert alert-warning">
        <strong>Please review the following data</strong>
        <br>Your CSV has been processed and the following values have been ascertained. Please verify them, and when
        happy to continue click "Import" below.
    </div>
    <?=form_open()?>
    <?=form_hidden('object_id', $oObject->id)?>
    <table>
        <thead>
            <tr>
                <?php
                foreach ($aFields as $sField) {
                    ?>
                    <th><?=$sField?></th>
                    <?php
                }
                ?>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php

            foreach ($aData as $aDatum) {
                $aDatum = array_combine($aHeader, $aDatum);
                ?>
                <tr>
                    <?php
                    foreach ($aFields as $sField) {
                        ?>
                        <td><?=$aDatum[$sField] ?? '-'?></td>
                        <?php
                    }
                    ?>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
    <?=\Nails\Admin\Helper::floatingControls([
        'save' => [
            'text'  => 'Import',
            'name'  => 'action',
            'value' => 'import',
        ],
    ])?>
    <?=form_close()?>
</div>
