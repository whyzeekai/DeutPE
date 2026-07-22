<?php

declare(strict_types=1);

namespace timurkaundefined\gametimer;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

class EventHandler implements Listener{

	/**
	 * @param PlayerJoinEvent $event
	 * @noinspection PhpUnused
	 * @priority LOWEST
	 */
	function handlePlayerJoin(PlayerJoinEvent $event){
		GameTimer::writeJoinTime($event->getPlayer());
	}

	/** @noinspection PhpUnused */
	function handlePlayerQuit(PlayerQuitEvent $event){
		GameTimer::updateGameTime($event->getPlayer(), false);
	}
}