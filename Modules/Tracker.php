<?php
use Nuwani\Bot;

/**
 * Nuwani 2.0 News Tracker
 * The feed tracker enables you to keep track of news, updates and information from other
 * projects and websites. All items can be outputted on IRC easily, allowing monitoring
 * channels to be created for undefined purposes.
 * This file is part of the Nuwani v2 Bot Framework, a simple set of PHP classes which allow
 * you to set-up and run your own bot. It features advanced, PHP-5.3 based syntax for
 * optimal performance and security.
 * @author  Peter Beverloo <peter@lvp-media.com>
 * @version 1.0
 */
class Tracker extends ModuleBase
{
    /**
     * An array containing the feeds we'll be controlling through this module. All
     * feeds will get their own entry with various information available:
     * 'name'       => Name of the feed that is being monitored.
     * 'location'   => Location of the feed as an absolute URL.
     * 'channel'    => Channel in which this feed should be displayed.
     * 'hidden'     => Hide this channel from normal output (required halfop)?
     * 'offset'    => Offset of the first letter in the title (used for prefixes).
     * 'format'     => Format of the message which will be displayed on IRC.
     * 'lastitem'   => Update time of the last received item, since some feeds have delays.
     * 'updated'    => Timestamp of the last update of this feed.
     * 'frequency'  => How often should this feed be updated?
     * New entries can easily be added using the addFeed method available in this
     * class. This array should be synched with the database at all times.
     * @var array
     */

    private $m_aFeedList;

    /**
     * The last update property defines the time we last checked for updates. Since
     * the onTick callback can be invoked multiple times per second, it would be
     * silly to list all to-be-updated feeds every time.
     */

    private $m_nLastUpdate;

    /**
     * The constructor of this class will easily initialise the feeds that should
     * be available for parsage. In order to do so, we'll connect to the database
     * and retreive a full list of the feeds which are available, including their
     * updated timestamps.
     */

    public function __construct ()
    {
        $this->m_aFeedList = array ();
        if (file_exists ('Data/Tracker.dat'))
        {
            $aTrackerData = unserialize (file_get_contents ('Data/Tracker.dat'));
            if (is_array ($aTrackerData))
            {
                $this->m_aFeedList = $aTrackerData;

                foreach ($this->m_aFeedList as $nIndex => $aItem)
                {
                    $nFrequencyOffset = $aItem ['frequency'] / 2;
                    $nOffset          = time () + rand ((0 - $nFrequencyOffset), ($nFrequencyOffset));

                    $this->m_aFeedList [$nIndex] ['updated']  = $nOffset;
                    $this->m_aFeedList [$nIndex] ['lastitem'] = $nOffset;
                }
            }
        }

        echo ' - ' . count ($this->m_aFeedList) . ' feeds loaded... ';
        $this->m_nLastUpdate = 0;
    }

    /**
     * The updateFeed method may be used to update a feed when required, which will
     * be determined by the onTick method. In here we download the feed's contents
     * and check whether there are new items, and if so, output them to IRC.
     *
     * @param integer $nIndex Index of the feed which has to be updated.
     */

    private function updateFeed ($nIndex)
    {
        if (!isset ($this->m_aFeedList [$nIndex]))
        {
            echo '[Tracker] Warning: Could not update feed #' . $nIndex . PHP_EOL;

            return;
        }

        $aFeedInfo = $this->m_aFeedList [$nIndex];
        $aItems    = $this->retrieveFeedItems ($aFeedInfo ['location']);

        if (count ($aItems) > 0)
        {
            $nUpdated = $aFeedInfo ['lastitem'];
            foreach ($aItems as $aItemInfo)
            {
                if ($aItemInfo ['pubDate'] <= $aFeedInfo ['lastitem'])
                {
                    continue;
                }

                //echo '[Tracker] New item on feed "' . $aFeedInfo ['name'] . '": ' . $aItemInfo ['title'] . PHP_EOL;
                $nUpdated = max ($nUpdated, $aItemInfo ['pubDate']);

                $this->updateItem ($aItemInfo, $aFeedInfo);
            }

            $this->m_aFeedList [$nIndex] ['lastitem'] = $nUpdated;
        }

        $this->m_aFeedList [$nIndex] ['updated'] = time ();
    }

