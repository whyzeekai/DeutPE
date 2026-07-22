<?php

declare(strict_types=1);

namespace timurkaundefined\auction;

use API\Loader;
use pocketmine\block\BlockIds;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Witch;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\Player;
use timurkaundefined\auction\utils\Helper;
use timurkaundefined\auction\utils\inventory\PersonalDoubleInventory;
use function number_format;
use function round;
use function time;

class EventHandler implements Listener{

	/** @var array */
	private $confirmations = [];

	/**
	 * @param InventoryTransactionEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handleInventoryTransaction(InventoryTransactionEvent $event){
		/** @noinspection PhpDeprecationInspection */
		$transaction = $event->getTransaction();
		if(!Auction::getInstance()->isViewingAuction($player = $transaction->getSource())){
			return;
		}
		$event->setCancelled(true);
		/** @var SlotChangeAction $_action */
		foreach($transaction->getActions() as $_action){
			if(!$_action instanceof SlotChangeAction){
				continue;
			}
			$inventory = $_action->getInventory();
			if($inventory instanceof PersonalDoubleInventory){
				/** @var Player $player */
				if($inventory->getViewerName() !== $player->getName()){
					continue;
				}
				$item = $_action->getSourceItem();
				if(!$item->hasCompoundTag()){
					continue;
				}
				$nbt = $item->getNamedTag();
				if(!$nbt->hasTag("auctionWindowItem")){
					continue;
				}


				if($item->getCustomName() == "§r§a§lКатегории\n§r§rНажмите чтобы выбрать категорию предметов!"){
					Auction::getInstance()->openCategories($player, $inventory);
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §bБроня§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'armor');
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §cИнструменты§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'tools');
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §aБлоки§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'blocks');
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §dДругое§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'other');
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §eВсе предметы§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0);
					return;
				}
				if($item->getCustomName() == 
					"§r§7Категория: §aЕда§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'foods');
					return;
				}

				if($item->getCustomName() == 
					"§r§7Категория: §9Зелья§r"
				){
					Auction::getInstance()->openPage($player, $inventory, 0, 'potions');
					return;
				}


				if($nbt->hasTag("page")){
					Auction::getInstance()->openPage($player, $inventory, $nbt->getTag("page")->getValue());
					return;
				}
				if(!$nbt->hasTag("owner") or !$nbt->hasTag("uniqueKey")){
					continue;
				}
				$uniqueKey = $nbt->getTag("uniqueKey")->getValue();
				if(Auction::getInstance()->hasItemInvalidated($uniqueKey)){
					$player->sendMessage("§l§6➛ §rЭтот предмет больше не продаётся!");
					$player->sendTitle("", "§cПредмет не продаётся!", 20, 20, 20);
					return;
				}
				$ownerName = $nbt->getTag("owner")->getValue();
				$currentTime = time();
				if($ownerName === $player->getLowerCaseName()){
					if(($this->confirmations[$player->getName()][$uniqueKey] ?? 0) < $currentTime){
						$this->confirmations[$player->getName()][$uniqueKey] = $currentTime + 8;
						$player->sendPopup("§l§6➛ §rНажми на предмет ещё раз!");
						return;
					}
					$inventory->setItem($_action->getSlot(), new Item(BlockIds::AIR));
					Auction::getInstance()->pullFromTheAuction($player, $uniqueKey);
					unset($this->confirmations[$player->getName()][$uniqueKey]);
					return;
				}
				if($nbt->getTag("expirationDate")->getValue() < Helper::breakTime()){
					$player->sendMessage("§l§6➛ §rЭтот предмет больше не продаётся!");
					$player->sendTitle("", "§cВремя вышло", 20, 20, 20);
					return;
				}
				$price = $nbt->getTag("price")->getValue();
				if(($playerMoney = Loader::getInstance()->getMoney($player)) < $price){
					$notEnough = number_format($price - $playerMoney);
					$player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . "$");
					return;
				}
				if(!$player->getInventory()->canAddItem($item)){
					$player->sendMessage("§l§6➛ §rУ тебя нет места в инвентаре!");
					return;
				}
				if(($this->confirmations[$player->getName()][$uniqueKey] ?? 0) < $currentTime){
					$this->confirmations[$player->getName()][$uniqueKey] = $currentTime + 12;
					$player->sendPopup("§l§6➛ §rНажми ещё раз для покупки! §l§7◄");
					return;
				}
				$inventory->setItem($_action->getSlot(), new Item(BlockIds::AIR));
				Auction::getInstance()->pullFromTheAuction($player, $uniqueKey, true);
				unset($this->confirmations[$player->getName()][$uniqueKey]);
				return;
			}
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @noinspection PhpUnused
	 * @priority MONITOR
	 */
	public function handlePlayerJoin(PlayerJoinEvent $event){
		if(Auction::getInstance()->hasInvalidatedItems($player = $event->getPlayer())){
			$player->sendMessage("§r§7╔ §rС аукциона были сняты §aтвои предметы§r. Вернуть их: §a/auc back");
			$player->sendMessage("§r§7╚ §rНе забудь освободить §aместо в инвентаре!");
		}
	}

	/**
	 * @param PlayerDropItemEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerDropItem(PlayerDropItemEvent $event){
		$item = $event->getItem();
		if(!$item->hasCompoundTag()){
			return;
		}
		if(!$item->getNamedTag()->hasTag("auctionWindowItem")){
			return;
		}
		$event->setCancelled(true);
	}

	/**
	 * @param PlayerItemHeldEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerItemHeld(PlayerItemHeldEvent $event){
		if($event->isCancelled()){
			return;
		}
		$player = $event->getPlayer();
		$item = $event->getItem();
		if(!$item->hasCompoundTag()){
			return;
		}
		if(!$item->getNamedTag()->hasTag("auctionWindowItem")){
			return;
		}
		$player->getInventory()->setItemInHand(new Item(BlockIds::AIR));
	}

	/**
	 * @param PlayerInteractEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$item = $event->getItem();
		if(!$item->hasCompoundTag()){
			return;
		}
		if(!$item->getNamedTag()->hasTag("auctionWindowItem")){
			return;
		}
		$player->getInventory()->setItemInHand(new Item(BlockIds::AIR));
	}

	/**
	 * @param CraftItemEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerCraftItem(CraftItemEvent $event){
		foreach($event->getInput() as $item){
			if(!$item->hasCompoundTag()){
				continue;
			}
			if(!$item->getNamedTag()->hasTag("auctionWindowItem")){
				continue;
			}
			$event->setCancelled(true);
			return;
		}
	}

	/**
	 * @param InventoryCloseEvent $event
	 * @noinspection PhpUnused
	 * @priority NORMAL
	 */
	public function handleInventoryClose(InventoryCloseEvent $event){
		if(!Auction::getInstance()->isViewingAuction($player = $event->getPlayer())){
			return;
		}
		if(!$event->getInventory() instanceof PersonalDoubleInventory){
			return;
		}
		Auction::getInstance()->addToDelayedClose($player);
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @noinspection PhpUnused
	 * @priority LOWEST
	 */
	public function handlePlayerQuit(PlayerQuitEvent $event){
		Auction::getInstance()->removeBlockAndTile($player = $event->getPlayer());
		unset($this->confirmations[$player->getName()]);
	}

	/**
	 * @param EntityDamageEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled false
	 * @priority LOWEST
	 */
	public function handleEntityDamage(EntityDamageEvent $event){
		if(!$event instanceof EntityDamageByEntityEvent){
			return;
		}
		$entity = $event->getEntity();
		if(!$entity instanceof Human or $entity instanceof Player){
			return;
		}
		$attacker = $event->getDamager();
		if(!$attacker instanceof Player){
			return;
		}
		if(!$entity->namedtag->hasTag("auction")){
			return;
		}
		$event->setCancelled(true);
		if(!$attacker->isSurvival()){
			$attacker->sendMessage("§l§6➛ §rВыключите §aкреатив!");
			return;
		}
		Auction::getInstance()->open($attacker);
	}

	/**
	 * @param PlayerChatEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled false
	 * @priority LOWEST
	 */
	public function handlePlayerChat(PlayerChatEvent $event){
		$player = $event->getPlayer();
		if(!$player->isOp()){
			return;
		}
		if($event->getMessage() !== '.setauc'){
			return;
		}
		$event->setCancelled(true);

		foreach($player->getLevel()->getEntities() as $entity){
			if($entity->namedtag->hasTag("auction")){
				$entity->close();
			}
		}

		$nbt = Helper::createBaseNBT($player);

		$nbt->setTag(new ByteTag('auction', 1));

		$npc = Entity::createEntity('Human', $player->getLevel(), $nbt);
		$npc->setNameTagVisible(true);
		$npc->setNameTagAlwaysVisible(true);
		$npc->setNameTag(Auction::NPC_NAMETAG);
		$npc->spawnToAll();

		$player->sendMessage('§f► §bВы успешно создали NPC §eАукциона§b!');
	}

	/**
	 * @param EntitySpawnEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handleEntitySpawn(EntitySpawnEvent $event){
		$entity = $event->getEntity();
		if(!$entity instanceof Human or $entity instanceof Player){
			return;
		}
		if(!$entity->namedtag->hasTag("auction")){
			return;
		}
		$entity->setScale(1.3);
		$entity->getDataPropertyManager()->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, 2);
	}
}