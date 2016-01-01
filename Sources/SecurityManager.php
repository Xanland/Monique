<?php
/**
 * Nuwani PHP IRC Bot Framework
 * Copyright (c) 2006-2010 The Nuwani Project
 *
 * Nuwani is a framework for IRC Bots built using PHP. Nuwani speeds up bot development by handling
 * basic tasks as connection- and bot management, timers and module managing. Features for your bot
 * can easily be added by creating your own modules, which will receive callbacks from the framework.
 *
 * This program is free software: you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright Copyright (c) 2006-2011 The Nuwani Project, http://nuwani.googlecode.com/
 * @author Peter Beverloo <peter@lvp-media.com>
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * @version $Id: SecurityManager.php 162 2013-07-11 19:27:50Z dik.grapendaal $
 * @package Nuwani
 */

namespace Nuwani;

require_once __DIR__ . '/User.php';

class SecurityManager
{
        /**
         * This permission indicates that a user is the bot owner.
         *
         * @var string
         */

        const        PERMISSION_BOT_OWNER        = 'owner';

        /**
         * The bot this SecurityManager is configured for. This is actually a work-around for the fact that the
         * onPrivmsg callback is invoked by the ModuleManager... which is a singleton. This means that all
         * SecurityManagers for all bots are called when one bot receives an event. When using multiple bots across
         * multiple networks, this interferes with each other. To prevent this, we'll compare the given Bot object with
         * the one we have here. In the future a proper fix will have to be made, one that only calls the
         * SecurityManager for the applicable bot.
         * 
         * @var Bot
         */
        private $bot;

        /**
         * All known users with their granted permissions are stored in here.
         *
         * @var array
         */

        private $permissions;

        /**
         * Sets up the security manager with the permissions as configured.
         *
         * @param Bot $bot The bot this SecurityManager is for.
         * @param array $permissions The array of permissions as configured in the configuration file.
         */

        public function initialize (Bot $bot, $users)
        {
                $this->bot = $bot;

                foreach ($users as $details)
                {
                        $password = isset ($details ['Password']) ? $details ['Password'] : false;

                        $user = $this -> addUser ($details ['UserMask'], $password);
                        
                        foreach ($details ['Permissions'] as $permission)
                        {
                                $this -> grantPermission ($user, $permission);
                        }
                }
        }
        
        /**
         * Convenience method to check whether the given user has the bot owner permission.
         * 
         * @param User $user The user to check.
         * @return boolean Is he a bot owner?
         */
        
        public function isBotOwner (User $user)
        {
                return $this -> hasPermission ($user, self :: PERMISSION_BOT_OWNER);
        }
        
        /**
         * Adds a user without any permissions. These can be granted using {@link SecurityManager::grantPermission()}.
         * The given mask is parsed using the {@link User} class and is returned for later re-use. The mask is in the
         * form of nickname!username@hostname, where each of the three parts can be a wildcard. Partial wildcards are
         * not supported.
         * 
         * @param string $mask The user mask the user should match against.
         * @param string $password The MD5 hashed (without salt) password. Use false for no password.
         * @return \Nuwani\User The parsed user mask.
         */
        
        public function addUser ($mask, $password = false)
        {
                $user = new User ('', $mask);
                $password = $password != '' ? $password : false;
                
                $this -> permissions [] = array
                (
                        'Nickname'		=> strtolower ($user -> Nickname),
                        'Username'		=> strtolower ($user -> Username),
                        'Hostname'		=> strtolower ($user -> Hostname),
                        'Password'		=> $password,
                        'Identified'	=> false,
                        'CachedUser'	=> '',
                        'Permissions'	=> array ()
                );
                
                return $user;
        }
        
        /**
         * Returns whether we know a user with the given user mask.
         * 
         * @param string $mask The user mask to look up.
         * @return boolean Do we know this guy?
         */
        
