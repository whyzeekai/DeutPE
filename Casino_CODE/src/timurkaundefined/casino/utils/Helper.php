<?php

declare(strict_types=1);

namespace timurkaundefined\casino\utils;

use ParkourSystem\MishaShulman;
use pocketmine\entity\Entity;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\Player;
use pocketmine\Server;
use timurkaundefined\casino\entity\Croupier;
use timurkaundefined\casino\entity\Lightning;
use function array_keys;
use function array_merge;
use function array_values;
use function floor;
use function getrandmax;
use function implode;
use function json_decode;
use function min;
use function ord;
use function rand;
use function round;
use function str_replace;
use function str_split;

abstract class Helper{

	public static function sendSound(Player $player, string $soundName, bool $exactRecipient = false, float $pitch = 1.0, float $volume = 1.0) : void{
		$pk = self::getSoundPacket($player, $soundName, $pitch, $volume);

		if(!$exactRecipient and $player->isValid()){
			$player->getLevel()->addChunkPacket($pk->x >> 4, $pk->z >> 4, $pk);
		}else{
			$player->dataPacket($pk);
		}
	}

	public static function getSoundPacket(Vector3 $vector3, string $soundName, float $pitch = 1.0, float $volume = 1.0) : PlaySoundPacket{
		$pk = new class extends PlaySoundPacket{
			public function decode(){
				$this->sound = $this->getString();
				$this->getBlockCoords($this->x, $this->y, $this->z);
				$this->x /= 8;
				$this->y /= 8;
				$this->z /= 8;
				$this->volume = $this->getLFloat();
				$this->float = $this->getLFloat();
			}

			public function encode(){
				$this->reset();
				$this->putString($this->sound);
				$this->putBlockCoords((int)($this->x * 8), (int)($this->y * 8), (int)($this->z * 8));
				$this->putLFloat($this->volume);
				$this->putLFloat($this->float);
			}
		};
		$pk->sound = $soundName;
		$pk->x = floor($vector3->x);
		$pk->y = floor($vector3->y);
		$pk->z = floor($vector3->z);
		$pk->volume = $volume;
		$pk->float = $pitch;

		return $pk;
	}

