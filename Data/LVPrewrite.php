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
 * @author Peter Beverloo <peter@lvp-media.com>
 * @author Dik Grapendaal <dik.grapendaal@gmail.com>
 * @see http://nuwani.googlecode.com
 */

/**
 * One whole class for handling messages received that are LVP-related.
 *
 * @todo Maybe seperating this stuff to other classes.
 */
class LVPrewrite_s extends ModuleBase
{
    /**
     * Do some important things on the start and inform that the module is star-
     * ted and ready.
     */
    public function __construct ()
    {
        $this -> addSeenCommand ();
        // $this -> addGetIdCommand ();
        // $this -> addGetNameCommand ();
    }

    /**
     * We want to know some information of the GTA Merchant, like what vehicle it
     * is and the price for it.
     *
     * @param array $aDetails Array of the message, splitted, from the merchant.
     */
    private function registerMerchantRequires ($aDetails)
    {
        $lvp_merchant = new Nuwani \ Model ('lvp_merchant', 'lvp_merchant_id', 'lvp_merchant');
        $lvp_merchant -> lvp_merchant_id = 'lvp_merchant';
        $lvp_merchant -> sCarName = $aDetails [1];
        $lvp_merchant -> iMoneyGiven = $aDetails [2];
        $lvp_merchant -> save ();
    }

    /**
     * We want to know if the GTA Merchant is kicked.
     */
    private function registerMerchantKicked ()
    {
        $lvp_merchant = new Nuwani \ Model ('lvp_merchant', 'lvp_merchant_id', 'lvp_merchant');
        $lvp_merchant -> lvp_merchant_id = 'lvp_merchant';
        $lvp_merchant -> sCarName = 'none';
        $lvp_merchant -> iMoneyGiven = 0;
        $lvp_merchant -> save ();
    }

    /**
     * We want to know some information of the song on LVP radio.
     *
     * @param type $aDetails Array of the whole nowplaying-message, splitted, of
     *                       LVP_Radio.
     */
    /*
    private function LVPradioSong ($aDetails)
    {
        $lvp_radio = new Nuwani \ Model ('lvp_radio', 'lvp_radio_id', 'song');
        $lvp_radio -> lvp_radio_id = 'song';
        $lvp_radio -> sName = $aDetails [2];
        $lvp_radio -> save ();
    }

    /**
     * We want to know some information of the dj on LVP radio.
     *
     * @param type $aDetails Array of the whole nowplaying-message, splitted, of
     *                       LVP_Radio.
     */
    /*
    private function LVPradioDj ($aDetails)
    {
        $lvp_radio = new Nuwani \ Model ('lvp_radio', 'lvp_radio_id', 'dj');
        $lvp_radio -> lvp_radio_id = 'dj';
        $lvp_radio -> sName = $aDetails [1];
        $lvp_radio -> save ();
    }

    /**
     * Method to save the last seen persons with reason and time.
     *
     * @param string $sFieldName
     * @param mixed $sFieldValue
     * @param array $aDetails
     * @param boolean $bOnline
     */
    private function savePersonLastSeen ($sFieldName, $sFieldValue, $aDetails, $bOnline = true, $sReasonLeft = null)
    {
        if ($sReasonLeft != '')
        {
            $aDetails [6] = $sReasonLeft;
        }
        $insert = new Nuwani \ Model ('lvp_person_last_seen', $sFieldName, $sFieldValue);
        $insert -> $sFieldName = $sFieldValue;
        if ($sFieldName == 'iId')
        {
            $insert = new Nuwani \ Model ('lvp_person_last_seen', 'lvp_person_last_seen_id', $insert -> lvp_person_last_seen_id);
        }
        $insert -> iTime = time ();
        if ($bOnline === true)
        {
            $insert -> iId = str_replace (array ('[', ']'), '', Util :: stripFormat ($aDetails [0]));
            $insert -> sReason = 'online';
        }
        elseif ($bOnline === false)
        {
            $insert -> iId = -1;
            $insert -> sReason = $aDetails [6];
        }
        $insert -> save ();
    }

