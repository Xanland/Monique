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
require_once 'InGame/Merchant.php';
require_once 'InGame/QuoteDevice.php';
require_once 'InGame/Seen.php';

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
        $moduleManager = ModuleManager :: getInstance () -> offsetGet ('Commands');
        Seen :: addSeenCommand ($moduleManager);
        NuwaniSisters :: addNuwaniSistersCommands ($moduleManager);
        QuoteDevice :: addMqdCommands($moduleManager);
        LVP :: add8ballCommand ($moduleManager);
    }

    /**
     * Here we process each individual message if it matches the right user and
     * channel. If so we can send it to the specific module to process it.
     *
     * @param  Bot    $pBot
     * @param  string $sChannel
     * @param  string $sNickname
     * @param  string $sMessage
     */
    public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
    {
        $sChannel = strtolower ($sChannel);
        $sMessage = Util::stripFormat ($sMessage);

        if ($sChannel == self :: ECHO_CHANNEL || $sChannel == self :: LOGGING_CHANNEL)
        {
            $pConfiguration = Configuration :: getInstance ();
            $aConfiguration = $pConfiguration -> get ('LVP');

            if (in_array ($sNickname, $aConfiguration['NuwaniSistersEchoBots']))
            {
                if ($sMessage == '*** Global Gamemode Initialization')
                {
                    Seen:: resetOnlinePlayersAtGamemodeInit ();
                    Merchant:: resetInformation ();
                }
                else
                {
                    Seen:: setPersonInformation ($sMessage);
                    Merchant:: setInformation ($sMessage);
                    if ($pBot['Nickname'] == 'Monique')
                        QuoteDevice:: setInformation ($pBot, $sMessage);
                }
            }

            if ($sMessage == '!players')
            {
                Seen:: syncOnlinePlayers ();
            }
        }

        if ($sChannel == self :: RADIO_CHANNEL && $sNickname == 'LVP_Radio')
        {
            Radio :: setNowPlayingInformation ($sMessage);
        }
    }

    private function sendNamesCommand (Bot $pBot, $sChannel)
    {
        if (strtolower ($sChannel) == self :: ECHO_CHANNEL)
            $pBot -> send ('NAMES ' . self :: ECHO_CHANNEL);
    }

    public function onChannelNames (Bot $pBot, $sChannel, $sNicknames)
    {
        $sChannel = strtolower ($sChannel);

        $aNicknames = explode (' ', NuwaniSisters :: cleanNicknameStringFromRights ($sNicknames));

        if ($sChannel == self :: ECHO_CHANNEL)
        {
            if (!in_array(NuwaniSisters::MASTER_BOT_NAME, $aNicknames))
                $pBot -> send ('PRIVMSG #xanland.logging :Nuwani - ' . date ('d-m-Y H:i:s'));

            NuwaniSisters:: setUsersOnlineInChannel ($aNicknames);
        }
    }

    public function onChannelJoin (Bot $pBot, $sChannel, $sNickname)
    {
        $this -> sendNamesCommand ($pBot, $sChannel);
    }

    public function onChannelPart (Bot $pBot, $sChannel, $sNickname, $sReason)
    {
        $this -> sendNamesCommand ($pBot, $sChannel);
    }

    public function onChannelKick (Bot $pBot, $sChannel, $sKicked, $sKicker, $sReason)
    {
        $this -> sendNamesCommand ($pBot, $sChannel);
    }

    public function onChangeNick (Bot $pBot, $sNickname, $sNewNick)
    {
        $this -> sendNamesCommand ($pBot, self :: ECHO_CHANNEL);
    }

    public function onQuit (Bot $pBot, $sNickname, $sReason)
    {
        $this -> sendNamesCommand ($pBot, self :: ECHO_CHANNEL);
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

                    'Reply hazy try again.',
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