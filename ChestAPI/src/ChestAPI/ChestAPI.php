<?php

namespace ChestAPI;

use pocketmine\{Player, Server};
use pocketmine\item\Item;
use pocketmine\nbt\tag\{CompoundTag, StringTag, IntTag};
use pocketmine\tile\{Tile, Chest};
use pocketmine\math\Vector3;
use pocketmine\inventory\Inventory;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\scheduler\{CallbackTask, ClosureTask};
use pocketmine\block\Block;

use pocketmine\event\inventory\{InventoryTransactionEvent};

class ChestAPI extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener{

	public static $instance;
	public static $players_in_chest = [];
	public $chests = [];

	const SINGLE_CHEST = 1;
	const DOUBLE_CHEST = 2;

	const SINGLE_CHEST_MAX_SIZE = 26;
	const DOUBLE_CHEST_MAX_SIZE = 53;

	const CHEST_HEIGHT = 5;

	public function onEnable(){
		self::$instance = $this;
		Server::getInstance()->getPluginManager()->registerEvents($this, $this);
	}

	public function openChest(Player $p, array $params, string $name = 'ВанВикс', int $data = ChestAPI::SINGLE_CHEST, $sound = true, $delete = true, string $message = ""){
		if(isset(ChestAPI::$players_in_chest[strtolower($p->getName())])) return false;
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestOpenEvent($p, $params, $name, $data, $message, ChestAPI::getInstance()));
		if($ev->isCancelled()) return false;
		if($sound) $p->getLevel()->addSound(new \pocketmine\level\sound\ClickSound($p));
		// Calculate chest position directly below player, close to player
		$chestX = (int) floor($p->x);
		$chestY = (int) floor($p->y - ChestAPI::CHEST_HEIGHT);
		$chestZ = (int) floor($p->z);
		
