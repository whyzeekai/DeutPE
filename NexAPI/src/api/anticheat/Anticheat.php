<?php

declare(strict_types=1);

namespace api\anticheat;

use api\Loader;
use DateTime;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\item\Item;
use pocketmine\item\ItemIds;

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\{PlayerMoveEvent};
use pocketmine\event\block\BlockBreakEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;

class Anticheat implements Listener {

    /** @var Loader */
    private $loader;
    
    /** @var array */
    private $lastHitTime = [];
    
    /** @var array */
    private $hitCount = [];

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    private function getPlayerPing(Player $player): int {
        return $player->getPing();
    }

    private function notifyHelpers(Player $player, string $cheatType) {
        $message = "§l§cАНТИ§7-§cЧИТ §8:: §rИгрок §c{$player->getName()} §rподозревается в §7({$cheatType})§r!";

        foreach (Server::getInstance()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("helper.system")) {
                $onlinePlayer->sendMessage($message);
            }
        }
    }

    public function onEntityDamage2(EntityDamageEvent $event){
        if($event instanceof EntityDamageByEntityEvent){
            $damager = $event->getDamager();

            if($damager instanceof Player){
                $itemInHand = $damager->getInventory()->getItemInHand();

                $excludedItems = [
                    ItemIds::BOW,
                    ItemIds::ENDER_PEARL,
                    ItemIds::SNOWBALL 
                ];

                if(in_array($itemInHand->getId(), $excludedItems)){
                    return; 
                }

                $entity = $event->getEntity();

                if($entity instanceof \pocketmine\entity\Human || $entity instanceof \pocketmine\entity\Villager) {return; 
                }

                $distance = $damager->distance($entity);
                if($distance > 6){
                    $this->notifyHelpers($damager, "AutoKillaura");
                }

                if($distance > 7.5){
                    $date = (new DateTime())->format('Y-m-d H:i:s');
                    $this->notifyHelpers($damager, "AutoKillaura");
                    $damager->kick("§l§cАНТИ§7-§cЧИТ §7| §r§7{$date}§r\n§rПричина§7: §cподозрение на AutoKillaura\n", false); 
                    Server::getInstance()->broadcastMessage("§l§cАНТИ§7-§cЧИТ §8:: §rИгрок §a" . $damager->getName()." §rбыл выгнан за подозрение на использование §cAutoKillaura§r!");
                }
            }
        }
    }

    public function onBlockCrash(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();

        if ($this->getPlayerPing($player) > 190) {
            return;
        }

        $currentTime = microtime(true);

        if (!isset($this->lastHitTime[$name]) || $currentTime - $this->lastHitTime[$name] >= 1) {
            $this->lastHitTime[$name] = $currentTime;
            $this->hitCount[$name] = 0;
        }

        $this->hitCount[$name]++;

        if ($this->hitCount[$name] > 10) {
            $this->notifyHelpers($player, "FastBreak");
        }
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if ($damager instanceof Player && $entity instanceof Player) {
                if ($this->getPlayerPing($damager) > 190) {
                    return;
                }

                $distance = $damager->distance($entity);

                if ($damager->getInventory()->getItemInHand()->getId() !== Item::BOW && $distance > 15) {
                    $this->notifyHelpers($damager, "HitBox/Reach");
                }

                $name = $damager->getName();
                $currentTime = microtime(true);

                if (!isset($this->lastHitTime[$name]) || $currentTime - $this->lastHitTime[$name] >= 1) {
                    $this->lastHitTime[$name] = $currentTime;
                    $this->hitCount[$name] = 0;
                }

                $this->hitCount[$name]++;

                if ($this->hitCount[$name] > 20) {
                    $this->notifyHelpers($damager, "AutoClicker");
                }
            }
        }
    }
}