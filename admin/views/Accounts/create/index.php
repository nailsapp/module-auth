<div class="group-accounts create">
    <?=form_open()?>
    <p>
        <?=lang('accounts_create_intro')?>
    </p>
    <?php

    $this->load->view('Accounts/create/inc-basic');

    ?>
    <p>
        <?=form_submit('submit', lang('accounts_create_submit'), 'class="btn btn-primary"')?>
    </p>
    <?=form_close()?>
</div>
