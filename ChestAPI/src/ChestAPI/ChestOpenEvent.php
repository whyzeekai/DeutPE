<?php

namespace ChestAPI;

class ChestOpenEvent extends \pocketmine\event\plugin\PluginEvent{

	public static $handlerList = null;
	public $cancelled = false;
	
	/** @var ChestAPI */
	public $plugin;
	/** @var \pocketmine\Player */
	public $player;
	/** @var array */
	public $params;
	/** @var string */
	public $name;
	/** @var int */
	public $data;
	/** @var string */
	public $message;

	public function __construct(\pocketmine\Player $p, array $params, string $name, int $data, string $message, ChestAPI $plugin){
		parent::__construct($plugin);

		$this->plugin = $plugin;
		$this->player = $p;
		$this->params = $params;
		$this->name = $name;
		$this->data = $data;
		$this->message = $message;
	}

	public function getPlayer() : \pocketmine\Player{
		return $this->player;
	}

	public function getParams() : array{
		return $this->params;
	}

	public function getName() : string{
		return $this->name;
	}

	public function getData() : int{
		return $this->data;
	}

	public function getMessage() : string{
		return $this->message;
	}

	public function setCancelled($value = true) : void{
		$this->cancelled = $value;
	}

	public function isCancelled() : bool{
		return $this->cancelled;
	}
}