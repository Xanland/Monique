<?php
//
///**
// * Nuwani PHP IRC Bot Framework
// * Copyright (c) 2006-2010 The Nuwani Project
// *
// * Nuwani is a framework for IRC Bots built using PHP. Nuwani speeds up bot development by handling
// * basic tasks as connection- and bot management, timers and module managing. Features for your bot
// * can easily be added by creating your own modules, which will receive callbacks from the framework.
// *
// * This program is free software: you can redistribute it and/or modify it under the terms of the
// * GNU General Public License as published by the Free Software Foundation, either version 3 of the
// * License, or (at your option) any later version.
// *
// * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
// * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
// * General Public License for more details.
// *
// * You should have received a copy of the GNU General Public License along with this program.
// * If not, see <http://www.gnu.org/licenses/>.
// *
// * @copyright Copyright (c) 2006-2011 The Nuwani Project, http://nuwani.googlecode.com/
// * @author Peter Beverloo <peter@lvp-media.com>
// * @author Dik Grapendaal <dik.grapendaal@gmail.com>
// * @author Xander Hoogland <home@xanland.nl>
// * @version $Id: Model.php 151 2011-08-21 17:43:24Z dik.grapendaal@gmail.com $
// * @package Nuwani
// */
//
//namespace Nuwani;
//
//if (!class_exists('\PDO')) {
//    /** PDO is not required by the bot's core. * */
//    return;
//}
//
//class Model
//{
//    /**
//     * Id from the row to get
//     *
//     * @var integer
//     */
//    private $_sId;
//
//    /**
//     * The name of the column for $_sId
//     *
//     * @var string
//     */
//    private $_sIdColumn;
//
//    /**
//     * The table where to get the data from
//     *
//     * @var string
//     */
//    private $_sTable;
//
//    /**
//     * The data of the row
//     *
//     * @var array
//     */
//    private $_aData;
//
//    /**
//     * The connection with the database via PDO
//     *
//     * @var object
//     */
//    private $_pPDO;
//
//    /**
//     * Constructor for defining some settings
//     *
//     * @global object $pPDO
//     * @param string $sId
//     * @param string $sIdColumn
//     * @param string $sTable
//     */
//    public function __construct ( $sTable, $sIdColumn = 'id', $sId = NULL )
//    {
//        $this -> connect ();
//
//        $this -> _sTable = $sTable;
//
//        $this -> _sIdColumn = $sIdColumn;
//
//        $this -> _sId = $sId;
//
//        if ($this -> _sId !== NULL )
//        {
//            $this -> load ();
//        }
//    }
//
//    /**
//     * To work with serializing we have to make connection each time it is
//     * being deserialized. It is also used when just a Model is initialized.
//     */
//    private function connect ()
//    {
//        try
//        {
//            $this -> _pPDO = Database :: getInstance ();
//            $this -> _pPDO -> setAttribute (PDO :: ATTR_ERRMODE, PDO :: ERRMODE_WARNING);
//        }
//        catch (PDOException $PDOe)
//        {
//            // Shut up
//        }
//    }
//
//    /**
//     * Get all the data from the table specified by the id
//     *
//     * @return boolean
//     */
//    private function load ()
//    {
//        $sQuery = "SELECT * FROM `" . $this -> _sTable . "` WHERE `" . $this -> _sIdColumn . "` LIKE :sId;";
//        try
//        {
//            $oStmt = $this -> _pPDO -> prepare ($sQuery);
//            $oStmt -> bindParam (':sId', $this -> _sId, PDO::PARAM_STR);
//            $oStmt -> execute ();
//            for ($i = 0; $i < $oStmt -> columnCount(); $i++)
//            {
//                $aColumnInfo = $oStmt -> getColumnMeta ($i);
//                $sColumnName = $aColumnInfo ['name'];
//                $oStmt -> bindColumn ($sColumnName, $this -> _aData [$sColumnName]);
//            }
//
//            if ($oStmt -> fetch (PDO::FETCH_BOUND))
//            {
//                return true;
//            }
//            else
//            {
//                return false;
//            }
//        }
//        catch (PDOException $e)
//        {
//            // ePDOException :: writeMessage ($e);
//            return false;
//        }
//    }
//
//    /**
//     * Also set the changed data and update the table
//     *
//     * @return boolean
//     */
//    public function save ()
//    {
//        foreach ($this -> _aData as $sVarName => $sVarValue)
//        {
//            if ($this -> _sId === NULL )
//            {
//                $sQuery = "INSERT INTO `" . $this -> _sTable . "` (`" . $sVarName . "`) VALUES (:sVarValue);";
//            }
//            else
//            {
//                $sQuery = "UPDATE `" . $this -> _sTable . "` SET `" . $sVarName . "` = :sVarValue WHERE `" . $this -> _sIdColumn . "` = :sId;";
//            }
//
//            try
//            {
//                $oStmt = $this -> _pPDO -> prepare ($sQuery);
//                $oStmt -> bindParam (':sVarValue', $sVarValue);
//                if ($this -> _sId !== NULL)
//                {
//                    $oStmt -> bindParam (':sId', $this -> _sId);
//                }
//
//                if ($oStmt -> execute () && $this -> _sId === NULL)
//                {
//                    $this -> __construct ($this -> _sTable, $this -> _sIdColumn, $this -> _pPDO -> lastInsertId());
//                }
//                elseif ($oStmt -> execute ())
//                {
//                    // Can't return anything when in adding
//                    // a record. Will then return true out-
//                    // side the foreach statement
//                }
//                else
//                {
//                    return false;
//                }
//            }
//            catch (PDOException $e)
//            {
//                // ePDOException :: writeMessage ($e);
//                return false;
//            }
//        }
//
//        return true;
//    }
//
//    /**
//     * Getter for the data before doing save()
//     *
//     * @param string $sVarName
//     * @return mixed
//     */
//    public function __get ( $sVarName )
//    {
//        if (isset ( $sVarName ))
//        {
//            return $this -> _aData [ $sVarName ];
//        }
//    }
//
//    /**
//     * Setter for the data before doing save()
//     *
//     * @param string $sVarName
//     * @param mixed $sVarValue
//     */
//    public function __set ( $sVarName, $sVarValue )
//    {
//        $this -> _aData [ $sVarName ] = $sVarValue;
//
//        if ($this -> _sId === NULL )
//        {
//            $this -> save ();
//        }
//    }
//
//    /**
//     * Removing a record from a table should be handy and easy
//     *
//     * @return boolean
//     */
//    public function delete ()
//    {
//        $sQuery = "DELETE FROM `" . $this -> _sTable . "` WHERE `" . $this -> _sIdColumn . "` = :sId;";
//
//        try
//        {
//            $oStmt = $this -> _pPDO -> prepare ($sQuery);
//            $oStmt -> bindParam (':sId', $this -> _sId);
//            if ($oStmt -> execute ())
//            {
//                return true;
//            }
//            else
//            {
//                return false;
//            }
//        }
//        catch (PDOException $e)
//        {
//            return false;
//        }
//    }
//
//    /**
//     * Method for getting all the records of a table
//     *
//     * @return array cModel
//     */
//    public function getAll ($sIdColumn = null, $sOrder = null)
//    {
//        if ($sIdColumn === null)
//        {
//            $sIdColumn = $this -> _sIdColumn;
//        }
//        $aModels = array ();
//
//        $sQuery = "SELECT * FROM `" . $this -> _sTable . "`";
//        if (isset ($this -> _sId))
//        {
//            $sQuery .= " WHERE `" . $this -> _sIdColumn . "` LIKE :sId";
//        }
//        if ($sOrder !== null)
//        {
//            $sQuery .= ' order by ' . $sOrder;
//        }
//        $sQuery .= ";";
//        try
//        {
//            $oStmt = $this -> _pPDO -> prepare ($sQuery);
//            $oStmt -> bindParam (':sId', $this -> _sId);
//            $oStmt -> execute ();
//
//            foreach ($oStmt -> fetchAll (PDO :: FETCH_ASSOC) as $aRow)
//            {
//                $aModels[] = new Model ($this -> _sTable, $sIdColumn, $aRow [ $sIdColumn ] );
//            }
//        }
//        catch (PDOException $e)
//        {
//            // ePDOException :: writeMessage ($e);
//        }
//
//        return $aModels;
//    }
//
//    /**
//     * When serializing we only need the array containing all the getters/
//     * setters.
//     *
//     * @return array
//     */
//    public function __sleep ()
//    {
//        return array ('_aData');
//    }
//
//    /**
//     * Reconnect so we can save the model stored in a session
//     */
//    public function __wakeup ()
//    {
//        $this -> connect ();
//    }
//}