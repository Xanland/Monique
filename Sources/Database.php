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
 * @author Xander Hoogland <home@xanland.nl>
 * @version $Id: Database.php 151 2011-08-21 17:43:24Z dik.grapendaal@gmail.com $
 * @package Nuwani
 */

namespace Nuwani;

if (!class_exists ('\PDO'))
{
        /** PDO is not required by the bot's core. **/
        return;
}

class Database extends \ PDO
{
        /**
         * This property contains the instance of the active MySQLi instance.
         * By utilizing Singleton here we avoid having MySQL connections for
         * every single requests, but rather just when they're needed.
         *
         * @var string
         */

        private static $m_sInstance;

        /**
         * This property indicates when the current connection has to
         * be killed, and restarted to clear up buffers and all.
         *
         * @var integer
         */

        private static $m_nRestartTime;

        /**
         * The constructor will connect to the configured MySQL database.
         */

        public function __construct ($singleton = false)
        {
                if ($singleton === false)
                        throw new \Exception("This class should be used as singleton, please use Database::getInstance() instead of new Database()!");

                $aConfiguration = Configuration :: getInstance () -> get ('MySQL');

                parent :: __construct
                (
                        $aConfiguration ['dsn'],
                        $aConfiguration ['username'],
                        $aConfiguration ['password']
                );
        }

        /**
         * Creates a new connection with the database or returns the active
         * one if there is one, so no double connections for anyone. Returns
         * null if no credentials have been configured, or PDO has been
         * disabled.
         *
         * @return Database|null
         */

        public static function getInstance ()
        {
                if (self :: $m_sInstance == null || self :: $m_nRestartTime < time ())
                {
                        if (self :: $m_sInstance != null)
                        {
                                self :: $m_sInstance = null;
                        }

                        self :: $m_sInstance = null;

                        $aConfiguration = Configuration :: getInstance () -> get ('MySQL');

                        if (empty ($aConfiguration) ||
                            (isset ($aConfiguration ['enabled']) && $aConfiguration ['enabled'] === false))
                        {
                                /** No configuration found or PDO is not wanted, bail out. **/
                                return null;
                        }

                        self :: $m_sInstance    = new self (true);
                        self :: $m_nRestartTime = $aConfiguration ['restart'] + time ();
                }

                return self :: $m_sInstance;
        }
};

?>