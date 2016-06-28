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

use Nuwani\Common\stringH;

if (!class_exists ('\PDO'))
{
    /** PDO is not required by the bot's core. * */
    return;
}

class Model
{
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
     * The data of the row which can be muted.
     *
     * @access private
     * @var array
     */
    private $_aMutableData;

    /**
     * The actual data representing the table-row.
     *
     * @access private
     * @var array
     */
    private $_aLoadedData;

    /**
     * The connection with the database via PDO.
     *
     * @access private
     * @var object
     */
    private $_pPDO;

    /**
     * Sets the table where to select from based on the given id and id-column. Based on the last param it connects to
     * the database or not.
     *
     * @param string $sTable            Table to connect to
     * @param string $sIdColumn         Column to select from
     * @param string $sId               Id to the select in the specified column
     * @param bool   $connectToDatabase If it should connect to the database
     */
    public function __construct (string $sTable, string $sIdColumn, string $sId = null, bool $connectToDatabase = true)
    {
        $this->_sTable = $sTable;

        $this->_sIdColumn = $sIdColumn;

        $this->_sId = $sId;

        if ($connectToDatabase && !stringH :: IsNullOrWhiteSpace($sId))
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
                   where " . $this->_sTable . "." . $this->_sIdColumn . " like CONVERT(:sId USING utf8) COLLATE utf8_bin
                   limit 1;";
        //echo str_replace (':sId', $this -> _sId, $sQuery);

        try
        {
            $oStmt = $this->_pPDO->prepare ($sQuery);
            $oStmt->bindParam (':sId', $this->_sId, \ PDO::PARAM_STR);
            $oStmt->execute ();

            if ($oStmt->rowCount() === 0)
                return;

            for ($i = 0; $i < $oStmt->columnCount (); $i++)
            {
                $aColumnInfo = $oStmt->getColumnMeta ($i);
                $sColumnName = $aColumnInfo ['name'];
                $oStmt->bindColumn ($sColumnName, $this->_aLoadedData [$sColumnName]);
            }

            $oStmt->fetch (\ PDO::FETCH_BOUND);
            $this->_aMutableData = $this->_aLoadedData;
            return;
        }
        catch (\ PDOException $e)
        {
            return;
        }
    }

    /**
     * Also set the changed data and update the table.
     *
     * @return boolean
     */
    public function save ()
    {
        if ($this->_aLoadedData == [])
        {
            $this->_aMutableData = array ($this->_sIdColumn => $this->_sId) + $this->_aMutableData;
        }

        foreach ($this->_aMutableData as $sVarName => $sVarValue)
        {
            if ($this->_aLoadedData != null && $sVarValue == $this->_aLoadedData[$sVarName])
                continue;

            if ($this->_aLoadedData == [])
            {
                $sQueryWithColumns = "insert into `" . $this->_sTable . "` (`" . $this->_sIdColumn . "`, ";
                foreach ($this -> _aMutableData as $sColumn => $sValue)
                {
                    if ($sColumn == $this->_sIdColumn)
                        continue;

                    $sQueryWithColumns .= "`" . $sColumn . "`, ";
                }
                $sQuery = substr($sQueryWithColumns, 0, -2) . ')';

                $sQueryWithValues = "select :sVarValue, ";
                foreach ($this -> _aMutableData as $sColumn => $sValue)
                {
                    if ($sColumn == $this->_sIdColumn)
                        continue;

                    $sQueryWithValues .= ":" . $sColumn . ", ";
                }
                $sQuery .= substr($sQueryWithValues, 0, -2);
            }
            else
            {
                $sQuery = "update " . $this->_sTable . "
                           set " . $sVarName . " = :sVarValue
                           where " . $this->_sIdColumn . " like CONVERT(:sId USING utf8) COLLATE utf8_bin;";
            }
            //echo str_replace (array (':sVarValue', ':sId'), array ($sVarValue, $this -> _sId), $sQuery) . PHP_EOL;

            try
            {
                if ($this -> _pPDO == null)
                {
                    $this -> connectToDatabase ();
                }

                $oStmt = $this -> _pPDO -> prepare ($sQuery);

                $oStmt -> bindParam (':sVarValue', $sVarValue);
                if ($this->_aLoadedData != null)
                {
                    $oStmt->bindParam (':sId', $this -> _sId);
                }
                else
                {
                    foreach ($this -> _aMutableData as $sColumn => $sValue)
                    {
                        if ($sColumn == $this->_sIdColumn)
                            continue;

                        if (stringH::IsNullOrWhiteSpace($sValue))
                            $sValue = null;

                        $oStmt -> bindValue (':' . $sColumn, $sValue);
                    }
                }

                if($oStmt->execute())
                {
                    if ($this->_aLoadedData == [])
                    {
                        $this->fillLoadedData();
                    }
                    else
                    {
                        $this->_aLoadedData[$sVarName] = $sVarValue;
                    }

                    //                    if ($sVarName == $this->_sIdColumn)
                    //                        $this->_sId = $sVarValue;
                }
                else
                {
                    ob_start ();
                    var_dump ($oStmt->errorInfo ());
                    $varDump = ob_get_contents ();
                    ob_end_clean ();
                    file_put_contents ('error.log', $varDump, FILE_APPEND);
                }
            }
            catch (\ PDOException $e)
            {
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
     * @throws \Exception Var not found when not set.
     */
    public function __get (string $sVarName)
    {
        if (isset ($sVarName))
        {
            return $this->_aMutableData [$sVarName];
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
        $this->_aMutableData [$sVarName] = $sVarValue;
    }

    /**
     * Removing a record from a table should be handy and easy.
     *
     * @return boolean
     */
    public function delete ()
    {
        $sQuery = "delete from `" . $this -> _sTable . "`
                   where `" . $this -> _sIdColumn . "` = CONVERT(:sId USING utf8) COLLATE utf8_bin;";

        try
        {
            if ($this -> _pPDO == null)
            {
                $this -> connectToDatabase ();
            }

            $oStmt = $this -> _pPDO -> prepare ($sQuery);
            $oStmt -> bindParam (':sId', $this -> _sId);
            if ($oStmt -> execute ())
            {
                $this->_aMutableData = $this->_aLoadedData = [];
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
    public function getAll (string $orderBy = null)
    {
        $aModels = array ();

        $sQuery = "select *
                   from " . $this->_sTable . " ";
        if (isset($this->_sId))
            $sQuery .= "where " . $this->_sIdColumn . " like CONVERT(:sId USING utf8) COLLATE utf8_bin ";
        if (isset ($orderBy) && !is_null ($orderBy) && strlen ($orderBy) > 1)
            $sQuery .= "order by " . $orderBy;
        $sQuery .= ";";

        try
        {
            $oStmt = $this->_pPDO->prepare ($sQuery);
            $oStmt->bindParam (':sId', $this->_sId, \ PDO::PARAM_STR);
            $oStmt->execute ();
            $i = 0;

            foreach ($oStmt->fetchAll (\ PDO :: FETCH_ASSOC) as $aRow)
            {
                $aModels[$i] = new Model ($this -> _sTable, $this -> _sIdColumn, $aRow [$this -> _sIdColumn], false);
                foreach ($aRow as $sKey => $sValue)
                {
                    $aModels[$i] -> $sKey = $sValue;
                }
                $aModels[$i]->fillLoadedData();

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
        return array ('_aLoadedData');
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

    /**
     * Copies the data of the mutable-array in the loadeddata-array.
     */
    public function fillLoadedData()
    {
        $this->_aLoadedData = $this->_aMutableData;
    }
}
