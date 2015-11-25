<div class="group-accounts create">
    <?=form_open()?>
        <p>
            <?=lang('accounts_create_intro')?>
        </p>
        <?php

        $this->load->view('accounts/create/inc-basic');

        ?>
        <p>
            <?=form_submit('submit', lang('accounts_create_submit'), 'class="awesome"')?>
        </p>
    <?=form_close()?>
</div>
