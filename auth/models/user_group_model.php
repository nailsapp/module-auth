<?php

/**
 * OVERLOADING NAILS' MODELS
 *
 * Note the name of this class; done like this to allow apps to extend this class.
 * Read full explanation at the bottom of this file.
 *
 **/

class NAILS_User_group_model extends NAILS_Model
{
    protected $default_group;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        $this->_table        = NAILS_DB_PREFIX . 'user_group';
        $this->_table_prefix = 'ug';

        // --------------------------------------------------------------------------

        $this->_default_group = $this->getDefaultGroup();
    }

    // --------------------------------------------------------------------------

    /**
     * Set's a group as the default group
     * @param mixed $group_id_slug The group's ID or slug
     */
    public function setAsDefault($group_id_slug)
    {
        $_group = $this->get_by_id_or_slug($group_id_slug);

        if (!$_group) {

            $this->_set_error('Invalid Group');
        }

        // --------------------------------------------------------------------------

        $this->db->trans_begin();

        //  Unset old default
        $this->db->set('is_default', false);
        $this->db->set('modified', 'NOW()', false);
        if ($this->user_model->isLoggedIn()) {

            $this->db->set('modified_by', activeUser('id'));

        }
        $this->db->where('is_default', true);
        $this->db->update($this->_table);

        //  Set new default
        $this->db->set('is_default', true);
        $this->db->set('modified', 'NOW()', false);
        if ($this->user_model->isLoggedIn()) {

            $this->db->set('modified_by', activeUser('id'));

        }
        $this->db->where('id', $_group->id);
        $this->db->update($this->_table);

        if ($this->db->trans_status() === false) {

            $this->db->trans_rollback();
            return false;

        } else {

            $this->db->trans_commit();

            //  Refresh the default group variable
            $this->getDefaultGroup();

            return true;
        }
    }

    // --------------------------------------------------------------------------

    public function getDefaultGroup()
    {
        $_data['where']   = array();
        $_data['where'][] = array('column' => 'is_default', 'value' => true);

        $_group = $this->get_all(null, null, $_data);

        if (!$_group) {

            showFatalError('No Default Group Set', 'A default user group must be set.');
        }

        $this->_default_group = $_group[0];

        return $this->_default_group;
    }

    // --------------------------------------------------------------------------

    public function getDefaultGroupId()
    {
        return $this->_default_group->id;
    }

    // --------------------------------------------------------------------------

    public function changeUserGroup($userIds, $newGroupId)
    {
        $group = $this->get_by_id($newGroupId);

        if (empty($group)) {

            $this->_set_error('"' . $newGroupId . '" is not a valid group ID.');
            return false;
        }

        $users = $this->user_model->get_by_ids((array) $userIds);

        $this->db->trans_begin();

        foreach ($users as $user) {

            $preMethod  = 'changeUserGroup_pre_' . $user->group_slug . '_' . $group->slug;
            $postMethod = 'changeUserGroup_post_' . $user->group_slug . '_' . $group->slug;

            if (method_exists($this, $preMethod)) {

                if (!$this->$preMethod($user)) {

                    $this->db->trans_rollback();
                    $msg = '"' . $preMethod. '()" returned false for user ' . $user->id . ', rolling back changes';
                    $this->_set_error($msg);
                    return false;
                }
            }

            $data = array('group_id' => $group->id);
            if (!$this->user_model->update($user->id, $data)) {

                $this->db->trans_rollback();
                $msg = 'Failed to update group ID for user ' . $user->id;
                $this->_set_error($msg);
                return false;
            }

            if (method_exists($this, $postMethod)) {

                if (!$this->$postMethod($user)) {

                    $this->db->trans_rollback();
                    $msg = '"' . $postMethod. '()" returned false for user ' . $user->id . ', rolling back changes';
                    $this->_set_error($msg);
                    return false;
                }
            }

        }

        $this->db->trans_commit();
        return true;
    }

    // --------------------------------------------------------------------------

    public function processPermissions($permissions, $prefix = '')
    {
        $out = array();

        //  Level 1
        foreach ($permissions as $levelOneSlug => $levelOnePermissions) {

            if (is_string($levelOnePermissions)) {

                $out[] = $levelOneSlug;
                continue;
            }

            foreach ($levelOnePermissions as $levelTwoSlug => $levelTwoPermissions) {

                if (is_string($levelTwoPermissions)) {

                    $out[] = $levelOneSlug . ':' . $levelTwoSlug;
                    continue;
                }

                foreach ($levelTwoPermissions as $levelThreeSlug => $levelThreePermissions) {

                    $out[] = $levelOneSlug . ':' . $levelTwoSlug . ':' . $levelThreeSlug;
                }
            }
        }

        $out = array_unique($out);
        $out = array_filter($out);

        return json_encode($out);
    }

    // --------------------------------------------------------------------------

    protected function _format_object(&$obj)
    {
        $obj->acl = json_decode($obj->acl);
    }
}

// --------------------------------------------------------------------------

/**
 * OVERLOADING NAILS' MODELS
 *
 * The following block of code makes it simple to extend one of the core
 * models. Some might argue it's a little hacky but it's a simple 'fix'
 * which negates the need to massively extend the CodeIgniter Loader class
 * even further (in all honesty I just can't face understanding the whole
 * Loader class well enough to change it 'properly').
 *
 * Here's how it works:
 *
 * CodeIgniter instantiate a class with the same name as the file, therefore
 * when we try to extend the parent class we get 'cannot redeclare class X' errors
 * and if we call our overloading class something else it will never get instantiated.
 *
 * We solve this by prefixing the main class with NAILS_ and then conditionally
 * declaring this helper class below; the helper gets instantiated et voila.
 *
 * If/when we want to extend the main class we simply define NAILS_ALLOW_EXTENSION
 * before including this PHP file and extend as normal (i.e in the same way as below);
 * the helper won't be declared so we can declare our own one, app specific.
 *
 **/

if (!defined('NAILS_ALLOW_EXTENSION_USER_GROUP_MODEL')) {

    class User_group_model extends NAILS_User_group_model
    {
    }
}
