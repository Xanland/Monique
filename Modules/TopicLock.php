<?php
use Nuwani\Bot;
use Nuwani\ModuleManager;

/**
 * Nuwani PHP IRC Bot Framework
 * Copyright (c) 2006-2009 The Nuwani Project
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
 * @copyright Copyright (c) 2006-2009 The Nuwani Project
 * @package TopicLock
 * @author Paul Redert <paul@redert.net>
 * @see http://nuwani.googlecode.com
 */

class TopicLock extends ModuleBase
{

	/**
	 * The topics and their lock state for all joined channels.
	 */
	private $m_aChannels;

	/**
	 * Constructor.
	 */
	public function __construct ()
	{
	    if (file_exists ('Data/TopicLock.dat') && is_array (unserialize (file_get_contents ('Data/TopicLock.dat'))))
	    {
		$this->m_aChannels = unserialize (file_get_contents ('Data/TopicLock.dat'));
	    }
	    else
	    {
		$this->m_aChannels = array ();
	    }
	}

	/**
	 * Destructor.
	 */
	public function __destruct ()
	{
	    file_put_contents ('Data/TopicLock.dat', serialize ($this->m_aChannels));
	}

	/**
	 * Set the topic of a channel to the specified topic.
	 * @param string $sChannel the channel to change the topic for
	 * @param string $sTopic the topic to be set
	 * @return true on succes, false on failure
	 */
	private function setTopic ($sChannel, $sTopic)
	{

		$this->m_aChannels[$sChannel]['Topic'] = $sTopic;

	}

	/**
	 * Lock the topic.
	 */
	private function lockTopic ($sChannel)
	{

		$this->m_aChannels[$sChannel]['Locked'] = true;

	}

	/**
	 * Unock the topic.
	 */
	private function unlockTopic ($sChannel)
	{

		$this->m_aChannels[$sChannel]['Locked'] = false;

	}

	/**
	 * Gets called when we join a channel, so lets store the topic.
	 */
	public function onChannelTopic (Bot $pBot, $sChannel, $sTopic)
	{
		$this->m_aChannels[$sChannel]['Topic'] = $sTopic;
	}

	public function onChangeTopic (Bot $pBot, $sChannel, $sNickname, $sNewTopic)
	{
		if ($sNickname === $pBot->offsetGet('Nickname'))
		{
			// It's us!
			return ;
		}

		if ( $this->m_aChannels[$sChannel]['Locked'] === true )
		{
			// Topic is locked, lets try to reset it.
			$pBot->send( 'TOPIC ' . $sChannel . ' :' . $this->m_aChannels[$sChannel]['Topic'] );

		}
		else
		{
			// Topic is not locked, lets store it
			$this->setTopic($sChannel, $sNewTopic);

		}

	}

	/**
	 * We recieved a message! Oh joy!
	 * @param Bot $pBot
	 * @param string $sChannel
	 * @param string $sNickname
	 * @param string $sMessage
	 */
	public function onChannelPrivmsg (Bot $pBot, $sChannel, $sNickname, $sMessage)
	{

		$pEval = ModuleManager :: getInstance () -> offsetGet ('Evaluation');

		if (!$pEval->checkSecurity ($pBot, 9999))
		{
			// Not the (logged in) owner.
			return ;
		}

		$aParts = Util::parseMessage ($sMessage);

		switch ($aParts[0])
		{

			case '!locktopic' :
			{
				$this->lockTopic($sChannel);
				$pBot->send('PRIVMSG ' . $sChannel. ' :4Notice: Topic locked.');
				file_put_contents ('Data/TopicLock.dat', serialize ($this->m_aChannels));
				break;
			}

			case '!unlocktopic' :
			{
				$this->unlockTopic($sChannel);
				$pBot->send('PRIVMSG ' . $sChannel. ' :4Notice: Topic unlocked.');
				file_put_contents ('Data/TopicLock.dat', serialize ($this->m_aChannels));
				break;
			}

			case '!listtopic' :
			{

				if ($aParts[1] === null)
				{
					$pBot->send('PRIVMSG ' . $sChannel. ' :4Listing topics: ');

					foreach (array_keys($this->m_aChannels) as $sCurrentChannel)
					{

						$pBot->send('PRIVMSG ' . $sChannel. ' :[' . $sCurrentChannel . '] -> ' . $this->m_aChannels[$sCurrentChannel]['Topic']);

					}

					break;
				}
				else
				{
					$pBot->send('PRIVMSG ' . $sChannel. ' :4Topic for channel ' . $aParts[1] . ': ' . $this->m_aChannels[$aParts[1]]['Topic']);
					break;
				}
			}

		}

	}
}

?>