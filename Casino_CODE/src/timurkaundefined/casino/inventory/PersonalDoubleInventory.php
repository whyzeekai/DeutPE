<?php

declare(strict_types=1);

namespace timurkaundefined\casino\inventory;

use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\Player;
use pocketmine\Server;
use timurkaundefined\casino\Casino;
use timurkaundefined\casino\tile\VirtualCasinoChest;

class PersonalDoubleInventory extends DoubleChestInventory{

	/** @var string */
	private $viewerName;

	private $invalidationAcknowledged = false;

	public function __construct(VirtualCasinoChest $left, VirtualCasinoChest $right, string $viewerName){
		parent::__construct($left, $right);

		$this->viewerName = $viewerName;
	}

	public function onOpen(Player $who){
		ContainerInventory::onOpen($who);
	}

	public function onClose(Player $who){
		ContainerInventory::onClose($who);
		Casino::getInstance()->addToDelayedClose($who);

		$this->invalidationAcknowledged = true;
	}

	public function getContents($withAir = false) : array{
		return [];
	}

	public function getViewerName() : string{
		return $this->viewerName;
	}

	public function invalidate(){
		if(!$this->invalidationAcknowledged){
			$player = Server::getInstance()->getPlayerExact($this->viewerName);
			if($player instanceof Player and $player->isOnline()){
				Casino::getInstance()->onInventoryInvalidation($player, $this->getLeftSide(), $this->getRightSide());
			}
		}
		parent::invalidate();
	}
}
