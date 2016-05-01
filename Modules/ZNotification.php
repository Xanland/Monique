<?php
/**
 * Nuwani Notification Module
 *
 * People aren't around on IRC all the time, so it'll commonly happen that you want to tell someone
 * something but can't, because they're not around. This module adds a notification command which
 * will pass on a message as soon as they're available. The syntax is rather simple:
 *
 * ?tell [Nickname] [Message]
 *
 * When the user logs in to IRC again and says something, Nuwani will tell them about the messages
 * which were left behind. Most IRC services offer a similar service via Anope, called MemoServ, but
 * barely anyone uses it.
 *
 * The module can be set up in two ways, depending on the constants defined on the top of the class.
 * It can work on a per-channel basis, but also on a network-wide basis.
 *
 * @copyright Copyright (c) 2010 The Nuwani Project, http://nuwani.googlecode.com/
 * @author Peter Beverloo <peter@lvp-media.com>
 * @version $Id$
 * @package Modules
 */

use Nuwani\Bot;
use Nuwani\Common\stringH;
use Nuwani\Model;
use Nuwani\ModuleManager;

class ZNotification extends ModuleBase
{
	/**
     * Define the command name which should be used for triggering notifications. There are no
     * limits on the syntax you could use here. Comparison will be done case sensitive.
     *
     * @var string
     */

    const   NOTIFICATION_COMMAND_NAME       = 'tell';

    /**
     * Notifications can be either network wide (default) or on a per-channel basis. While the
     * network wide notifications are more accessible for users, you'd loose the possibility to
     * send someone (semi-)private messages.
     *
     * @var boolean
     */

    const   NOTIFICATION_NETWORK_WIDE       = false;

    /**
     * Define a maximum number of messages which we can store per user. Without a limit this
     * system could be abused to make the bot spam someone.
     *
     * @var integer
     */

    const   NOTIFICATION_MESSAGE_LIMIT      = PHP_INT_MAX;

    /**
     * Notifications are saved in the database. When saving them or looking them up we have to
     * know in which database table we have to take a look.
     *
     * @var string
     */

    const   NOTIFICATION_TABLE              = 'notification';

    /**
     * The notified users will be stored in this array. This way we take away a call to
     * the database EVERYTIME someone said something in a channel.
     *
     * @var array
     */

    private $notifiedUsers;

    /**
     * Loading the notifications from a file is important, considering an IRC bot won't be
     * running until eternity. All notifications will be stored in a file within the Data folder
     * called "notifications.dat", and can only be loaded in the constructor.
     */

    public function __construct ()
    {
        $this->notifiedUsers = [];
        foreach((new Model(self :: NOTIFICATION_TABLE, 'notification_id', '%'))->getAll() as $oNotification)
        {
            $sReceiver = strtolower($oNotification -> sReceiver);
            if (!in_array($sReceiver, $this->notifiedUsers))
                $this->notifiedUsers[] = $sReceiver;
        }

        $this->registerTellCommand();
    }

    /**
     * To know on which command we have to reply on when someone wants to set a notification, we
     * have to register the command with the Commands-module.
     */
    private function registerTellCommand()
    {
        $moduleManager = ModuleManager :: getInstance () -> offsetGet ('Commands');
        $moduleManager -> registerCommand (new \ Command (self :: NOTIFICATION_COMMAND_NAME,
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {

                if (stringH::IsNullOrWhiteSpace($sMessage))
                {
                    echo '7* Usage: nickname message';
                    return;
                }

                list ($sReceiver, $sNotification) = explode (' ', $sMessage, 2);
                if (strtolower($sReceiver) == strtolower($sNickname))
                {
                    echo '10* Info: You cannot send notifications to yourself.';
                    return;
                }

                $oNotification = new Model(self :: NOTIFICATION_TABLE, 'sReceiver', $sReceiver);
                if (count($oNotification->getAll()) >= self :: NOTIFICATION_MESSAGE_LIMIT)
                {
                    echo '10* Info: The message could not be stored, because there are already ' .
                        self :: NOTIFICATION_MESSAGE_LIMIT . ' messages waiting for ' . $sReceiver;
                    return;
                }

                $oNotification = new Model(self :: NOTIFICATION_TABLE, 'iTimestamp', time());
                $oNotification -> sReceiver = $sReceiver;
                $oNotification -> sSender = $sNickname;
                $oNotification -> sMessage = $sNotification;
                $oNotification -> iTimestamp = time ();
                $oNotification -> sNetwork = $pBot ['Network'];
                $oNotification -> sChannel = $sChannel;

                if ($oNotification -> save())
                {
                    $sReceiver = strtolower($oNotification -> sReceiver);
                    if (!in_array($sReceiver, $this->notifiedUsers))
                        $this->notifiedUsers[] = $sReceiver;

                    echo 'Sure ' . $sNickname . '!';
                }
                return;
            }
        ));
    }

