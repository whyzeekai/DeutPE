<?php

namespace ChestAPI;

class ChestCloseEvent extends \pocketmine\event\plugin\PluginEvent{

	public static $handlerList = null;
	
	/** @var ChestAPI */
	public $plugin;
	/** @var \pocketmine\Player */
	public $player;

	public function __construct(\pocketmine\Player $p, ChestAPI $plugin){
		parent::__construct($plugin);

		$this->plugin = $plugin;
		$this->player = $p;
	}

	public function getPlayer() : \pocketmine\Player{
		return $this->player;
	}
}