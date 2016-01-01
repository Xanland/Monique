<?php
/**
 * Nuwani PHP IRC Bot Framework
 * Copyright (c) 2006-2010 The Nuwani Project
 * Nuwani is a framework for IRC Bots built using PHP. Nuwani speeds up bot development by handling
 * basic tasks as connection- and bot management, timers and module managing. Features for your bot
 * can easily be added by creating your own modules, which will receive callbacks from the framework.
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/>.
 * @copyright Copyright (c) 2006-2011 The Nuwani Project, http://nuwani.googlecode.com/
 * @author    Peter Beverloo <peter@lvp-media.com>
 * @author    Dik Grapendaal <dik.grapendaal@gmail.com>
 * @author    Xander Hoogland <home@xanland.nl>
 * @version   $Id: Model.php 151 2011-08-21 17:43:24Z dik.grapendaal@gmail.com $
 * @package   Nuwani
 */

namespace Nuwani;

use Nuwani\Common\stringHelper;

if (!class_exists ('\PDO'))
{
    /** PDO is not required by the bot's core. * */
    return;
}

class Model
{
    /**
     * Given when specified value in primary key-column already exists.
     */
    const DUPLICATE_ENTRY_FOR_KEY_PRIMARY = 23000;

    /**
     * Id from the row to get.
     *
     * @access private
     * @var integer
     */
    private $_sId;

    /**
     * The name of the column to search on.
     *
     * @access private
     * @var string
     */
    private $_sIdColumn;

    /**
     * The table where to get the data from.
     *
     * @access private
     * @var string
     */
    private $_sTable;

    /**
     * The data of the row.
     *
     * @access private
     * @var array
     */
    private $_aData;

    /**
     * The connection with the database via PDO.
     *
     * @access private
     * @var object
     */
    private $_pPDO;

    private $bypassInsert;

    /**
     * Sets the table where to select from based on the given id and id-column. Based on the last param it connects to
     * the database or not.
     *
     * @param string $sTable            Table to connect to
     * @param string $sIdColumn         Column to select from
     * @param string $sId               Id to the select in the specified column
     * @param bool   $connectToDatabase If it should connect to the database
     * @param bool   $bypassInsert      Makes from the insert an update
     */
    //public function __construct ($sTable, $sIdColumn, $sId, $connectToDatabase = true)
    public function __construct (string $sTable, string $sIdColumn, string $sId, bool $connectToDatabase = true, bool
    $bypassInsert = false)
    {
        $this->_sTable = $sTable;

        $this->_sIdColumn = $sIdColumn;

        $this->_sId = $sId;

        $this->bypassInsert = $bypassInsert;

        if ($connectToDatabase)
        {
            $this->connectToDatabase ();

            $this->load ();
        }
    }

