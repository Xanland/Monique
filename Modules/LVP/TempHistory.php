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
 * @package LVP Radio Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP;

use Command;
use Commands;
use ModuleBase;
use Nuwani\Common\stringH;
use Nuwani\Model;
use Util;

class TempHistory
{
    public static function addTempHistoryCommand (Commands $commandsModule)
    {
        $commandsModule -> registerCommand (new Command ('temphistory',
            function ($bot, $destination, $channel, $nickname, $params, $message)
            {
                if (strtolower ($channel) != '#lvp.crew' && strtolower ($channel) != '#xanland.logging')
                    return;

                if (count ($params) < 1 || count ($params) > 2)
                {
                    echo '!temphistory [playerName] [items=5]';
                    return Command :: OUTPUT_USAGE;
                }

                $tempedPlayer = $params [0];

                $lvpTemphistorySingle = new Model ('lvp_temphistory', 'temped_player', $tempedPlayer);
                $lvpTemphistoryArray = $lvpTemphistorySingle -> getAll ('start_timestamp desc');
                $amountOfTempRows = count ($lvpTemphistoryArray);
                if ($amountOfTempRows == 0)
                {
                    echo stringH :: Format ('{0}*** No items found for player {1}.', ModuleBase::COLOUR_RED, $tempedPlayer);
                    return;
                }

                $items = $params [1] ?? 5;
                if ($items > $amountOfTempRows)
                    $items = $amountOfTempRows;

                $s = 's';
                if ($amountOfTempRows == 1)
                    $s = '';

                echo stringH::Format('{0}*** Temp. admin log for {1} ({2}/{3} item{4}){5}',
                    ModuleBase :: COLOUR_RED, $tempedPlayer, $items, $amountOfTempRows, $s, PHP_EOL);

                $i = 0;
                foreach ($lvpTemphistoryArray as $lvpTemphistory)
                {
                    $startTimestamp = $lvpTemphistory -> start_timestamp;
                    $endTimestamp  = $lvpTemphistory -> end_timestamp;
                    $takenByOrIngame = 'taken by: ' . $lvpTemphistory -> temp_taken_by;

                    if (is_null ($lvpTemphistory -> temp_taken_by) && is_null($endTimestamp))
                    {
                        $takenByOrIngame = 'currently in-game';
                        $endTimestamp = 'now';
                    }

                    echo stringH::Format('{0}[{1}] {2}(Granted by: {3}, {4}){5}: {6} - {7} {8}({9}){10}',
                        ModuleBase :: COLOUR_RED, date ('d-m-Y', $startTimestamp), // 0, 1
                        ModuleBase :: COLOUR_DARKGREEN, $lvpTemphistory -> temp_granted_by, $takenByOrIngame, // 2, 3, 4
                        ModuleBase :: CLEAR, date ('H:i:s', $startTimestamp), ($endTimestamp == 'now' ? 'now' : date ('H:i:s', $endTimestamp)), // 5, 6, 7
                        ModuleBase :: COLOUR_GREY, Util :: formatTime(($endTimestamp == 'now' ? strtotime($endTimestamp) : $endTimestamp) - $startTimestamp, true), PHP_EOL); // 8, 9, 10

                    if($i++ == $items-1)
                        break;
                }
            }
        ));
    }

    public static function messageHandler (string $message)
    {
        $message = Util :: stripFormat($message);
        $messageArray = explode (' ', $message);

        if ($messageArray [0] == '***' && $messageArray [5] == 'temp.')
        {
            if ($messageArray [4] == 'granted')
                self :: onPlayerTempGranted ($messageArray [1] . ' ' . $messageArray [2], $messageArray [9]);
            else if ($messageArray [4] == 'taken')
                self :: onPlayerTempTaken ($messageArray [1], $messageArray [9]);
        }
        else if ($messageArray [1] == '***' && $messageArray [3] == 'left')
            self :: onPlayerTempTaken (str_replace (array (' ', '.', '(', ')'), array ('', '', '<', '>'), $messageArray [6]), $messageArray [2]);
    }

    public static function onPlayerTempGranted (string $temp_granted_by, string $temped_player)
    {
        $lvp_temphistoryArray = new Model('lvp_temphistory', 'temped_player', $temped_player);
        $lvp_temphistoryLatestTimestamp = $lvp_temphistoryArray -> getAll('start_timestamp desc')[0];
        if (isset ($lvp_temphistoryLatestTimestamp) && is_null ($lvp_temphistoryLatestTimestamp -> end_timestamp))
            return; // Granting to an already temp admin

        $lvp_temphistory = new Model('lvp_temphistory', 'lvp_temphistory_id');

        $lvp_temphistory -> temp_granted_by = $temp_granted_by;
        $lvp_temphistory -> temped_player = $temped_player;
        $lvp_temphistory -> start_timestamp = time();

        $lvp_temphistory -> save ();
    }

    public static function onPlayerTempTaken (string $temp_taken_by, string $temped_player)
    {
        $lvp_temphistoryArray = new Model('lvp_temphistory', 'temped_player', $temped_player);
        $lvp_temphistoryLatestTimestamp = $lvp_temphistoryArray -> getAll('start_timestamp desc')[0];
        if (!isset ($lvp_temphistoryLatestTimestamp))
            return; // Taken from someone not recorded granted temp admin rights

        $lvp_temphistory = new Model ('lvp_temphistory', 'start_timestamp', $lvp_temphistoryLatestTimestamp -> start_timestamp);
        if (!is_null ($lvp_temphistory -> end_timestamp))
            return; // Taken from someone who already left or got taken rights

        $lvp_temphistory -> temp_taken_by = $temp_taken_by;
        $lvp_temphistory -> end_timestamp = time();

        $lvp_temphistory -> save ();
    }
}
