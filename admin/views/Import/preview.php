<div class="module-auth import import--preview">
    <div class="alert alert-warning">
        <strong>Please review the following data</strong>
        <br>Your CSV has been processed and the following values have been ascertained. Please verify them, and when
        happy to continue click "Import" below.
    </div>
    <?=form_open()?>
    <?=form_hidden('object_id', $oObject->id)?>
    <?=form_hidden('skip_existing', $bSkipExisting)?>
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
                if (is_string($aDatum)) {
                    ?>
                    <tr class="danger">
                        <td colspan="10000">
                            <?=$aDatum?>
                        </td>
                    </tr>
                    <?php
                } else {
                    echo '<tr>';
                    foreach ($aFields as $sField) {
                        ?>
                        <td><?=$aDatum[$sField] ?? '-'?></td>
                        <?php
                    }
                    echo '</tr>';
                }
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
