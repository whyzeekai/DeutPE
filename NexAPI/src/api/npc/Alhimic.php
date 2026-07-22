<?php

declare(strict_types=1);

namespace API\npc;

use API\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\entity\{Human, Witch};
use pocketmine\entity\Entity;

use pocketmine\nbt\tag\{CompoundTag, IntTag, StringTag, ListTag, DoubleTag};
use pocketmine\nbt\NBT;

use pocketmine\level\sound\{PopSound, AnvilFallSound, ClickSound, MinecraftSound};

use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\level\Level;

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use pocketmine\event\inventory\InventoryClickEvent;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

use pocketmine\network\mcpe\protocol\AnimatePacket;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;

class Alhimic implements Listener {

    const QUEST_FALSE_STATUS = "§l§cНЕ ВЫПОЛНЕН§r\n\n§7Нажми дважды, чтобы сдать!";
    const QUEST_TRUE_STATUS = "§l§aВЫПОЛНЕН§r\n";

    const ERROR_SELL = "§l§7► §rУ вас §cнедостаточно§r зельей в §aинвентаре§r!";
    const SUCCESSFUL_SELL = "§l§7► §rВы успешно продали §aзельея§r!";

    const ERROR_BUY = "§l§7► §rУ вас §cнедостаточно§r монет на §aбалансе§r!";
    const SUCCESSFUL_BUY = "§l§7► §rВы успешно купили §aвещь§r!";

    public $itemClicks = [];
    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    private function countItemsInInventory(Player $player, int $itemId, int $itemDamage = 0) {
        $count = 0;
        foreach ($player->getInventory()->getContents() as $inventoryItem) {
            if ($inventoryItem->getId() === $itemId && $inventoryItem->getDamage() === $itemDamage) {
                $count += $inventoryItem->getCount();
            }
        }
        return $count;
    }

    public function sellPotion(Player $player): void {
        $status = false;
        foreach ($player->getInventory()->getContents() as $item) {
            if($item->getId() == 373) {
                $status = true;
                Loader::getInstance()->addMoney($player, 5000);
                $player->sendMessage(self::SUCCESSFUL_SELL);
                $player->getInventory()->removeItem($item);
                break;
            } elseif($item->getId() == 438) {
                $status = true;
                Loader::getInstance()->addMoney($player, 5000);
                $player->sendMessage(self::SUCCESSFUL_SELL);
                $player->getInventory()->removeItem($item);
                break;
            }
        }

        if(!$status) {
            $player->sendMessage(self::ERROR_SELL);
        }
    }

    public function buyPotion(Player $player, int $id, int $damage, int $count, int $price): void {
        if(Loader::getInstance()->getMoney($player) < $price) {
            $player->sendMessage(self::ERROR_BUY);
            return;
        }

        Loader::getInstance()->remMoney($player, $price);
        $player->sendMessage(self::SUCCESSFUL_BUY);
        $player->getInventory()->addItem(Item::get($id, $damage, $count));
        return;
    }

    public function onInventoryClickEvent(InventoryClickEvent $event) {
        $inventory = $event->getInventory(); $player = $event->getPlayer();
        $item = $event->getItem();
        $name = strtolower($player->getName());

        if(strpos($item->getCustomName(), "#2000") !== false) {
            $itemKey = "bytpotion1";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 10000);
            return;
        }

