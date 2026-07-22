<?php

declare(strict_types=1);

namespace api\npc;

use api\Loader;
//use api\task\TaskTeleport;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\entity\Human;
use pocketmine\entity\passive\Villager;
use pocketmine\entity\Entity;

use pocketmine\nbt\tag\{CompoundTag, IntTag, StringTag, ListTag, DoubleTag};
use pocketmine\nbt\NBT;

use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\level\Level;

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerMoveEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\item\Item;

use pocketmine\scheduler\CallbackTask;

use pocketmine\network\mcpe\protocol\AnimatePacket;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;

class Npc implements Listener {

    private $npc;
    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function onChatEvent(PlayerChatEvent $event){
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if($player->isOp() && $message === ".setnpcfree"){
            $nbt = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $player->x),
                    new DoubleTag("", $player->y),
                    new DoubleTag("", $player->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new DoubleTag("", $player->yaw),
                    new DoubleTag("", 0)
                ]),
                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId())
                ])
            ]);
            $npc = new Human($player->level, $nbt);
            $inventory = $player->getInventory();
            $inv = $npc->getInventory();
            $inv->setItemInHand($inventory->getItemInHand());
            $npc->setNameTag("     §l§bFREE PREFIXCASE\n§rНажмите на NPC для просмотра!");
            $npc->setNameTagVisible();
            $npc->setNameTagAlwaysVisible();
            $npc->SpawnToAll();
        }

        if($player->isOp() && $message === ".setnpcarena"){
            $nbt = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $player->x),
                    new DoubleTag("", $player->y),
                    new DoubleTag("", $player->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new DoubleTag("", $player->yaw),
                    new DoubleTag("", 0)
                ]),
                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId())
                ])
            ]);
            $npc = new Human($player->level, $nbt);
            $inventory = $player->getInventory();
            $inv = $npc->getInventory();
            $inv->setItemInHand($inventory->getItemInHand());
            $npc->setNameTag("arenaworld");
            $npc->setNameTagVisible(false);
            $npc->setNameTagAlwaysVisible(false);
            $npc->SpawnToAll();
        }

        if($player->isOp() && $message === ".setnpctest"){
            $nbt = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $player->x),
                    new DoubleTag("", $player->y),
                    new DoubleTag("", $player->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new DoubleTag("", $player->yaw),
                    new DoubleTag("", 0)
                ]),
                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId())
                ])
            ]);
            $npc = new Human($player->level, $nbt);
            $npc->setNameTag("EnderDragon");
            $npc->setNameTagVisible(false);
            $npc->setNameTagAlwaysVisible(false);
            $npc->SpawnToAll();
        }

    if($player->isOp() && $message === ".setnpcduel"){
            $nbt = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $player->x),
                    new DoubleTag("", $player->y),
                    new DoubleTag("", $player->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new DoubleTag("", $player->yaw),
                    new DoubleTag("", 0)
                ]),
                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId())
                ])
            ]);
            $npc = new Human($player->level, $nbt);
            $inventory = $player->getInventory();
            $inv = $npc->getInventory();
            $inv->setItemInHand($inventory->getItemInHand());
            $npc->setNameTag("duelworld");
            $npc->setNameTagVisible(false);
            $npc->setNameTagAlwaysVisible(false);
            $npc->SpawnToAll();
        }
    }

    public function onChatEventleave(PlayerChatEvent $event){
        $player = $event->getPlayer();
        $message = $event->getMessage();

         if($player->isOp() && $message === ".setnpcleavearena"){
            $nbt = new CompoundTag("", ["Pos" => new ListTag("Pos", [new DoubleTag("", $player->x), new DoubleTag("", $player->y), new DoubleTag("", $player->z)]), "Motion" => new ListTag("Motion", [new DoubleTag("", 0), new DoubleTag("", 0), new DoubleTag("", 0)]), "Rotation" => new ListTag("Rotation", [new DoubleTag("", $player->yaw), new DoubleTag("", $player->pitch)]), "Skin" => new CompoundTag("Skin", ["Data" => new StringTag("Data", $player->getSkin()->getSkinData()), "Name" => new StringTag("Name", $player->getSkin()->getSkinId())])]);
            $npc = new Villager($player->level, $nbt);
            $npc->setNameTag("§l§lbВЫЙТИ С АРЕНЫ");
            $npc->setNameTagVisible(true);
            $npc->setNameTagAlwaysVisible();
            $npc->SpawnToAll();
        }
    }

    public function onChatEventgruef(PlayerChatEvent $event){
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if($player->isOp() && $message === ".setnpcgrief"){
            $nbt = new CompoundTag("", [
                "Pos" => new ListTag("Pos", [
                    new DoubleTag("", $player->x),
                    new DoubleTag("", $player->y),
                    new DoubleTag("", $player->z)
                ]),
                "Motion" => new ListTag("Motion", [
                    new DoubleTag("", 0),
                    new DoubleTag("", 0),
                    new DoubleTag("", 0)
                ]),
                "Rotation" => new ListTag("Rotation", [
                    new DoubleTag("", $player->yaw),
                    new DoubleTag("", 0)
                ]),
                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId())
                ])
            ]);
            $npc = new Human($player->level, $nbt);
            $inventory = $player->getInventory();
            $inv = $npc->getInventory();
            $inv->setItemInHand($inventory->getItemInHand());
            $npc->setNameTag("griefworld");
            $npc->setNameTagVisible(false);
            $npc->setNameTagAlwaysVisible(false);
            $npc->SpawnToAll();
        }
    }

    public function handleEntityDamage(EntityDamageEvent $event){
        if(!$event instanceof EntityDamageByEntityEvent){
            return;
        }

        $player = $event->getDamager();
        $entity = $event->getEntity();

        if ($player instanceof Player && strpos($entity->getNameTag(), "duelworld") !== false) {
            $event->setCancelled();

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);
            Server::getInstance()->dispatchCommand($player, "duel join");
        }

        if ($player instanceof Player && strpos($entity->getNameTag(), "arenaworld") !== false) {
            $event->setCancelled();

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);
            Server::getInstance()->dispatchCommand($player, "arena"); 
        }

        if ($player instanceof Player && strpos($entity->getNameTag(), "ВЫЙТИ С АРЕНЫ") !== false) {
            $event->setCancelled();

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);
            if (isset(Loader::getInstance()->pvp[$player->getLowerCaseName()])) {
                $player->sendMessage("§l§cPVP §8:: §r§fУ вас режим §c§lБОЯ §rвыйти с §cарены§r нельзя!");
                return true;
            }
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
            $world1 = count(Server::getInstance()->getLevelByName("arena")->getPlayers());
            Loader::getInstance()->arena1->setTitle("§r§f §rОнлайн в мире§7: §a{$world1} §r§f");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arena1);
        }
    }
}