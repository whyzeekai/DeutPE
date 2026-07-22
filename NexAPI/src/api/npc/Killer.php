<?php

declare(strict_types=1);

namespace api\npc;

use api\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\entity\Human;
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

use pocketmine\math\Vector3;

use pocketmine\scheduler\CallbackTask;

use pocketmine\network\mcpe\protocol\AnimatePacket;

use pocketmine\event\inventory\InventoryClickEvent;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;

class Killer implements Listener {

    public $itemClicks = [];
    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function handleChestClose(\ChestAPI\ChestCloseEvent $event){
        $player = $event->getPlayer();
        $name = strtolower($player->getName());

        if(isset($this->itemClicks[$name])){
            unset($this->itemClicks[$name]);
        }
    }

    public function onChatEvent(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if ($player->isOp() && $message === ".setnpckiller") {
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
                    new DoubleTag("", $player->pitch)
                ]),

                "Skin" => new CompoundTag("Skin", [
                    "Data" => new StringTag("Data", $player->getSkin()->getSkinData()),
                    "Name" => new StringTag("Name", $player->getSkin()->getSkinId()),
                ])
            ]);

            $npc = new Human($player->level, $nbt);
            $inventory = $player->getInventory();
            $inv = $npc->getInventory();
            $inv->setItemInHand($inventory->getItemInHand());
            $npc->setNameTag("        §r §l§cКиллер §r\n§rНажмите на NPC для просмотра!");
            $npc->setNameTagVisible(true);
            $npc->setNameTagAlwaysVisible();
            $npc->SpawnToAll();
            $event->setCancelled();
        }
    }

    public function handleEntityDamage(EntityDamageEvent $event){
        if(!$event instanceof EntityDamageByEntityEvent){
            return;
        }

        $player = $event->getDamager();
        $entity = $event->getEntity();

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§cКиллер") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            $killer = Loader::getInstance()->killer->getAll();
            $slot = 8;

            $chest = ChestAPI::getInstance()->openChest($player, [
                 "0-8" => Item::get(102),
                 "45-54" => Item::get(102),
                 49 => Item::get(340)->setCustomName("§r §l§fИНФОРМАЦИЯ §r\n\n§r §rЧтобы заказать на §aигрока\n§rубийство нужно прописать\nкоманду §a/killer (никнейм) (сумма)\n\n§r§r Заказ удаляется через §a24ч. 0м."),

            ], "§c§lКиллер §8:: §rЗаказные убийства!", ChestAPI::DOUBLE_CHEST);

            if(is_bool($chest)) return;
            $inventory = $chest["inventory"];

            if (is_array($killer) && !empty($killer)) {
                foreach ($killer as $targetName => $data) {
                    if ($data['time'] <= time()) {
                        Loader::getInstance()->killer->remove($targetName); 
                        Loader::getInstance()->killer->save();
                        continue; 
                    }
                    ++$slot;

                    $executorName = $data['executor'];
                    $amount = $data['amount']; 
                    $expirationTime = $data['time'];

                    $remainingDays = max(0, ceil(($data['time'] - time()) / 86400));

                    if ($inventory->getItem($slot)->getId() === 0) {
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r §l§cЗАКАЗ УБИЙСТВО НА §6{$targetName} §r")->setLore([
                            "\n§r §rЗаказчик§7: §a{$executorName}",
                            "§r §rСумма§7: §a". number_format($amount) ."§r§f⩐",
                            "§r §rИстечёт через§7: §a". Loader::getInstance()->parseTime($expirationTime) .""
                        ]));
                    }
                }
            } else {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cНет доступных заказов.\n\n§r §rЧтобы заказать на §aигрока\n§rубийство нужно прописать\nкоманду §a/killer (никнейм) (сумма)"));
                $inventory->setItem(49, Item::get(102));
            }
        }
    }

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());
    }
}