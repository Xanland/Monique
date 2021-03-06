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
 * @package LVP Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

require_once 'LVPQueryApi.php';
require_once 'NuwaniSisters.php';
require_once 'Radio.php';
require_once 'TempHistory.php';
require_once 'InGame/Merchant.php';
require_once 'InGame/QuoteDevice.php';
require_once 'InGame/Seen.php';

use LVP\TempHistory;
use Nuwani\Bot;
use Nuwani\Configuration;
use Nuwani\ModuleManager;
use LVP\NuwaniSisters;
use LVP\Radio;
use LVP\InGame\Merchant;
use LVP\InGame\QuoteDevice;
use LVP\InGame\Seen;

class LVP extends \ModuleBase
{
    /**
     * The channel we need to scan for the merchant and last-seen-module.
     */
    const ECHO_CHANNEL = '#lvp.echo';

    /**
     * The channel we need to scan for LVP Radio's information.
     */
    const RADIO_CHANNEL = '#lvp.radio';

    /**
     * The channel where we can execute the secret sync-command.
     */
    const LOGGING_CHANNEL = '#xanland.logging';

    /**
     * Add the .seen-command provided by the Seen-module
     */
    public function __construct ()
    {
        $commandsModule = ModuleManager :: getInstance () -> offsetGet ('Commands');
        Seen :: addSeenCommand ($commandsModule);
        NuwaniSisters :: addNuwaniSistersCommands ($commandsModule);
        QuoteDevice :: addMqdCommands($commandsModule);
        self :: add8ballCommand ($commandsModule);
        TempHistory :: addTempHistoryCommand ($commandsModule);
    }

    /**
     * Here we process each individual message if it matches the right user and
     * channel. If so we can send it to the specific module to process it.
     *
     * @param  Bot          $pBot
     * @param  string $channel
     * @param  string $sNickname
     * @param  string $message
     */
    public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
    {
        if ($sChannel[0] == '+' || $sChannel[0] == '%' || $sChannel[0] == '@' || $sChannel[0] == '&'
            || $sChannel[0] == '~')
            $channel = substr ($sChannel, 1);

        $channel = strtolower ($sChannel);
        $message = Util::stripFormat ($sMessage);

        if ($channel == self :: ECHO_CHANNEL || $channel == self :: LOGGING_CHANNEL)
        {
            $pConfiguration = Configuration :: getInstance ();
            $aConfiguration = $pConfiguration -> get ('LVP');

            if (in_array ($sNickname, $aConfiguration['NuwaniSistersEchoBots']))
            {
                if ($message == '*** Global Gamemode Initialization')
                {
                    Seen:: resetOnlinePlayersAtGamemodeInit ();
                    Merchant:: resetInformation ();
                }
                else
                {
                    Seen:: setPersonInformation ($message);
                    Merchant:: setInformation ($message);
                    TempHistory :: messageHandler ($message);
                    if ($pBot['Nickname'] == 'Monique')
                        QuoteDevice:: setInformation ($pBot, $message);
                }
            }

            if ($message == '!players')
            {
                Seen:: syncOnlinePlayers ();
            }
        }

        if ($channel == self :: RADIO_CHANNEL && $sNickname == 'LVP_Radio')
        {
            Radio :: setNowPlayingInformation ($message);
        }
    }

    public function onChannelJoin (Bot $pBot, $sChannel, $sNickname)
    {
        if (strtolower ($sChannel) == self :: ECHO_CHANNEL
            && strtolower ($sNickname) == strtolower (NuwaniSisters::MASTER_BOT_NAME))
        {
            NuwaniSisters::$isMasterBotAvailable = true;
        }
    }

    public function onChannelPart (Bot $pBot, $sChannel, $sNickname, $sReason)
    {
        if (strtolower ($sChannel) == self :: ECHO_CHANNEL
            && strtolower ($sNickname) == strtolower (NuwaniSisters::MASTER_BOT_NAME))
        {
            NuwaniSisters::$isMasterBotAvailable = false;
        }
    }

    public function onChannelKick (Bot $pBot, $sChannel, $sKicked, $sKicker, $sReason)
    {
        if (strtolower ($sChannel) == self :: ECHO_CHANNEL
            && strtolower ($sKicked) == strtolower (NuwaniSisters::MASTER_BOT_NAME))
        {
            NuwaniSisters::$isMasterBotAvailable = false;
        }
    }

    public function onQuit (Bot $pBot, $sNickname, $sReason)
    {
        if (strtolower ($sNickname) == strtolower (NuwaniSisters::MASTER_BOT_NAME))
        {
            NuwaniSisters::$isMasterBotAvailable = false;
        }
    }

    public static function add8ballCommand (Commands $moduleManager)
    {
        $moduleManager -> registerCommand (new \ Command ('.8ball',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (substr ($sMessage, -1) != '?')
                {
                    echo '!msg * Absurdity: Yours truly cordially beseeches thee to endow thine enquiry.';
                    return;
                }

                // An array with the traditional old and new answers.
                $a_sAnswers = array
                (
                    'It is certain!',
                    'It is decidedly so!',
                    'Without a doubt!',
                    'Yes, definitely!',
                    'You may rely on it!',
                    'As I see it, yes!',
                    'Most likely!',
                    'Outlook good!',
                    'Yes!',
                    'Signs point to yes!',

                    'Reply hazy, try again.',
                    'Ask again later.',
                    'Better not tell you now.',
                    'Cannot predict now.',
                    'Concentrate and ask again.',
                    'Maybe.',

                    'Don\'t count on it!',
                    'My reply is no!',
                    'My sources say no!',
                    'Outlook not so good!',
                    'Very doubtful!',
                    'No!'
                );
                $sAnswer = $a_sAnswers[mt_rand(0, count($a_sAnswers) - 1)];

                echo '!msg ' . $sNickname . ': ' . $sAnswer;
                return;
            }
        ));
    }
}