        if(strpos($item->getCustomName(), "#2001") !== false) {
            $itemKey = "bytpotion2";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 10000);
            return;
        }

        if(strpos($item->getCustomName(), "#2002") !== false) {
            $itemKey = "bytpotion3";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 10000);
            return;
        }

        if(strpos($item->getCustomName(), "#2003") !== false) {
            $itemKey = "bytpotion4";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 10000);
            return;
        }

        if(strpos($item->getCustomName(), "#2004") !== false) {
            $itemKey = "bytpotion5";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 10000);
            return;
        }

        if(strpos($item->getCustomName(), "#2005") !== false) {
            $itemKey = "bytpotion6";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 20000);
            return;
        }

        if(strpos($item->getCustomName(), "#2006") !== false) {
            $itemKey = "bytpotion7";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 20000);
            return;
        }

        if(strpos($item->getCustomName(), "#2007") !== false) {
            $itemKey = "bytpotion8";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 20000);
            return;
        }

        if(strpos($item->getCustomName(), "#2008") !== false) {
            $itemKey = "bytpotion9";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 20000);
            return;
        }

        if(strpos($item->getCustomName(), "#2009") !== false) {
            $itemKey = "bytpotion10";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->buyPotion($player, $item->getId(), $item->getDamage(), $item->getCount(), 20000);
            return;
        }

        if(strpos($item->getCustomName(), "#2010") !== false) {
            $itemKey = "sellpotion1";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            $this->sellPotion($player);
            return;
        }

        if(strpos($item->getCustomName(), "#2011") !== false) {
            $itemKey = "buyalh1";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2012") !== false) {
            $itemKey = "buyalh2";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2013") !== false) {
            $itemKey = "buyalh3";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2014") !== false) {
            $itemKey = "buyalh4";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2015") !== false) {
            $itemKey = "buyalh5";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2016") !== false) {
            $itemKey = "buyalh6";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2017") !== false) {
            $itemKey = "buyalh7";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }

        if(strpos($item->getCustomName(), "#2018") !== false) {
            $itemKey = "buyalh7";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
            if(Loader::getInstance()->getMoney($player) < 5000) {
                $player->sendMessage(self::ERROR_BUY);
                return;
            }

            Loader::getInstance()->remMoney($player, 5000);
            $player->sendMessage(self::SUCCESSFUL_BUY);
            $player->getInventory()->addItem(Item::get($item->getId(), $item->getDamage(), $item->getCount()));
            return;
        }
    }
    
    public function onChatEvent(PlayerChatEvent $event){
        $player = $event->getPlayer();
        $message = $event->getMessage();

         if($player->isOp() && $message === ".setnpcalhimic"){
            $nbt = new CompoundTag("", ["Pos" => new ListTag("Pos", [new DoubleTag("", $player->x), new DoubleTag("", $player->y), new DoubleTag("", $player->z)]), "Motion" => new ListTag("Motion", [new DoubleTag("", 0), new DoubleTag("", 0), new DoubleTag("", 0)]), "Rotation" => new ListTag("Rotation", [new DoubleTag("", $player->yaw), new DoubleTag("", $player->pitch)]), "Skin" => new CompoundTag("Skin", ["Data" => new StringTag("Data", $player->getSkin()->getSkinData()), "Name" => new StringTag("Name", $player->getSkin()->getSkinId())])]);
            $npc = new Witch($player->level, $nbt);
            $npc->setNameTag("           §l§bАлхимик\n§rНажмите на NPC для просмотра!");
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§bАлхимик") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            $chest = ChestAPI::getInstance()->openChest($player, [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),
                20 => Item::get(373, 0, 1)->setCustomName("§r§l§dМАГАЗИН ЗЕЛЬЕЙ§r")->setLore(["§r§8Нажми дважды, чтобы перейти!"]),
                24 => Item::get(382)->setCustomName("§r§l§eМАГАЗИН ИНГРЕДИЕНТОВ§r")->setLore(["§r§8Нажми дважды, чтобы перейти!"]),
                //31 => Item::get(340)->setCustomName("§r§l§bКВЕСТЫ АЛХИМИКА§r")->setLore(["\n§rВсего квестов§7:§a 10шт\n\n§7Нажми дважды, чтобы перейти!"])
            ], "                 §l§8Алхимик", ChestAPI::DOUBLE_CHEST);
        }
    }

        public function handleChestClose(\ChestAPI\ChestCloseEvent $event){
            $player = $event->getPlayer();
            $name = strtolower($player->getName());

            if(isset($this->itemClicks[$name])){
                unset($this->itemClicks[$name]);
            }
        }

        public function onInventoryClickKits(InventoryClickEvent $event) {
            $player = $event->getWhoClicked();
            $item = $event->getItem();
            $itemName = $item->getCustomName();
            $playerName = $player->getName();
            $name = strtolower($player->getName());
            $money = number_format(Loader::getInstance()->getMoney($player));

            if (strpos($itemName, "§r§l§dМАГАЗИН ЗЕЛЬЕЙ§r") !== false) {
                    $itemKey = "sho123pmenupotion";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }

                    ChestAPI::getInstance()->setInventory($event->getInventory(), [
                        "0-53" => Item::get(102),
                        "10-43" => Item::get(0),
                        49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §bАЛХИМИКА"),
                    ]);

                    $item = Item::get(373, 8, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2000)\n\n§r§7╔ §rЦена за 1шт§7:§a 10,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(20, $item);

                    $item = Item::get(373, 11, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2001)\n\n§r§7╔ §rЦена за 1шт§7:§a 10,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(21, $item);

                    $item = Item::get(373, 33, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2002)\n\n§r§7╔ §rЦена за 1шт§7:§a 10,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(22, $item);

                    $item = Item::get(373, 16, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2003)\n\n§r§7╔ §rЦена за 1шт§7:§a 10,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(23, $item);

                    $item = Item::get(373, 30, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2004)\n\n§r§7╔ §rЦена за 1шт§7:§a 10,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(24, $item);

                    $item = Item::get(438, 16, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2005)\n\n§r§7╔ §rЦена за 1шт§7:§a 20,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(29, $item);

                    $item = Item::get(438, 11, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2006)\n\n§r§7╔ §rЦена за 1шт§7:§a 20,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(30, $item);

                    $item = Item::get(438, 33, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2007)\n\n§r§7╔ §rЦена за 1шт§7:§a 20,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(31, $item);

                    $item = Item::get(438, 30, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2008)\n\n§r§7╔ §rЦена за 1шт§7:§a 20,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(32, $item);

                    $item = Item::get(438, 22, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2009)\n\n§r§7╔ §rЦена за 1шт§7:§a 20,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(33, $item);

                    $item = Item::get(373, 0, 1);
                    $item->setCustomName("§r§l§dПРОДАЖА ЗЕЛЕК§r §r(#2010)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7╔ §rСкупает §aлюбые§r зелья\n§7╙ §rпо очень выгодной §aцене§r!\n\n§7Нажми дважды, чтобы продать!");
                    $event->getInventory()->setItem(43, $item);
                }

                if (strpos($itemName, "§r§l§eМАГАЗИН ИНГРЕДИЕНТОВ§r") !== false) {
                    $itemKey = "shopme123nu123";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }

                    ChestAPI::getInstance()->setInventory($event->getInventory(), [
                        "0-53" => Item::get(102),
                        "10-43" => Item::get(0),
                        49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §bАЛХИМИКА"),
                    ]);

                    $item = Item::get(382, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2011)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(20, $item);

                    $item = Item::get(373, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2012)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(21, $item);

                    $item = Item::get(379, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2013)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(22, $item);

                    $item = Item::get(378, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2014)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(23, $item);

                    $item = Item::get(375, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2015)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(24, $item);

                    $item = Item::get(377, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2016)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(30, $item);

                    $item = Item::get(399, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2017)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(31, $item);

                    $item = Item::get(376, 0, 1);
                    $item->setCustomName("§r§l§a{$item->getName()}§r §r(#2018)\n\n§r§7╔ §rЦена за 1шт§7:§a 5,000§2$\n§7╙ §rВаш баланс§7: §a{$money}§2$\n\n§7Нажми дважды, чтобы купить!");
                    $event->getInventory()->setItem(32, $item);
                }
            }


        public function handleChestClick(\ChestAPI\ChestClickEvent $event){
            $player = $event->getPlayer();
            $name = strtolower($player->getName());
            $item = $event->getItem();
            $itemName = $item->getCustomName();
            $money = number_format(Loader::getInstance()->getMoney($player));

            switch($event->getCustomName()){

                case '§rВЕРНУТЬСЯ В МЕНЮ §bАЛХИМИКА':
                    $itemKey = "menu";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }

                    ChestAPI::getInstance()->setInventory($event->getInventory(), [
                        "0-53" => Item::get(102),
                        "10-43" => Item::get(0),
                        20 => Item::get(373, 0, 1)->setCustomName("§r§l§dМАГАЗИН ЗЕЛЬЕЙ§r")->setLore(["§r§8Нажми дважды, чтобы перейти!"]),
                        24 => Item::get(382)->setCustomName("§r§l§eМАГАЗИН ИНГРЕДИЕНТОВ§r")->setLore(["§r§8Нажми дважды, чтобы перейти!"]),
                    ]);
                break;
            }
        }
    }