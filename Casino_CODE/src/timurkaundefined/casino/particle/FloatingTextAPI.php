<?php

declare(strict_types=1);

namespace timurkaundefined\casino\particle;

use pocketmine\math\Vector3;
use pocketmine\Player;
use timurkaundefined\casino\Casino;

abstract class FloatingTextAPI{

	/** @var IndividualFloatingText[] */
	private static $list = [];

	public static function store(string $title, Vector3 $pos) : void{
		self::$list[$title] = new IndividualFloatingText($pos, $title);
	}

	public static function callbackToAll(string $callback) : void{
		foreach(self::$list as $floatingText){
			$floatingText->{$callback}();
		}
	}

	public static function updateOne(string $title) : void{
		self::$list[$title]->updateToAll();
	}

	public static function despawnFromAll(string $title) : void{
		self::$list[$title]->despawnFromAll();
	}

	public static function spawnToOne(Player $player) : void{
		foreach(self::$list as $title => $floatingText){
			if($title === 'timeToStart' and Casino::getInstance()->isRunning()){
				continue;
			}
			$floatingText->spawnTo($player);
		}
	}

	public static function despawnFromOne(Player $player) : void{
		foreach(self::$list as $floatingText){
			$floatingText->despawnFrom($player, false);
		}
	}
}