<?php

namespace ChestAPI;

class ChestDropEvent extends \pocketmine\event\plugin\PluginEvent{

	public static $handlerList = null;
	public $cancelled = false;

	public function __construct(\pocketmine\Player $p, \pocketmine\item\Item $item, \pocketmine\entity\Entity $entity, ChestAPI $plugin){
		parent::__construct($plugin);

		$this->plugin = $plugin;
		$this->player = $p;
		$this->item = $item;
		$this->entity = $entity;
	}

	public function getPlayer() : \pocketmine\Player{
		return $this->player;
	}

	public function getItem() : \pocketmine\item\Item{
		return $this->item;
	}

	public function getEntity(){
		return $this->entity;
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