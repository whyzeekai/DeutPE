<?php

declare(strict_types=1);

namespace api\utils;

use pocketmine\math\Vector3;
use pocketmine\Player;
use api\utils\particle\FloatingTextParticle;
use function array_map;
use function explode;

abstract class Leaderboard{

	/** @var FloatingTextParticle[][] */
	private static $cachedTops = [];

	public static function add(string $identifier, Vector3 $pos, string $top) : void{
		if(!isset(self::$cachedTops[$identifier])){
			self::$cachedTops[$identifier] = [];
			foreach(explode("\n", $top) as $num => $line){
				self::$cachedTops[$identifier]['line' . $num] = new FloatingTextParticle($pos->add(0.5, -0.3 * $num, 0.5), $line);
			}
			array_map(function(FloatingTextParticle $particle) : void{
				$particle->spawnToAll();
			}, self::$cachedTops[$identifier]);
			return;
		}
		foreach(explode("\n", $top) as $num => $line){
			if(!isset(self::$cachedTops[$identifier]['line' . $num])){
				continue;
			}
			self::$cachedTops[$identifier]['line' . $num]->update($line);
		}
	}

	public static function showAllToPlayer(Player $player) : void{
		array_map(function(array $particles) use ($player) : void{
			array_map(function(FloatingTextParticle $particle) use ($player) : void{
				$particle->spawnTo($player);
			}, $particles);
		}, self::$cachedTops);
	}

	public static function despawnAllFrom(Player $player) : void{
		array_map(function(array $particles) use ($player) : void{
			array_map(function(FloatingTextParticle $particle) use ($player) : void{
				$particle->despawnFrom($player);
			}, $particles);
		}, self::$cachedTops);

	}
}