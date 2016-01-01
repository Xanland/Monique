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
 * @package Commands Module
 * @author Peter Beverloo <peter@lvp-media.com>
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * @see http://nuwani.googlecode.com
 */

use Nuwani \ Bot;
use Nuwani \ ErrorExceptionHandler;

class Command implements Serializable, ArrayAccess
{
        /**
         * These constants define the different types of output a command can give.
         * To trigger one of these, use the return statement somewhere in your
         * command code.
         *
         * @var integer
         */
        
        const   OUTPUT_NORMAL           = 0;
        const   OUTPUT_ERROR            = 1;
        const   OUTPUT_INFO             = 2;
        const   OUTPUT_NOTICE           = 3;
        const   OUTPUT_SUCCESS          = 4;
        const   OUTPUT_USAGE            = 5;

        /**
         * The actual command name, e.g. "test". The prefix is NOT included in this,
         * seeing we want to have that private.
         *
         * @var string
         */

        private $m_sCommand;

        /**
         * Contains the PHP code for the command or the callback. For performance
         * reasons this will be cached in another property, as an anonymous function.
         *
         * @var mixed
         */

        private $m_mCode;

        /**
         * The permission needed in order to be able to execute this command. To keep
         * the command public, set to null.
         *
         * @var string
         */

        private $m_sPermission;

        /**
         * The cached command is a anonymous function that'll execute the command's
         * code. Reason for this is that it's faster when cached.
         *
         * @var resource
         */

        private $m_rCachedCommand;

        /**
         * The networks on which this command will be executed on. If it's empty,
         * it will be executed on all networks. The networks should be supplied as
         * the name given to them in config.php.
         *
         * @var array
         */

        private $m_aNetworks;

        /**
         * The channels this command will be permitted to execute on. If it's empty,
         * all channels are allowed.
         *
         * @var array
         */

        private $m_aChannels;

        /**
         * The array holding all of the statistics of this command.
         *
         * @var array
         */

        private $m_aStatistics;

        /**
         * Whether this command should serialized and saved by the Commands module.
         * I wouldn't suggest this when using in external modules, since those get
         * reregistered on startup anyway. This property is also enforced on
         * non-serializable callbacks.
         *
         * @var boolean
         */

        private $m_bSave;

        /**
         * The constructor will initialise the basic variables in this class, and, incase
         * required and possible, initialise the cached handler as well.
         *
         * @param string $sCommand Command that should be executed.
         * @param mixed $mCode Code or callback that should be executed for the command.
         * @param string $sPermission The permission needed to execute this command.
         * @param boolean $bSave Whether this command should be saved by the Commands module.
         */

        public function __construct ($sCommand, $mCode = null, $sPermission = null, $bSave = false)
        {
                $this -> setCommand ($sCommand);
                $this -> setCode ($mCode);
                $this -> setPermission ($sPermission);
                $this -> setSave ($bSave);

                /** Defaults. **/
                $this -> m_aNetworks      = array ();
                $this -> m_aChannels      = array ();

                $this -> m_aStatistics    = array
                (
                        'Executed'        => 0,
                        'TotalTime'       => 0.0,
                        'LastTime'        => 0,
                );
        }

        /**
         * This function returns the name of the command which will be executed,
         * pretty much returning the m_sCommand property.
         *
         * @return string
         */

        public function getCommand ()
        {
                return $this -> m_sCommand;
        }

        /**
         * Returns the code or callback which has been associated with this command.
         * Making changes or whatsoever is not possible using this.
         *
         * @return mixed
         */

        public function getCode ()
        {
                return $this -> m_mCode;
        }

        /**
         * Returns the permission needed in order to be able to execute this command.
         * Null means that any user is able to execute it, given that other the
         * properties allow for this to happen as well.
         *
         * @return string
         */

        public function getPermission ()
        {
                return $this -> m_sPermission;
        }

        /**
         * Tells us whether this command will be saved by the Commands module.
         *
         * @return boolean
         */

        public function getSave ()
        {
                return $this -> m_bSave;
        }

        /**
         * This function returns the networks on which this command is allowed
         * to execute.
         *
         * @return array
         */

        public function getNetworks ()
        {
                return $this -> m_aNetworks;
        }

        /**
         * This function will return an array with the channels this command is
         * allowed to execute in.
         *
         * @return array
         */

        public function getChannels ()
        {
                return $this -> m_aChannels;
        }

        /**
         * Retrieves the array containing the statistics of this command.
         *
         * @return array
         */

        public function getStatistics ()
        {
                return $this -> m_aStatistics;
        }

        /**
         * This function will set the actual command's name, to change it's name this function
         * should be called. Purely for internal reference sake though.
         *
         * @param string $sCommand New name of this command.
         */