		switch($data){
			case ChestAPI::SINGLE_CHEST:
				if(isset($params[ChestAPI::SINGLE_CHEST_MAX_SIZE + 1])) return ChestAPI::sendLog('Максимальное количество слотов в ОДИНАРНОМ сундуке - '.ChestAPI::SINGLE_CHEST_MAX_SIZE.'. Исправьте ошибку!');

				$level = $p->getLevel();
				$blockReplaced = $level->getBlock(new Vector3($chestX, $chestY, $chestZ));

				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockMeta = 0;
				$pk->flags = 0b0000;
				$pk->x = $chestX;
				$pk->y = $chestY;
				$pk->z = $chestZ;
				$p->dataPacket($pk);

				$chest = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $chestX), new IntTag("y", $chestY), new IntTag("z", $chestZ)]));
				if($chest == null) return false;
				$inventory = $chest->getInventory();

				foreach($params as $slot => $item){
					if(strpos($slot, '-') !== false){
						$one = explode('-', $slot)[0];
						$two = explode('-', $slot)[1];
						for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
					}elseif(strpos($slot, '|') !== false){
						$one = explode('|', $slot)[0];
						$two = explode('|', $slot)[1];
						while($one <= $two){
							$inventory->setItem($one, $item);
							$one += 9;
						}
					}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
				}

				$p->sendMessage($message);
				$p->addWindow($inventory);

				ChestAPI::$players_in_chest[strtolower($p->getName())] = [
					'x' => $chest->x,
					'y' => $chest->y,               // сохраняем координаты тайлов в массиве, что бы в дальнейшем удалить честы;
					'z' => $chest->z,
					'inventory' => $inventory,
					'delete' => $delete,
					'data' => ChestAPI::SINGLE_CHEST,
					'blockReplaced' => $blockReplaced,
					'tile' => $chest
				];
			return ['inventory' => $inventory, 'tile' => $chest, 'player' => $p, 'params' => $params, 'data' => $data, 'customname' => $name, 'message' => $message]; // возвращаем массив с переменными для удобства и дальнейшего использования;
			case ChestAPI::DOUBLE_CHEST:
				$direction = $p->getDirection();
				$chest1X = $chestX;
				$chest1Y = $chestY;
				$chest1Z = $chestZ;
				$chest2X = $chestX;
				$chest2Y = $chestY;
				$chest2Z = $chestZ;
				
				if($direction === 2 || $direction === 0){ 
					$chest1X--;
					$chest2X++;
				} elseif($direction === 3 || $direction === 1){
					$chest1Z--;
					$chest2Z++;
				} else {
					$chest1X--;
					$chest2X++;
				}
				
				$level = $p->getLevel();
				$blockReplaced = $level->getBlock(new Vector3($chest1X, $chest1Y, $chest1Z));
				$blockReplaced2 = $level->getBlock(new Vector3($chest2X, $chest2Y, $chest2Z));
				
				$chest_1 = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $chest1X), new IntTag("y", $chest1Y), new IntTag("z", $chest1Z)]));
				$chest_2 = Tile::createTile("Chest", $p->getLevel(), new CompoundTag("", [new StringTag("id", Tile::CHEST), new StringTag("CustomName", $name), new IntTag("x", $chest2X), new IntTag("y", $chest2Y), new IntTag("z", $chest2Z)]));

				$chest_1->pairWith($chest_2); // пейрим сундуки между собой;
				$chest_2->pairWith($chest_1);
				if($chest_1 == null or $chest_2 == null) return false;
				$inventory = $chest_1->getInventory(); // получаем двойной инвентарь;

				ChestAPI::$players_in_chest[strtolower($p->getName())] = [
					'x' => $chest_1->x,
					'y' => $chest_1->y,
					'z' => $chest_1->z,
					'inventory' => $inventory,
					'delete' => $delete,
					'data' => ChestAPI::DOUBLE_CHEST, // сохраняем координаты тайлов в массиве, что бы в дальнейшем удалить честы;
					'blockReplaced' => $blockReplaced,
					'blockReplaced2' => $blockReplaced2,
					'tile' => $chest_1,
					'tile2' => $chest_2
				];

				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockMeta = 0;
				$pk->flags = 0b0000;
				$pk->x = $chest1X;
				$pk->y = $chest1Y;
				$pk->z = $chest1Z;
				$p->dataPacket($pk);
				 								// отправляем игроку блоки сундуков;
				$pk = new UpdateBlockPacket();
				$pk->blockId = 54;
				$pk->blockMeta = 0;
				$pk->flags = 0b0000;
				$pk->x = $chest2X;
				$pk->y = $chest2Y;
				$pk->z = $chest2Z;
				$p->dataPacket($pk);


				foreach($params as $slot => $item){
					if(strpos($slot, '-') !== false){
						$one = explode('-', $slot)[0];
						$two = explode('-', $slot)[1];
						for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
					}elseif(strpos($slot, '|') !== false){
						$one = explode('|', $slot)[0];
						$two = explode('|', $slot)[1];
						while($one <= $two){
							$inventory->setItem($one, $item);
							$one += 9;
						}
					}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
				}

				$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openDoubleInventory"], [$p, $inventory, $chest_1, $delete]), 4); // открытие двойного сундука, без таска не сработает! Если поставить задержку меньше 3 тиков, тоже не сработает.

			return ['inventory' => $inventory, 'tile_1' => $chest_1, 'tile_2' => $chest_2, 'player' => $p, 'params' => $params, 'data' => $data, 'customname' => $name, 'message' => $message]; // возвращаем массив с переменными для удобства и дальнейшего использования;
		}
	}
	public function deleteChest(Player $p) : bool{
		if(!isset(ChestAPI::$players_in_chest[strtolower($p->getName())])) return false;
		$pos = ChestAPI::$players_in_chest[strtolower($p->getName())];
		// Remove player from array BEFORE closing chest to prevent infinite recursion
		unset(ChestAPI::$players_in_chest[strtolower($p->getName())]);
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestDeleteEvent($p, $pos, ChestAPI::getInstance()));
		if($ev->isCancelled()) {
			// Restore entry if cancelled
			ChestAPI::$players_in_chest[strtolower($p->getName())] = $pos;
			return false;
		}
		if(($tile = $p->getLevel()->getTile(new Vector3($pos['x'], $pos['y'], $pos['z']))) instanceof Chest){
			switch($pos['data']){
				case ChestAPI::SINGLE_CHEST:

					// удаляем тайл;
					$tile->close();

					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($pos['x'], $pos['y'], $pos['z']))->getId();
					$pk->blockMeta = $p->getLevel()->getBlock(new Vector3($pos['x'], $pos['y'], $pos['z']))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $pos['x']; 				// отправляем игроку блоки, которые были на месте фейк сундуков;
					$pk->y = $pos['y'];
					$pk->z = $pos['z'];
					$p->dataPacket($pk);

				break;
				case ChestAPI::DOUBLE_CHEST:
					$chest_2 = $tile->getPair(); 		// получаем второй тайл;

					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($tile->x, $tile->y, $tile->z))->getId();
					$pk->blockMeta = $p->getLevel()->getBlock(new Vector3($tile->x, $tile->y, $tile->z))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $tile->x;
					$pk->y = $tile->y;
					$pk->z = $tile->z;
					$p->dataPacket($pk);
														// отправляем игроку блоки, которые были на месте фейк сундуков;
					$pk = new UpdateBlockPacket();
					$pk->blockId = $p->getLevel()->getBlock(new Vector3($chest_2->x, $chest_2->y, $chest_2->z))->getId();
					$pk->blockMeta = $p->getLevel()->getBlock(new Vector3($chest_2->x, $chest_2->y, $chest_2->z))->getDamage();
					$pk->flags = 0b0000;
					$pk->x = $chest_2->x;
					$pk->y = $chest_2->y;
					$pk->z = $chest_2->z;
					$p->dataPacket($pk);

					// удаляем тайлы;

					$tile->close();
					$chest_2->close();
				break;
			}
		}
		return true;
	}
	
	/**
	 * Удалить блоки и тайлы (восстановить оригинальные блоки)
	 * @param Player $player
	 */
	public function removeBlockAndTile(Player $player = null): void{
		if(!$player instanceof Player || !$player->isOnline()){
			return;
		}
		$playerName = strtolower($player->getName());
		if(!isset(ChestAPI::$players_in_chest[$playerName])){
			return;
		}
		
		$pos = ChestAPI::$players_in_chest[$playerName];
		$level = $player->getLevel();
		switch($pos['data']){
			case ChestAPI::SINGLE_CHEST:
				$tile = $pos['tile'] ?? null;
				if($tile instanceof Chest){
					try {
						// Закрываем тайл перед удалением
						if(!$tile->closed){
							$tile->close();
						}
						$tileLevel = $tile->getLevel();
						if($tileLevel !== null){
							$tileLevel->removeTile($tile);
						}
					} catch (\Exception $e) {
						// Tile already removed, skip
					}
				}
				break;
				
			case ChestAPI::DOUBLE_CHEST:
				$tile = $pos['tile'] ?? null;
				$tile2 = $pos['tile2'] ?? null;
				
				if($tile instanceof Chest){
					try {
						// Закрываем тайл перед удалением
						if(!$tile->closed){
							$tile->close();
						}
						$tileLevel = $tile->getLevel();
						if($tileLevel !== null){
							$tileLevel->removeTile($tile);
						}
					} catch (\Exception $e) {
						// Tile already removed, skip
					}
				}
				if($tile2 instanceof Chest){
					try {
						// Закрываем тайл перед удалением
						if(!$tile2->closed){
							$tile2->close();
						}
						$tileLevel = $tile2->getLevel();
						if($tileLevel !== null){
							$tileLevel->removeTile($tile2);
						}
					} catch (\Exception $e) {
						// Tile already removed, skip
					}
				}
				break;
		}
		
		if($player instanceof Player && $player->isOnline()){
    	    $player->getInventory()->sendContents($player);
    	    $player->getArmorInventory()->sendContents($player);
				
    	    $player->getInventory()->sendHeldItem($player);
    	}

    	unset(ChestAPI::$players_in_chest[strtolower($player->getName())]);
	}
	public function getItemsNames(Inventory $inventory) : array{
		$custom = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0){
				if($item->hasCustomName()) $custom[] = $item->getCustomName();
				else $custom[] = $item->getName();
			}
		}
		return $custom;
	}
	public function getItems(Inventory $inventory) : array{
		$items = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $items[] = $item;
		}
		return $items;
	}
	public function getItemsIds(Inventory $inventory) : array{
		$ids = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $ids[] = $item->getId();
		}
		return $ids;
	}
	public function getItemsDamages(Inventory $inventory) : array{
		$damages = [];
		for($i = 0; $i <= $inventory->getSize(); ++$i){
			$item = $inventory->getItem($i);
			if($item instanceof Item and $item->getId() !== 0) $damages[] = $item->getDamage();
		}
		return $damages;
	}
	public function setInventory(Inventory $inventory, array $params){
		foreach($params as $slot => $item){
			if(strpos($slot, '-') !== false){
				$one = explode('-', $slot)[0];
				$two = explode('-', $slot)[1];
				for($i = $one; $i <= $two; ++$i) $inventory->setItem($i, $item);
			}elseif(strpos($slot, '|') !== false){
				$one = explode('|', $slot)[0];
				$two = explode('|', $slot)[1];
				while($one <= $two){
					$inventory->setItem($one, $item);
					$one += 9;
				}
			}else $inventory->setItem($slot, $item); // перебираем массив и расставляем вещи в инвентаре;
		}
	}
	public function handlePlayerDropItem(\pocketmine\event\player\PlayerDropItemEvent $e){
		if(!isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])) return;
		Server::getInstance()->getPluginManager()->callEvent($ev = new ChestDropEvent($e->getPlayer(), $e->getItem(), $e->getEntity(), ChestAPI::getInstance()));
		if($ev->isCancelled()) $e->setCancelled();
	}
	public function handleInventoryClose(\pocketmine\event\inventory\InventoryCloseEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			$player = $e->getPlayer();
			// Вызываем метод отложенного закрытия
			$this->addToDelayedClose($player);
		}
	}
	
	/**
	 * Отложить удаление блоков и тайлов на 20 тиков
	 * @param Player $player
	 */
	public function addToDelayedClose(Player $player): void{
		$playerName = strtolower($player->getName());
		
		if(!isset(ChestAPI::$players_in_chest[$playerName])){
			return;
		}
		
		if(!$this->isEnabled()){
			$this->removeBlockAndTile($player);
			return;
		}
		
		$playerNameExact = $player->getName();
		
		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($playerNameExact, $playerName): void{
			if(!$this->isEnabled()){
				return;
			}
			$player = Server::getInstance()->getPlayerExact($playerNameExact);
			if($player !== null && $player->isOnline() && isset(ChestAPI::$players_in_chest[$playerName])){
				// Удаляем блоки и тайлы
				$this->removeBlockAndTile($player);
				
				// Вызываем событие закрытия
				Server::getInstance()->getPluginManager()->callEvent($ev = new ChestCloseEvent($player, ChestAPI::getInstance()));
			}
		}), 5);
	}
	/*public function handleInventoryClick(\pocketmine\event\inventory\InventoryClickEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			Server::getInstance()->getPluginManager()->callEvent($ev = new ChestClickEvent($e->getPlayer(), $e->getItem(), $e->getSlot(), $e->getInventory(), ChestAPI::getInstance()));
			if($ev->isCancelled()) $e->setCancelled();
		}
	}
	*/

	public function onInventoryTransaction(\pocketmine\event\inventory\InventoryTransactionEvent $event) {
		$transaction = $event->getTransaction();
		$player = $transaction->getSource(); // Получаем игрока из транзакции

		if (isset(ChestAPI::$players_in_chest[strtolower($player->getName())])) {
			$chestData = ChestAPI::$players_in_chest[strtolower($player->getName())];
			$chestInventory = $chestData['inventory'] ?? null;
			
			if ($chestInventory === null) {
				return;
			}
			
			// Получаем инвентари из действий транзакции
			$hasChestAPIInventory = false;
			foreach ($transaction->getActions() as $action) {
				if ($action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
					$inventory = $action->getInventory();
					// Если это инвентарь из ChestAPI - разрешаем транзакцию
					if ($inventory === $chestInventory) {
						$hasChestAPIInventory = true;
						break;
					}
				}
			}
			
			// Если транзакция не связана с инвентарем ChestAPI, но игрок в ChestAPI сундуке
			// и это взаимодействие с другим сундуком - блокируем
			if (!$hasChestAPIInventory) {
				foreach ($transaction->getActions() as $action) {
					if ($action instanceof \pocketmine\inventory\transaction\action\SlotChangeAction) {
						$inventory = $action->getInventory();
						// Блокируем взаимодействие с другими сундуками, пока открыт ChestAPI сундук
						if ($inventory instanceof \pocketmine\inventory\ChestInventory || 
							$inventory instanceof \pocketmine\inventory\DoubleChestInventory) {
							$event->setCancelled(true);
							return;
						}
					}
				}
			}
		}
	}

	public function handlePlayerQuit(\pocketmine\event\player\PlayerQuitEvent $e){
		if(isset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())])){
			$shouldDelete = ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]['delete'];
			// Remove from array first to prevent recursion
			unset(ChestAPI::$players_in_chest[strtolower($e->getPlayer()->getName())]);
			if($shouldDelete) {
				ChestAPI::getInstance()->deleteChest($e->getPlayer());
			}
			Server::getInstance()->getPluginManager()->callEvent($ev = new ChestCloseEvent($e->getPlayer(), ChestAPI::getInstance()));
		}
	}
	public function closeInventory(Player $p){
    $name = strtolower($p->getName());
    if(isset(self::$players_in_chest[$name])){
        $inventory = self::$players_in_chest[$name]['inventory'] ?? null;
        if($inventory !== null){
            $this->removeBlockAndTile($p); 
            $p->removeWindow($inventory);
        }
    }
}
	public function openDoubleInventory(Player $p, $inventory, $chest_1, $delete){
		if(!$p instanceof Player or !$inventory instanceof \pocketmine\inventory\DoubleChestInventory) return;
		$p->addWindow($inventory);
	}
	public static function sendLog(string $message){
		Server::getInstance()->getLogger()->critical('[ChestAPI] '.$message);
	}
	public static function getInstance() : ChestAPI{
		return self::$instance;
	}
}