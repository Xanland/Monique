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
 * @package Output Module
 * @author Peter Beverloo <peter@lvp-media.com>
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * @see http://nuwani.googlecode.com
 */

use Nuwani\Bot;

class Output extends ModuleBase
{
    private $_aForbiddenChannels;

    public function __construct ()
    {
        $this -> _aForbiddenChannels = array ('#xanland.logging',
                                              '#lvp.echo');
    }

        /**
         * A function that detects when someone enters a certain channel,
         * specified by the two arguments in this function.
         *
         * @param Bot $pBot The bot which received this message.
         * @param string $sChannel Where did this person join?
         * @param string $sNickname Nickname who joined this channel.
         */

        public function onChannelJoin (Bot $pBot, $sChannel, $sNickname)
        {
                // $pBot -> send ('PRIVMSG #xanland.logging :[onChannelJoin in/by ' . $sChannel . '] * ' . $sNickname . ' has joined the channel.');
        }

        /**
         * This function gets invoked when someone leaves a channel, possibly
         * with a defined reason, otherwise just.. a silent part.
         *
         * @param Bot $pBot The bot which received this message.
         * @param string $sChannel The channel that he/she left.
         * @param string $sNickname Who is parting the channel?
         * @param string $sReason Reason why this person left the channel.
         */

        public function onChannelPart (Bot $pBot, $sChannel, $sNickname, $sReason)
        {
                // $pBot -> send ('PRIVMSG #xanland.logging :[onChannelPart in/by ' . $sChannel . '] * ' . $sNickname . ' has left the channel (' . $sReason . ')');
        }

        /**
         * Of course we want to be able to catch FiXeR's being kicked from the
         * channel for various reasons, which is what this function does.
         *
         * @param Bot $pBot The bot which received this message.
         * @param string $sChannel Channel this kick occured in.
         * @param string $sKicked Nickname of the one who got kicked.
         * @param string $sKicker The person who kicked the former nickname.
         * @param string $sReason Why did this person get kicked?
         */

        public function onChannelKick (Bot $pBot, $sChannel, $sKicked, $sKicker, $sReason)
        {
                $pBot -> send ('PRIVMSG #xanland.logging :[onChannelKick in ' . $sChannel . '] * ' . $sKicked . ' has been kicked by ' . $sKicker . ' (' . $sReason . ')' );
        }

        /**
         * When we receive a normal message, in a normal channel, this is the function
         * that will be called by the Bot-core system.
         *
         * @param Bot $pBot The bot which received this message.
         * @param string $sChannel The channel this message was spammed in
         * @param string $sNickname Nickname who is messaging us (or the channel).
         * @param string $sMessage The message being send to us.
         */

        public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
        {
            if (!in_array (strtolower ($sChannel), $this -> _aForbiddenChannels))
            {
                // $pBot -> send ('PRIVMSG #xanland.logging :[onChannelPrivmsg in/by ' . $sChannel . '] <' . $sNickname . '> ' . $sMessage );
            }
        }

        /**
         * This function will receive the private messages received by us which
         * did not occur in a channel, which could be an upset estroe.
         *
         * @param Bot $pBot The bot which received this message.
         * @param string $sNickname Nickname who is PM'ing us.
         * @param string $sMessage The message being send to us.
         */

        public function onPrivmsg (Bot $pBot, $sNickname, $sMessage)
        {
                $pBot -> send ('PRIVMSG #xanland.logging :[onPrivmsg by ' .$sNickname . '] private: ' . $sMessage );
        }

    /**
     * This function gets invoked when someone sends us a CTCP message, which
     * could, for example, be an ACTION.
     *
     * @param \Bot   $pBot
     * @param string $sDestination Where did this CTCP come from?
     * @param string $sNickname    Who did send us this CTCP message?
     * @param string $sType        Type of CTCP message that has been received.
     * @param string $sMessage     The actual CTCP message.
     */