        public function setCommand ($sCommand)
        {
                $this -> m_sCommand = $sCommand;
        }

        /**
         * This function will update the code associated with this command. The caching will
         * automatically be re-initialised for performance reasons.
         *
         * @param mixed $mCode Code or callback this command should be executing.
         */

        public function setCode ($mCode)
        {
                $this -> m_mCode = $mCode;

                if ($this -> m_mCode != null)
                {
                        $this -> cache ();
                }
        }

        /**
         * Sets the permission needed in order to be able to execute this command.
         * Set to null to remove the permission.
         *
         * @param string $sPermission The permission needed.
         */

        public function setPermission ($sPermission)
        {
                $this -> m_sPermission = $sPermission;
        }

        /**
         * Sets this command to be serialized and saved to file by the Commands. This
         * command has to be registered with the Commands module in order to be able
         * to do that, however. This property will also be forced to false when a
         * non-serializable callback is used, that is, anything other than a string
         * of code.
         *
         * @param boolean $bSave Whether to save or not.
         */

        public function setSave ($bSave)
        {
                /** We don't like being set to true, only if we have actual code within us. **/
                $this -> m_bSave = false;

                /** The is_string() check is to check against failing array callbacks. **/
                if (!is_callable ($this -> m_mCode) && is_string ($this -> m_mCode))
                {
                        $this -> m_bSave = $bSave;
                }
        }

        /**
         * This function will let you add all the networks you want to give this
         * command at once.
         *
         * @param array $aNetworks The networks to apply to this command.
         */

        public function setNetworks ($aNetworks)
        {
                if (count ($aNetworks) == 1 && $aNetworks [0] == '-')
                {
                        return $this -> m_aNetworks = array ();
                }

                $this -> m_aNetworks = $aNetworks;
        }

        /**
         * Adds a single network entry to the networks array.
         *
         * @param string $sNetwork The network to add to this command.
         */

        public function addNetwork ($sNetwork)
        {
                if (!in_array ($sNetwork, $this -> m_aNetworks))
                {
                        $this -> m_aNetworks [] = $sNetwork;
                }
        }

        /**
         * This function checks if this command if allowed to execute on the given
         * network.
         *
         * @param string $sNetwork The network to check for.
         * @return boolean
         */

        public function checkNetwork ($sNetwork)
        {
                if (empty ($this -> m_aNetworks))
                {
                        return true;
                }

                return in_array ($sNetwork, $this -> m_aNetworks);
        }

        /**
         * This function allows you to set the array of channels in which this
         * command is allowed to execute in.
         *
         * @param array $aChannels The channels to apply to this command.
         */

        public function setChannels ($aChannels)
        {
                if (count ($aChannels) == 1 && $aChannels [0] == '-')
                {
                        return $this -> m_aChannels = array ();
                }

                $this -> m_aChannels = array_map ('strtolower', $aChannels);
        }

        /**
         * This function lets you add a channel to the array of allowed channels.
         *
         * @param string $sChannel The channel to add.
         */

        public function addChannel ($sChannel)
        {
                $sChannel = strtolower ($sChannel);

                if (!in_array ($sChannel, $this -> m_aChannels))
                {
                        $this -> m_aChannels [] = $sChannel;
                }
        }

        /**
         * This function checks if this command is allowed to execute in the
         * given channel.
         *
         * @param string $sChannel The channel to check.
         * @return boolean
         */

        public function checkChannel ($sChannel)
        {
                if (empty ($this -> m_aChannels))
                {
                        return true;
                }

                return in_array (strtolower ($sChannel), $this -> m_aChannels);
        }

        /**
         * The cache function will cache the actual command's code, to make sure the
         * performance loss of eval() only occurs once, rather than every time.
         *
         * @throws Exception When somehow the callback couldn't be created.
         */

        private function cache ()
        {
                if (is_callable ($this -> m_mCode))
                {
                        /** Nothing to be done. **/
                        $this -> m_rCachedCommand = $this -> m_mCode;
                }
                else if (is_string ($this -> m_mCode))
                {
                        /** Try to create a callback from the code. **/
                        $rCachedCommand = @ create_function ('$pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage', $this -> m_mCode);

                        if ($rCachedCommand === false)
                        {
                                /** Syntax error in the code. **/
                                throw new Exception ('Error creating callback from given code.');
                        }

                        $this -> m_rCachedCommand = $rCachedCommand;
                }
                else
                {
                        /** We can't do anything with this. **/
                        throw new Exception ('Couldn\'t create a Command from the supplied parameters.');
                }
        }