	public static function createFirework(Vector3 $vector3) : void{
		$x = $z = 0;
		while($x === 0 and $z === 0){
			$x = rand(-1, 1);
			$z = rand(-1, 1);
		}
		$vector3->x += $x * rand(40, 55) / 20;
		$vector3->y += rand(40, 54) / 10;
		$vector3->z += $z * rand(40, 55) / 20;

		$batch = new BatchPacket();
		$pk = new LevelEventPacket;
		$pk->evid = LevelEventPacket::EVENT_ADD_PARTICLE_MASK | Particle::TYPE_DUST;

		$getRandomVector = function() : Vector3{
			$x = rand() / getrandmax() * 2 - 1;
			$y = rand() / getrandmax() * 2 - 1;
			$z = rand() / getrandmax() * 2 - 1;
			$v = new Vector3($x, $y, $z);
			return $v->normalize();
		};

		for($i = 0; $i < 64; ++$i){
			$v3 = $vector3->add($getRandomVector()->multiply(2.7));
			$pk->isEncoded = false;
			$pk->x = $v3->x;
			$pk->y = $v3->y;
			$pk->z = $v3->z;
			$pk->data = 0xff000000 | (rand(0, 255) << 16) | (rand(0, 255) << 8) | rand(0, 255);
			$batch->addPacket($pk);
		}

		foreach(Server::getInstance()->getOnlinePlayers() as $recipient){
			Helper::sendSound($recipient, 'z.f', true, 1);
			Helper::sendSound($recipient, 'mt0', true, 1);
			if($recipient->distanceSquared($vector3) < 256){
				$recipient->dataPacket($batch);
			}
		}
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

	public static function createCroupierNPC(Player $player) : void{
		foreach($player->getLevel()->getEntities() as $entity){
			if($entity instanceof Croupier){
				$entity->close();
			}
		}

		$nbt = Entity::createBaseNBT($player->floor()->add(0.5, 0, 0.5), new Vector3(0, 0, 0),
			round($player->yaw, -1), round($player->pitch, -1)
		);
		$nbt->Health = new ShortTag("Health", 1);
		$nbt->Skin = new CompoundTag("Skin", [
			"Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
			"Name" => new StringTag("Name", $player->getSkin()->getSkinId())
		]);
		$npc = new Croupier($player->getLevel(), $nbt);
		$npc->spawnToAll();
		$npc->saveNBT();
		$player->sendMessage('§7► §aNPC-крупье успешно создан!');
	}

	public static function applyUTFSymbols(string $text) : string{
		$values = ["a" => "а", "e" => "е", "o" => "о", "c" => "с"];
		$secureValues = ["O" => "О", "A" => "А", "H" => "Н", "E" => "Е", "T" => "Т", "X" => "Х", "C" => "С", "B" => "В", "M" => "М", "P" => "Р"];
		$save = [];
		foreach(array_keys($values) as $f){
			$save["§" . $f] = "^" . ord($f) . "^";
		}
		$text = str_replace(array_keys($save), array_values($save), $text);
		$text = str_replace(array_merge(array_keys($values), array_keys($secureValues)), array_merge(array_values($values), array_values($secureValues)), $text);
		return str_replace(array_values($save), array_keys($save), $text);
	}

	public static function toPrettyString(string $word) : string{
		$s = '{"A":"\uff21","B":"\uff22","C":"\uff23","D":"\uff24","E":"\uff25","F":"\uff26",
			"G":"\uff27","H":"\uff28","I":"\uff29",
			"J":"\uff2a","K":"\uff2b","L":"\uff2c","M":"\uff2d",
			"N":"\uff2e","O":"\uff2f","P":"\uff30","Q":"\uff31","R":"\uff32","S":"\uff33",
			"T":"\uff34","U":"\uff35","V":"\uff36","W":"\uff37","X":"\uff38","Y":"\uff39",
			"Z":"\uff3a","a":"\uff41","b":"\uff42","c":"\uff43","d":"\uff44",
			"e":"\uff45","f":"\uff46","g":"\uff47","h":"\uff48","i":"\uff49","j":"\uff4a",
			"k":"\uff4b","l":"\uff4c","m":"\uff4d","n":"\uff4e","o":"\uff4f","p":"\uff50",
			"q":"\uff51","r":"\uff52","s":"\uff53","t":"\uff54","u":"\uff55","v":"\uff56",
			"w":"\uff57","x":"\uff58","y":"\uff59","z":"\uff5a","0":"\uff10","1":"\uff11",
			"2":"\uff12","3":"\uff13","4":"\uff14",
			"5":"\uff15","6":"\uff16","7":"\uff17","8":"\uff18","9":"\uff19"}';

		$s = (array)json_decode($s);

		$step3 = str_split($word);

		$result = "";
		foreach($step3 as $letters){
			if(!isset($s[$letters])){
				$result .= $letters;
				continue;
			}
			$result .= $s[$letters];
		}

		return $result;
	}

	public static function secondsToString(int $seconds) : string{
		$minutes = (int)($seconds / 60);
		$seconds = $seconds - $minutes * 60;
		return $minutes . ":" . ($seconds >= 10 ? $seconds : "0" . $seconds);
	}

	public static function prettySeconds(int $seconds) : string{
		$minutes = (int)($seconds / 60);
		$seconds = $seconds - $minutes * 60;
		$array = [
			Helper::toPlural($minutes, ['минуту', 'минуты', 'минут']) => $minutes,
			Helper::toPlural($seconds, ['секунду', 'секунды', 'секунд']) => $seconds
		];

		$cache = [];
		foreach($array as $key => $value){
			if($value > 0){
				$cache[] = $value . ' ' . $key;
			}
		}
		return implode(' ', $cache);
	}

	public static function toPlural(int $number, array $forms) : string{ //Спасибо Вадиму Соколову (@yexeed) за предоставленный код.
		$cases = [2, 0, 1, 1, 1, 2];
		return $forms[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]] ?? "";
	}
}