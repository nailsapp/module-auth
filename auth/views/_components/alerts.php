<?php

$aAlerts = [
    ['danger', $error ?? null],
    ['danger', $negative ?? null],
    ['success', $success ?? null],
    ['success', $positive ?? null],
    ['info', $info ?? null],
    ['warning', $warning ?? null],

    //  @deprecated
    ['warning', $message ?? null],
    ['info', $notice ?? null],
];

foreach ($aAlerts as $aAlert) {
    [$sClass, $sMessage] = $aAlert;
    if (!empty($sMessage)) {
        ?>
        <div class="alert alert--<?=$sClass?>">
            <?=$sMessage?>
        </div>
        <?php
    }
}
