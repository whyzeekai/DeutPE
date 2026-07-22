<?php

declare(strict_types=1);

namespace timurkaundefined\casino\tile;

use pocketmine\inventory\DoubleChestInventory;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\tile\Chest;
use timurkaundefined\casino\inventory\PersonalDoubleInventory;

class VirtualCasinoChest extends Chest{

	/** @var string */
	private $viewerName;
	/** @var PersonalDoubleInventory|null */
	protected ?DoubleChestInventory $doubleInventory = null;
	/** @var bool */
	private $shouldBeSpawned = false;

	public function __construct(Level $level, CompoundTag $nbt, string $viewerName){
		$this->viewerName = $viewerName;

		parent::__construct($level, $nbt);
	}

	public function getInventory(){
		return $this->doubleInventory instanceof PersonalDoubleInventory ? $this->doubleInventory : $this->inventory;
	}

	public function setDoubleInventory(PersonalDoubleInventory $inventory){
		$this->doubleInventory = $inventory;
	}

	public function setShouldBeSpawned(){
		$this->shouldBeSpawned = true;
	}

	public function spawnTo(Player $player) : bool{
		if(!$this->shouldBeSpawned or $this->viewerName !== $player->getName()){
			return false;
		}
		return parent::spawnTo($player);
	}

	public function spawnToAll() : void{
		if($this->closed){
			return;
		}

		$player = Server::getInstance()->getPlayerExact($this->viewerName);
		if($player instanceof Player and $player->isOnline()){
			$player->dataPacket($this->createSpawnPacket());
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = new CompoundTag();
		$nbt->setString(\pocketmine\tile\Tile::TAG_ID, static::getSaveId());
		$nbt->setInt(\pocketmine\tile\Tile::TAG_X, $this->x);
		$nbt->setInt(\pocketmine\tile\Tile::TAG_Y, $this->y);
		$nbt->setInt(\pocketmine\tile\Tile::TAG_Z, $this->z);
		// Don't call writeSaveData() to prevent saving additional data
		return $nbt;
	}
}