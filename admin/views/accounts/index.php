<div class="group-accounts all">
    <?php

        echo '<p>';

            echo isset($page->description) ? $page->description : lang('accounts_index_intro');

            if (user_has_permission('admin.accounts:0.can_create_user')) {

                echo anchor('admin/auth/accounts/create', lang('accounts_nav_create'), 'class="awesome small green right"');
            }

        echo '</p>';

        $this->load->view('admin/accounts/utilities/search');
        $this->load->view('admin/accounts/utilities/pagination');

    ?>
    <table>
        <thead>
            <tr>
                <th class="id"><?=lang('accounts_index_th_id')?></th>
                <th class="details"><?=lang('accounts_index_th_user')?></th>
                <th class="group"><?=lang('accounts_index_th_group')?></th>
                <?php

                    foreach ($columns as $col) {

                        echo isset($col['class']) ? '<th class="' . $col['class'] . '">' : '<th>';
                        echo $col['label'];
                        echo '</th>';
                    }

                ?>
                <th class="actions"><?=lang('accounts_index_th_actions')?></th>
            </tr>
        </thead>
        <tbody>
            <?php

                if ($users->data) {

                    foreach ($users->data as $member) {

                        $data = array(
                            'member' => &$member
                       );
                        $this->load->view('admin/accounts/utilities/user_row', $data);

                    }

                } else {

                    ?>
                    <tr>
                        <td colspan="<?=(4+count($columns))?>" class="no-data">
                            <p><?=lang('accounts_index_no_users')?></p>
                        </td>
                    </tr>
                    <?php
                }
            ?>
        </tbody>
    </table>
    <?php

        $this->load->view('admin/accounts/utilities/pagination');

    ?>
</div>
<script type="text/javascript">
<!--//

    $(function(){

        //  Hijack the fancybox links and inform the target the view is inline.
        $('a.fancybox-max').each(function() {

            $(this).fancybox({
                'type'      : 'iframe',
                'autoSize'  : false,
                'autoScale' : false,
                'width'     : '85%',
                'height'    : '85%',
                'href'      : $(this).attr('href') + '&inline=true'
            });
        });

    });

//-->
</script>