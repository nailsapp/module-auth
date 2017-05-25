<div class="group-accounts all">
    <p>
        This section lists all users registered on site. You can browse or search this  list using
        the search facility below.
    </p>
    <?php

        echo adminHelper('loadSearch', $search);
        echo adminHelper('loadPagination', $pagination);

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

                    $sPermPrefix    = 'admin:auth:accounts:';
                    $bIsActiveUser  = $member->id == activeUser('id');
                    $bActiveIsSuper = isSuperuser();
                    $bMemberIsSuper = isSuperuser($member);
                    $sMemberNameFL  = $member->first_name . ' ' . $member->last_name;
                    $sMemberNameLF  = $member->last_name . ', ' . $member->first_name;

                    ?>
                    <tr>
                        <td class="id">
                            <?=number_format($member->id)?>
                        </td>
                        <td class="details">
                            <?php

                            if ($member->profile_img) {

                                echo anchor(
                                    cdnServe($member->profile_img),
                                    img(array(
                                        'src' => cdnCrop($member->profile_img, 65, 65),
                                        'class' => 'profile-img'
                                    )),
                                    'class="fancybox"'
                                );

                            } else {

                                echo img(array(
                                    'src' => cdnBlankAvatar(65, 65, $member->gender),
                                    'class' => 'profile-img'
                                ));
                            }

                            ?>
                            <div>
                                <?php

                                switch ($this->input->get('sort')) {

                                    case 'u.last_name':

                                        echo '<strong>' . $sMemberNameLF . '</strong>';
                                        break;

                                    default:

                                        echo '<strong>' . $sMemberNameFL . '</strong>';
                                        break;
                                }

                                ?>
                                <small>
                                    <?php

                                    echo $member->email;

                                    if ($member->email_is_verified) {

                                        echo '<span class="verified" rel="tipsy" title="Verified email address">';
                                            echo '&nbsp;<span class="fa fa-check-circle"></span>';
                                        echo '<span>';
                                    }

                                    ?>
                            </small>
                            <small>
                                Last login:
                                <?php

                                if ($member->last_login) {

                                    echo '<span class="nice-time">';
                                    echo toUserDate($member->last_login, 'Y-m-d H:i:s');
                                    echo '</span> ';
                                    echo '(' . $member->login_count . ' logins)';

                                } else {

                                    echo 'Never Logged In';
                                }

                                ?>
                            </small>
                            </div>
                        </td>
                        <td class="group">
                            <?=$member->group_name?>
                        </td>
                        <td class="actions">
                            <?php

                            //  Actions, only super users can do anything to other superusers
                            if (!$bActiveIsSuper && $bMemberIsSuper) {

                                //  Member is a superuser and the admin is not a super user, no editing facility
                                ?>
                                <span class="not-editable">
                                    You do not have permission to perform manipulations on this user.
                                </span>
                                <?php

                            } else {

                                $return  = uri_string() . '?' . $this->input->server('QUERY_STRING');
                                $return  = '?return_to=' . urlencode($return);
                                $buttons = array();

                                //  Login as?
                                if (!$bIsActiveUser && userHasPermission($sPermPrefix. 'loginAs')) {

                                    //  Generate the return string
                                    $url = uri_string();

                                    if ($this->input->get()) {

                                        /**
                                         * Remove common problematic GET vars (for instance, we don't want
                                         * isModal when we return)
                                         */

                                        $params = $this->input->get();
                                        unset($params['isModal']);

                                        if ($params) {

                                            $url .= '?' . http_build_query($params);
                                        }
                                    }

                                    $return_string = '?return_to=' . urlencode($url);

                                    // --------------------------------------------------------------------------

                                    $url = site_url(
                                        'auth/override/login_as/' . md5($member->id) . '/' . md5($member->password) . $return_string
                                    );

                                    $buttons[] = anchor($url, lang('admin_login_as'), 'class="btn btn-xs btn-warning"');
                                }

                                // --------------------------------------------------------------------------

                                //  Edit
                                if ($bIsActiveUser || userHasPermission($sPermPrefix . 'editOthers')) {

                                    $buttons[] = anchor(
                                        'admin/auth/accounts/edit/' . $member->id . $return,
                                        lang('action_edit'),
                                        'data-fancybox-type="iframe" class="edit btn btn-xs btn-primary"'
                                    );
                                }

                                // --------------------------------------------------------------------------

                                //  Suspend user
                                if ($member->is_suspended) {

                                    if (!$bIsActiveUser && userHasPermission($sPermPrefix . 'unsuspend')) {

                                        $buttons[] = anchor(
                                            'admin/auth/accounts/unsuspend/' . $member->id . $return,
                                            lang('action_unsuspend'),
                                            'class="btn btn-xs btn-success"'
                                        );
                                    }

                                } else {

                                    if (!$bIsActiveUser && userHasPermission($sPermPrefix . 'suspend')) {

                                        $buttons[] = anchor(
                                            'admin/auth/accounts/suspend/' . $member->id . $return,
                                            lang('action_suspend'),
                                            'class="btn btn-xs btn-danger"'
                                        );
                                    }
                                }

                                // --------------------------------------------------------------------------

                                //  Delete user
                                if (!$bIsActiveUser && userHasPermission($sPermPrefix . 'delete') && !$bMemberIsSuper) {

                                    $aAttr = array(
                                        'class="confirm btn btn-xs btn-danger"',
                                        'data-title="Delete user &quot;' . $sMemberNameFL . '&quot?"',
                                        'data-body="Are you sure you want to delete this user? This cannot be undone."'
                                    );

                                    $buttons[] = anchor(
                                        'admin/auth/accounts/delete/' . $member->id . $return,
                                        lang('action_delete'),
                                        implode(' ', $aAttr)
                                    );
                                }

                                // --------------------------------------------------------------------------

                                //  Update user's group
                                if (userHasPermission($sPermPrefix . 'changeUserGroup')) {

                                    /**
                                     * If this user is a super user and the current user is not a super user
                                     * then don't allow this option
                                     */

                                    if ($bMemberIsSuper && !$bActiveIsSuper) {

                                        //  Nothing

                                    } else {

                                        $buttons[] = anchor(
                                            'admin/auth/accounts/change_group?users=' . $member->id,
                                            'Edit Group',
                                            'class="btn btn-xs btn-primary"'
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
                                            'class="btn btn-xs btn-default ' . $button['class'] . '"'
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

                            ?>
                        </td>
                    </tr>
                    <?php
                }

            } else {

                ?>
                <tr>
                    <td colspan="4" class="no-data">
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

        echo adminHelper('loadPagination', $pagination);

    ?>
</div>
