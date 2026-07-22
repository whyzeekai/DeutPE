<?php

declare(strict_types=1);

namespace timurkaundefined\auction\utils\tile;

use pocketmine\inventory\DoubleChestInventory;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\tile\Chest;
use timurkaundefined\auction\utils\inventory\PersonalDoubleInventory;

class VirtualChest extends Chest{

	/** @var string */
	private $viewerName;
	/** @var PersonalDoubleInventory|null */
	protected ?DoubleChestInventory $doubleInventory = null;
	/** @var bool */
	private $shouldBeSpawned = false;

	public function __construct(Level $level, CompoundTag $nbt, ?string $viewerName = null){
		// Store viewerName before calling parent constructor
		// If not provided, it will be read from NBT in readSaveData()
		if ($viewerName !== null) {
			// Store it in NBT temporarily so readSaveData() can access it
			$nbt->setString("VirtualChestViewer", $viewerName);
		}
		parent::__construct($level, $nbt);
	}

	protected function readSaveData(CompoundTag $nbt) : void{
		parent::readSaveData($nbt);
		// Read viewerName from NBT (either from constructor parameter or saved data)
		$this->viewerName = $nbt->getString("VirtualChestViewer", "");
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		parent::writeSaveData($nbt);
		// Save viewerName to NBT
		if (!empty($this->viewerName)) {
			$nbt->setString("VirtualChestViewer", $this->viewerName);
		}
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

	public function saveNBT() : CompoundTag{
		// Keep signature compatible with pocketmine\tile\Tile::saveNBT(): CompoundTag
		// and avoid writing any custom data for this virtual tile.
		return parent::saveNBT();
	}
}