        public function isUserKnown ($mask)
        {
                $user = new User ('', strtolower ($mask));
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo))
                        {
                                return true;
                        }
                }
                
                return false;
        }
        
        /**
         * Returns an array with permissions the given user has. This method also check whether the given user has
         * identified, so the permission list changes depending on whether the user has identified himself. Returns an
         * empty array in case of an unknown user.
         * 
         * @param User $user The user we want to know the permissions of.
         * @return array The permissions this user has been blessed with.
         */
        
        public function getPermissionList (User $user)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && $this -> isIdentified ($user, $userInfo))
                        {
                                return $userInfo ['Permissions'];
                        }
                }
                
                return array ();
        }
        
        /**
         * Attempt to identify the given user with the given MD5 hashed password.
         * 
         * @param User $user The user we're trying to identify.
         * @param string $hashedPassword The MD5 hashed password (no salt).
         * @return boolean Was the user successfully identified?
         */
        
        public function identifyUser (User $user, $hashedPassword)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && $userInfo ['Password'] !== false)
                        {
                                if ($this -> isIdentified ($user, $userInfo))
                                {
                                        // Already identified.
                                        return true;
                                }
                                
                                if ($hashedPassword === $userInfo ['Password'])
                                {
                                        $userInfo ['Identified'] = true;
                                        $userInfo ['CachedUser'] = strtolower ((string) $user);
                                        return true;
                                }
                        }
                }
                
                return false;
        }
        
        /**
         * Logs out the specified user and resets their cached user mask.
         * 
         * @param User $user The user to logout.
         * @return boolean Did we find the user and log them out properly?
         */
        
        public function logoutUser (User $user)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && $userInfo ['Password'] !== false &&
                            $this -> isIdentified ($user, $userInfo))
                        {
                                $userInfo ['Identified'] = false;
                                $userInfo ['CachedUser'] = '';
                                return true;
                        }
                }
                
                return false;
        }
        
        /**
         * This function will check whether the given user has the given permission. If so, true is returned.
         *
         * @param User $user The user to check.
         * @param string $permission The permission to check.
         * @return boolean Whether the user has been granted this permission.
         */

        public function hasPermission (User $user, $permission)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && $this -> isIdentified ($user, $userInfo) &&
                            in_array ($permission, $userInfo ['Permissions']))
                        {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Grants the given user the specified permission. The return value is true when the user was found and didn't
         * have the permission yet. False in all other cases.
         *
         * @param User $user The user to look for.
         * @param string $permission The permission to grant.
         * @return boolean Whether the permission is successfully granted.
         */

        public function grantPermission (User $user, $permission)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && !in_array ($permission, $userInfo ['Permissions']))
                        {
                                $userInfo ['Permissions'] [] = $permission;
                                return true;
                        }
                }

                return false;
        }

        /**
         * Revokes the given permission from the specified user. The return value is true when the user was found and
         * had the revoked permission. False in all other cases.
         *
         * @param User $user The user to look for.
         * @param string $permission The permission to revoke.
         * @return boolean Whether the permission was successfully revoked.
         */

        public function revokePermission (User $user, $permission)
        {
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($this -> isUser ($user, $userInfo) && in_array ($permission, $userInfo ['Permissions']))
                        {
                                $key = array_search ($permission, $userInfo ['Permissions']);
                                unset ($userInfo ['Permissions'] [$key]);
                                return true;
                        }
                }

                return false;
        }

        /**
         * This function will try to confirm the identity of the given user against the given credentials.
         *
         * @param User $user The user to identify.
         * @param array $userInfo Our information to check against.
         * @return boolean Whether we have a match.
         */

        private function confirmIdentity (User $user, $userInfo)
        {
                if (($userInfo ['Nickname'] == strtolower ($user -> Nickname) || $userInfo ['Nickname'] == '*') &&
                    ($userInfo ['Username'] == strtolower ($user -> Username) || $userInfo ['Username'] == '*') &&
                    ($userInfo ['Hostname'] == strtolower ($user -> Hostname) || $userInfo ['Hostname'] == '*'))
                {
                        return true;
                }

                return false;
        }
        
        /**
         * Determines whether the given user is identified against the given user information.
         * 
         * @param User $user The user to check.
         * @param array $userInfo The user information we want to check against.
         * @return boolean Is he identified?
         */
        
        private function isIdentified (User $user, $userInfo)
        {
                return ($userInfo ['Password'] !== false && $userInfo ['Identified'] === true) ||
                        $userInfo ['Password'] === false;
        }

        /**
         * Checks whether the given user matches against the given user information.
         *
         * @param User $user The user we're looking for.
         * @param array $userInfo The user information we want to match against.
         * @return boolean Is it the user?
         */

        private function isUser (User $user, $userInfo)
        {
                return ($userInfo ['CachedUser'] == strtolower ((string) $user) ||
                       ($userInfo ['CachedUser'] == '' && $this -> confirmIdentity ($user, $userInfo)));
        }

        /**
         * This method gets called when a user changes their nickname. If it is a user with rights, we update their
         * cached usermask so that things continue to work like the user would expect them to.
         *
         * @param Bot $bot The bot that received the message.
         * @param string $oldNick The old nickname.
         * @param string $newNick The new nickname.
         */

        public function onChangeNick (Bot $bot, $oldNick, $newNick)
        {
                if ($bot != $this->bot) {
                        return;
                }

                $usermask = (string) $bot -> In -> User;
                foreach ($this -> permissions as & $userInfo)
                {
                        if (($userInfo ['Password'] === false || $userInfo ['Identified'] === true) &&
                             $userInfo ['CachedUser'] == $usermask)
                        {
                                $userInfo ['CachedUser'] = $usermask;
                                return;
                        }
                }
        }

        /**
         * When a user quits IRC and it happens to be a user with rights and a password, we log them out for security
         * purposes. If it's a normal user without password, we just reset their cached user mask.
         *
         * @param Bot $bot The bot that received the message.
         * @param string $nickname The nickname of the user who quitted.
         * @param string $message The quit message.
         */

        public function onQuit (Bot $bot, $nickname, $message)
        {
                if ($bot != $this->bot) {
                        return;
                }

                $usermask = (string) $bot -> In -> User;
                foreach ($this -> permissions as & $userInfo)
                {
                        if ($userInfo ['CachedUser'] == $usermask)
                        {
                                $userInfo ['CachedUser'] = '';

                                if ($userInfo ['Password'] !== false && $userInfo ['Identified'] === true)
                                {
                                        $userInfo ['Identified'] = false;
                                }
                        }
                }
        }

        /**
         * In order for the user to be able to login with their password and obtain their elevated rights, we need to
         * catch their login attempt. For completeness sake, we also handle the logout command here.
         *
         * @param Bot $bot The bot that received the message.
         * @param string $nickname The nickname of the user.
         * @param string $message The actual message the user sent us.
         */

        public function onPrivmsg (Bot $bot, $nickname, $message)
        {
                if ($bot != $this->bot) {
                        return;
                }

                if (substr ($message, 0, 6) == 'login ')
                {
                        if ($this -> identifyUser ($bot -> In -> User, md5 (substr ($message, 6))))
                        {
                                $bot -> send ('PRIVMSG ' . $nickname . ' :You have successfully identified yourself!');
                                return;
                        }
                        else
                        {
                                $bot -> send ('PRIVMSG ' . $nickname . ' :Incorrect password.');
                                return;
                        }
                }
                else if (substr ($message, 0, 6) == 'logout')
                {
                        if ($this -> logoutUser ($bot -> In -> User))
                        {
                                $bot -> send ('PRIVMSG ' . $nickname . ' :You have been logged out successfully.');
                                return;
                        }
                }
        }
}