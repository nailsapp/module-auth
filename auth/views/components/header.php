<?php

if (!isset($paths)) {

    $paths = array();
}

if (defined('FCPATH') && defined('BASE_URL')) {

    $paths[] = array(
        FCPATH . 'assets/img/logo.png',
        BASE_URL . 'assets/img/logo.png'
    );

    $paths[] = array(
        FCPATH . 'assets/img/logo.jpg',
        BASE_URL . 'assets/img/logo.jpg'
    );

    $paths[] = array(
        FCPATH . 'assets/img/logo.gif',
        BASE_URL . 'assets/img/logo.gif'
    );

    $paths[] = array(
        FCPATH . 'assets/img/logo/logo.png',
        BASE_URL . 'assets/img/logo/logo.png'
    );

    $paths[] = array(
        FCPATH . 'assets/img/logo/logo.jpg',
        BASE_URL . 'assets/img/logo/logo.jpg'
    );

    $paths[] = array(
        FCPATH . 'assets/img/logo/logo.gif',
        BASE_URL . 'assets/img/logo/logo.gif'
    );

    if (NAILS_BRANDING) {

        $paths[] = array(
            FCPATH . 'vendor/nailsapp/module-asset/assets/img/nails/icon/icon@2x.png',
            BASE_URL . 'vendor/nailsapp/module-asset/assets/img/nails/icon/icon@2x.png'
        );
    }
}

foreach ($paths as $path) {

    if (is_file($path[0])) {

    ?>
    <h1>
        <div id="logo-container">
            <img src="<?=$path[1]?>" id="logo" />
        </div>
    </h1>
    <?php
        break;
    }
}

if ($success || $error || $message || $notice) {

    ?>
    <div class="row">
        <?php

        if (!empty($success)) {
            ?>
            <div class="col-sm-6 col-sm-offset-3">
                <p class="alert alert-success">
                    <?=$success?>
                </p>
            </div>
            <?php
        }

        if (!empty($error)) {
            ?>
            <div class="col-sm-6 col-sm-offset-3">
                <p class="alert alert-danger">
                    <?=$error?>
                </p>
            </div>
            <?php
        }

        if (!empty($message)) {
            ?>
            <div class="col-sm-6 col-sm-offset-3">
                <p class="alert alert-warning">
                    <?=$message?>
                </p>
            </div>
            <?php
        }

        if (!empty($notice)) {
            ?>
            <div class="col-sm-6 col-sm-offset-3">
                <p class="alert alert-info">
                    <?=$notice?>
                </p>
            </div>
            <?php
        }

        ?>
    </div>
    <?php

}