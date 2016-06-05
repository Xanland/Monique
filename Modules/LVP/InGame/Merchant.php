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
 * @package LVP InGame Merchant Module
 * @author Xander "Xanland" Hoogland <home@xanland.nl>
 * @see http://nuwani.googlecode.com
 */

namespace LVP\InGame;

class Merchant
{
    /**
     * The file put to that file which containts the LVP Merchant data.
     *
     * @var string
     */
    private static $m_sMerchantFile = 'Data/LVP/Merchant.dat';

    /**
     * Array containing the unserialized data taken from the file defined
     * above.
     *
     * @var array
     */
    private static $m_aMerchantFileContent = array ();

    /**
     * Since the commands use an instance of this class they can get their data
     * easily by only instantiate it.
     *
     * @return array
     */
    public static function execute ()
    {
        return (object) self :: getMerchantFileContent ();
    }

    /**
     * Read out the file with the merchant information when possible, else it
     * gives an empty array.
     */
    private static function getMerchantFileContent ()
    {
        if (file_exists (self :: $m_sMerchantFile))
        {
            self :: $m_aMerchantFileContent = unserialize (file_get_contents (self :: $m_sMerchantFile));
        }
        else
        {
            self :: $m_aMerchantFileContent = array ();
        }

        return self :: $m_aMerchantFileContent;
    }

    /**
     * Save all the details into the merchantfile for use by the commands.
     */
    private static function saveMerchantFile ()
    {
        file_put_contents (self :: $m_sMerchantFile, serialize (self :: $m_aMerchantFileContent));
    }

    /**
     * To know what the merchant now wants we need to parse the information
     * we are receiving in #LVP.echo from a Nuwani-sisters-bot.
     *
     * @param string $sMerchantInfo The message with the information what the
     *                              merchant wants at this moment, like:
     *                              vehicle and the price he buys it for.
     */
    public static function setInformation ($sMerchantInfo)
    {
        if (preg_match('/Grand Theft Auto: The merchant now requires (.+) for \$(.+)/', $sMerchantInfo, $aMerchantInformation))
        {
            self :: getMerchantFileContent ();

            self :: $m_aMerchantFileContent ['vehicle'] = $aMerchantInformation [1];
            self :: $m_aMerchantFileContent ['price']   = $aMerchantInformation [2];

            self :: saveMerchantFile ();
        }

        return;
    }

    public static function resetInformation ()
    {
        self :: getMerchantFileContent ();

        self :: $m_aMerchantFileContent ['vehicle'] = 'none';
        self :: $m_aMerchantFileContent ['price']   = 0;

        self :: saveMerchantFile ();
    }
}