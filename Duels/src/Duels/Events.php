<?php

namespace Duels;

use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerJoinEvent, PlayerQuitEvent, PlayerInteractEvent, PlayerMoveEvent, PlayerDropItemEvent};
use pocketmine\event\entity\{EntityDamageEvent, EntityDamageByEntityEvent};
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use pocketmine\Server;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;
use pocketmine\event\inventory\{InventoryPickupItemEvent};
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;


class Events implements Listener
{
	/** @var Main */
	private $pg;

	public function __construct(Main $pg){
		$this->pg = $pg;
	}

	public function onPlayerPickupItem(InventoryPickupItemEvent $event) {
		$item = $event->getItem()->getItem();
		if ($item->hasCustomName() && $item->getCustomName() === "§l§aБОНУС") {
			$player = $event->getPlayer();
			$event->setCancelled(); 
			$itemEntity = $event->getItem();
			$itemEntity->close(); 

			$chance = mt_rand(1, 100);
			if ($chance <= 50) {
				$effectType = rand(0, 1) ? Effect::SPEED : Effect::STRENGTH; 
				$duration = 20 * 10; // 10 секунд
				$effect = new EffectInstance(Effect::getEffect($effectType), $duration, 0, false);
				$player->addEffect($effect);
				$player->sendMessage("§aВы получили эффект!");
			} else {
				$player->sendMessage("§cУлучшение не активировалось :(");
			}
		}
	}

	public function onMove(PlayerMoveEvent $event){
	   		if($event->getPlayer()->getLevel()->getFolderName() == "duels") { 
	   			if($event->getPlayer()->getY() < 50) {
	   				$event->getPlayer()->teleport(new Vector3(3000, 65, 3000));
	   			}
	}
}

	public function onQuit(PlayerQuitEvent $ev){
	   foreach($this->pg->arenas as $arena){
			if($arena->inArena($ev->getPlayer()) == 1){
				$arena->removePlayer($ev->getPlayer(), "quit");
			}
		}
	}

	public function onJoin(PlayerJoinEvent $event) { 
        if($event->getPlayer()->getLevel()->getFolderName() == "duels") { 
        	$event->getPlayer()->teleport($this->pg->getServer()->getLevelByName("spawn")->getSafeSpawn());
			$event->getPlayer()->teleport(new Position(9.5166, 35, -29.7749, Server::getInstance()->getLevelByName("spawn")));
			$event->getPlayer()->getInventory()->clearAll();
        } 
    }

	public function onClick(PlayerInteractEvent $ev) {
		if ($ev->getItem()->getCustomName() == "§r§l§8[§l§cВЫЙТИ§8]") {
        $player = $ev->getPlayer();
        foreach ($this->pg->arenas as $arena) {
            if ($arena->inArena($player) == 1) {
                if ($arena->status == Arena::STATUS_WAITING) {
                    $arena->removePlayer($player, "quit");
                    $player->sendMessage("§l§eＤＵＥＬＳ §8:: §r§aВы вышли из дуэли.");
                } elseif ($arena->status == Arena::STATUS_GAME) {
                    $player->sendMessage("§l§eＤＵＥＬＳ §8:: §r§cНельзя, сейчас у вас идет бой!");
                }
                $ev->setCancelled();
                return; 
            } else {
                $player->getInventory()->setItemInHand(Item::get(0, 0));
            }
        }
    }

    if ($ev->getItem()->getCustomName() == "§r§l§eВЫБОР НАБОРА") {
        $player = $ev->getPlayer();
        $chest = ChestAPI::getInstance()->openChest($player, [
        	"0-53" => Item::get(102),
            20 => Item::get(278, 0, 1)->setCustomName("§r§dВыбрать набор§7: §aDefault§r\n\n§7Нажмите, чтобы выбрать!"),
            21 => Item::get(278, 0, 1)->setCustomName("§r§dВыбрать набор§7: §aCombo§r\n\n§7Нажмите, чтобы выбрать!"),
            23 => Item::get(278, 0, 1)->setCustomName("§r§dСлучайный набор§r\n\n§7Нажмите, чтобы выбрать!")
        ], "§8§lВыбор Кита", ChestAPI::DOUBLE_CHEST);
    }
}


	public function onDrop(PlayerDropItemEvent $ev){
		if($ev->getItem()->getCustomName() == "§r§l§8[§l§cВЫЙТИ§8]"){
			foreach($this->pg->arenas as $arena){
				if($arena->inArena($ev->getPlayer()) == 1){
					$ev->setCancelled();
			}
		}
	}
}

	public function onDamage(EntityDamageEvent $ev){
		$e = $ev->getEntity();
		if($e instanceof Player){
			$cause = $e->getLastDamageCause();
			if($cause instanceof EntityDamageByEntityEvent){
				$d = $cause->getDamager();
				if($d instanceof Player){
					foreach($this->pg->arenas as $arena){
						if($arena->inArena($e) == 1 && $arena->inArena($d) == 1){
							if($arena->status != Arena::STATUS_GAME){
								$ev->setCancelled();
							}else{
								if(($e->getHealth() - $ev->getFinalDamage()) <= 2){
									if($e->getOffHandInventory()->getItemInOffHand()->getId() == 450 or $e->getInventory()->getItemInHand()->getId() == 450) return;
									$ev->setCancelled();
									$arena->win($d, $e);
								}
							}
						}
					}
				}
			 }else{
				foreach($this->pg->arenas as $arena){
					if($arena->inArena($e) == 1){
						if($arena->status != Arena::STATUS_GAME){
							$ev->setCancelled();
						}else{
							if(($e->getHealth() - $ev->getFinalDamage()) <= 2){
								if($e->getOffHandInventory()->getItemInOffHand()->getId() == 450) return;
								$ev->setCancelled();
								$opponent = ($arena->players[0] == strtolower($e->getName())) ? $arena->players[1] : $arena->players[0];
								$o = $this->pg->getServer()->getPlayer($opponent);
								$arena->win($o, $e);
							}
						}
					}
				}
			}
			if($ev->getCause() == EntityDamageEvent::CAUSE_VOID){
				foreach($this->pg->arenas as $arena){
					if($arena->inArena($e) == 1){
						if($arena->status != Arena::STATUS_GAME){
							$ev->setCancelled();
							 $e->teleport(new Position(2990, 81, 3000, $this->pg->getServer()->getLevelByName("duels")));
						}else{
							if($e->getOffHandInventory()->getItemInOffHand()->getId() == 450) return;
							$opponent = ($arena->players[0] == strtolower($e->getName())) ? $arena->players[1] : $arena->players[0];
							$o = $this->pg->getServer()->getPlayer($opponent);
							$ev->setCancelled();
							$arena->win($o, $e);
						}
					}
				}
			}
		}
	}

	public function PlayerCommandPreprocessEvent(\pocketmine\event\player\PlayerCommandPreprocessEvent $event) { 
		$player = $event->getPlayer(); 
		$command = $event->getMessage(); 

		foreach($this->pg->arenas as $arena) {
			if($arena->inArena($player) == 1) {
				if(strpos($command, '/') === 0) {
					$player->sendMessage("§l§cＤＵＥＬＳ §8:: §r§cВо время дуэли запрещено использовать команды!");
					$event->setCancelled(); 
					return false; 
				}
            } 
        }
    }
}
