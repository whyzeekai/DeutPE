<?php

namespace ChestAPI;

class ChestDeleteEvent extends \pocketmine\event\plugin\PluginEvent{

	public static $handlerList = null;
	public $cancelled = false;
	
	/** @var ChestAPI */
	public $plugin;
	/** @var \pocketmine\Player */
	public $player;
	/** @var array */
	public $pos;

	public function __construct(\pocketmine\Player $p, array $pos, ChestAPI $plugin){
		parent::__construct($plugin);

		$this->plugin = $plugin;
		$this->player = $p;
		$this->pos = $pos;
	}

	public function getPlayer() : \pocketmine\Player{
		return $this->player;
	}

	public function getPos() : array{
		return $this->pos;
	}

	public function setCancelled($value = true) : void{
		$this->cancelled = $value;
	}

	public function isCancelled() : bool{
		return $this->cancelled;
	}
}