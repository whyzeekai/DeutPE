<?php

declare(strict_types=1);

namespace API\npc;

use API\Loader;

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

class Cosmetic implements Listener {

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

        if ($player->isOp() && $message === ".setnpckosmetik") {
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
            $npc->setNameTag("          §l§aКосметика\n§rНажмите на NPC для просмотра!");
            $npc->setNameTagVisible(true);
            $npc->setNameTagAlwaysVisible();
            $npc->SpawnToAll();
            $event->setCancelled();
        }
    }

    public function handlePlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();

        foreach ($player->getLevel()->getEntities() as $entity) {
            if ($entity->getNameTag() == "          §l§aКосметика\n§rНажмите на NPC для просмотра!") {
                if ($player->distance($entity->getPosition()) < 1) {
                    $direction = $player->getDirectionVector();

                    $motionX = $direction->x * -2;
                    $motionY = 0.34;
                    $motionZ = $direction->z * -2;

                    $player->setMotion(new Vector3($motionX / 6, $motionY, $motionZ / 6));
                }
            }
        }
    }

    public function handleEntityDamage(EntityDamageEvent $event){
        if(!$event instanceof EntityDamageByEntityEvent){
            return;
        }

        $player = $event->getDamager();
        $entity = $event->getEntity();

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§aКосметика") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            $chest = ChestAPI::getInstance()->openChest($player, [
                 "0-53" => Item::get(102),
                 "10-43" => Item::get(0),
                 20 => Item::get(340)->setCustomName("§r§l§eСообщение о входе §r§7(украшение)\n\n§r§8Нажмите дважды, чтобы перейти!"),
            ], "§a§lКосметика §8:: §rУкрась свой аккаунт!", ChestAPI::DOUBLE_CHEST);
        }
    }

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        $lvl = Loader::getInstance()->getLvl($player);
        $clan = Loader::getInstance()->getPrefixClans($player);
        $os = Loader::AVAILABLE_OS[$player->getDeviceOS()];
        $donate = Loader::getInstance()->getPrefix($player);
        $marry = Loader::getInstance()->marry->getMarryManager()->isMarriedPrefix($player);

        $group = Loader::getInstance()->getPrefix($player);
        $sub = Loader::getInstance()->getSub($player);

        $cosmeticData = Loader::getInstance()->cosmetic->get($player->getName());

        for ($i = 1; $i <= 5; $i++) {
            if ($itemName === "§r§l§aВключить §r§7(Сообщение о входе №{$i})§r") {

                $cosmeticData["join{$i}_status"] = true;
                Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                Loader::getInstance()->cosmetic->save();
                $player->sendMessage("§l§aКОСМЕТИКА §8:: §rСообщение о входе §l§f№{$i} §r§aвключено!");
                ChestAPI::getInstance()->closeInventory($player);
            } elseif ($itemName === "§r§l§cВыключить §r§7(Сообщение о входе №{$i})§r") {
                $cosmeticData["join{$i}_status"] = false;
                Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                Loader::getInstance()->cosmetic->save();
                $player->sendMessage("§l§aКОСМЕТИКА §8:: §rСообщение о входе §l§f№{$i} §r§cотключено!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §r§7(украшение)") !== false) {
            $itemKey = "list1";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),

                20 => (isset($cosmeticData["join_1"]) && $cosmeticData["join_1"]) 
                ? Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№1\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rвлетел в игру.\n\n§r§7• §rСтатус§7: §aКуплен.\n\n§7Нажмите дважды, чтобы применить!") 
                : Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№1\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rвлетел в игру.\n\n§r§7• §rЦена§7: §a1,500,000§2$\n\n§7Нажмите дважды, чтобы купить!"),

                21 => (isset($cosmeticData["join_2"]) && $cosmeticData["join_2"]) 
                ? Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№2\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rпришел раздавать радость.\n\n§r§7• §rСтатус§7: §aКуплен.\n\n§7Нажмите дважды, чтобы применить!") 
                : Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№2\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rпришел раздавать радость.\n\n§r§7• §rЦена§7: §a1,500,000§2$\n\n§7Нажмите дважды, чтобы купить!"),

                22 => (isset($cosmeticData["join_3"]) && $cosmeticData["join_3"])
                ? Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№3\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rзашёл выносить всех.\n\n§r§7• §rСтатус§7: §aКуплен.\n\n§7Нажмите дважды, чтобы применить!")
                : Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№3\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rзашёл выносить всех.\n\n§r§7• §rЦена§7: §a1,500,000§2$\n\n§7Нажмите дважды, чтобы купить!"),

                23 => (isset($cosmeticData["join_4"]) && $cosmeticData["join_4"])
                ? Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№4\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rвошел в рэп-игру.\n\n§r§7• §rСтатус§7: §aКуплен.\n\n§7Нажмите дважды, чтобы применить!")
                : Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№4\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rвошел в рэп-игру.\n\n§r§7• §rЦена§7: §a1,500,000§2$\n\n§7Нажмите дважды, чтобы купить!"),

                24 => (isset($cosmeticData["join_5"]) && $cosmeticData["join_5"])
                ? Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№5\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rзашёл, ничего не дам.\n\n§r§7• §rСтатус§7: §aКуплен.\n\n§7Нажмите дважды, чтобы применить!")
                : Item::get(340)->setCustomName("§r§l§eСообщение о входе §f№5\n\n§rПример§7:\n§r§8[{$group}{$sub}§r§8] §r{$player->getName()} §rзашёл, ничего не дам.\n\n§r§7• §rЦена§7: §a1,500,000§2$\n\n§7Нажмите дважды, чтобы купить!"),
            ]);
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §f№1") !== false) {
            $itemKey = "list2";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            if (!isset($cosmeticData["join_1"]) || !$cosmeticData["join_1"]) {
                if (Loader::getInstance()->getMoney($player) >= 1500000) {
                    Loader::getInstance()->remMoney($player, 1500000);

                    $cosmeticData["join_1"] = true;
                    $cosmeticData["join1_message"] = "§rвлетел в игру.";
                    $cosmeticData["join1_status"] = false;
                    Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                    Loader::getInstance()->cosmetic->save();
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВы купили §r§l§eСообщение о входе §f№1§r, за §a1,500,000§2$");
                    ChestAPI::getInstance()->closeInventory($player);
                } else {
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §r§cУ вас недостаточно средств!");
                }
            } else {
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(54)->setCustomName("§r§l§aВключить §r§7(Сообщение о входе №1)§r"),
                    23 => Item::get(54)->setCustomName("§r§l§cВыключить §r§7(Сообщение о входе №1)§r"),
                ]);
            }
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §f№2") !== false) {
            $itemKey = "list3";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            if (!isset($cosmeticData["join_2"]) || !$cosmeticData["join_2"]) {
                if (Loader::getInstance()->getMoney($player) >= 1500000) {
                    Loader::getInstance()->remMoney($player, 1500000);

                    $cosmeticData["join_2"] = true;
                    $cosmeticData["join2_message"] = "§rпришел раздавать радость.";
                    $cosmeticData["join2_status"] = false;
                    Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                    Loader::getInstance()->cosmetic->save();
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВы купили §r§l§eСообщение о входе §f№2§r, за §a1,500,000§2$");
                    ChestAPI::getInstance()->closeInventory($player);
                } else {
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §r§cУ вас недостаточно средств!");
                }
            } else {
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(54)->setCustomName("§r§l§aВключить §r§7(Сообщение о входе №2)§r"),
                    23 => Item::get(54)->setCustomName("§r§l§cВыключить §r§7(Сообщение о входе №2)§r"),
                ]);
            }
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §f№3") !== false) {
            $itemKey = "list4";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            if (!isset($cosmeticData["join_3"]) || !$cosmeticData["join_3"]) {
                if (Loader::getInstance()->getMoney($player) >= 1500000) {
                    Loader::getInstance()->remMoney($player, 1500000);

                    $cosmeticData["join_3"] = true;
                    $cosmeticData["join3_message"] = "§rзашёл выносить всех.";
                    $cosmeticData["join3_status"] = false;
                    Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                    Loader::getInstance()->cosmetic->save();
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВы купили §r§l§eСообщение о входе §f№3§r, за §a1,500,000§2$");
                    ChestAPI::getInstance()->closeInventory($player);
                } else {
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §r§cУ вас недостаточно средств!");
                }
            } else {
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(54)->setCustomName("§r§l§aВключить §r§7(Сообщение о входе №3)§r"),
                    23 => Item::get(54)->setCustomName("§r§l§cВыключить §r§7(Сообщение о входе №3)§r"),
                ]);
            }
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §f№4") !== false) {
            $itemKey = "list5";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            if (!isset($cosmeticData["join_4"]) || !$cosmeticData["join_4"]) {
                if (Loader::getInstance()->getMoney($player) >= 1500000) {
                    Loader::getInstance()->remMoney($player, 1500000);

                    $cosmeticData["join_4"] = true;
                    $cosmeticData["join4_message"] = "§rвошел в рэп-игру.";
                    $cosmeticData["join4_status"] = false;
                    Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                    Loader::getInstance()->cosmetic->save();
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВы купили §r§l§eСообщение о входе §f№4§r, за §a1,500,000§2$");
                    ChestAPI::getInstance()->closeInventory($player);
                } else {
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §r§cУ вас недостаточно средств!");
                }
            } else {
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(54)->setCustomName("§r§l§aВключить §r§7(Сообщение о входе №4)§r"),
                    23 => Item::get(54)->setCustomName("§r§l§cВыключить §r§7(Сообщение о входе №4)§r"),
                ]);
            }
        }

        if (strpos($itemName, "§r§l§eСообщение о входе §f№5") !== false) {
            $itemKey = "list6";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            if (!isset($cosmeticData["join_5"]) || !$cosmeticData["join_5"]) {
                if (Loader::getInstance()->getMoney($player) >= 1500000) {
                    Loader::getInstance()->remMoney($player, 1500000);

                    $cosmeticData["join_5"] = true;
                    $cosmeticData["join5_message"] = "§rзашёл, ничего не дам.";
                    $cosmeticData["join5_status"] = false;
                    Loader::getInstance()->cosmetic->set($player->getName(), $cosmeticData);
                    Loader::getInstance()->cosmetic->save();
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §rВы купили §r§l§eСообщение о входе §f№5§r, за §a1,500,000§2$");
                    ChestAPI::getInstance()->closeInventory($player);
                } else {
                    $player->sendMessage("§l§aКОСМЕТИКА §8:: §r§cУ вас недостаточно средств!");
                }
            } else {
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(54)->setCustomName("§r§l§aВключить §r§7(Сообщение о входе №5)§r"),
                    23 => Item::get(54)->setCustomName("§r§l§cВыключить §r§7(Сообщение о входе №5)§r"),
                ]);
            }
        }
    }
}