    /**
     * Private messages to channels can mean that we have to do either of two things: create a
     * new notification or check for notifications on the active user's account.
     *
     * @param Bot $bot The bot which has received this message, which will answer if needed.
     * @param string $channel Channel in which the message has been distributed.
     * @param string $nickname Nickname of the user which distributed a message.
     * @param string $message The actual message the user has sent to the channel.
     * @return boolean Were we able to do something with the message?
     */

    public function onChannelPrivmsg (Bot $bot, $channel, $nickname, $message)
    {
        /**
         * We have to check if we might have to inform them of pending messages,
         * which requires an easy lookup in the local notifiedUsers array.
         */

        $loweredNickname  = strtolower ($nickname);

        if (in_array($loweredNickname, $this->notifiedUsers))
        {
            foreach((new Model(self :: NOTIFICATION_TABLE, 'sReceiver', $nickname))->getAll() as $oNotification)
            {
                if ($oNotification->sNetwork != $bot ['Network'])
                    continue;

                if (self :: NOTIFICATION_NETWORK_WIDE === false && $oNotification->sChannel != $channel)
                    continue;

                $timeDifference = $this -> formatNotificationInterval ($oNotification -> iTimestamp);
                $notificationMessage = $nickname . ', ' . $oNotification -> sSender . ' said: ' .
                    $oNotification -> sMessage . ' 15(' . $timeDifference . ')';

                $bot -> send ('PRIVMSG ' . $channel . ' :' . $notificationMessage);
                $oNotification -> delete();

                $key = array_search($loweredNickname, $this->notifiedUsers);
                if ($key !== false)
                    unset($this->notifiedUsers[$key]);
            }
        }

        return true;
    }

    /**
     * Formatting the notification's interval may be done by invoking this method, which will
     * return a fancy twitter-like string saying how long ago it had been set.
     *
     * This method has been based on the method in the following comment on php.net:
     * http://nl3.php.net/manual/en/dateinterval.format.php#96768
     *
     * @param integer $timestamp Timestamp of the moment the notification had been set.
     * @return string A formatted string with the interval between now and the notify-date.
     */

    private function formatNotificationInterval ($timestamp)
    {
        $startDate = new DateTime (date ('Y-m-d H:i:s', $timestamp));
        $endDate   = new DateTime ('now');

        $interval  = $startDate -> diff ($endDate);
        $addPlural = function ($value, $word)
        {
            return $value == 1 ? $word : $word . 's';
        };

        $format = array ();
        if ($interval -> y !== 0) // years
        {
            $format [] = '%y ' . $addPlural ($interval -> y, 'year');
        }

        if ($interval -> m !== 0) // months
        {
            $format [] = '%m ' . $addPlural ($interval -> m, 'month');
        }

        if ($interval -> d !== 0) // days
        {
            $format [] = '%d ' . $addPlural ($interval -> d, 'day');
        }

        if ($interval ->h !== 0) // hours
        {
            $format [] = '%h ' . $addPlural ($interval -> h, 'hour');
        }

        if ($interval ->i !== 0) // minutes
        {
            $format [] = '%i ' . $addPlural ($interval -> i, 'minute');
        }

        if ($interval -> s !== 0 && count ($format) == 0)
        {
            $format [] = 'less than a minute';
        }

        /**
         * Use the two largest parts to avoid having really long notations of the interval
         * between the notification's set date, and the current time.
         */

        $formatString = '';
        if (count ($format) > 1)
        {
            $formatString = array_shift ($format) . ' and ' . array_shift ($format);
        }
        else
        {
            $formatString = array_shift ($format);
        }

        /**
         * Allow PHP's DateInterval::format to do the real work for us, append the text
         * "ago" to the result and return the string to the callee.
         */

        return $interval -> format ($formatString) . ' ago';
    }
}