    /**
     * When a feed has new contents, the new items have to be displayed on IRC. This
     * will be done by the following method. Every item has a format specified with it
     * allowing the feed-displaying to be modified in real-time. Available format
     * modifiers which are available are:
     *  %name%        Name of the feed which received an update.
     *  %title%       Title of the new item in the feed.
     *  %link%        Link of the item which has been added.
     *  %desc%        Description of the new item which has been added.
     *  %category%    Category this new item has been filed under.
     * The format of a feed can be displyed using the !fupdate command, further
     * documentation of which is available for the onChannelPrivmsg() method.
     *
     * @param array $aItemInfo Information about the item which has been published.
     * @param array $aFeedInfo Information about the feed as a whole.
     */

    private function updateItem ($aItemInfo, $aFeedInfo)
    {
        $aFormatTags = array ('%name%' => $aFeedInfo ['name'], '%title%' => $aItemInfo ['title'], '%link%' => $aItemInfo ['link'], '%desc%' => $aItemInfo ['description'], '%category%' => $aItemInfo ['category']);

        if (isset ($aFeedInfo ['offset']) && $aFeedInfo ['offset'] > 0)
        {
            $aFormatTags ['%title%'] = substr ($aFormatTags ['%title%'], $aFeedInfo ['offset']);
        }

        /*
        $pDatabase  = Database :: getInstance ();
        $pStatement = $pDatabase -> prepare ('INSERT INTO Tracker.tracker_feeds (link) VALUES (?)');
        $pStatement -> bind_param ('s', $aItemInfo ['link']);
        if ($pStatement -> execute ())
        {
            $aFormatTags ['%link%'] = 'http://pb.tc/' . $pStatement -> insert_id;
        }
        */
        if (isset ($aItemInfo ['author']))
        {
            $aFormatTags ['%author%'] = $aItemInfo ['author'];
        }

        $sMessage = str_replace (array_keys ($aFormatTags), array_values ($aFormatTags), $aFeedInfo ['format']);
        $sChannel = $aFeedInfo ['channel'];

        $pBot = Nuwani \ BotManager:: getInstance ()->offsetGet ('channel:' . $sChannel);
        if ($pBot !== false && count ($pBot) > 0)
        {
            if ($aFeedInfo ['hidden'] === true)
            {
                $sChannel = '%' . $sChannel;
            }

            $pBot->send ('PRIVMSG ' . $sChannel . ' :' . $sMessage);
        }
    }

    /**
     * Retrieving all contents from a feed can be a tough job, seeing feeds could
     * be having weird contents, wrong formats or other irregularities. Regardless,
     * for now we'll just assume everything is fine and allow SimpleXML to bite it.
     *
     * @param string $sLocation Absolute URL of the feed's contents.
     */