        /**
         * This function returns this command in a serialized form, so it can be stored
         * in a file and re-created later on, using the unserialize function (suprise!).
         *
         * @return string
         */

        public function serialize ()
        {
                return serialize (array
                (
                        $this -> m_sCommand,
                        $this -> m_mCode,
                        $this -> m_sPermission,
                        $this -> m_aNetworks,
                        $this -> m_aChannels,
                        $this -> m_aStatistics
                ));
        }

        /**
         * The unserialize method will, yes, unserialize a previously serialized command
         * so we can use it again. Quite convenient for various reasons.
         */

        public function unserialize ($sData)
        {
                $aInformation = unserialize ($sData);

                /** Remove the deprecated 'internal' indicator. **/
                if ($aInformation [0] === false)
                {
                        array_shift ($aInformation);
                }
                
                /** Update commands which are still using the old level system. **/
                if ($aInformation [2] == 0) {
                        $aInformation [2] = null;
                } else if ($aInformation [2] == 9999) {
                        $aInformation [2] = 'owner';
                }

                $this -> m_sCommand    = $aInformation [0];
                $this -> m_mCode       = $aInformation [1];
                $this -> m_sPermission = $aInformation [2];
                $this -> m_aNetworks   = $aInformation [3];
                $this -> m_aChannels   = $aInformation [4];
                $this -> m_aStatistics = $aInformation [5];

                $this -> cache ();
        }

        /**
         * The invoking function which allows us to use fancy syntax for commands. It allows the user
         * and bot-system to directly invoke the object variable.
         *
         * @param Bot $pBot The bot that got the command.
         * @param string $sDestination The place we should send the output to.
         * @param string $sChannel The channel the command was received in.
         * @param string $sNickname The nickname of the user who executed the command.
         * @param array $aParams The parameters given to the command.
         * @param string $sMessage The complete message after the command.
         * @throws Exception If the command was not callable.
         * @return boolean Did the command execute?
         */

