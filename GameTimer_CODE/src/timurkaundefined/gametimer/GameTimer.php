<?php

declare(strict_types=1);

namespace timurkaundefined\gametimer;

use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use function is_dir;
use function mkdir;
use function strtolower;
use function substr;
use function time;

class GameTimer extends PluginBase{

	/** @var GameTimer */
	private static $instance;
	/** @var Config */
	private static $data;

	private static $joinTime = [];

	function onEnable(){
		self::$instance = $this;

		if(!is_dir($directoryPath = $this->getDataFolder())){
			mkdir($directoryPath, 0777, true);
		}
		Server::getInstance()->getPluginManager()->registerEvents(new EventHandler(), $this);

		self::$data = new Config($directoryPath . "data.json", Config::JSON);
	}

	public static function getInstance() : GameTimer{
		return self::$instance;
	}

	public static function getData() : Config{
		return self::$data;
	}

	public static function writeJoinTime(Player $player) : void{
		$name = $player->getLowerCaseName();
		self::$joinTime[$name] = time();
	}

	public static function updateGameTime($player, bool $remove = false) : int{
		$name = $player instanceof Player ? $player->getLowerCaseName() : strtolower((string)$player);
		if(!isset(self::$joinTime[$name])){
			return (int)self::$data->get($name, 0);
		}
		$currentTime = time();
		$joinTime = self::$joinTime[$name];
		$difference = $currentTime - $joinTime;
		self::$data->set($name, (int)self::$data->get($name, 0) + $difference);
		if($remove){
			unset(self::$joinTime[$name]);
		}
		self::$joinTime[$name] = $currentTime;
		return $value ?? $difference;
	}

	public static function getGameTime($player) : int{
		self::updateGameTime($player);
		$name = $player instanceof Player ? $player->getLowerCaseName() : strtolower((string)$player);
		return (int)self::$data->get($name, 0);
	}

	public static function convertTimeOptimized(int $diff) : string{
		$days = (int)($diff / 86400);
		$diff -= $days * 86400;
		$hours = (int)($diff / 3600);
		$diff -= $hours * 3600;
		$minutes = (int)($diff / 60);

		if($days === 0 and $hours === 0 and $minutes === 0){
			return '§aменее минуты';
		}

		$result = "";
		$array = ["д." => $days, "ч." => $hours, "м." => $minutes];
		foreach($array as $key => $value){
			if($value > 0){
				$result .= "§a" . $value . " §a" . $key . " ";
			}
		}
		return $result !== "" ? substr($result, 0, -1) : "n/a";
	}

	public function dataToArray() : array{
		foreach(self::$joinTime as $playerName => $_){
			self::updateGameTime($playerName);
		}
		return self::$data->getAll();
	}

	public function onDisable(){
		self::$data->save();
	}
}