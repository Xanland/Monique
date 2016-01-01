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
 * @version $Id: User.php 151 2011-08-21 17:43:24Z dik.grapendaal@gmail.com $
 * @package Nuwani
 */

namespace Nuwani;

class User
{
        /**
         * The network name as configured in the bot.
         * 
         * @var string
         */
        
        public $Network;
        
        /**
         * The nickname of this user.
         * 
         * @var string
         */
        
        public $Nickname;
        
        /**
         * The username of the user. Sometimes also called the ident.
         * 
         * @var string
         */
        
        public $Username;
        
        /**
         * The hostname of this user.
         * 
         * @var string
         */
        
        public $Hostname;
        
        /**
         * Constructs a new user from a user mask. A user mask is usually in the form of nickname!username@hostname. If
         * not, it's likely a hostname from a server notice. The network name is also required to prevent collisions.
         * 
         * @param string $network The network this user is on.
         * @param string $usermask The user mask to parse.
         */
        
        public function __construct ($network, $usermask)
        {
                $this -> Network = $network;
                if (strpos ($usermask, '!') !== false)
                {
                        list ($this -> Nickname, $this -> Username, $this -> Hostname) =
                                preg_split ('/!|@/', $usermask, 3);
                }
                else
                {
                        // The rest stays null.
                        $this -> Nickname = $usermask;
                }
        }
        
        /**
         * Returns this user's full user mask.
         * 
         * @return string
         */
        
        public function __toString ()
        {
                $usermask = $this -> Nickname;
                if ($this -> Username !== null) {
                        $usermask .= '!' . $this -> Username;
                }
                if ($this -> Hostname !== null) {
                        $usermask .= '@' . $this -> Hostname;
                }
                
                return $usermask;
        }
}