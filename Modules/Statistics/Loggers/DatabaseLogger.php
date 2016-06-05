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

namespace Statistics\Loggers;

use LVPModel;

class DatabaseLogger implements ILogger
{
    private $medium;

    public function CreateInstance()
    {
        $this -> medium = new LVPModel('irc_statistics', 'irc_statistics_id');
        $this -> medium -> date = date ('Y-m-d');
        $this -> medium -> time = date ('H:i:s');
    }

    public function SetDetails (string $channel, string $nick)
    {
        $this -> medium -> channel = $channel;
        $this -> medium -> nick = $nick;
    }

    public function SetMessageType (string $messageType)
    {
        $this -> medium -> message_type = $messageType;
    }

    public function SetMessage (string $message)
    {
        $this -> medium -> message = $message;
    }

    public function SaveInstance () : bool
    {
        return $this -> medium -> save ();
    }
}
