<?php

namespace ChestAPI;

class ChestClickEvent extends \pocketmine\event\plugin\PluginEvent{

	public static $handlerList = null;
	public $cancelled = false;

	public function __construct(\pocketmine\Player $p, \pocketmine\item\Item $item, int $slot, \pocketmine\inventory\Inventory $inventory, ChestAPI $plugin){
		parent::__construct($plugin);

		$this->plugin = $plugin;
		$this->player = $p;
		$this->inventory = $inventory;
		$this->item = $item;
		$this->slot = $slot;
	}

	public function getPlayer() : \pocketmine\Player{
		return $this->player;
	}

	public function getInventory() : \pocketmine\inventory\Inventory{
		return $this->inventory;
	}

	public function getItem() : \pocketmine\item\Item{
		return $this->item;
	}

	public function getSlot() : int{
		return $this->slot;
	}

	public function getId() : int{
		return $this->item->getId();
	}

	public function getDamage() : int{
		return $this->item->getDamage();
	}

	public function getCustomName(): string{
		return $this->item->getCustomName();
	}

	public function setCancelled($value = true) : void{
		$this->cancelled = $value;
	}

	public function isCancelled() : bool{
		return $this->cancelled;
	}
}