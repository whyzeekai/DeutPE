<?php

declare(strict_types=1);

namespace timurkaundefined\casino\entity;

use pocketmine\entity\Animal;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;

class Lightning extends Animal{
	const NETWORK_ID = 93;

	public function getName() : string{
		return "Lightning";
	}

	public function initEntity() : void{
		parent::initEntity();
		$this->setMaxHealth(2);
		$this->setHealth(2);
	}

	public function onUpdate($currentTick) : bool{
		if($this->closed){
			return false;
		}

		if($this->getHealth() <= 0){
			$this->close();
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			return false;
		}

		$this->lastUpdate = $currentTick;

		return $this->entityBaseTick($tickDiff);
	}

	public function entityBaseTick(int $diff = 1) : bool{
		$this->justCreated = false;
		if(++$this->ticksLived >= 20){
			$this->close();
			return false;
		}
		return true;
	}

	public function spawnTo(Player $player) : void{
		if(!isset($this->hasSpawned[$player->getLoaderId()])){
			$this->hasSpawned[$player->getLoaderId()] = $player;

			$this->sendSpawnPacket($player);
		}
	}

	public function sendSpawnPacket(Player $player) : void{
		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = self::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		$player->dataPacket($pk);
	}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	public function canBeCollidedWith() : bool{
		return false;
	}

	public function canSaveWithChunk() : bool{
		return false;
	}

	public function attack(EntityDamageEvent $source) : void{}
}