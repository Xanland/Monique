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
 * @package LVP Radio Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP;

class Radio
{
    /**
     * The file put to that file which containts the LVP Radio data.
     *
     * @var string
     */
    private static $_m_sRadioFile = 'Data/LVP/Radio.dat';

    /**
     * Array containing the unserialized data taken from the file defined
     * above.
     *
     * @var array
     */
    private static $_m_aRadioFileContent = array ();

    /**
     * Since the commands use an instance of this class they can get their data
     * easily by only instantiate it.
     *
     * @return array
     */
    public static function execute ()
    {
        return (object) self :: getRadioFileContent ();
    }

    /**
     * Read out the file with the radio information when possible, else it gives
     * an empty array.
     */
    private static function getRadioFileContent ()
    {
        if (file_exists (self :: $_m_sRadioFile))
        {
            self :: $_m_aRadioFileContent = unserialize (file_get_contents (self :: $_m_sRadioFile));
        }
        else
        {
            self :: $_m_aRadioFileContent = array ();
        }

        return self :: $_m_aRadioFileContent;
    }

    /**
     * Save all the details into the radiofile for use by the commands.
     */
    private static function saveRadioFile ()
    {
        file_put_contents (self :: $_m_sRadioFile, serialize (self :: $_m_aRadioFileContent));
    }

    /**
     * To know what is happening at LVP Radio we need to parse the information
     * we are receiving in #LVP.Radio from the LVP_Radio-bot.
     *
     * @param string $sPlayingInfo The message with the information what at this
     *                             moment is happening at LVP Radio, like: dj,
     *                             song and amount of listeners.
     */
    public static function setNowPlayingInformation ($sPlayingInfo)
    {
        self :: getRadioFileContent ();

        if (preg_match ('/(.+) is playing (.+) - Listeners: (.+)\/(.+)/', $sPlayingInfo, $aRadioInformation)
         || preg_match ('/\[LVP_Radio\] Current DJ: (.+)\]/', $sPlayingInfo, $aRadioInformation)
         || preg_match ('/The current DJ is: (.+)/', $sPlayingInfo, $aRadioInformation)
         || preg_match ('/(.+) is currently playing  (.+)/', $sPlayingInfo, $aRadioInformation))
        {
            self :: $_m_aRadioFileContent ['dj']       = $aRadioInformation [1];

            if (isset ($aRadioInformation [2]))// && isset ($aRadioInformation [3]))
            {
                self :: $_m_aRadioFileContent ['song'] = $aRadioInformation [2];
             // self :: $_m_aRadioFileContent ['listeners'] = $aRadioInformation [3];
            }
        }
        else if (preg_match ('/You are listening to L V P Radio\. Up next: (.+)/', $sPlayingInfo, $aRadioInformation)
              || preg_match ('/The next song is: (.+)/', $sPlayingInfo, $aRadioInformation)
              || preg_match ('/This is your beloved L V P Auto Dee Jay, the bot with Style\. Our next song is: (.+)/',
                                 $sPlayingInfo, $aRadioInformation))
        {
            if (isset ($aRadioInformation [1]))// && isset ($aRadioInformation [3]))
            {
                self :: $_m_aRadioFileContent ['song'] = $aRadioInformation [1];
            }
        }
     // else if (preg_match('/Current Listeners: \'(.+)\'/', $sPlayingInfo, $aRadioInformation))
         // self :: $_m_aRadioFileContent ['listeners'] = $aRadioInformation [2];

        self :: saveRadioFile ();
        return;
    }
}