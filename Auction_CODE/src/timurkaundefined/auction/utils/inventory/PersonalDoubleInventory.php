<?php

declare(strict_types=1);

namespace timurkaundefined\auction\utils\inventory;

use pocketmine\inventory\ContainerInventory;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\Player;
use timurkaundefined\auction\Auction;
use timurkaundefined\auction\utils\tile\VirtualChest;

class PersonalDoubleInventory extends DoubleChestInventory{

	/** @var string */
	private $viewerName;

	public function __construct(VirtualChest $left, VirtualChest $right, string $viewerName){
		parent::__construct($left, $right);

		$this->viewerName = $viewerName;
	}

	public function onOpen(Player $who) : void{
		ContainerInventory::onOpen($who);
	}

	public function onClose(Player $who) : void{
		ContainerInventory::onClose($who);
		$instance = Auction::getInstance();
		// Проверяем, что плагин все еще включен перед планированием задачи
		if($instance !== null and $instance->isEnabled()){
			$instance->addToDelayedClose($who);
		}
	}

	public function getContents($withAir = false) : array{
		return [];
	}

	public function firstOccupied() : int{
		return -1;
	}

	public function getViewerName() : string{
		return $this->viewerName;
	}
}
