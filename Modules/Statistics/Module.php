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
use Nuwani\Common\stringH;
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
        $configuration = Configuration :: getInstance ();
        $this -> configuration = $configuration -> get ('Statistics');

        $this -> logger = new $this -> configuration ['ILoggerImplementation'] ();
    }

    public function onChannelPrivmsg (Bot $bot, string $channel, string $nickname, string $message)
    {
        if ($bot ['Nickname'] != 'Monique')
            return;

        $message = Util :: stripFormat ($message);
        $messageParts = explode (' ', $message);
        $messageType = 'privmsg';

        $this -> HandleChannelWithIngameChat ('LVP', $channel, $nickname, $message, $messageType, $messageParts);
        $this -> HandleChannelWithIngameChat ('OAS MC', $channel, $nickname, $message, $messageType, $messageParts);

        if (!in_array ($channel, $this -> configuration ['Channels'])
            || $messageType == 'nyi')
            return; // Not a channel we need to log or not yet implemented messagetype

        $this -> logger -> CreateInstance ();

        $this -> logger -> SetDetails ($channel, $nickname);
        $this -> logger -> SetMessageType ($messageType);
        $this -> logger -> SetMessage ($message);

        $this -> logger -> SaveInstance ();
    }

    public function onCTCP (Bot $bot, string $messageSource, string $nickname, string $type, string $message)
    {
        if ($bot ['Nickname'] != 'Monique')
            return;

        if (strtolower ($type) == 'action' && in_array ($messageSource, $this -> configuration ['Channels']))
        {
            $this -> logger -> CreateInstance ();

            $this -> logger -> SetDetails ($messageSource, $nickname);
            $this -> logger -> SetMessageType ('action');
            $this -> logger -> SetMessage ($message);

            $this -> logger -> SaveInstance ();
        }
    }

    private function HandleChannelWithIngameChat (string $servername, string &$channel, string &$nickname, string &$message, string &$messageType, array $messageParts)
    {
        if (strtolower ($channel) == '#lvp.echo' || strtolower ($channel) == '#xanland.logging')
        {
            if (in_array ($nickname, $this -> configuration ['NuwaniSistersEchoBots'])
                && $this -> IsValidEchoLine ($servername, $messageParts, $messageType))
            {
                $channel = $servername . ' In-game';
                $nickname = $this -> GetNicknameFromValidEchoLine ($servername, $messageParts, $messageType);
                $message = $this -> GetMessageFromValidEchoLine ($servername, $messageParts, $messageType);
            }
        }
    }

    private function IsValidEchoLine (string $servername, array $messageParts, string &$messageType) : bool
    {
        if ($servername == 'LVP')
        {
            if (strpos ($messageParts [0], '[') !== false && strpos ($messageParts [0], ']') !== false && strpos ($messageParts [1], ':') !== false)
            {
                $messageType = 'privmsg';
                return true;
            }

            if (strpos ($messageParts [0], '[') !== false && strpos ($messageParts [0], ']') !== false && strpos ($messageParts [1], '***') !== false)
            {
                $messageType = 'nyi';
                return true;
            }

            if (strpos ($messageParts [0], '[') !== false && strpos ($messageParts [0], ']') !== false)
            {
                $messageType = 'action';
                return true;
            }
        }
        else if ($servername == 'OAS MC')
        {
            if (strpos ($messageParts [0], '[') !== false && strpos ($messageParts [0], ']') !== false && strpos ($messageParts [1], '<') !== false && strpos ($messageParts [1], '>') !== false)
            {
                $messageType = 'privmsg';
                return true;
            }

            if (strpos ($messageParts [0], '[') !== false && strpos ($messageParts [0], ']') !== false && strpos ($messageParts [0], '***') !== false)
            {
                $messageType = 'action';
                return true;
            }
        }

        return false;
    }

    private function GetNicknameFromValidEchoLine (string $servername, array $messageParts, string $messageType) : string
    {
        if ($servername == 'LVP')
        {
            if ($messageType == 'privmsg')
                return str_replace (':', '', $messageParts [1]);

            if ($messageType == 'action')
            {
                if (!stringH::IsNullOrWhiteSpace($messageParts [1]))
                    return $messageParts [1];

                return '';
            }
        }
        else if ($servername == 'OAS MC')
        {
            if ($messageType == 'privmsg')
                return str_replace (array ('<', '>'), '', $messageParts [1]);

            if ($messageType == 'action')
            {
                $nickname = strstr ($messageParts [0], '***');
                unset ($nickname[0]);
                return $nickname;
            }
        }

        return '';
    }

    private function GetMessageFromValidEchoLine (string $servername, array $messageParts, string $messageType) : string
    {
        if ($servername == 'LVP')
        {
            if ($messageType == 'privmsg' || $messageType == 'action')
                return Util :: getPieces ($messageParts, ' ', 2);
        }
        else if ($servername == 'OAS MC')
        {
            if ($messageType == 'privmsg')
                return Util :: getPieces ($messageParts, ' ', 2);

            if ($messageType == 'action')
                return Util :: getPieces ($messageParts, ' ', 1);
        }

        return '';
    }
}
