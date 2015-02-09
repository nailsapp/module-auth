<div class="group-accounts all">
    <?php

        echo '<p>';

            echo 'This section lists all users registered on site. You can browse or search this ';
            echo 'list using the search facility below.';

        echo '</p>';

        echo \Nails\Admin\Helper::loadSearch($search);
        echo \Nails\Admin\Helper::loadPagination($pagination);

    ?>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="id">User ID</th>
                    <th class="details">User</th>
                    <th class="group">Group</th>
                    <?php

                    if (!empty($columns)) {

                        foreach ($columns as $col) {

                            echo isset($col['class']) ? '<th class="' . $col['class'] . '">' : '<th>';
                            echo $col['label'];
                            echo '</th>';
                        }
                    }

                    ?>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php

                    if ($users) {

                        foreach ($users as $member) {

                            $data = array(
                                'member' => &$member
                           );
                            $this->load->view('admin/accounts/utilities/user_row', $data);

                        }

                    } else {

                        $colspan = !empty($columns) ? 4 + count($columns) : 4;
                        ?>
                        <tr>
                            <td colspan="<?=$colspan?>" class="no-data">
                                <p>No Users Found</p>
                            </td>
                        </tr>
                        <?php
                    }
                ?>
            </tbody>
        </table>
    </div>
    <?php

        echo \Nails\Admin\Helper::loadPagination($pagination);

    ?>
</div>