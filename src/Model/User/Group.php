<?php

/**
 * This model contains all methods for interacting with user groups.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model\User;

use Nails\Common\Model\Base;
use Nails\Factory;

class Group extends Base
{
    protected $defaultGroup;

    // --------------------------------------------------------------------------

    /**
     * Group constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->table             = NAILS_DB_PREFIX . 'user_group';
        $this->tableAlias        = 'ug';
        $this->defaultGroup      = $this->getDefaultGroup();
        $this->defaultSortColumn = 'id';
    }

    // --------------------------------------------------------------------------

    /**
     * Set's a group as the default group
     *
     * @param mixed $group_id_slug The group's ID or slug
     *
     * @return boolean
     */
    public function setAsDefault($group_id_slug)
    {
        $group = $this->getByIdOrSlug($group_id_slug);

        if (!$group) {
            $this->setError('Invalid Group');
        }

        // --------------------------------------------------------------------------

        $oDb = Factory::service('Database');
        $oDb->trans_begin();

        //  Unset old default
        $oDb->set('is_default', false);
        $oDb->set('modified', 'NOW()', false);
        if (isLoggedIn()) {
            $oDb->set('modified_by', activeUser('id'));
        }
        $oDb->where('is_default', true);
        $oDb->update($this->table);

        //  Set new default
        $oDb->set('is_default', true);
        $oDb->set('modified', 'NOW()', false);
        if (isLoggedIn()) {
            $oDb->set('modified_by', activeUser('id'));
        }
        $oDb->where('id', $group->id);
        $oDb->update($this->table);

        if ($oDb->trans_status() === false) {

            $oDb->trans_rollback();
            return false;

        } else {

            $oDb->trans_commit();

            //  Refresh the default group variable
            $this->getDefaultGroup();

            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the default user group
     * @return \stdClass
     */
    public function getDefaultGroup()
    {
        $data['where']   = [];
        $data['where'][] = ['column' => 'is_default', 'value' => true];

        $group = $this->getAll(null, null, $data);

        if (!$group) {
            showFatalError('No Default Group Set', 'A default user group must be set.');
        }

        $this->defaultGroup = $group[0];

        return $this->defaultGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the default group's ID
     * @return int
     */
    public function getDefaultGroupId()
    {
        return $this->defaultGroup->id;
    }

    // --------------------------------------------------------------------------

    /**
     * Change the user group of multiple users, executing any pre/post upgrade functionality as required
     *
     * @param  array   $userIds    An array of User ID's to update
     * @param  integer $newGroupId The ID of the new user group
     *
     * @return boolean
     */
    public function changeUserGroup($userIds, $newGroupId)
    {
        $group = $this->getById($newGroupId);

        if (empty($group)) {

            $this->setError('"' . $newGroupId . '" is not a valid group ID.');
            return false;
        }

        $oDb        = Factory::service('Database');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $users      = $oUserModel->getByIds((array) $userIds);

        $oDb->trans_begin();

        foreach ($users as $user) {

            $preMethod  = 'changeUserGroup_pre_' . $user->group_slug . '_' . $group->slug;
            $postMethod = 'changeUserGroup_post_' . $user->group_slug . '_' . $group->slug;

            if (method_exists($this, $preMethod)) {
                if (!$this->$preMethod($user)) {
                    $oDb->trans_rollback();
                    $msg = '"' . $preMethod . '()" returned false for user ' . $user->id . ', rolling back changes';
                    $this->setError($msg);
                    return false;
                }
            }

            $data = ['group_id' => $group->id];
            if (!$oUserModel->update($user->id, $data)) {
                $oDb->trans_rollback();
                $msg = 'Failed to update group ID for user ' . $user->id;
                $this->setError($msg);
                return false;
            }

            if (method_exists($this, $postMethod)) {
                if (!$this->$postMethod($user)) {
                    $oDb->trans_rollback();
                    $msg = '"' . $postMethod . '()" returned false for user ' . $user->id . ', rolling back changes';
                    $this->setError($msg);
                    return false;
                }
            }
        }

        $oDb->trans_commit();
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an array of permissions into a JSON encoded string suitable for the database
     *
     * @param  array $permissions An array of permissions to set
     *
     * @return string
     */
    public function processPermissions($permissions)
    {
        if (empty($permissions)) {
            return null;
        }

        $out = [];

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

    /**
     * Determines whether the specified group has a certain ACL permission
     *
     * @param   string $sSearch The permission to check for
     * @param   mixed  $mGroup  The group to check for;  if numeric, fetches group, if object
     *                          uses that object
     *
     * @return  boolean
     */
    public function hasPermission($sSearch, $mGroup)
    {
        //  Fetch the correct ACL
        if (is_numeric($mGroup)) {

            $oGroup = $this->getById($mGroup);

            if (isset($oGroup->acl)) {
                $aAcl = $oGroup->acl;
                unset($oGroup);
            } else {
                return false;
            }

        } elseif (isset($mGroup->acl)) {
            $aAcl = $mGroup->acl;
        } else {
            return false;
        }

        if (!$aAcl) {
            return false;
        }

        // --------------------------------------------------------------------------

        // Super users or CLI users can do anything their heart's desire
        $oInput = Factory::service('Input');
        if (in_array('admin:superuser', $aAcl) || $oInput->isCli()) {
            return true;
        }

        // --------------------------------------------------------------------------

        /**
         * Test the ACL
         * We're going to use regular expressions here so we can allow for some
         * flexibility in the search, i.e admin:* would return true if the user has
         * access to any of admin.
         */

        $bHasPermission = false;

        /**
         * Replace :* with :.* - this is a common mistake when using the permission
         * system (i.e., assuming that star on it's own will match)
         */

        $sSearch = preg_replace('/:\*/', ':.*', $sSearch);

        foreach ($aAcl as $sPermission) {

            $sPattern = '/^' . $sSearch . '$/';
            $bMatch   = preg_match($sPattern, $sPermission);

            if ($bMatch) {
                $bHasPermission = true;
                break;
            }
        }

        return $bHasPermission;
    }


    // --------------------------------------------------------------------------

    /**
     * Formats a single object
     *
     * The getAll() method iterates over each returned item with this method so as to
     * correctly format the output. Use this to cast integers and booleans and/or organise data into objects.
     *
     * @param  object $oObj      A reference to the object being formatted.
     * @param  array  $aData     The same data array which is passed to getCountCommon, for reference if needed
     * @param  array  $aIntegers Fields which should be cast as integers if numerical and not null
     * @param  array  $aBools    Fields which should be cast as booleans if not null
     * @param  array  $aFloats   Fields which should be cast as floats if not null
     *
     * @return void
     */
    protected function formatObject(
        &$oObj,
        $aData = [],
        $aIntegers = [],
        $aBools = [],
        $aFloats = []
    ) {
        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);

        $oObj->acl            = json_decode($oObj->acl);
        $oObj->password_rules = json_decode($oObj->password_rules);
    }
}
