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
 * @package LVP NuwaniSisters Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP;

use Commands;
use LVP\InGame\Seen;
use Nuwani\Model;

class NuwaniSisters
{
    const MASTER_BOT_NAME = 'Nuwani';
    //const MASTER_BOT_NAME = 'LVPpropbot2';

    private static $_nicknames = array();

    public static function addNuwaniSistersCommands (Commands $moduleManager)
    {
        $unusableCommandsArray = array ('pm', 'admin', 'say', 'getid', 'msg', 'announce', 'mute');

        foreach ($unusableCommandsArray as $command)
        {
            $moduleManager -> registerCommand (new \ Command ($command,
                function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage) use ($command)
                {
                    if (! \LVP\NuwaniSisters :: isMasterBotAvailable () && (strtolower ($sChannel) == '#xanland.logging' ||  strtolower ($sChannel) == '#lvp.echo'))
                    {
                        echo 'Due to ' . \LVP\NuwaniSisters :: MASTER_BOT_NAME . ' (main-bot) being offline, no commands can be handled until it is restarted. You can request a restart in #LVP (or #LVP.Dev).';
                        return \ Command :: OUTPUT_ERROR;
                    }
                }
            ));
        }

        $moduleManager -> registerCommand (new \ Command ('players',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (! \LVP\NuwaniSisters :: isMasterBotAvailable ()
                    && (strtolower ($sChannel) == '#lvp' || strtolower ($sChannel) == '#lvp.echo'))
                {
                    if (!isset ($aParams [0]))
                    {
                        $aIngamePlayers = new Model ('lvp_person_last_seen', 'sReason', 'online');
                        $aIngamePlayers = $aIngamePlayers -> getAll ();
//                        preg_match_all ('/<nickname>(.+)<\/nickname>/', file_get_contents ('http://sa-mp.nl/online.xml'), $aMatches);

                        $sEcho = '7Online players - 4No information about status and/or level available! (' . count ($aIngamePlayers) . '): ';

                        foreach ($aIngamePlayers as $sPlayer)
                            $sEcho .= $sPlayer -> lvp_person_last_seen_id . ', ';

                        Seen :: syncOnlinePlayers();

                        // 512 (max IRC message length) - 2 (\r\n) - 50 (max channel name) - 10 (PRIVMSG part + safety) = 450
                        echo wordwrap (substr ($sEcho, 0, -2), 450, PHP_EOL);
                    }
                    else
                    {
                        echo 'If ' . $aParams [0].  ' is a registered player on Las Venturas Playground, the latest information can be found here: http://profile.sa-mp.nl/' . urlencode ($aParams [0]) . '';
                        return \ Command :: OUTPUT_INFO;
                    }
                }
            }
        ));

        $moduleManager -> registerCommand (new \ Command ('getname',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (! \LVP\NuwaniSisters :: isMasterBotAvailable ()
                    && (strtolower ($sChannel) == '#lvp' || strtolower ($sChannel) == '#lvp.echo'))
                {
                    if (!isset ($aParams[0]))
                    {
                        echo '!getname [playerId] - 4No information about status and/or level available!';
                        return \ Command :: OUTPUT_USAGE;
                    }
                    else
                    {
                        $oMatchingPlayer = new Model ('lvp_person_last_seen', 'iId', $aParams [0]);
                        if ($oMatchingPlayer -> lvp_person_last_seen_id === null)
                            echo 'There is no player with ID ' . $aParams [0] . ' online at the moment. - 4No information about status and/or level available!';
                        else
                            echo 'Player with ID ' . $aParams [0] . ' has nickname "' . $oMatchingPlayer -> lvp_person_last_seen_id. '". - 4No information about status and/or level available!';

                        return \ Command :: OUTPUT_INFO;
                    }
                }
            }
        ));

//        $moduleManager -> registerCommand (new \ Command ('getid',
//            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
//            {
//                if (! \LVP\NuwaniSisters :: isMasterBotAvailable ()
//                    && (strtolower ($sChannel) == '#xanland.logging' || strtolower ($sChannel) == '#lvp.echo'))
//                {
//                    if (!isset ($aParams[0]))
//                    {
//                        echo '!getid [nickname] - 4No information about status and/or level available!';
//                        return \ Command :: OUTPUT_USAGE;
//                    }
//                    else
//                    {
//                        $oMatchingPlayers = new \Nuwani\Model ('lvp_person_last_seen', 'lvp_person_last_seen_id', '%' . $aParams [0] . '%');
//                        print_r ($oMatchingPlayers);
//                        if ($oMatchingPlayers -> lvp_person_last_seen_id === null)
//                            echo 'No online players found matching "' . $aParams [0] . '". - 4No information about status and/or level available!';
//                        else
//                        {
//                            $oMatchingPlayers = $oMatchingPlayers -> getAll ();
//                            $sEcho = 'Online players found (' . count ($oMatchingPlayers) . '): ';
//
//                            foreach ($oMatchingPlayers as $oPlayer)
//                            {
//                                if ($oPlayer -> sReason == 'online')
//                                    $sEcho .= $oPlayer -> lvp_person_last_seen_id . ' (ID: ' . $oPlayer -> iId . '), ';
//                            }
//
//                            $sEcho = substr ($sEcho, 0, -2);
//                            $sEcho .= ' - 4No information about status and/or level available!';
//
//                            // 512 (max IRC message length) - 2 (\r\n) - 50 (max channel name) - 10 (PRIVMSG part + safety) = 450
//                            echo wordwrap ($sEcho, 450, PHP_EOL);
//                        }
//
//                        return \ Command :: OUTPUT_INFO;
//                    }
//                }
//            }
//        ));
    }

    public static function cleanNicknameStringFromRights ($sNicknames)
    {
        $rightsArray = array ('~'
                             ,'&'
                             ,'@'
                             ,'%'
                             ,'+');

        return str_replace ($rightsArray, '', $sNicknames);
    }

    public static function setUsersOnlineInChannel ($aNicknames)
    {
        self :: $_nicknames = $aNicknames;
    }

    public static function isMasterBotAvailable ()
    {
        if (!in_array (self :: MASTER_BOT_NAME,  self :: $_nicknames))
            return false;

        return true;
    }
}