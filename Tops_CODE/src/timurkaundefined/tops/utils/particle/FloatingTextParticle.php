<?php

declare(strict_types=1);

namespace timurkaundefined\tops\utils\particle;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
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

		$pk = new SetActorDataPacket();
		$pk->entityRuntimeId = $this->entityId;
		$pk->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $title]];
		$pk->syncedProperties = new PropertySyncData([], []);
		Server::getInstance()->broadcastPacket($this->viewers, $pk);
	}

	public function spawnTo(Player $player) : void{
		if(!isset($this->viewers[$playerName = $player->getName()])){
			$pk = new AddActorPacket();
			$pk->entityUniqueId = $this->entityId;
			$pk->entityRuntimeId = $this->entityId;
			$pk->type = EntityIds::NPC;
			$pk->position = new Vector3($this->x, $this->y, $this->z);
			$pk->motion = new Vector3(0, 0, 0);
			$pk->pitch = 0.0;
			$pk->yaw = 0.0;
			$pk->headYaw = 0.0;
			$pk->bodyYaw = 0.0;
			$pk->attributes = [];
			$flags = (
				(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_IMMOBILE) |
				(1 << Entity::DATA_FLAG_SILENT)
			);
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->title],
				Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01], //zero causes problems on debug builds
				Entity::DATA_BOUNDING_BOX_WIDTH => [Entity::DATA_TYPE_FLOAT, 0.0],
				Entity::DATA_BOUNDING_BOX_HEIGHT => [Entity::DATA_TYPE_FLOAT, 0.0]
			];
			$pk->syncedProperties = new PropertySyncData([], []);
			$pk->links = [];
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
