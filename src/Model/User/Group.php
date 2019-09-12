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

use Nails\Auth\Constants;
use Nails\Common\Exception\NailsException;
use Nails\Common\Model\Base;
use Nails\Factory;

class Group extends Base
{
    protected $oDefaultGroup;

    // --------------------------------------------------------------------------

    /**
     * Group constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->table             = NAILS_DB_PREFIX . 'user_group';
        $this->defaultSortColumn = 'id';
        $this->oDefaultGroup     = $this->getDefaultGroup();
    }

    // --------------------------------------------------------------------------

    /**
     * Set's a group as the default group
     *
     * @param mixed $mGroupIdOrSlug The group's ID or slug
     *
     * @return boolean
     */
    public function setAsDefault($mGroupIdOrSlug)
    {
        $oGroup = $this->getByIdOrSlug($mGroupIdOrSlug);

        if (!$oGroup) {
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
        $oDb->update($this->getTableName());

        //  Set new default
        $oDb->set('is_default', true);
        $oDb->set('modified', 'NOW()', false);
        if (isLoggedIn()) {
            $oDb->set('modified_by', activeUser('id'));
        }
        $oDb->where('id', $oGroup->id);
        $oDb->update($this->getTableName());

        if ($oDb->trans_status() === false) {

            $oDb->trans_rollback();
            return false;

        } else {

            $oDb->trans_commit();
            $this->getDefaultGroup();
            return true;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the default user group
     *
     * @return \stdClass
     */
    public function getDefaultGroup()
    {
        $aGroups = $this->getAll([
            'where' => [
                ['is_default', true],
            ],
        ]);

        if (empty($aGroups)) {
            throw new NailsException('A default user group must be defined.');
        }

        $this->oDefaultGroup = reset($aGroups);

        return $this->oDefaultGroup;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the default group's ID
     *
     * @return int
     */
    public function getDefaultGroupId()
    {
        return $this->oDefaultGroup->id;
    }

    // --------------------------------------------------------------------------

    /**
     * Change the user group of multiple users, executing any pre/post upgrade functionality as required
     *
     * @param  array   $aUserIds    An array of User ID's to update
     * @param  integer $iNewGroupId The ID of the new user group
     *
     * @return boolean
     */
    public function changeUserGroup(array $aUserIds, $iNewGroupId)
    {
        $oGroup = $this->getById($iNewGroupId);
        if (empty($oGroup)) {
            $this->setError('"' . $iNewGroupId . '" is not a valid group ID.');
            return false;
        }

        if (!empty($oGroup->acl) && in_array('admin:superuser', $oGroup->acl) && !isSuperuser()) {
            $this->setError('You do not have permission to add user\'s to the superuser group.');
            return false;
        }

        $oDb        = Factory::service('Database');
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        $aUsers     = $oUserModel->getByIds((array) $aUserIds);

        try {

            $oDb->trans_begin();
            foreach ($aUsers as $oUser) {

                //  Permission check
                if ($oUser->id === activeUser('id') && !userHasPermission('admin:auth:accounts:changeOwnUserGroup')) {
                    throw new \RuntimeException('You do not have permission to change your own user group');
                } elseif ($oUser->id !== activeUser('id') && !userHasPermission('admin:auth:accounts:changeUserGroup')) {
                    throw new \RuntimeException('You do not have permission to change another user\'s user group');
                } elseif (isSuperuser($oUser) && !isSuperuser()) {
                    throw new \RuntimeException('You do not have permission to change a super user\'s usergroup');
                }

                //  @todo (Pablo - 2019-01-25) - Use the event system
                $sPreMethod  = 'changeUserGroup_pre_' . $oUser->group_slug . '_' . $oGroup->slug;
                $sPostMethod = 'changeUserGroup_post_' . $oUser->group_slug . '_' . $oGroup->slug;

                if (method_exists($this, $sPreMethod)) {
                    if (!$this->$sPreMethod($oUser)) {
                        throw new \RuntimeException(
                            '"' . $sPreMethod . '()" returned false for user ' . $oUser->id . ', rolling back changes'
                        );
                    }
                }

                $aData = ['group_id' => $oGroup->id];
                if (!$oUserModel->update($oUser->id, $aData)) {
                    throw new \RuntimeException('Failed to update group ID for user ' . $oUser->id);
                }

                if (method_exists($this, $sPostMethod)) {
                    if (!$this->$sPostMethod($oUser)) {
                        throw new \RuntimeException(
                            '"' . $sPostMethod . '()" returned false for user ' . $oUser->id . ', rolling back changes'
                        );
                    }
                }
            }
            $oDb->trans_commit();

            return true;

        } catch (\Exception $e) {
            $oDb->trans_rollback();
            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Formats an array of permissions into a JSON encoded string suitable for the database
     *
     * @param  array $aPermissions An array of permissions to set
     *
     * @return string
     */
    public function processPermissions(array $aPermissions)
    {
        if (empty($aPermissions)) {
            return null;
        }

        $aOut = [];

        //  Level 1
        foreach ($aPermissions as $levelOneSlug => $levelOnePermissions) {

            if (is_string($levelOnePermissions)) {
                $aOut[] = $levelOneSlug;
                continue;
            }

            foreach ($levelOnePermissions as $levelTwoSlug => $levelTwoPermissions) {

                if (is_string($levelTwoPermissions)) {
                    $aOut[] = $levelOneSlug . ':' . $levelTwoSlug;
                    continue;
                }

                foreach ($levelTwoPermissions as $levelThreeSlug => $levelThreePermissions) {
                    $aOut[] = $levelOneSlug . ':' . $levelTwoSlug . ':' . $levelThreeSlug;
                }
            }
        }

        $aOut = array_unique($aOut);
        $aOut = array_filter($aOut);

        return json_encode($aOut);
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
        if (in_array('admin:superuser', $aAcl) || $oInput::isCli()) {
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
        array $aData = [],
        array $aIntegers = [],
        array $aBools = [],
        array $aFloats = []
    ) {
        parent::formatObject($oObj, $aData, $aIntegers, $aBools, $aFloats);

        $oObj->acl            = json_decode($oObj->acl);
        $oObj->password_rules = json_decode($oObj->password_rules);
    }
}
