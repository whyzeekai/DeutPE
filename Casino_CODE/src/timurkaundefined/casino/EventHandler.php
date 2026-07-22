<?php

declare(strict_types=1);

namespace timurkaundefined\casino;

use API\Loader;

use _64FF00\PurePerms\PurePerms;
use pocketmine\entity\Projectile;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\ItemFrameDropItemEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\inventory\InventoryPickupArrowEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\inventory\PlayerInventory;
use pocketmine\Server;
use timurkaundefined\casino\particle\FloatingTextAPI;
use pocketmine\block\BlockIds;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Item;
use pocketmine\Player;
use timurkaundefined\casino\entity\Croupier;
use timurkaundefined\casino\inventory\PersonalDoubleInventory;
use timurkaundefined\casino\utils\Helper;
use function strtolower;
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
		if(!Casino::getInstance()->isViewingCasino($player = $transaction->getSource())){
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
				$slot = $_action->getSlot();
				if(!$item->hasCompoundTag()){
					return;
				}
				$nbt = $item->getNamedTag();
				if(!$nbt->hasTag("casinoItem")){
					return;
				}
				if($nbt->hasTag("quit")){
					if(Casino::getInstance()->getPlayerBet($player) === null){
						$player->sendMessage('§7► §cТы не участвуещь в розыгрыше!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					if(Casino::getInstance()->isRunning()){
						$player->sendMessage('§7► §cРозыгрыш уже начался!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					Helper::sendSound($player, 'random.click', true, 1.0, 0.8);
					$currentTime = time();
					if(($this->confirmations[$player->getName()][$slot] ?? 0) < $currentTime){
						$this->confirmations[$player->getName()][$slot] = $currentTime + 4;
						$player->sendMessage("§aНажми ещё раз для §cвыхода §aиз розыгрыша!");
						return;
					}
					unset($this->confirmations[$player->getName()][$slot]);
					Casino::getInstance()->removePlayer($player);
					$player->sendMessage("§7► §aТы покинул(-а) розыгрыш!");
				}elseif($nbt->hasTag("bet")){
					if(Casino::getInstance()->isRunning()){
						$player->sendMessage('§7► §cРозыгрыш уже начался!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					$bet = $nbt->getTag("bet")->getValue();
					if(Casino::getInstance()->isFull() and Casino::getInstance()->getPlayerBet($player) === null){
						$player->sendMessage('§7► §eСейчас участвует максимальное число игроков!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					if(Loader::getInstance()->getMoney($player) < $bet){
						$player->sendMessage('§7► §cНедостаточно денег чтобы сделать эту ставку!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					if(Casino::getInstance()->getPlayerBet($player) === $bet){
						$player->sendMessage('§7► §cТы уже выбрал(-а) эту ставку! Если желаешь, можно выбрать §6другую§c!');
						Helper::sendSound($player, 'note.bass', true);
						return;
					}
					$currentTime = time();
					if(($this->confirmations[$player->getName()][$slot] ?? 0) < $currentTime){
						$this->confirmations[$player->getName()][$slot] = $currentTime + 4;
						$player->sendMessage("§aНажми ещё раз чтобы сделать ставку!");
						Helper::sendSound($player, 'random.click', true, 1.0, 0.8);
						return;
					}
					unset($this->confirmations[$player->getName()][$slot]);
					Casino::getInstance()->addPlayer($player, $bet);
				}else{
					Helper::sendSound($player, 'random.click', true, 1.0, 0.8);
				}
				return;
			}
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @noinspection PhpUnused
	 * @priority LOWEST
	 */
	public function handlePlayerJoin(PlayerJoinEvent $event){
		/** @var PurePerms|null $purePerms */
		$purePerms = Server::getInstance()->getPluginManager()->getPlugin('PurePerms');
		if($purePerms !== null){
			$player = $event->getPlayer();
			/** @noinspection PhpDeprecationInspection */
			Casino::getInstance()->getServer()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player, $purePerms) : void{
				if(!$player instanceof Player or !$player->isOnline()){
					return;
				}
				$purePerms->getUserDataMgr()->setUserPermission($player, 'bet.command');
			}), 10);
		}
		if(!Casino::getInstance()->hasPlayers()){
			return;
		}
		FloatingTextAPI::spawnToOne($event->getPlayer());
	}

	/**
	 * @param PlayerQuitEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		Casino::getInstance()->removeBlockAndTile($player);
		Casino::getInstance()->removePlayer($player);
		Casino::getInstance()->weakShowPlayer($player);
		FloatingTextAPI::despawnFromOne($player);

		unset($this->confirmations[$player->getName()]);
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
		$nbt = $item->getNamedTag();
		if(!$nbt->hasTag("dirtyItem", \pocketmine\nbt\tag\ByteTag::class)){
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
		$nbt = $item->getNamedTag();
		if(!$nbt->hasTag("dirtyItem", \pocketmine\nbt\tag\ByteTag::class)){
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
		if(!$item->getNamedTag()->hasTag("casinoItem")){
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
			$nbt = $item->getNamedTag();
			if(!$nbt->hasTag("dirtyItem", \pocketmine\nbt\tag\ByteTag::class)){
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
		if(!Casino::getInstance()->isViewingCasino($player = $event->getPlayer())){
			return;
		}
		if(!$event->getInventory() instanceof PersonalDoubleInventory){
			return;
		}
		Casino::getInstance()->addToDelayedClose($player);
	}

	/**
	 * @param EntitySpawnEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handleEntitySpawn(EntitySpawnEvent $event){
		$entity = $event->getEntity();
		if(!$entity instanceof Croupier){
			return;
		}
		Casino::getInstance()->storeCroupierThings($entity);
	}

	/**
	 * @param AddMoneyEvent $event
	 * @noinspection PhpUnused
	 * @priority LOWEST
	 */

	/**
	 * @param PlayerChatEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerChat(PlayerChatEvent $event){
		$player = $event->getPlayer();
		if(!$player->isOp() and $player->getLowerCaseName() !== 'Wassabi' and $player->getLowerCaseName() !== 'gg_script_kill'){
			return;
		}
		if($event->getMessage() !== '.casinonpc'){
			return;
		}
		$event->setCancelled(true);
		Helper::createCroupierNPC($player);
	}

	/** @noinspection PhpUnused */
	public function onTeleport(EntityTeleportEvent $event){
		if($event->isCancelled()){
			return;
		}
		$player = $event->getEntity();
		if(!$player instanceof Player){
			return;
		}
		if(Casino::getInstance()->isInCasinoGame($player)){
			$event->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onPickupItem(InventoryPickupItemEvent $event){
		$inventory = $event->getInventory();
		if(!$inventory instanceof PlayerInventory){
			return;
		}
		$player = $inventory->getHolder();
		if(!$player instanceof Player){
			return;
		}
		if(Casino::getInstance()->isInCasinoGame($player)){
			$event->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onPickupArrow(InventoryPickupArrowEvent $event){
		$inventory = $event->getInventory();
		if(!$inventory instanceof PlayerInventory){
			return;
		}
		$player = $inventory->getHolder();
		if(!$player instanceof Player){
			return;
		}
		if(Casino::getInstance()->isInCasinoGame($player)){
			$event->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onCraftItem(CraftItemEvent $event){
		$player = $event->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя крафтить, пока ты идёт розыгрыш!');
			$event->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onItemConsume(PlayerItemConsumeEvent $event){
		$player = $event->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя кушать, пока идёт розыгрыш денег!');
			$event->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onTransaction(InventoryTransactionEvent $event){
		$transaction = $event->getTransaction();
		$player = $transaction->getSource();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$event->setCancelled(true);
			$player->sendPopup('§7► §cНельзя перемещать предметы во время розыгрыша! §7◄');
		}
	}

	/** @noinspection PhpUnused */
	public function onProjectileLaunch(ProjectileLaunchEvent $event){
		$entity = $event->getEntity();
		if(!$entity instanceof Projectile){
			return;
		}
		$player = $entity->getOwningEntity();
		if(!$player instanceof Player){
			return;
		}
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя взаимодействовать с сервером, пока идёт розыгрыш!');
			$event->setCancelled(true);
		}
	}

	/**
	 * @param ItemFrameDropItemEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function onFrame(ItemFrameDropItemEvent $event){
		$player = $event->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя взаимодействовать с рамками, пока идёт розыгрыш!');
			$event->setCancelled(true);
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function onDamage(EntityDamageEvent $event){
		$player = $event->getEntity();

		if($player instanceof Player){
			if($event instanceof EntityDamageByEntityEvent){
				$damager = $event->getDamager();
				if($damager instanceof Player){
					if(Casino::getInstance()->isInCasinoGame($damager)){
						$damager->sendMessage('§7► §cТы не можешь атаковать пока идёт розыгрыш!');
						$event->setCancelled(true);
						return;
					}
				}
			}

			if(Casino::getInstance()->isInCasinoGame($player)){
				if($event instanceof EntityDamageByEntityEvent){
					$damager = $event->getDamager();
					if($damager instanceof Player){
						$damager->sendMessage('§7► §cИгрока нельзя атаковать т.к. он участвует в розыгрыше!');
					}
				}
				$event->setCancelled(true);
			}
		}
	}

	/** @noinspection PhpUnused */
	public function onDrop(PlayerDropItemEvent $e){
		$player = $e->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя выкидывать предметы, пока идёт розыгрыш!');
			$e->setCancelled(true);
		}
	}

	/** @noinspection PhpUnused */
	public function onBreak(BlockBreakEvent $e){
		$player = $e->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя взаимодействовать с сервером, идёт розыгрыш!');
			$e->setCancelled(true);
		}
	}

	/**
	 * @param PlayerInteractEvent $e
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function onTap(PlayerInteractEvent $e){
		$player = $e->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendPopup('§7► §cНельзя взаимодействовать с сервером, пока идёт розыгрыш!');
			$e->setCancelled(true);
		}
	}

	/**
	 * @param PlayerGameModeChangeEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerGameModeChange(PlayerGameModeChangeEvent $event){
		$player = $event->getPlayer();
		if(Casino::getInstance()->isInCasinoGame($player)){
			$player->sendMessage('§7► §cНельзя менять режим игры пока идёт розыгрыш!');
			$event->setCancelled(true);
		}
	}

	/**
	 * @param PlayerCommandPreprocessEvent $event
	 * @noinspection PhpUnused
	 * @ignoreCancelled true
	 * @priority LOWEST
	 */
	public function handlePlayerCommandPreprocess(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer();
		if(!Casino::getInstance()->isInCasinoGame($player)){
			return;
		}

		if(substr($event->getMessage(), 0, 1) !== '/'){
			return;
		}

		$event->setCancelled(true);
		$player->sendMessage('§7► §cВо время розыгрыша нельзя использовать команды!');
	}
}