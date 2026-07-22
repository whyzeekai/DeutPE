<?php

declare(strict_types=1);

namespace api\utils\particle;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\Server;
use function array_map;

class FloatingTextParticle extends Vector3{

	/** @var string */
	protected $title;

	/** @var int */
	protected $entityId;

	/** @var Player[] */
	protected $viewers = [];

	public function __construct(Vector3 $pos, string $title){
		parent::__construct($pos->x, $pos->y, $pos->z);

		$this->title = $title;
		$this->entityId = Entity::$entityCount++;
	}

	public function update(string $title) : void{
		$this->title = $title;

		$pk = new SetEntityDataPacket();
		$pk->eid = $this->entityId;
		$pk->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $title]];
		Server::getInstance()->broadcastPacket($this->viewers, $pk);
	}

	public function spawnTo(Player $player) : void{
		if(!isset($this->viewers[$playerName = $player->getName()])){
			$pk = new AddEntityPacket();
			$pk->type = 39;

			$pk->eid = $this->entityId;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$flags = (
				(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_IMMOBILE) |
				(1 << Entity::DATA_FLAG_SILENT)
			);
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->title],
				Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.0]
			];
			$player->dataPacket($pk);
			$this->viewers[$playerName] = $player;
		}
	}

	public function spawnToAll() : void{
		array_map(function(Player $player) : void{
			$this->spawnTo($player);
		}, Server::getInstance()->getOnlinePlayers());
	}

	public function getTitle() : string{
		return $this->title;
	}

	public function despawnFrom(Player $player) : void{
		unset($this->viewers[$player->getName()]);
	}
}
