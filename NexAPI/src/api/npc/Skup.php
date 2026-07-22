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
use pocketmine\level\sound\{PopSound, AnvilFallSound, ClickSound, MinecraftSound};
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

use api\task\TaskLevelUpSeller;

class Skup implements Listener {

    public $itemClicks = [];
    /** @var Loader */
    private $loader;
    const QUEST_FALSE_STATUS = "§l§cНЕДОСТУПЕН§r\n\n§r §rВыполняйте квесты\nи, открывайте следующие";
    const QUEST_TRUE_STATUS = "§l§aВЫПОЛНЕН§r\n";

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    private function countItemsInInventory(Player $player, int $itemId, int $itemDamage = 0, int $maxCount = 1000){
        $count = 0;
        foreach ($player->getInventory()->getContents() as $inventoryItem) {
            if ($inventoryItem->getId() === $itemId && $inventoryItem->getDamage() === $itemDamage) {
                $count += $inventoryItem->getCount();
                if ($count >= $maxCount) {
                    return $maxCount; 
                }
            }
        }
        return $count; 
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

        if ($player->isOp() && $message === ".setnpcskup") {
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
            $npc->setNameTag("       §r §l§bСкупщик §r\n§rНажмите на NPC для просмотра!");
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§bСкупщик") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            if($this->createQuests($player)) {
                Server::getInstance()->getLogger()->warning("[Buyer] Recording player {$player->getName()} in datebase.");
            }

            $lvlskup = Loader::getInstance()->getLvlbuyer($player);
            $expskup = Loader::getInstance()->getExpbuyer($player);
            $expNeeded = 50 * pow(1.2, Loader::getInstance()->getLvlbuyer($player));
            

            if (Loader::getInstance()->getExpbuyer($player) >= $expNeeded){
                $statuslvlup = "§aДоступно";
            }else{
                $statuslvlup = "§cНедоступно";
            }

            $chest = ChestAPI::getInstance()->openChest($player, [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                 47 => Item::get(389)->setCustomName("§r§l§bИНФОРМАЦИЯ СКУПЩИКА§r")->setLore([
                        "\n§r§7 §fУровень скупщика§7: §d{$lvlskup}\n§r§7 §fОпыт скупщика§7: §d{$expskup}§7/§d{$expNeeded}\n\n§r§7 §f§rПроходи §bквесты§r скупщика\n§r§7 §fчтобы получить §dопыт§r скупщика!\n§r§7 §fи повышайте уровень §bскупщика§r!\n"
                    ]),

                 49 => Item::get(374)->setCustomName("§r§l§bПОВЫСИТЬ УРОВЕНЬ СКУПЩИКА§r")->setLore([
                            "\n§r§7 §fСтатус§7:§a {$statuslvlup}\n§r§7 §fОпыт§7: §d{$expskup}/{$expNeeded}\n\n§7Нажми дважды, чтобы повысить!"
                        ]),

                51 => Item::get(340)->setCustomName("§r§l§bКВЕСТЫ СКУПЩИКА§r")->setLore([
                            "\n§r§7 §fВсего квестов§7:§a 10шт\n§r§7 §fУдобная система квестов!\n\n§7Нажми дважды, чтобы перейти!"
                        ]),

                11 => Item::get(103)->setCustomName("§r§l§fБлок Арбуза§r§7 x1")->setLore(["\n§rЦена§7: §a" . (80 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                12 => Item::get(86)->setCustomName("§r§l§fТыква§r§7 x1")->setLore(["\n§rЦена§7: §a" . (70 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                13 => Item::get(170)->setCustomName("§r§l§fБлок сена§r§7 x1")->setLore(["\n§rЦена§7: §a" . (300 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                14 => Item::get(296)->setCustomName("§r§l§fПшеница§r§7 x1")->setLore(["\n§rЦена§7: §a" . (50 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                15 => Item::get(392)->setCustomName("§r§l§fКартошка§r§7 x1")->setLore(["\n§rЦена§7: §a" . (15 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                20 => Item::get(391)->setCustomName("§r§l§fМорковь§r§7 x1")->setLore(["\n§rЦена§7: §a" . (15 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),

                21 => Item::get(287)->setCustomName("§r§l§fНитки§r§7 x1")->setLore(["\n§rЦена§7: §a" . (50 * (1 + 0.1 * $lvlskup)) . "§2$\n\n§rМножитель§7: §ax" . (1 + 0.1 * $lvlskup)
                    ]),
            ], "§b§lСкупщик §8:: §rПродовай свои вещи!", ChestAPI::DOUBLE_CHEST);

            if(is_bool($chest)) return;
            $inventory = $chest["inventory"];
        }
    }

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        $lvlskup = Loader::getInstance()->getLvlbuyer($player);
        $expskup = Loader::getInstance()->getExpbuyer($player);
        $expNeeded = 50 * pow(1.2, Loader::getInstance()->getLvlbuyer($player));

        if (strpos($itemName, "§r§l§bПОВЫСИТЬ УРОВЕНЬ СКУПЩИКА§r") !== false) {
            $itemKey = "sellerlvlup";
            if (!isset($this->itemClicks[$name][$itemKey])) {
                return $this->itemClicks[$name][$itemKey] = 1;
            } elseif ($this->itemClicks[$name][$itemKey] === 1) {
                unset($this->itemClicks[$name][$itemKey]);
            }

            if (Loader::getInstance()->getExpbuyer($player) >= $expNeeded) {
                $this->loader->getScheduler()->scheduleRepeatingTask(new TaskLevelUpSeller($this->loader, $player), 20);
                Loader::getInstance()->remExpbuyer($player, $expNeeded);
                ChestAPI::getInstance()->closeInventory($player);
            } else {
                $player->addTitle("§l§bСКУПЩИК", "§сНедостаточно опыта для повышения!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        $array = Loader::$questsbuyer->get($player->getLowerCaseName());

        if (strpos($itemName, "§r§l§bКВЕСТЫ СКУПЩИКА§r") !== false) {
            $itemKey = "minequests";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),

                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aSKUP§r"),
            ]);

            for ($i = 1; $i <= 5; $i++) {
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ СКУПЩИКА §f#{$i}§r\n\n§rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§rНаграда§7: §d25exp §7(SKUP)\n\n§rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestBuyer", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(10 + $i, $item);
            }
            for ($i = 6;  $i <= 10; $i++){
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ СКУПЩИКА §f#{$i}§r\n\n§rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§rНаграда§7: §d50exp §7(SKUP)\n\n§rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestBuyer", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(14 + $i, $item);
            }
        }

        $itemsell = [
            "§r§l§fБлок Арбуза§r" => [
                "price" => 80 * (1 + 0.1 * $lvlskup),
                "id_item" => "103",
                "data" => 0,
            ],
            "§r§l§fТыква§r" => [
                "price" => 70 * (1 + 0.1 * $lvlskup),
                "id_item" => "86",
                "data" => 0,
            ],
            "§r§l§fБлок сена§r§7" => [
                "price" => 300 * (1 + 0.1 * $lvlskup),
                "id_item" => "170",
                "data" => 0, 
            ],
            "§r§l§fПшеница§r" => [
                "price" => 50 * (1 + 0.1 * $lvlskup),
                "id_item" => "296",
                "data" => 0, 
            ],
            "§r§l§fКартошка§r" => [
                "price" => 15 * (1 + 0.1 * $lvlskup),
                "id_item" => "392",
                "data" => 0, 
            ],
            "§r§l§fМорковь§r" => [
                "price" => 15 * (1 + 0.1 * $lvlskup),
                "id_item" => "391",
                "data" => 0, 
            ],
            "§r§l§fНитки§r" => [
                "price" => 50 * (1 + 0.1 * $lvlskup),
                "id_item" => "287",
                "data" => 0,
            ],
        ];

        foreach ($itemsell as $itemNameMine => $info) {
            if (strpos($itemName, $itemNameMine) !== false) {
                $itemKey = "buyer";
                if(!isset($this->itemClicks[$name][$itemKey])){
                    return $this->itemClicks[$name][$itemKey] = 1;
                }else if($this->itemClicks[$name][$itemKey] === 1){
                    unset($this->itemClicks[$name][$itemKey]);
                }

                $playerInv = $player->getInventory();
                $totalCount = 0;

                foreach ($playerInv->getContents() as $item) {
                    if ($item->getId() == (int)$info["id_item"] && $item->getDamage() == (int)$info['data']) {
                        $totalCount += $item->getCount();
                    }
                }

                if ($totalCount > 0) {
                    $player->getInventory()->removeItem(Item::get((int)$info["id_item"], (int)$info['data'], $totalCount));
                    $moneyEarned = $totalCount * $info["price"];
                    Loader::getInstance()->addMoney($player, $moneyEarned);

                    $player->sendMessage("§l§bСКУПЩИК §8:: §rВы продали {$itemNameMine} §7x{$totalCount} §rза §a". number_format($moneyEarned) ."§2$\n§l§bСКУПЩИК §8:: §rМножитель§7: §ax" . (1 + 0.1 * $lvlskup). "");
                    $player->getLevel()->addSound(new PopSound($player));
                } else {
                    $player->getLevel()->addSound(new AnvilFallSound($player));
                    $player->sendMessage("§l§bСКУПЩИК §8:: §r§cУ вас нету предмета {$itemNameMine}");
                }
                break; 
            }
        }
    }

    public function createQuests(Player $player): bool {
        $quests = [
            "Снопы сена" => 170,
            "Булыжник" => 4, 
            "Алмазы" => 264,
            "Золотой Слиток" => 266,
            "Изумруд" => 388,
            "Угольная Руда" => 16,
            "Золотая Руда" => 14,
            "Дуб" => 17,
            "Железо" => 265,
            "Пшеница" => 296,
            "Морковь" => 391,
            "Ломтик арбуза" => 360,
            "Тыквы" => 86,
            "Пустой пузырёк" => 374,
            "Золотое яблоко" => 322,
            "Семена пшеницы" => 295,
            "Алмазы" => 264,
            "Уголь" => 263,
            "Хлеб" => 297,
            "Тыквы" => 86,
            "Эндер Перлы" => 368,
        ];

        $questNames = array_keys($quests);
        shuffle($questNames);

        if (!Loader::$questsbuyer->exists($player->getLowerCaseName())) {
            $questData = [];

            for ($i = 0; $i < 10; $i++) {
                if (isset($questNames[$i])) {
                    $item_name = $questNames[$i];
                    $item_id = $quests[$item_name];
                    $rand_maxcount = rand(1000, 3000);

                    $questData["quest_" . ($i + 1)] = [
                        "item_name" => $item_name,
                        "counts" => 0,
                        "max_counts" => $rand_maxcount,
                        "id" => $item_id,
                        "status" => false 
                    ];
                }
            }

            Loader::$questsbuyer->set($player->getLowerCaseName(), $questData);
            Loader::$questsbuyer->save();
            return true;
        } else {
            return false;
        }
        return false; 
    }

    public function getStatusQuest(Player $player, string $quest): string {
        if(Loader::$questsbuyer->get($player->getLowerCaseName())[$quest]["status"]) {
            return self::QUEST_TRUE_STATUS;
        }
        return self::QUEST_FALSE_STATUS;
    }

    public function onInventoryClickEvent(InventoryClickEvent $event) {
        $inventory = $event->getInventory(); $player = $event->getPlayer();
        $item = $event->getItem();
        $name = mb_strtolower($player->getName());

        $nbt = $item->getNamedTag();
        if($nbt->hasTag("QuestBuyer", \pocketmine\nbt\tag\IntTag::class)) {
            $number_quest = $nbt->getInt("QuestBuyer");
            $config = Loader::$questsbuyer->get($player->getLowerCaseName())["quest_{$number_quest}"];

            if($config["status"] == true) {
                return;
            }

            if($number_quest > 1) {
                $number_quest_next = $number_quest - 1;

                if(Loader::$questsbuyer->get($player->getLowerCaseName())["quest_{$number_quest_next}"]["status"] == false) {

                    $itemKey = "nodoubleclick";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    }else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }
                    $player->sendTitle("§r§l§bСКУПЩИК", "§rВыполните сначала квест §a#".$number_quest_next." ");
                    ChestAPI::getInstance()->closeInventory($player);
                    return;
                }
            }

            if($config["counts"] >= $config["max_counts"]) {
                Loader::$questsbuyer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.status", true);
                Loader::$questsbuyer->save();

                switch ($number_quest) {
                    case 1:
                        Loader::getInstance()->addExpbuyer($player, 25);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d25exp");
                    break;

                    case 2:
                        Loader::getInstance()->addExpbuyer($player, 25);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d25exp");
                    break;

                    case 3:
                        Loader::getInstance()->addExpbuyer($player, 25);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d25exp");
                    break;

                    case 4:
                        Loader::getInstance()->addExpbuyer($player, 25);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d25exp");
                    break;

                    case 5:
                        Loader::getInstance()->addExpbuyer($player, 25);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d25exp");
                    break;

                    case 6:
                        Loader::getInstance()->addExpbuyer($player, 50);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d50exp");
                    break;

                    case 7:
                        Loader::getInstance()->addExpbuyer($player, 50);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d50exp");
                    break;

                    case 8:
                        Loader::getInstance()->addExpbuyer($player, 50);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d50exp");
                    break;

                    case 9:
                        Loader::getInstance()->addExpbuyer($player, 50);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d50exp");
                    break;

                    case 10:
                        Loader::getInstance()->addExpbuyer($player, 50);
                        $player->sendMessage("§l§bСКУПЩИК §8:: §rВам зачислено: §d50exp");
                    break;
                }

                ChestAPI::getInstance()->closeInventory($player);
                return;
            }

            $items = $this->countItemsInInventory($player, $config["id"], 0, $config["max_counts"]);

            $itemKey = "gfe3wf";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if($player->getInventory()->contains(Item::get($config["id"], 0, $items))) {
                $player->getInventory()->removeItem(Item::get($config["id"], 0, $items));
                Loader::$questsbuyer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.counts", $config["counts"] + $items);
                Loader::$questsbuyer->save();
            }
            return;
        }
    }
}