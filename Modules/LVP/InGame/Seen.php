<?php
/**
 * Nuwani PHP IRC Bot Framework
 * Copyright (c) 2006-2010 The Nuwani Project
 *
 * Nuwani is a framework for IRC Bots built using PHP. Nuwani speeds up bot
 * development by handling basic tasks as connection- and bot management, timers
 * and module managing. Features for your bot can easily be added by creating
 * your own modules, which will receive callbacks from the framework.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright Copyright (c) 2006-2010 The Nuwani Project
 * @package LVP InGame Seen Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP\InGame;

use Commands;
use LVP;
use LVP\LVPQueryApi;
use Nuwani\BotManager;
use Nuwani\Model;

class Seen
{
    /**
     * Name of the table filled with players
     */
    const SEEN_TABLE = 'lvp_person_last_seen';

    /**
     * Gets the data of the specified person from the database
     *
     * @param string $fieldName    Name of the field to search on
     * @param string $value        Value of the field defined in $fieldName
     * @param bool   $bypassInsert Makes from the insert an update
     *
     * @return mixed False or the object of the player
     */
    public static function getPersonSeenData (string $fieldName, string $value, bool $bypassInsert = false)
    {
        return new Model (self :: SEEN_TABLE, $fieldName, $value, true, $bypassInsert);
    }

    /**
     * Sets the data of the person after he changed their name or joined or left the game
     *
     * @param string $name   Username of the player
     * @param string $id     User-id of the player
     * @param string $reason Why the user left the game (or that it is online)
     *
     * @return bool
     */
    private static function setPersonSeenData (int $id, string $name, string $reason = 'online')
    {
        if ($name == '')
            $person = self :: getPersonSeenData ('iId', $id);
        else
            $person = self :: getPersonSeenData ('lvp_person_last_seen_id', $name);

        $person -> lvp_person_last_seen_id = $name;
        $person -> iId = $id;
        $person -> iTime = time ();
        $person -> sReason = $reason;
        if (!$person -> save ())
            return false;

        return true;
    }

    /**
     * Gets called from the main-LVP-module to handle a player
     *
     * @param string $personInfo Line of one of the echo-bots about what happened in-game
     *
     * @return bool
     */
    public static function setPersonInformation (string $personInfo)
    {
        if (preg_match('/\[(.+)\] \*\*\* (.+) (.+) the game(.*)\./', $personInfo, $personData))
        {
            if ($personData [3] == 'joined' && $personData[4] == '')
                return self :: setPersonSeenData ($personData [1], $personData [2]);
            else if ($personData [3] == 'left' && $personData[4] != '')
                return self :: setPersonSeenData (-1, $personData [2], $personData [4]);
        }
        else if (preg_match('/\[(.+)\] \*\*\* (.+) decided to play as a guest\./', $personInfo, $personData))
            return self :: setPersonSeenData ($personData [1], '', ' (leaving)');
    }

    /**
     * Synchronizes the online players with what is on the server.
     */
    public static function syncOnlinePlayers ()
    {
        $oApi               = new LVPQueryApi();
        $aPlayerInformation = $oApi -> getDetailedPlayers ();
        $aOnlinePlayers     = array();

        $ii = 0;
        foreach ($aPlayerInformation as $aPlayer)
        {
            $aOnlinePlayers['name'][$ii] = $aPlayer['nickname'];
            $aOnlinePlayers['id'][$ii] = $aPlayer['playerid'];
            $ii++;
        }

        $oSeenData = self :: getPersonSeenData('lvp_person_last_seen_id', '%');
        $i = 0;
        $amountOfPlayersSynced = 0;
        foreach ($oSeenData -> getAll() as $oPerson)
        {
            if (in_array($oPerson -> lvp_person_last_seen_id, $aOnlinePlayers['name']))
            {
                if ($oPerson -> sReason != 'online')
                {
                    //$oPerson -> lvp_person_last_seen_id = $aOnlinePlayers['name'];
                    $oPerson -> sReason = 'online';
                    $oPerson -> iId = $aOnlinePlayers['id'][$i];
                    $oPerson -> iTime = time();
                    if ($oPerson -> save ())
                        $amountOfPlayersSynced++;
                }
            }
            else
            {
                if ($oPerson -> sReason == 'online')
                {
                    //$oPerson -> lvp_person_last_seen_id = $aOnlinePlayers['name'];
                    $oPerson -> sReason = ' (desync)';
                    $oPerson -> iId = -1;
                    $oPerson -> iTime = time();
                    if ($oPerson -> save ())
                        $amountOfPlayersSynced++;
                }
            }

            $i++;
        }

        $j = 0;
        foreach ($aOnlinePlayers['name'] as $sName)
        {
            $oPlayer = self :: getPersonSeenData ('lvp_person_last_seen_id', $sName);

            if ($oPlayer -> sReason != 'online')
            {
                $oPlayer -> lvp_person_last_seen_id = $sName;
                $oPlayer -> sReason = 'online';
                $oPlayer -> iId = $aOnlinePlayers['id'][$j];
                $oPlayer -> iTime = time();
                if ($oPlayer -> save ())
                    $amountOfPlayersSynced++;
            }

            $j++;
        }

        $pBot = BotManager :: getInstance () -> offsetGet ('channel:' . LVP :: LOGGING_CHANNEL);
        $pBot -> send ('PRIVMSG ' . LVP :: LOGGING_CHANNEL . ' :LVP\InGame\Seen\syncOnlinePlayers: Synced ' .
            $amountOfPlayersSynced . ' players.');
    }

    /**
     * Registers the .seen-command in the commands-module for use in-game
     *
     * @param Commands $moduleManager Object so the command can be register in the commands-module
     */
    public static function addSeenCommand (Commands $moduleManager)
    {
        $moduleManager -> registerCommand (new \ Command ('.seen',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if ($aParams [0] == '')
                    echo '!msg * Usage: .seen <username>';
                else
                {
                    $oLastSeenPerson = self :: getPersonSeenData ('lvp_person_last_seen_id', $aParams[0]);
                    if (!is_null ($oLastSeenPerson -> lvp_person_last_seen_id))
                    {
                        if ($oLastSeenPerson -> sReason == 'online')
                            echo '!msg ' . $oLastSeenPerson -> lvp_person_last_seen_id . ' is already online for ' . \ Util :: formatTime (time () - $oLastSeenPerson -> iTime, true) . '.';
                        else
                            echo '!msg ' . $oLastSeenPerson -> lvp_person_last_seen_id . ' was last seen online ' . date ('H:i:s @ d-m-Y', $oLastSeenPerson -> iTime) . $oLastSeenPerson -> sReason;
                    }
                    else
                        echo '!msg * Error: Sorry, this username has not (yet) been found.';
                }
            }
        ));
    }

    /**
     * Resets the amount of online players to what it should be.
     */
    public static function resetOnlinePlayersAtGamemodeInit ()
    {
        $onlinePlayers = self :: getPersonSeenData ('sReason', 'online', true);
        foreach ($onlinePlayers -> getAll() as $onlinePlayer)
        {
            $onlinePlayer -> iId = -1;
            $onlinePlayer -> iTime = time();
            $onlinePlayer -> sReason = ' (init)';
            $onlinePlayer -> save ();
        }
    }
}