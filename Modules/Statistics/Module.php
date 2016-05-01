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
 * @package Statistics Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

require_once 'Loggers/ILogger.php';
require_once 'Loggers/DatabaseLogger.php';

use Nuwani\Bot;
use Nuwani\Configuration;

if (!class_exists ('Model'))
{
    // Class is needed for the Statistics-module to work
    return;
}

class Statistics extends ModuleBase
{
    private $configuration;

    private $logger;

    public function __construct ()
    {
        $this -> configuration = Configuration :: getInstance ();
        $aConfiguration = $this -> configuration -> get ('Statistics');

        $this -> logger = new $aConfiguration['ILoggerImplementation'] ();
    }

    public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
    {
        $aMessageParts = explode (' ', $sMessage);

        if (strtolower ($sChannel) == '#lvp.echo'
            && in_array ($sNickname, $this -> configuration -> get ('NuwaniSistersEchoBots'))
            && (strpos ($aMessageParts [0], '[') && strpos ($aMessageParts [0], ']'))
            && strpos ($aMessageParts [1], ':'))
        {
            $sChannel = 'LVP In-game';
            $sNickname = str_replace (':', '', $aMessageParts [1]);
        }

        $this -> logger -> SetDetails ($sChannel, $sNickname);

    }
}