        public function onCTCP (Bot $pBot, $sDestination, $sNickname, $sType, $sMessage)
        {
                if ($sDestination [0] != '#') // We only want channel messages;
                        return ;

                $pBot -> send ('PRIVMSG #xanland.logging :[onCTCP in/by ' . $sDestination . ' (' . $sType . ')] * ' . $sNickname . ' ' . $sMessage );
        }

        /**
         * An error could occur for various reasons. Not-initialised variables, deviding
         * things by zero, or using older PHP functions which shouldn't be used.
         *
         * @param integer $nErrorType Type of error that has occured, like a warning.
         * @param string $sErrorString A textual representation of the error
         * @param string $sErrorFile File in which the error occured
         * @param integer $nErrorLine On which line did the error occur?
         */

        public function onError (Bot $pBot, $nErrorType, $sErrorString, $sErrorFile, $nErrorLine)
        {
                $sError = '';

                switch ($nErrorType)
                {
                        case E_WARNING:
                        case E_USER_WARNING:    { $sError .= '[Warning]';       break; }
                        case E_NOTICE:
                        case E_USER_NOTICE:     { $sError .= '[Notice]';        break; }
                        case E_DEPRECATED:
                        case E_USER_DEPRECATED: { $sError .= '[Deprecated]';    break; }
                }

                $sError .= ' Error occured in "' . $sErrorFile . '" on line ' . $nErrorLine . ': "';
                $sError .= $sErrorString . '".' . PHP_EOL;
                // $pBot -> send ('PRIVMSG #xanland.logging :' . $sError);
        }

        /**
         * When exceptions occur, it would be quite convenient to be able and fix them
         * up. That's why this function exists - output stuff about the exception.
         *
         * @param Bot $pBot The bot that was active while the exception occured.
         * @param string $sSource Source of the place where the exception began.
         * @param Exception $pException The exception that has occured.
         */

        public function onException (Bot $pBot, $sSource, Exception $pException)
        {
                $sMessage  = '[Exception] Exception occured in "' . $pException -> getFile () . '" on line ';
                $sMessage .= $pException -> getLine () . ': "' . $pException -> getMessage () . '".' . PHP_EOL;

                if ($sSource !== null && $pBot instanceof Bot)
                {
                        $pBot -> send ('PRIVMSG #xanland.logging :' . $sSource . ' :' . $sMessage);
                }

                $pBot -> send ('PRIVMSG #xanland.logging :' . $sMessage);
        }

        /**
         * This function gets invoked when a bot sends out some command. This could
         * be a message, but also a ping, connect or anything else. Use appropriate!
         *
         * @param Bot $pBot The bot that is sending the message.
         * @param string $sCommand The message that's being send.
         */

        public function onRawSend (Bot $pBot, $sCommand)
        {
                if (strtoupper (substr ($sCommand, 0, 7)) == 'PRIVMSG')
                {
                        list ($_foo, $sChannel, $sMessage) = explode (' ', $sCommand, 3);
                        if (substr ($sMessage, 0, 1) == ':')
                        {
                                // Remove the column
                                $sMessage = substr ($sMessage, 1);
                        }

                       if (!in_array (strtolower ($sChannel), $this -> _aForbiddenChannels))
                       {
                           // $pBot -> send ('PRIVMSG #xanland.logging :[onRawSend in/by ' . $sChannel . '] <' . $pBot ['Nickname'] . '> ' . $sMessage );
                       }
                }
        }

        /**
         * This function gets invoked when the user receives a notice from a
         * user, the server or someone else.
         *
         * @param Bot $pBot The bot that is sending the message.
         * @param string $sToNickname Nickname of the bot who receives
         * @param string $sFromNickname Nickname of who we get the notice
         * @param string $sMessage The message it gives us
         */
        public function onNotice (Bot $pBot, $sChannel, $sNickname, $sMessage)
        {
            if (strtolower ($sChannel) != 'auth' && strtolower ($sNickname) != 'gtanet')
            {
                $pBot -> send ('PRIVMSG #xanland.logging :[onNotice ' .$sChannel . '/' . $sNickname . ']: ' . $sMessage );
            }
        }
}