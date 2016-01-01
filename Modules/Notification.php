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

class Notification extends ModuleBase
{
	/**
     * Define the command name which should be used for triggering notifications. There are no
     * limits on the syntax you could use here. Comparison will be done case sensitive.
     *
     * @var string
     */

    const   NOTIFICATION_COMMAND_NAME       = '?dotell';

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

    const   NOTIFICATION_MESSAGE_LIMIT      = 10;

    /**
     * The active notifications will be stored in this array. Each notification stores the date,
     * channel it was set in, network it was set in, the source-user and the recieving-user.
     *
     * @var array
     */

    private $notifications;

    /**
     * Loading the notifications from a file is important, considering an IRC bot won't be
     * running until eternity. All notifications will be stored in a file within the Data folder
     * called "notifications.dat", and can only be loaded in the constructor.
     */

    public function __construct ()
    {
            $this -> notifications = array ();
            if (file_exists (__DIR__ . '/../Data/notifications.dat'))
            {
                    $serializedNotifications = file_get_contents (__DIR__ . '/../Data/notifications.dat');
                    if (strlen ($serializedNotifications) < 6) // minimum length of a serialized array.
                            return;

                    $savedNotifications = unserialize ($serializedNotifications);
                    if (is_array ($savedNotifications) && count ($savedNotifications) > 0)
                    {
                            $this -> notifications = $savedNotifications;
                    }
            }
    }

    /**
     * In order to properly save all notifications when the bot is being shut down, we'll make
     * sure the save() method gets triggered upon destruction of this class.
     */

    public function __destruct ()
    {
            $this -> save ();
    }

    /**
     * Storing the notifications to a file makes sure that they will be available in future
     * sessions of this bot. We'll use the file "notifications.dat" for this purpose.
     *
     * @return boolean Were we able to properly store all the notifications?
     */

    public function save ()
    {
            $serializedNotifications = serialize ($this -> notifications);
            if (is_writable (__DIR__ . '/../Data/notifications.dat'))
            {
                    file_put_contents (__DIR__ . '/../Data/notifications.dat', $serializedNotifications);
                    return true;
            }

            return false;
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
             * The first check which we do is to see whehter the user is trying to register a
             * new notification. The message has to start with the chosen command name.
             */

            $loweredNickname  = strtolower ($nickname);
            $processedCommand = false;

            if (substr ($message, 0, strlen (self :: NOTIFICATION_COMMAND_NAME)) == self :: NOTIFICATION_COMMAND_NAME)
            {
                    $commandParameters = trim (substr ($message, strlen (self :: NOTIFICATION_COMMAND_NAME) + 1));
                    if (strlen ($commandParameters) == 0 || strpos ($commandParameters, ' ') === false)
                    {
                            $bot -> send ('PRIVMSG ' . $channel . ' :10Usage: ' . self :: NOTIFICATION_COMMAND_NAME . ' nickname message');
                            return true;
                    }

                    list ($receiver, $notification) = explode (' ', $commandParameters, 2);
                    $receiver = strtolower ($receiver);

                    if ($receiver == $loweredNickname)
                    {
                            $bot -> send ('PRIVMSG ' . $channel . ' :10Warning: You cannot send notifications to yourself.');
                            return true;
                    }

                    if (!isset ($this -> notifications [$receiver]))
                    {
                            $this -> notifications [$receiver] = array ();
                    }

                    if (count ($this -> notifications [$receiver]) >= self :: NOTIFICATION_MESSAGE_LIMIT)
                    {
                            $bot -> send ('PRIVMSG ' . $channel . ' :10Warning: The message could not be stored, because there are already ' . self :: NOTIFICATION_MESSAGE_LIMIT . ' messages waiting for ' . $receiver);
                            return true;
                    }

                    $this -> notifications [$receiver] [] = array
                    (
                            'channel'       => $channel,
                            'network'       => $bot ['Network'],
                            'sender'        => $nickname,
                            'message'       => $notification,
                            'timestamp'     => time ()
                    );

                    $bot  -> send ('PRIVMSG ' . $channel . ' :Sure ' . $nickname . '!');
                    $this -> save ();

                    $processedCommand = true;
            }

            /**
             * Otherwise it's possible that we might have to inform them of pending messages,
             * which requires an easy lookup in the local notifications array.
             */

            if (isset ($this -> notifications [$loweredNickname]) && $processedCommand === false)
            {
                    foreach ($this -> notifications [$loweredNickname] as $index => $notification)
                    {
                            if ($notification ['network'] != $bot ['Network'])
                                    continue;

                            if (self :: NOTIFICATION_NETWORK_WIDE === false && $notification ['channel'] != $channel)
                                    continue;

                            $timeDifference = $this -> formatNotificationInterval ($notification ['timestamp']);
                            $notificationMessage = $nickname . ', ' . $notification ['sender'] . ' said: ' . $notification ['message'] . ' 15(' . $timeDifference . ')';

                            $bot -> send ('PRIVMSG ' . $channel . ' :' . $notificationMessage);

                            unset ($this -> notifications [$loweredNickname][$index]);
                    }

                    if (count ($this -> notifications [$loweredNickname]) == 0)
                    {
                            unset ($this -> notifications [$loweredNickname]);
                    }

                    $this -> save ();
                    return true;
            }

            return $processedCommand;
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

            $intervalChunks = array ();
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

            if ($interval -> h !== 0) // hours
            {
                    $format [] = '%h ' . $addPlural ($interval -> h, 'hour');
            }

            if ($interval -> i !== 0) // minutes
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