    private function retrieveFeedItems ($sLocation)
    {
        $pContext = stream_context_create (array ('http' => array ('user_agent' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)', 'timeout' => 5.0)));

        $sFeedData  = file_get_contents ($sLocation, 0, $pContext);
        $aFeedItems = array ();

        if ($sFeedData !== false)
        {
            $pReader = simplexml_load_string ($sFeedData, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($pReader === false)
                /** syntaxical error **/
            {
                return $aFeedItems;
            }

            $pItems = $pReader->xpath ('/rss/channel/item');

            if (count ($pItems) > 0)
            {
                foreach ($pItems as $pItemInfo)
                {
                    $aFeedItems [] = array ('title' => (string) $pItemInfo->title, 'link' => (string) $pItemInfo->link, 'description' => (string) $pItemInfo->description, 'pubDate' => strtotime ($pItemInfo->pubDate), 'category' => (string) $pItemInfo->category, 'author' => (isset ($pItemInfo->author) ? (string) $pItemInfo->author : ''));
                }
            }
        }

        return $aFeedItems;
    }

    /**
     * We have to check for updates every few seconds, depending on the feeds that
     * are available. Each feed can be associated with a certain channel, in which
     * we will be outputting data when available.
     */

    public function onTick ()
    {
        $nCurrentTime = time ();

        if ($this->m_nLastUpdate == $nCurrentTime)
        {
            return false;
        }

        $aOutdatedIndexes = array ();
        foreach ($this->m_aFeedList as $nIndex => $aItem)
        {
            $nElapsed = ($nCurrentTime - $aItem ['updated']);
            if ($nElapsed > $aItem ['frequency'])
            {
                $aOutdatedIndexes [$nIndex] = $nElapsed;
            }
        }

        arsort ($aOutdatedIndexes);
        if (count ($aOutdatedIndexes) != 0)
        {
            reset ($aOutdatedIndexes); // return to the beginning..
            $this->updateFeed (key ($aOutdatedIndexes));
        }

        return true;
    }

    /**
     * Obviously it is important to be able to control the feeds and their settings,
     * which can be done using in-channel commands. This method will check the
     * incoming messages for commands and execute them if appropriate.
     *
     * @param Bot    $pBot      Instance of the bot which received this message.
     * @param string $sChannel  Channel in which the message got distributed.
     * @param string $sNickname Nickname of the person who distributed this message.
     * @param string $sMessage  Contents of the message itself.
     */

    public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
    {
        if ($sMessage [0] != '!' && $sNickname == 'Xanland')
        {
            return;
        }

        $aParameters = explode (' ', $sMessage . ' ');
        switch (strtolower ($aParameters [0]))
        {
            case '!fhelp':
            {
                $pBot->send ('PRIVMSG ' . $sChannel . ' :4Feed Tracker: !fhelp !fadd !flist !finfo !fupdate !fremove');
                break;
            }

            case '!flistall': // ..
            case '!flist': // <all=>
            {
                $sMessage = '4Active Feeds: ';
                $aFeeds   = array ();

                $bShowGlobal = (strtolower ($aParameters [0]) == '!flistall');
                $bShowHidden = (isset ($aParameters [1]) && $aParameters [1] == 'all');

                foreach ($this->m_aFeedList as $nIndex => $aFeedInfo)
                {
                    if (strtolower ($aFeedInfo ['channel']) != strtolower ($sChannel) && $bShowGlobal === false)
                    {
                        continue;
                    }

                    if ($aFeedInfo ['hidden'] === true && $bShowHidden === false)
                    {
                        continue;
                    }

                    $aFeeds [] = $aFeedInfo ['name'] . '15(' . $nIndex . ')';

                    if (count ($aFeeds) >= 15)
                    {
                        $pBot->send ('PRIVMSG ' . $sChannel . ' :' . $sMessage . implode (', ', $aFeeds));
                        $aFeeds = array ();
                    }
                }

                if (count ($aFeeds) == 0)
                {
                    $aFeeds [] = '14none';
                }

                $pBot->send ('PRIVMSG ' . $sChannel . ' :' . $sMessage . implode (', ', $aFeeds));
                break;
            }

            case '!fadd': // <name> <frequency> <location>
            {
                $sName      = isset ($aParameters [1]) ? $aParameters [1] : null;
                $nFrequency = isset ($aParameters [2]) ? $aParameters [2] : null;
                $sLocation  = isset ($aParameters [3]) ? $aParameters [3] : null;

                if (strlen ($sName) == 0 || strlen ($sName) > 16 || !is_numeric ($nFrequency) || strlen ($sLocation) == 0 || strlen ($sLocation) > 128 * 2)
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Usage: !fadd <name> <frequency> <location>');
                    break;
                }

                if ($nFrequency < 60 || $nFrequency > 86400)
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Error: The frequency should be between 60 and 86400 seconds.');
                    break;
                }

                $this->m_aFeedList [] = array ('name' => $sName, 'location' => $sLocation, 'channel' => $sChannel, 'hidden' => false, 'format' => '7[%name%] %title% 15(%link%)', 'offset' => 0, 'lastitem' => time (), 'updated' => time (), 'frequency' => $nFrequency);

                file_put_contents ('Data/Tracker.dat', serialize ($this->m_aFeedList));
                end ($this->m_aFeedList);

                $pBot->send ('PRIVMSG ' . $sChannel . ' :3Success: The feed has been added successfully (ID: ' . key ($this->m_aFeedList) . ').');
                break;
            }

            case '!fremove': // <id>
            {
                $nIndex = isset ($aParameters [1]) ? $aParameters [1] : -1;
                if (!is_numeric ($nIndex) || !isset ($this->m_aFeedList [$nIndex]))
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Usage: !fremove <id>');
                    break;
                }

                $aFeedInfo = $this->m_aFeedList [$nIndex];
                unset ($this->m_aFeedList [$nIndex]);

                file_put_contents ('Data/Tracker.dat', serialize ($this->m_aFeedList));

                $pBot->send ('PRIVMSG ' . $sChannel . ' :3Success: The feed "' . $aFeedInfo ['name'] . '" (' . $aFeedInfo ['location'] . ') has been removed.');
                break;
            }

            case '!fupdate': // <id> <key> <value>
            {
                $nIndex = isset ($aParameters [1]) ? $aParameters [1] : -1;
                $sKey   = isset ($aParameters [2]) ? $aParameters [2] : null;
                $sValue = isset ($aParameters [3]) ? implode (' ', array_slice ($aParameters, 3)) : null;

                if (!is_numeric ($nIndex) || !isset ($this->m_aFeedList [$nIndex]) || !strlen ($sKey) || !strlen ($sValue))
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Usage: !fupdate <id> <key> <value>');
                    break;
                }

                $aAllowedKeys = array ('name', 'location', 'channel', 'offset', 'hidden', 'format', 'lastitem', 'updated', 'frequency');
                if (!in_array ($sKey, $aAllowedKeys))
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Error: The key must be one of the following: ' .
                        implode (', ', $aAllowedKeys));
                    break;
                }

