<?php

declare(strict_types=1);

namespace API\event;

use api\Loader;
use api\task\RemoveBlockTask;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\event\block\BlockBreakEvent;

use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\item\Item;

use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Arena implements Listener {

    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();

        if($player->getLevel()->getFolderName() == "arena") {
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
        }
    }

    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();

        if($player->getLevel()->getFolderName() == "arena") {
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
        }
    }

    public function onBlockPlaceEvent(\pocketmine\event\block\BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $level = $block->getLevel();

        if($level->getName() === "kp3"){
            if($player->getName() !== "Ha1sech" && $player->getName() !== "Wassabi"){
                $event->setCancelled(true);
            }
        }

        if($level->getName() === "proxyworld"){
            if (Loader::getInstance()->time <= 20) {
                $time = Loader::getInstance()->time;
                $player->sendMessage("§r §rНельзя ставить блоки на §bарене§r, рестарт через §a0м. {$time}§aс.");
                $event->setCancelled(true);
                return true;
            }
            if($player->getName() !== "Ha1sech" && $player->getName() !== "Wassabi"){
                $this->loader->getScheduler()->scheduleDelayedTask(new RemoveBlockTask($block->asVector3(), $level), 20 * 5);
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $level = $block->getLevel();
        if($level->getName() === "proxyworld"){
            if($player->getName() !== "Ha1sech" && $player->getName() !== "Wassabi"){
                $event->setCancelled(true);
            }
        }

        if($level->getName() === "kp3"){
            if($player->getName() !== "Ha1sech" && $player->getName() !== "Wassabi"){
                $event->setCancelled(true);
            }
        }
    }

    public function onPlayerCommand(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        $level = $player->getLevel();

        if ($level->getName() === 'proxyworld') {
            $playerName = $player->getName();
            if ($playerName !== "Ha1sech" && $playerName !== "Wassabi") {
                if ($message[0] === "/") {
                    $event->setCancelled(true);
                    $player->sendMessage("§r §rВы находитесь на §bарене§r, команды использовать тут нельзя!");
                }
            }
        }
    }
}