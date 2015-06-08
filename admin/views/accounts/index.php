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
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php

            if ($users) {

                foreach ($users as $member) {

                    echo '<tr>';
                        echo '<td class="id">';
                            echo number_format($member->id);
                        echo '</td>';

                        echo '<td class="details">';

                            if ($member->profile_img) {

                                echo anchor(
                                    cdn_serve($member->profile_img),
                                    img(array(
                                        'src' => cdn_thumb($member->profile_img, 65, 65),
                                        'class' => 'profile-img'
                                    )),
                                    'class="fancybox"'
                                );

                            } else {

                                echo img(array(
                                    'src' => cdn_blank_avatar(65, 65, $member->gender),
                                    'class' => 'profile-img'
                                ));
                            }

                            echo '<div>';

                            switch ($this->input->get('sort')) {

                                case 'u.last_name':

                                    echo '<strong>' . $member->last_name . ', ' . $member->first_name . '</strong>';
                                    break;

                                default:

                                    echo '<strong>' . $member->first_name . ' ' . $member->last_name . '</strong>';
                                    break;
                            }

                            echo '<small>';

                                echo $member->email;

                                if ($member->email_is_verified) {

                                    echo '<span class="verified" rel="tipsy" title="Verified email address">';
                                        echo '&nbsp;<span class="fa fa-check-circle"></span>';
                                    echo '<span>';
                                }

                            echo '</small>';

                            echo '<small>';
                            if ($member->last_login) {

                                echo 'Last login: ';
                                echo '<span class="nice-time">' . toUserDate($member->last_login, 'Y-m-d H:i:s') . '</span> ';
                                echo '(' . $member->login_count . ' logins)';

                            } else {

                                echo 'Last login: Never Logged In';
                            }
                            echo '</small>';
                            echo '</div>';

                        echo '</td>';
                        echo '<td class="group">';
                            echo $member->group_name;
                        echo '</td>';

                        echo '<td class="actions">';

                            //  Actions, only super users can do anything to other superusers
                            if (!$this->user_model->isSuperuser() && $this->user_model->isSuperuser($member)) {

                                //  Member is a superuser and the admin is not a super user, no editing facility
                                echo '<span class="not-editable">';
                                    echo 'You do not have permission to perform manipulations on this user.';
                                echo '</span>';

                            } else {

                                $return  = uri_string() . '?' . $this->input->server('QUERY_STRING');
                                $return  = '?return_to=' . urlencode($return);
                                $buttons = array();

                                //  Login as?
                                if ($member->id != activeUser('id') && userHasPermission('admin:auth:accounts:loginAs')) {

                                    //  Generate the return string
                                    $url = uri_string();

                                    if ($this->input->get()) {

                                        //  Remove common problematic GET vars (for instance, we don't want isModal when we return)
                                        $params = $this->input->get();
                                        unset($params['isModal']);

                                        if ($params) {

                                            $url .= '?' . http_build_query($params);
                                        }
                                    }

                                    $return_string = '?return_to=' . urlencode($url);

                                    // --------------------------------------------------------------------------

                                    $url = site_url('auth/override/login_as/' . md5($member->id) . '/' . md5($member->password) . $return_string);

                                    $buttons[] = anchor($url, lang('admin_login_as'), 'class="awesome small"');
                                }

                                // --------------------------------------------------------------------------

                                //  Edit
                                if ($member->id == activeUser('id') || userHasPermission('admin:auth:accounts:editOthers')) {

                                    $buttons[] = anchor(
                                        'admin/auth/accounts/edit/' . $member->id . $return,
                                        lang('action_edit'),
                                        'data-fancybox-type="iframe" class="edit awesome small"'
                                    );
                                }

                                // --------------------------------------------------------------------------

                                //  Suspend user
                                if ($member->is_suspended) {

                                    if ($member->id != activeUser('id') && userHasPermission('admin:auth:accounts:unsuspend')) {

                                        $buttons[] = anchor(
                                            'admin/auth/accounts/unsuspend/' . $member->id . $return,
                                            lang('action_unsuspend'),
                                            'class="awesome small green"'
                                        );
                                    }

                                } else {

                                    if ($member->id != activeUser('id') && userHasPermission('admin:auth:accounts:suspend')) {

                                        $buttons[] = anchor(
                                            'admin/auth/accounts/suspend/' . $member->id . $return,
                                            lang('action_suspend'),
                                            'class="awesome small red"'
                                        );
                                    }
                                }

                                // --------------------------------------------------------------------------

                                //  Delete user
                                if ($member->id != activeUser('id') && userHasPermission('admin:auth:accounts:delete') && !$this->user_model->isSuperuser($member->id)) {

                                    $buttons[] = anchor(
                                        'admin/auth/accounts/delete/' . $member->id . $return,
                                        lang('action_delete'),
                                        'class="confirm awesome small red" data-title="Delete user &quot;' . $member->first_name . ' ' . $member->last_name . '&quot?" data-body="Are you sure you want to delete this user? This action is not undoable."'
                                    );
                                }

                                // --------------------------------------------------------------------------

                                //  Update user's group
                                if (userHasPermission('admin:auth:accounts:changeUserGroup')) {
                                    //  If this user is a super user and the current user is not a super user then don't allow this option
                                    if ($this->user_model->isSuperuser($member->id) && !$this->user_model->isSuperuser()) {
                                        //  Nothing
                                    } else {
                                        $buttons[] = anchor(
                                            'admin/auth/accounts/change_group?users=' . $member->id,
                                            'Edit Group',
                                            'class="awesome small"'
                                        );
                                    }
                                }

                                // --------------------------------------------------------------------------

                                //  These buttons are variable between views
                                if (!empty($actions)) {

                                    foreach ($actions as $button) {

                                        $buttons[] = anchor(
                                            $button['url'] . $return,
                                            $button['label'],
                                            'class="awesome small ' . $button['class'] . '"'
                                        );
                                    }
                                }

                                // --------------------------------------------------------------------------

                                //  Render all the buttons, if any
                                if ($buttons) {

                                    foreach ($buttons as $button) {

                                        echo $button;
                                    }

                                } else {

                                    echo '<span class="not-editable">' . lang('accounts_index_noactions') . '</span>';
                                }
                            }

                        echo '</td>';
                    echo '</tr>';
                }

            } else {

                echo '<tr>';
                    echo '<td colspan="4" class="no-data">';
                        echo '<p>No Users Found</p>';
                    echo '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php

        echo \Nails\Admin\Helper::loadPagination($pagination);

    ?>
</div>