        public function __invoke (Bot $pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
        {
                if (!is_callable ($this -> m_rCachedCommand))
                {
                        /** Quite a serious problem this is. Throw an Exception. **/
                        throw new Exception ('Command ' . $this -> getCommand () . ' is not callable.');
                }

                if ($this -> getPermission () !== null &&
                   !$pBot -> getSecurityManager () -> hasPermission ($pBot -> In -> User, $this -> getPermission ()))
                {
                        return false;
                }

                if ($this -> checkNetwork ($pBot ['Network']) && $this -> checkChannel ($sChannel))
                {
                        $cFunction = $this -> m_rCachedCommand;

                        $this -> m_aStatistics ['Executed'] ++;
                        $this -> m_aStatistics ['LastTime'] = time ();

                        /** Let the exception handler know where we are executing code. **/
                        ErrorExceptionHandler :: $Source = $sDestination;

                        /** Catch output. **/
                        ob_start ();

                        $fStart = microtime (true);

                        /** Execute the command. **/
                        $nReturnCode = call_user_func ($cFunction, $pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage);
                        
                        $this -> m_aStatistics ['TotalTime'] += microtime (true) - $fStart;
                        
                        if ($nReturnCode == null || !is_int ($nReturnCode))
                        {
                                $nReturnCode = self :: OUTPUT_NORMAL;
                        }
                        
                        switch ($nReturnCode)
                        {
                                default:
                                case self :: OUTPUT_NORMAL:  $sCallback = 'privmsg'; break;
                                case self :: OUTPUT_ERROR:   $sCallback = 'error';   break;
                                case self :: OUTPUT_INFO:    $sCallback = 'info';    break;
                                case self :: OUTPUT_NOTICE:  $sCallback = 'notice';  break;
                                case self :: OUTPUT_SUCCESS: $sCallback = 'success'; break;
                                case self :: OUTPUT_USAGE:   $sCallback = 'usage';   break;
                        }

                        /** Send output to IRC. **/
                        $aOutput = explode ("\n", trim (ob_get_clean ()));
                        if (isset ($pBot) && $pBot instanceof Bot)
                        {
                                foreach ($aOutput as $sLine)
                                {
                                        call_user_func (array ('Command', $sCallback), $pBot, $sDestination, $sLine);
                                }
                        }

                        return true;
                }

                return false;
        }

        /**
         * This magic method enables this object to be echo'd, without calling
         * methods. Useful for quick retrieval of which command this is.
         *
         * @return string
         */

        public function __toString ()
        {
                return $this -> m_sCommand;
        }

        // -------------------------------------------------------------------//
        // Region: ArrayAccess                                                //
        // -------------------------------------------------------------------//

        /**
         * Check whether the offset exists within this command.
         *
         * @param string $sOffset The setting or statistic to check.
         * @return boolean
         */
        public function offsetExists ($sOffset)
        {
                return (in_array ($sOffset, array ('Command', 'Code', 'Permission', 'Networks', 'Save')) ||
                        isset ($this -> m_aStatistics [$sOffset]));
        }

        /**
         * Gets a specific setting or statistic of this command. Returns false
         * if no setting or statistic has been found.
         *
         * @param string $sOffset The setting or statistic to get.
         * @return mixed
         */
        public function offsetGet ($sOffset)
        {
                switch ($sOffset)
                {
                        case 'Command':
                                return $this -> getCommand ();

                        case 'Code':
                                return $this -> getCode ();

                        case 'Permission':
                                return $this -> getPermission ();

                        case 'Networks':
                                return $this -> getNetworks ();

                        case 'Channels':
                                return $this -> getChannels ();

                        case 'Save':
                                return $this -> getSave ();
                }

                if (isset ($this -> m_aStatistics [$sOffset]))
                {
                        return $this -> m_aStatistics [$sOffset];
                }

                return false;
        }

        /**
         * Quickly set a certain setting for this command.
         *
         * @param string $sOffset The setting to set.
         * @param mixed $mValue The value.
         */
        public function offsetSet ($sOffset, $mValue)
        {
                switch ($sOffset)
                {
                        case 'Command':
                                return $this -> setCommand ($mValue);

                        case 'Code':
                                return $this -> setCode ($mValue);

                        case 'Permission':
                                return $this -> setPermission ($mValue);

                        case 'Networks':
                                return $this -> setNetworks ($mValue);

                        case 'Channels':
                                return $this -> setChannels ($mValue);

                        case 'Save':
                                return $this -> setSave ($mValue);
                }
        }

        /**
         * This is very much not allowed, since setting defaults could break the
         * command or even more.
         *
         * @param string $sOffset The setting to unset.
         */
        public function offsetUnset ($sOffset)
        {
                return;
        }

        // -------------------------------------------------------------------//
        // Region: Response methods                                           //
        // -------------------------------------------------------------------//

        /**
         * Sends a message to a destination, this can be a channel or a nickname.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */

        public static function privmsg (Bot $pBot, $sDestination, $sMessage)
        {
                foreach (explode (PHP_EOL, $sMessage) as $sMessage)
                {
                        $sMessage = trim ($sMessage);
                        $pBot -> send ('PRIVMSG ' . $sDestination . ' :' . $sMessage);
                }
        
                return true;
        }
        
        /**
         * Sends an error message to the destination. The message is prefixed
         * with '* Error:' in red, to distinguish the message from messages
         * which provide generally better news for the users.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */
        
        public static function error (Bot $pBot, $sDestination, $sMessage)
        {
                return self :: privmsg ($pBot, $sDestination, ModuleBase :: COLOUR_RED . '* Error: ' . $sMessage);
        }
        
        /**
         * Sends a general informational message to the user. The '* Info'
         * prefix message has a soft blue-ish color to indicate that's not a big
         * deal, unlike error messages.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */
        
        public static function info (Bot $pBot, $sDestination, $sMessage)
        {
                return self :: privmsg ($pBot, $sDestination, ModuleBase :: COLOUR_TEAL . '* Info: ' . $sMessage);
        }
        
        /**
         * Sends a message that requires the attention of the user, but is not
         * critical, unlike error messages. The '* Notice' prefix message has an
         * orange color to indicate that attention is required, but everything
         * will continue to work.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */
        
        public static function notice (Bot $pBot, $sDestination, $sMessage)
        {
                return self :: privmsg ($pBot, $sDestination, ModuleBase :: COLOUR_ORANGE . '* Notice: ' . $sMessage);
        }
        
        /**
         * Sends a message to the user indicating that something worked out
         * nicely. The '* Success' prefix message has a green color to indicate
         * that all's good.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */
        
        public static function success (Bot $pBot, $sDestination, $sMessage)
        {
                return self :: privmsg ($pBot, $sDestination, ModuleBase :: COLOUR_GREEN . '* Success: ' . $sMessage);
        }
        
        /**
         * Sends an informational message to the user about how to use a certain
         * command. The '* Usage' prefix message has a soft blue-ish color to
         * indicate that's not a big deal, unlike error messages.
         *
         * @param Bot $pBot The bot to send it with.
         * @param string $sDestination The destination.
         * @param string $sMessage The actual message to send.
         */
        
        public static function usage (Bot $pBot, $sDestination, $sMessage)
        {
                return self :: privmsg ($pBot, $sDestination, ModuleBase :: COLOUR_ORANGE . '* Usage: ' . $sMessage);
        }
}
?>