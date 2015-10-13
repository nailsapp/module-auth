<div class="container nails-module-auth register register-resend">
    <?php

    $this->load->view('components/header');

    ?>
    <div class="container">
        <div class="col-sm-6 col-sm-offset-3">
            <p class="alert alert-success">
                <?=lang( 'auth_register_resend_message', $email )?>
            </p>
            <h3><?=lang( 'auth_register_resend_next_title' )?></h3>
            <p>
                <?=lang( 'auth_register_resend_next_message' )?>
            </p>
        </div>
    </div>
    <?php

    $this->load->view('components/footer');

    ?>
</div>
