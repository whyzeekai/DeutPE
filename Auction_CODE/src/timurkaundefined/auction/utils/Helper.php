<?php

declare(strict_types=1);

namespace timurkaundefined\auction\utils;

use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use timurkaundefined\auction\Auction;
use function base64_decode;
use function base64_encode;
use function explode;
use function max;
use function min;
use function substr;
use function time;

abstract class Helper{

	public static function serializeItem(Item $item) : string{
		return $item->getId() . ">>" . $item->getDamage() . ">>" . $item->getCount() . ">>" . base64_encode($item->getCompoundTag());
	}

	public static function deserializeItem(string $serialized) : Item{
		[$id, $damage, $count, $tags] = explode(">>", $serialized);
		return Item::get((int)$id, (int)$damage, (int)$count, $tags !== '' ? base64_decode($tags) : '');
	}

	public static function breakTime() : int{
		return time() - Auction::DEDUCTIBLE;
	}

	public static function getTimeDiff(int $greaterTime, int $smallerTime) : int{
		return max(0, $greaterTime - $smallerTime);
	}

	public static function convertTimeOptimized(int $brokenTime, int $currentTime) : string{
		$diff = self::getTimeDiff($brokenTime, $currentTime);

		$hours = (int)($diff / 3600);
		$diff -= $hours * 3600;
		$minutes = (int)($diff / 60);

		if($hours === 0 and $minutes === 0){
			$minutes = 1;
		}

		$result = "";
		$array = ["ч." => $hours, "м." => $minutes];
		foreach($array as $key => $value){
			if($value > 0 and ($key !== "м." or $value > 1)){
				$result .= "§a" . $value . " §f" . $key . " ";
			}
		}
		return $result !== "" ? substr($result, 0, -1) : "n/a";
	}

	public static function convert(int $n, string $a){
		$a = explode(",", $a);
		$cases = [2, 0, 1, 1, 1, 2];
		return $a[($n % 100 > 4 and $n % 100 < 20) ? 2 : $cases[min($n % 10, 5)]];
	}

	public static function toPrettyNumber($number) : string{
		return (string)$number;
	}

	public static function createBaseNBT(Player $creator) : CompoundTag{
		return new CompoundTag("", [
			new ListTag("Pos", [
				new DoubleTag("", $creator->getFloorX() + 0.5),
				new DoubleTag("", $creator->getFloorY()),
				new DoubleTag("", $creator->getFloorZ() + 0.5)
			]),
			new ListTag("Motion", [
				new DoubleTag("", 0.0),
				new DoubleTag("", 0.0),
				new DoubleTag("", 0.0)
			]),
			new ListTag("Rotation", [
				new FloatTag("", round($creator->yaw, -1)), new FloatTag("", round($creator->pitch, -1))
			]),

			new CompoundTag("Skin", [
				"Data" => new StringTag("Data", $creator->getSkin()->getSkinData()),
				"Name" => new StringTag("Name", $creator->getSkin()->getSkinId())
			])
		]);
	}

	public static function createTileNBT(string $saveId, string $customName, Vector3 $pos, Vector3 $pairPos) : CompoundTag{
		return new CompoundTag("", [
			new StringTag("id", $saveId),

			new IntTag("x", $pos->x),
			new IntTag("y", $pos->y),
			new IntTag("z", $pos->z),

			new IntTag("pairx", $pairPos->x),
			new IntTag("pairz", $pairPos->z),

			new StringTag("CustomName", $customName)
		]);
	}
}