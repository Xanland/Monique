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
 * @package LVP InGame QuoteDevice Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP\InGame;
use Nuwani\Bot;
use Nuwani\Common\stringHelper;

class QuoteDevice
{
    /**
     * The file put to that file which containts the LVP QuoteDevice data.
     *
     * @var string
     */
    private static $m_sQuoteDeviceFile = 'Data/Logs/#lvp.echo.log';

    /**
     * Array containing the unserialized data taken from the file defined
     * above.
     *
     * @var array
     */
    private static $m_aQuoteDeviceFileContent = array ();

    /**
     * Read out the file with the quoteDevice information when possible, else it
     * gives an empty array.
     */
    private static function getQuoteDeviceFileContent ()
    {
        self :: $m_aQuoteDeviceFileContent = array ();

        if (file_exists (self :: $m_sQuoteDeviceFile))
        {
            self :: $m_aQuoteDeviceFileContent = file (self :: $m_sQuoteDeviceFile);
        }

        return self :: $m_aQuoteDeviceFileContent;
    }

    /**
     * To know what the quoteDevice now wants we need to parse the information we are receiving in #LVP.echo from a
     * Nuwani-sisters-bot.
     *
     * @param Bot    $pBot
     * @param string $sMessage The message with the information for the quoteDevice.
     */
    public static function setInformation (Bot $pBot, string $sMessage)
    {
        $aParameters = explode (' ', \Util :: stripFormat($sMessage . ' '));
        $aNewParameters = explode (' ', \Util :: stripFormat($sMessage . ' '));
        unset ($aNewParameters [0], $aNewParameters [1]);
        $sNewMessage = implode(' ', $aNewParameters);
        $sPathToLogFile = stringHelper :: Format ('Data/Logs/{0}-{1}-{2}-#lvp.echo.log', date ('Y'), date ('m'), date ('d'));

        if (strstr ($aParameters [0], '[') !== false && strstr ($aParameters [0], ']') !== false)
        {
            if (strstr ($aParameters [1], ':') !== false)
            {
                //file_put_contents ('Data/Logs/#lvp.echo.log', '[' . date('H:i:s') . '] <' . str_replace (':', '', $aParameters [1]) . '> ' . $sNewMessage . PHP_EOL, FILE_APPEND);
                file_put_contents ($sPathToLogFile, stringHelper :: Format ('[{0}] <{1}> {2}' . PHP_EOL, date('H:i:s'),
                    trim ($aParameters [1], ':'), $sNewMessage), FILE_APPEND);
            }
            elseif (strstr ($aParameters [1], ':') === false && strstr ($aParameters [1], '***') === false)
            {//06
                //file_put_contents ('Data/Logs/#lvp.echo.log', '[' . date('H:i:s') . '] * ' . str_replace (':', '', $aParameters [1]) . ' ' . $sNewMessage . PHP_EOL, FILE_APPEND);
                file_put_contents ($sPathToLogFile, stringHelper :: Format ('[{0}] * {1} {2}' . PHP_EOL, date('H:i:s'),
                    trim ($aParameters [1], ':'), $sNewMessage), FILE_APPEND);
            }
            elseif ($aParameters [1] == '***' && strstr ($aParameters [6], 'kicked') !== false)
            {
                file_put_contents ($sPathToLogFile, stringHelper :: Format ('[{0}] *** {1} was kicked by {2}' . PHP_EOL,
                    date('H:i:s'), $aParameters [2], 'LasVenturasPlayground'), FILE_APPEND);
            }
        }
        elseif ($aParameters [2] == 'on' && $aParameters [3] == 'IRC:')
        {
            unset ($aNewParameters [2], $aNewParameters [3]);
            $sNewMessage = implode(' ', $aNewParameters);
            //file_put_contents ('Data/Logs/#lvp.echo.log', '[' . date('H:i:s') . '] <' . str_replace (':', '', $aParameters [1]) . '> ' . $sNewMessage . PHP_EOL, FILE_APPEND);
            file_put_contents ($sPathToLogFile, stringHelper :: Format ('[{0}] <{1}> {2}' . PHP_EOL, date('H:i:s'),
                trim ($aParameters [1], ':'), $sNewMessage), FILE_APPEND);
        }

        if (strstr ($sMessage, 'Monique') && !strstr ($sMessage, 'Monique:') && file_get_contents ('Data/LVP/MQD.dat') != '')
        {
            self :: sendRandomMessage ($pBot);
        }

        return;
    }

    public static function sendRandomMessage (Bot $pBot)
    {
        $aMessages        = self :: getQuoteDeviceFileContent();
        $sOriginalMessage = array_slice  ($aMessages, count ($aMessages) - 50);
        $aMessageWords    = explode      (' ', $sOriginalMessage [mt_rand (2, count ($sOriginalMessage) - 1)]);
        $sMessage         = str_ireplace ('Monique', str_replace (array ('<', '>'), '', $aMessageWords [1]), $aMessageWords);

        $pBot -> send ('PRIVMSG #lvp.echo :!msg ' . \Util :: getPieces ($sMessage, ' ', 2));
    }
}