                switch ($sKey)
                {
                    case 'name':
                        $this->m_aFeedList [$nIndex]['name'] = $sValue;
                        break;
                    case 'location':
                        $this->m_aFeedList [$nIndex]['location'] = $sValue;
                        break;
                    case 'channel':
                        $this->m_aFeedList [$nIndex]['channel'] = $sValue;
                        break;
                    case 'hidden':
                        $this->m_aFeedList [$nIndex]['hidden'] = (bool) $sValue;
                        break;
                    case 'offset':
                        $this->m_aFeedList [$nIndex]['offset'] = (int) $sValue;
                        break;
                    case 'format':
                        $this->m_aFeedList [$nIndex]['format'] = $sValue;
                        break;
                    case 'lastitem':
                        $this->m_aFeedList [$nIndex]['lastitem'] = (int) $sValue;
                        break;
                    case 'updated':
                        $this->m_aFeedList [$nIndex]['updated'] = (int) $sValue;
                        break;
                    case 'frequency':
                        $this->m_aFeedList [$nIndex]['frequency'] = (int) $sValue;
                        break;
                }

                file_put_contents ('Data/Tracker.dat', serialize ($this->m_aFeedList));

                $pBot->send ('PRIVMSG ' . $sChannel . ' :3Success: The feed has been updated.');
                break;
            }

            case '!finfo': // <id> <key>
            {
                $nIndex = isset ($aParameters [1]) ? $aParameters [1] : -1;
                $sKey   = isset ($aParameters [2]) ? $aParameters [2] : null;

                if (!is_numeric ($nIndex) || !isset ($this->m_aFeedList [$nIndex]) || !strlen ($sKey))
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Usage: !finfo <id> <key>');
                    break;
                }

                $aAllowedKeys = array ('name', 'location', 'channel', 'hidden', 'offset', 'format', 'lastitem', 'updated', 'frequency');
                if (!in_array ($sKey, $aAllowedKeys))
                {
                    $pBot->send ('PRIVMSG ' . $sChannel . ' :4Error: The key must be one of the following: ' . implode (',', $aAllowedKeys));
                    break;
                }

                $mValue = $this->m_aFeedList [$nIndex][$sKey];
                switch ($sKey)
                {
                    case 'hidden':
                    {
                        $mValue = ($mValue) ? 'Yes' : 'No';
                        break;
                    }

                    case 'updated':
                    {
                        $mValue = date ('Y-m-d H:i:s', $mValue) . ' (' . floor ((time () - $mValue) / 60) . ' minutes ago)';
                        break;
                    }
                }

                $pBot->send ('PRIVMSG ' . $sChannel . ' :3Value of "' . $sKey . '": ' . $mValue);
                break;
            }
        }
    }
}