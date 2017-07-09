<?php

/**
 * This model contains all methods for interacting with user meta tables
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model\User;

use Nails\Factory;
use Nails\Common\Traits\Caching;
use Nails\Common\Traits\ErrorHandling;

class Meta
{
    use Caching;
    use ErrorHandling;

    // --------------------------------------------------------------------------

    /**
     * Fetches a record from a user_meta_* table
     *
     * @param  string  $sTable   The table to fetch from
     * @param  integer $iUserId  The ID of the user the record belongs to
     * @param  array   $aColumns Any specific columns to select
     *
     * @return \stdClass
     */
    public function get($sTable, $iUserId, $aColumns = [])
    {
        //  Check cache
        $oDb       = Factory::service('Database');
        $sCacheKey = 'user-meta-' . $sTable . '-' . $iUserId;
        $oCache    = $this->getCache($sCacheKey);

        if (!empty($oCache)) {
            return $oCache;
        }

        if (!empty($aColumns)) {
            $oDb->select($aColumns);
        }

        $oDb->where('user_id', $iUserId);
        $aResult = $oDb->get($sTable)->result();

        if (empty($aResult)) {
            $mOut = null;
        } else {
            $mOut = reset($aResult);
        }

        $this->setCache($sCacheKey, $mOut);

        return $mOut;
    }

    // --------------------------------------------------------------------------

    /**
     * Fetches records from a user_meta_table which may have many rows
     *
     * @param  string  $sTable   The table to fetch from
     * @param  integer $iUserId  The ID of the user the records belongs to
     * @param  array   $aColumns Any specific columns to select
     *
     * @return array
     */
    public function getMany($sTable, $iUserId, $aColumns = [])
    {
        //  Check cache
        $oDb       = Factory::service('Database');
        $sCacheKey = 'user-meta-many-' . $sTable . '-' . $iUserId;
        $oCache    = $this->getCache($sCacheKey);

        if (!empty($oCache)) {
            return $oCache;
        }

        if (!empty($aColumns)) {
            $oDb->select($aColumns);
        }

        $oDb->where('user_id', $iUserId);
        $aResult = $oDb->get($sTable)->result();

        $this->setCache($sCacheKey, $aResult);

        return $aResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Updates a user_meta_* table
     *
     * @param  string  $sTable  The table to update
     * @param  integer $iUserId The ID of the user the record belongs to
     * @param  array   $aData   The data to set
     *
     * @return boolean
     */
    public function update($sTable, $iUserId, $aData)
    {
        //  Safety: Ensure that the user_id is not overridden
        $aData['user_id'] = $iUserId;

        $oDb = Factory::service('Database');
        $oDb->where('user_id', $iUserId);
        if ($oDb->count_all_results($sTable)) {

            $oDb->set($aData);
            $oDb->where('user_id', $iUserId);
            $bResult = $oDb->update($sTable);

        } else {

            $oDb->set($aData);
            $bResult = $oDb->insert($sTable);
        }

        if ($bResult) {
            $this->unsetCache('user-meta-' . $sTable . '-' . $iUserId);
        }

        return $bResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Updates a user_meta_* table
     *
     * @param  string  $sTable           The table to update
     * @param  integer $iUserId          The ID of the user ID being updated
     * @param  array   $aData            An array of rows to update
     * @param  boolean $bDeleteUntouched Whether to delete records which were not updated or created
     *
     * @return boolean
     */
    public function updateMany($sTable, $iUserId, $aData, $bDeleteUntouched = true)
    {
        $oDb = Factory::service('Database');
        try {

            $aTouchedIds = [];

            $oDb->trans_begin();
            foreach ($aData as $aRow) {

                if (empty($aRow['id'])) {

                    //  Safety: Don't allow setting of the row ID
                    unset($aRow['id']);
                    //  Safety: overrwrite any user_id which may be passed
                    $aRow['user_id'] = $iUserId;
                    $oDb->set($aRow);
                    if (!$oDb->insert($sTable)) {
                        throw new \Exception('Failed to create item.', 1);
                    }

                    $aTouchedIds[] = $oDb->insert_id();

                } else {

                    //  Safety: Ensure that the row ID, and User ID are not overridden
                    $iId = $aRow['id'];
                    unset($aRow['id']);

                    $oDb->where('id', $iId);
                    $oDb->where('user_id', $iUserId);
                    $oDb->set($aRow);
                    if (!$oDb->update($sTable)) {
                        throw new \Exception('Failed to update item.', 1);
                    }

                    $aTouchedIds[] = $iId;
                }
            }

            if ($bDeleteUntouched && !empty($aTouchedIds)) {
                $oDb->where('user_id', $iUserId);
                $oDb->where_not_in('id', $aTouchedIds);

                if (!$oDb->delete($sTable)) {
                    throw new \Exception('Failed to delete old items.', 1);
                }
            }

            $oDb->trans_commit();
            $this->unsetCache('user-meta-many-' . $sTable . '-' . $iUserId);
            return true;

        } catch (\Exception $e) {

            $this->setError($e->getMessage());
            $oDb->trans_rollback();
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes a user_meta_* row
     *
     * @param  string  $sTable  The table to delete from
     * @param  integer $iUserId The ID of the user the record belongs to
     *
     * @return boolean
     */
    public function delete($sTable, $iUserId)
    {
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $iUserId);
        if (!$oDb->delete($sTable)) {
            return false;
        }

        $this->unsetCache('user-meta-' . $sTable . '-' . $iUserId);
        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Deletes from a user_meta_* table
     *
     * @param  string        $sTable  The table to delete from
     * @param  integer       $iUserId The ID of the user the record belongs to
     * @param  integer|array $mRowIds An array of row IDs to delete (or a single row ID)
     *
     * @return boolean
     */
    public function deleteMany($sTable, $iUserId, $mRowIds = null)
    {
        $oDb = Factory::service('Database');
        $oDb->where('user_id', $iUserId);

        if (is_numeric($mRowIds)) {
            $oDb->where('id', $mRowIds);
        } elseif (is_array($mRowIds)) {
            $oDb->where_in('id', $mRowIds);
        }

        if (!$oDb->delete($sTable)) {
            return false;
        }

        $this->unsetCache('user-meta-many-' . $sTable . '-' . $iUserId);
        return true;
    }
}
