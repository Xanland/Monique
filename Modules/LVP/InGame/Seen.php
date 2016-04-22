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
//use Nuwani\BotManager;
use Nuwani\Common\stringHelper;
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
     *
     * @return mixed False or the object of the player
     */
    public static function getPersonSeenData (string $fieldName, string $value)
    {
        return new Model (self :: SEEN_TABLE, $fieldName, $value);
    }

    /**
     * Sets the data of the person after he changed their name or joined or left the game
     *
     * @param int    $id     Id of the player
     * @param string $name   Username of the player
     * @param string $reason Why the user left the game (or that it is online)
     *
     * @return bool
     */
    private static function setPersonSeenData (int $id, string $name, string $reason = 'online')
    {
        $person = self:: getPersonSeenData ('lvp_person_last_seen_id', $name);
        $person -> lvp_person_last_seen_id = $name;
        $person -> iId = $id;
        $person -> iTime = time ();
        $person -> sReason = $reason;
        if ($person -> save ())
            return true;

        return false;
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
                self :: setPersonSeenData ($personData [1], $personData [2]);
            else if ($personData [3] == 'left' && $personData[4] != '')
                self :: setPersonSeenData (-1, $personData [2], $personData [4]);
        }
        else if (preg_match('/\[(.+)\] \*\*\* (.+) decided to play as (.+) \(guest\)\./', $personInfo, $personData))
        {
            self :: setPersonSeenData (-1, $personData [2], ' (leaving)');
            self :: setPersonSeenData ($personData [1], $personData [3]);
        }
    }

    /**
     * Synchronizes the online players with what is on the server.
     */
    public static function syncOnlinePlayers ()
    {
        $oApi           = new LVPQueryApi();
        $aIngamePlayers = $oApi -> getDetailedPlayers ();

        $amountOfPlayersSynced = 0;
        $oSeenData = self :: getPersonSeenData('lvp_person_last_seen_id', '%');
        foreach ($oSeenData -> getAll() as $oPerson)
        {
            if (!in_array($oPerson -> lvp_person_last_seen_id, $aIngamePlayers) && ($oPerson -> sReason == 'online'))
            {
                $oPerson -> sReason = ' (leaving)';
                $oPerson -> iId = -1;
                $oPerson -> iTime = time();
                if ($oPerson -> save ())
                    $amountOfPlayersSynced++;
            }
        }

        foreach ($aIngamePlayers as $iPlayerId => $sNickname)
        {
            $oPlayer = self :: getPersonSeenData ('lvp_person_last_seen_id', $sNickname);

            if ($oPlayer -> sReason != 'online')
            {
                $oPlayer -> lvp_person_last_seen_id = $sNickname;
                $oPlayer -> sReason = 'online';
                $oPlayer -> iId = $iPlayerId;
                $oPlayer -> iTime = time();
                if ($oPlayer -> save ())
                    $amountOfPlayersSynced++;
            }
        }

//        $pBot = BotManager :: getInstance () -> offsetGet ('channel:' . LVP :: LOGGING_CHANNEL);
//        $pBot -> send ('PRIVMSG ' . LVP :: LOGGING_CHANNEL . ' :LVP\InGame\Seen\syncOnlinePlayers: Synced ' .
//            $amountOfPlayersSynced . ' players.');
    }

    /**
     * Registers the .seen-command in the commands-module for use in-game
     *
     * @param Commands $moduleManager Object so the command can be registered in the commands-module
     */
    public static function addSeenCommand (Commands $moduleManager)
    {
        $moduleManager -> registerCommand (new \ Command ('.seen',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (stringHelper::IsNullOrWhiteSpace($aParams [0]))
                    echo '!msg * Usage: .seen <username>';
                else
                {
                    $oLastSeenPerson = self :: getPersonSeenData ('lvp_person_last_seen_id', $aParams[0]);
                    if (!stringHelper::IsNullOrWhiteSpace($oLastSeenPerson -> lvp_person_last_seen_id))
                    {
                        if ($oLastSeenPerson -> sReason != 'online')
                        {
                            echo stringHelper::Format ('!msg {0} was last seen online {1}{2}.',
                                $oLastSeenPerson -> lvp_person_last_seen_id, date ('H:i:s @ d-m-Y', $oLastSeenPerson -> iTime),
                                $oLastSeenPerson -> sReason);
                        }
                        else
                        {
                            echo stringHelper::Format ('!msg {0} is already online for {1}.',
                                $oLastSeenPerson -> lvp_person_last_seen_id,
                                \ Util:: formatTime (time () - $oLastSeenPerson -> iTime));
                        }
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
        $onlinePlayers = self :: getPersonSeenData ('lvp_person_last_seen_id', '%');
        foreach ($onlinePlayers -> getAll() as $onlinePlayer)
        {
            if ($onlinePlayer -> sReason == 'online')
            {
                $onlinePlayer -> iId = -1;
                $onlinePlayer -> iTime = time();
                $onlinePlayer -> sReason = ' (init)';
                $onlinePlayer -> save ();
            }
        }
    }
}