    /**
     * Get all the data from the table specified by the id.
     *
     * @return boolean
     */
    private function load ()
    {
        $sQuery = "select *
                   from " . $this->_sTable . "
                   where " . $this->_sTable . "." . $this->_sIdColumn . " like :sId;";
        //echo str_replace (':sId', $this -> _sId, $sQuery);

        try
        {
            $oStmt = $this->_pPDO->prepare ($sQuery);
            $oStmt->bindParam (':sId', $this->_sId, \ PDO::PARAM_STR);
            $oStmt->execute ();

            for ($i = 0; $i < $oStmt->columnCount (); $i++)
            {
                $aColumnInfo = $oStmt->getColumnMeta ($i);
                $sColumnName = $aColumnInfo ['name'];
                $oStmt->bindColumn ($sColumnName, $this->_aData [$sColumnName]);
            }

            if ($oStmt->fetch (\ PDO::FETCH_BOUND))
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        catch (\ PDOException $e)
        {
            $e->getMessage ();

            return false;
        }
    }

    /**
     * Also set the changed data and update the table.
     *
     * @return boolean
     */
    public function save ()
    {
        foreach ($this->_aData as $sVarName => $sVarValue)
        {
            if ($sVarName == $this->_sIdColumn && !$this->bypassInsert)
            {
                $sQuery = "insert " . $this->_sTable . " (" . $sVarName . ")
                           select :sVarValue; ";
            }
            else
            {
                if ($sVarName == stringHelper::Format("{0}_id", $this -> _sTable) || $sVarName == 'id')
                    continue;

                $sQuery = "update " . $this->_sTable . "
                           set " . $sVarName . " = :sVarValue
                           where " . $this->_sIdColumn . " like :sId;";
            }
            //echo str_replace (array (':sVarValue', ':sId'), array ($sVarValue, $this -> _sId), $sQuery);

            try
            {
                if ($this -> _pPDO == null)
                {
                    $this -> connectToDatabase ();
                }

                $oStmt = $this -> _pPDO -> prepare ($sQuery);
                $oStmt -> bindParam (':sVarValue', $sVarValue);
                if ($sVarName != $this->_sIdColumn)
                {
                    $oStmt->bindParam (':sId', $this -> _sId);
                }

                $oStmt -> execute();

                if ($oStmt -> errorInfo()[0] == Model::DUPLICATE_ENTRY_FOR_KEY_PRIMARY)
                    continue;
            }
            catch (\ PDOException $e)
            {
                echo $e->getMessage ();

                return false;
            }
        }

        return true;
    }

    /**
     * Getter for the data before doing setData().
     *
     * @param string $sVarName
     *
     * @return mixed
     * @throws Exception Var not found when not set.
     */
    public function __get (string $sVarName)
    {
        if (isset ($sVarName))
        {
            return $this->_aData [$sVarName];
        }

        throw \Exception($sVarName . ' not found!');
    }

    /**
     * Setter for the data before doing save ().
     *
     * @param string $sVarName
     * @param string $sVarValue
     */
    public function __set (string $sVarName, $sVarValue)
    {
        $this->_aData [$sVarName] = $sVarValue;
    }

    /**
     * Removing a record from a table should be handy and easy.
     *
     * @return boolean
     */
    public function delete ()
    {
        $sQuery = "delete from `" . $this -> _sTable . "`
                   where `" . $this -> _sIdColumn . "` = :sId;";

        try
        {
            $oStmt = $this -> _pPDO -> prepare ($sQuery);
            $oStmt -> bindParam (':sId', $this -> _sId);
            if ($oStmt -> execute ())
            {
                return true;
            }
            else
            {
                return false;
            }
        }
        catch (PDOException $e)
        {
            return false;
        }
    }

    /**
     * Method for getting all the records of a table.
     *
     * @return array Model
     */
    public function getAll ()
    {
        $aModels = array ();

        $sQuery = "select *
                   from " . $this->_sTable . " ";
        if (isset($this->_sId))
        {
            $sQuery .= "where " . $this->_sIdColumn . " like :sId";
        }
        $sQuery .= ";";

        try
        {
            $oStmt = $this->_pPDO->prepare ($sQuery);
            $oStmt->bindParam (':sId', $this->_sId, \ PDO::PARAM_STR);
            $oStmt->execute ();
            $i = 0;

            foreach ($oStmt->fetchAll (\ PDO :: FETCH_ASSOC) as $aRow)
            {
                $aModels[$i] = new Model ($this -> _sTable, $this -> _sIdColumn, $aRow [$this -> _sIdColumn], false,
                    $this->bypassInsert);
                foreach ($aRow as $sKey => $sValue)
                {
                    $aModels[$i] -> $sKey = $sValue;
                }

                $i++;
            }
        }
        catch (\ PDOException $e)
        {
            $e->getMessage ();
        }

        return $aModels;
    }

    /**
     * When serializing we only need the array containing all the getters/setters.
     *
     * @return array
     */
    public function __sleep ()
    {
        return array ('_aData');
    }

    /**
     * Reconnect so we can save the model stored in a session.
     */
    public function __wakeup ()
    {
        $this -> connectToDatabase ();
    }

    /**
     * Connects to the database with configuration defined from the config.php and sets correct modes.
     */
    private function connectToDatabase ()
    {
        $this->_pPDO = Database:: getInstance ();
        $this->_pPDO->setAttribute (\ PDO :: ATTR_ERRMODE, \ PDO :: ERRMODE_WARNING);
    }
}