    /**
     * This function adds the .seen-command internally since the code is too long.
     */
    private function addSeenCommand ()
    {
        Nuwani \ ModuleManager :: getInstance () -> offsetGet ('Commands') ->
        registerCommand (new Command ('.seen',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                $oLastSeenPerson = new Nuwani \ Model ('lvp_person_last_seen', 'lvp_person_last_seen_id', $aParams [0]);
                if ($aParams [0] == '')
                {
                    echo '!msg * Usage: .seen <username>';
                }
                else
                {
                    if (!is_null ($oLastSeenPerson -> lvp_person_last_seen_id))
                    {
                        if ($oLastSeenPerson -> sReason == 'online')
                        {
                            echo '!msg ' . $oLastSeenPerson -> lvp_person_last_seen_id . ' is already online for ' . Util :: formatTime (time () - $oLastSeenPerson -> iTime, true) . '.';
                        }
                        else
                        {
                            echo '!msg ' . $oLastSeenPerson -> lvp_person_last_seen_id . ' was last seen online ' . date ('H:i:s @ d-m-Y', $oLastSeenPerson -> iTime) . ' ' . $oLastSeenPerson -> sReason;
                        }
                    }
                    else
                    {
                        echo '!msg * Error: Sorry, this username has not (yet) been found.';
                    }
                }
            }
        ));
    }

    /**
     * This method adds the !getid-command internally since the code is too long.
     */
    private function addGetIdCommand ()
    {
        Nuwani \ ModuleManager :: getInstance () -> offsetGet ('Commands') ->
        registerCommand (new Command ('getid',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (strtolower ($sChannel) == strtolower ('#lvp.echo') || strtolower ($sChannel) == strtolower ('#lvp'))
                {
                    if ($aParams [0] == '')
                    {
                        echo '10* Usage: !getid [nickName]';
                    }
                    else
                    {
                        $oLastSeenPerson = new Nuwani \ Model ('lvp_person_last_seen', 'lvp_person_last_seen_id', '%' . $aParams [0] . '%');
                        $a_oLastSeenPerson = $oLastSeenPerson -> getAll ();
                        $sMatches = '';
                        $i = 0;
                        foreach ($a_oLastSeenPerson as $oLastSeenPerson)
                        {
                            if (!is_null ($oLastSeenPerson -> lvp_person_last_seen_id) && $oLastSeenPerson -> sReason == 'online')
                            {
                                $sMatches .= $oLastSeenPerson -> lvp_person_last_seen_id . ' 7(' . $oLastSeenPerson -> iId . '), ';
                                $i++;
                            }
                        }

                        if ($i <= 0)
                        {
                            echo '4Error: No matches found on "' . $aParams [0] . '".';
                        }
                        else
                        {
                            echo '6Matches Found (' . $i . '): ' . substr ($sMatches, 0, -2);
                        }
                    }
                }
            }
        ));
    }

    /**
     * This method adds the !getname-command internally since the code is too long.
     */
    private function addGetNameCommand ()
    {
        Nuwani \ ModuleManager :: getInstance () -> offsetGet ('Commands') ->
        registerCommand (new Command ('getname',
            function ($pBot, $sDestination, $sChannel, $sNickname, $aParams, $sMessage)
            {
                if (strtolower ($sChannel) == strtolower ('#lvp.echo') || strtolower ($sChannel) == strtolower ('#lvp'))
                {
                    $oLastSeenPerson = new Nuwani \ Model ('lvp_person_last_seen', 'iId', $aParams [0]);
                    if ($aParams [0] == '')
                    {
                        echo '10* Usage: !getname [playerId]';
                    }
                    else
                    {
                        if (!is_null ($oLastSeenPerson -> lvp_person_last_seen_id) && $oLastSeenPerson -> sReason == 'online')
                        {
                            echo '6*** The player with ID ' . $oLastSeenPerson -> iId . ' is called: ' . $oLastSeenPerson -> lvp_person_last_seen_id . '.';
                        }
                        else
                        {
                            echo '04* Error: No player with ID ' . $aParams [0] .' is connected.';
                        }
                    }
                }
            }
        ));
    }

    /**
     * When MQD is activated we also wants that Monique reacts with another
     * random message.
     *
     * @param Bot $pBot Instance of the bot which received this message.
     */
    private function MQDextra (Bot $pBot)
    {
        $aMessages        = file         ('Data/Logs/#lvp.echo.log');
        $sOriginalMessage = array_slice  ($aMessages, count ($aMessages) - 100);
        $aMessageWords    = explode      (' ', $sOriginalMessage [mt_rand (2, count ($sOriginalMessage) - 1)]);
        $sMessage         = str_ireplace ('Monique', str_replace (array ('<', '>'), '', $aMessageWords [1]), $aMessageWords);

        $pBot -> send ('PRIVMSG #lvp.echo :!msg ' . Util :: getPieces ($sMessage, ' ', 2));
    }

    /**
     * Here we handle some important things, like what happens in the LVP server.
     *
     * @param Bot $pBot         Instance of the bot which received this message.
     * @param string $sChannel  Channel in which the message got distributed.
     * @param string $sNickname Nickname of the person who distributed this message.
     * @param string $sMessage  Contents of the message itself.
     *
     * @todo Seperate this to elseif's so less code is processed each message.
     */

    public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
    {
        $aEchoBots = array ('Xanland', 'Nuwani', 'Nuweni', 'Nuwini', 'Nowani', 'Nuwoni', 'Nuwuni', 'Noweni', 'Nowini', 'Nowoni', 'Nowuni'); //, 'LVP_Radio');
        if (in_array ($sNickname, $aEchoBots))
        {
            if (strtolower ($sChannel) == strtolower ('#lvp.echo'))
            {
                if ($sMessage == '4*** Global Gamemode Initialization')
                {
                    $a_oLastSeenPerson = new Nuwani \ Model ('lvp_person_last_seen', 'sReason', 'online');
                    $a_oLastSeenPersons = $a_oLastSeenPerson -> getAll ();
                    foreach ($a_oLastSeenPersons as $oLastSeenPerson)
                    {
                        $oLastSeenPerson -> iId = -1;
                        $oLastSeenPerson -> sReason = '(leaving).';
                        $oLastSeenPerson -> iTime = time ();
                        $oLastSeenPerson -> save ();
                    }
                }
/*
                if (preg_match ('/3Grand Theft Auto: The merchant now requires (.+) for \$(.+)/', $sMessage, $aDetails))
                {
                    $this -> registerMerchantRequires ($aDetails);
                }

                if (preg_match ('/(.+) GTA_Merchant left the game \(kicked\)./', $sMessage, $aDetails))
                {
                    $this -> registerMerchantKicked ();
                }
*/
                $aParameters = explode (' ', Util :: stripFormat($sMessage . ' '));
                $aNewParameters = explode (' ', Util :: stripFormat($sMessage . ' '));
                unset ($aNewParameters [0], $aNewParameters [1]);
                $sNewMessage = implode(' ', $aNewParameters);

                if (strstr ($aParameters [0], '[') !== false && strstr ($aParameters [0], ']') !== false)
                {
                    if (strstr ($aParameters [1], ':') !== false)
                    {
                        //file_put_contents ('Data/Logs/#lvp.echo.log', '[' . date('H:i:s') . '] <' . str_replace (':', '', $aParameters [1]) . '> ' . $sNewMessage . PHP_EOL, FILE_APPEND);
                    }
                    elseif (strstr ($aParameters [1], ':') === false && strstr ($aParameters [1], '***') === false)
                    {
                        //file_put_contents ('Data/Logs/#lvp.echo.log', '06[' . date('H:i:s') . '] * ' . str_replace (':', '', $aParameters [1]) . ' ' . $sNewMessage . PHP_EOL, FILE_APPEND);
                    }
                    elseif ($aParameters [3] == 'left' && $aParameters [4] == 'the' && $aParameters [5] == 'game')
                    {
                        $this -> savePersonLastSeen ('lvp_person_last_seen_id', $aParameters [2], $aParameters, false);
                    }
                    elseif ($aParameters [3] == 'joined' && $aParameters [4] == 'the' && $aParameters [5] == 'game.')
                    {
                        $this -> savePersonLastSeen ('lvp_person_last_seen_id', $aParameters [2], $aParameters);
                    }
                    elseif ($aParameters [3] == 'decided' && $aParameters [5] == 'play' && $aParameters [8] == 'guest.')
                    {
                        $this -> savePersonLastSeen ('iId', str_replace (array ('[', ']'), '', Util :: stripFormat ($aParameters [0])), $aParameters, false, '(leaving).');
                        $this -> savePersonLastSeen ('lvp_person_last_seen_id', $aParameters [2], $aParameters);
                    }
                }
                elseif ($aParameters [2] == 'on' && $aParameters [3] == 'IRC:')
                {
                    unset ($aNewParameters [2], $aNewParameters [3]);
                    $sNewMessage = implode(' ', $aNewParameters);
                    //file_put_contents ('Data/Logs/#lvp.echo.log', '[' . date('H:i:s') . '] <' . str_replace (':', '', $aParameters [1]) . '> ' . $sNewMessage . PHP_EOL, FILE_APPEND);
                }

                if (strstr ($sMessage, 'Monique') && !strstr ($sMessage, 'Monique:') && file_get_contents ('Data/LVP/MQD.dat') != '')
                {
                    $this -> MQDextra ($pBot);
                }
            }

            /*
            if (strtolower ($sChannel) == strtolower ('#lvp.radio'))
            {
                if (preg_match ('/(.+) is playing (.+) - Listeners: (.+)\/(.+)/', $sMessage, $aDetails) ||
                    preg_match ('/(.+) is currently playing  (.+)/', $sMessage, $aDetails))
                {
                    $this -> LVPradioSong ($aDetails);
                    $this -> LVPradioDj ($aDetails);
                }
                else if (preg_match ('/The current DJ is: (.+)/', $sMessage, $aDetails))
                {
                    $this -> LVPradioDj ($aDetails);
                }
            }
            */
        }
    }
}