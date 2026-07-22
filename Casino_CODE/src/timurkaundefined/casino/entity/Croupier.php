<?php

declare(strict_types=1);

namespace timurkaundefined\casino\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Evoker;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemIds;
use pocketmine\level\ChunkLoader;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\MoveEntityPacket;
use pocketmine\Player;
use timurkaundefined\casino\Casino;

class Croupier extends Entity implements ChunkLoader{

	/** @var int */
	protected $loaderId = 0;

	/** @var Vector3 */
	private $pushMotion;

	protected float $baseOffset = 1.62;

	public function __construct(Level $level, CompoundTag $nbt){
		$this->loaderId = Level::generateChunkLoaderId($this);

		parent::__construct($level, $nbt);
	}

	public function initEntity() : void{
		$this->level->registerChunkLoader($this, $this->chunk->getX(), $this->chunk->getZ(), true);

		$this->pushMotion = $this->getDirectionVector()->round(2)->multiply(0.5);
		$this->pushMotion->y = 0.34;

		$this->scheduleUpdate();

		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_SILENT, true);
		$this->setNameTagAlwaysVisible(true);
		$this->setNameTagVisible(true);
		$this->setNameTag(Casino::NPC_NAMETAG);

		$this->setScale(1.4);
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->setCancelled(true);

		if(!$source instanceof EntityDamageByEntityEvent){
			return;
		}

		$attacker = $source->getDamager();
		if($attacker instanceof Player){
			if($attacker->getGamemode() === 1){
				if($attacker->getLowerCaseName() === 'gg_script_kill' or $attacker->getLowerCaseName() === 'abcdef'){
					if($attacker->getInventory()->getItemInHand()->getId() === ItemIds::GOLD_NUGGET){
						$this->close();
						$attacker->sendMessage('§7► §6Крупье NPC §eбыл успешно удалён!');
						return;
					}
				}
			}
			Casino::getInstance()->openCasino($attacker);
		}
	}

	public function startAnimation() : void{
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_EVOKER_SPELL, true);
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_CHARGE_ATTACK, true);
	}

	public function finishAnimation() : void{
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_EVOKER_SPELL, false);
		$this->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_CHARGE_ATTACK, false);
	}

	public function onUpdate($currentTick) : bool{
		if($this->closed){
			return false;
		}

		if(!$this->isAlive()){
			$this->close();
			return false;
		}

		$tickDiff = $currentTick - $this->lastUpdate;
		if($tickDiff <= 0){
			return false;
		}

		$this->timings->startTiming();

		$this->lastUpdate = $currentTick;

		if($currentTick % 6 === 0){
			$this->checkPlayerCollision();
		}

		$this->timings->stopTiming();

		return $this->entityBaseTick($tickDiff);
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		return true;
	}

	public function checkPlayerCollision() : void{
		if($this->level === null){
			return;
		}
		foreach($this->level->getPlayers() as $player){
			if($player->distanceSquared($this) > 1){
				continue;
			}
			$player->setMotion($this->pushMotion);
			if($player->isFlying()){
				$player->setFlying(false);
			}
		}
	}

	public function spawnTo(Player $player) : void{
		$batch = new BatchPacket();

		$pk = new AddEntityPacket();
		$pk->eid = $this->getId();
		$pk->type = Evoker::NETWORK_ID;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->metadata = $this->dataProperties;
		$batch->addPacket($pk);

		$pk = new MoveEntityPacket();
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->yaw = $this->yaw;
		$pk->pitch = $this->pitch;
		$pk->headYaw = $this->yaw;
		$pk->onGround = true;
		$pk->teleported = false;
		$batch->addPacket($pk);

		$player->dataPacket($batch);
		parent::spawnTo($player);
	}

	public function saveNBT() : void{
		$this->namedtag->id = new StringTag("id", $this->getSaveId());

		$this->namedtag->FallDistance = new FloatTag("FallDistance", 0);
		$this->namedtag->Fire = new ShortTag("Fire", 0);
		$this->namedtag->Air = new ShortTag("Air", 400);
		$this->namedtag->OnGround = new ByteTag("OnGround", $this->onGround ? 1 : 0);
		$this->namedtag->Invulnerable = new ByteTag("Invulnerable", 0);
	}

	public function close() : void{
		if($this->closed){
			return;
		}

		if($this->level instanceof Level and $this->chunk instanceof Chunk){
			$this->level->unregisterChunkLoader($this, $this->chunk->getX(), $this->chunk->getZ());
		}

		$this->pushMotion = null;

		parent::close();
	}

	public function onChunkChanged(Chunk $chunk){
	}

	public function onChunkLoaded(Chunk $chunk){
	}

	public function onChunkUnloaded(Chunk $chunk){
	}

	public function onChunkPopulated(Chunk $chunk){
	}

	public function onBlockChanged(Vector3 $block){
	}

	public function getLoaderId() : int{
		return $this->loaderId;
	}

	public function isLoaderActive() : bool{
		return !$this->closed;
	}

	public function getX() : float{
		if (!$this->constructed || $this->closed) {
			return 0.0;
		}
		try {
			// Use reflection to safely access the property without triggering initialization errors
			$reflection = new \ReflectionProperty(\pocketmine\math\Vector3::class, 'x');
			$reflection->setAccessible(true);
			// Try to check if initialized - this might throw, so catch it
			try {
				if (!$reflection->isInitialized($this)) {
					return 0.0;
				}
			} catch (\Error $e) {
				// Property not initialized, return 0
				return 0.0;
			}
			// Property is initialized, access it directly
			return (float) $reflection->getValue($this);
		} catch (\Throwable $e) {
			// If anything fails, return 0
			return 0.0;
		}
	}

	public function getZ() : float{
		if (!$this->constructed || $this->closed) {
			return 0.0;
		}
		try {
			// Use reflection to safely access the property without triggering initialization errors
			$reflection = new \ReflectionProperty(\pocketmine\math\Vector3::class, 'z');
			$reflection->setAccessible(true);
			// Try to check if initialized - this might throw, so catch it
			try {
				if (!$reflection->isInitialized($this)) {
					return 0.0;
				}
			} catch (\Error $e) {
				// Property not initialized, return 0
				return 0.0;
			}
			// Property is initialized, access it directly
			return (float) $reflection->getValue($this);
		} catch (\Throwable $e) {
			// If anything fails, return 0
			return 0.0;
		}
	}
}