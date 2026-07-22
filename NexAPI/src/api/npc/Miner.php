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

use pocketmine\level\sound\{PopSound, AnvilFallSound, ClickSound, MinecraftSound};

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

class Miner implements Listener {

    public $itemClicks = [];
    /** @var Loader */
    private $loader;

    const QUEST_FALSE_STATUS = "§l§cНЕДОСТУПЕН§r\n\n§r §rВыполняйте §aквесты§r и\nоткрывайте следующие §aквесты§r!";
    const QUEST_OPEN_STATUS = "§l§eВЫПОЛНЯЕТСЯ§r\n\n§7Нажми дважды, чтобы сдать!";
    const QUEST_TRUE_STATUS = "§l§aВЫПОЛНЕН§r\n";

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

        if ($player->isOp() && $message === ".setnpcminer") {
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
            $npc->setNameTag("        §r §l§bШахтер §r\n§rНажмите на NPC для просмотра!");
            $npc->setNameTagVisible(true);
            $npc->setNameTagAlwaysVisible();
            $npc->SpawnToAll();
            $event->setCancelled();
        }
    }

    public function handlePlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();

        foreach ($player->getLevel()->getEntities() as $entity) {
            if ($entity->getNameTag() == "        §r §l§bШахтер §r\n§rНажмите на NPC для просмотра!") {
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§bШахтер") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            if($this->createQuests($player)) {
                Server::getInstance()->getLogger()->warning("[Miner] Recording player {$player->getName()} in datebase.");
            }

            $chest = ChestAPI::getInstance()->openChest($player, [
                 "0-8" => Item::get(102),
                 "45-54" => Item::get(102),

            ], "§l§bШахтер §8:: §rПривет, §a{$player->getName()}§r!", ChestAPI::DOUBLE_CHEST);

            if(is_bool($chest)) return;
            $inventory = $chest["inventory"];

            $items = [
                11 => Item::get(264, 0, 1)->setCustomName("§r§l§bАлмаз§r")->setLore(["\n§r §rЦена за 1шт§7:§a 60§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                12 => Item::get(265, 0, 1)->setCustomName("§r§l§bЖелезо§r")->setLore(["\n§r §rЦена за 1шт§7:§a 40§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                13 => Item::get(266, 0, 1)->setCustomName("§r§l§bЗолото§r")->setLore(["\n§r §rЦена за 1шт§7:§a 50§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                14 => Item::get(263, 0, 1)->setCustomName("§r§l§bУголь§r")->setLore(["\n§r §rЦена за 1шт§7:§a 30§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                15 => Item::get(331, 0, 1)->setCustomName("§r§l§bРедстоун§r")->setLore(["\n§r §rЦена за 1шт§7:§a 35§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                20 => Item::get(388, 0, 1)->setCustomName("§r§l§bИзумруд§r")->setLore(["\n§r §rЦена за 1шт§7:§a 100§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                21 => Item::get(351, 4, 1)->setCustomName("§r§l§bЛазурит§r")->setLore(["\n§r §rЦена за 1шт§7:§a 30§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                23 => Item::get(4, 0, 1)->setCustomName("§r§l§bБулыжник§r")->setLore(["\n§r §rЦена за 1шт§7:§a 10§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                24 => Item::get(49, 0, 1)->setCustomName("§r§l§bОбсидиан§r")->setLore(["\n§r §rЦена за 1шт§7:§a 25§r§r§f⩐\n\n§r §rШахтер §aскупает§r сразу все\n§r §rресурсы которые вы §aпродаете§r!\n\n§7Нажми дважды, чтобы продать!"]),
                40 => Item::get(340, 0, 1)->setCustomName("§r§l§bКВЕСТЫ ШАХТЕРА§r")->setLore(["\n§r§r	§rВсего квестов§7:§a 10шт\n§r	§rУдобная система квестов!\n\n§r	§rПроходи §aквесты§r шахтера\n§r	§rи получай от него §aнаграду§r!\n\n§7Нажми дважды, чтобы перейти!"]),
            ];

            foreach ($items as $slot => $item) {
                $inventory->setItem($slot, $item);
            }
        }
    }

    public function onInventoryClickEventMiner(InventoryClickEvent $event) {
    $inventory = $event->getInventory(); 
    $player = $event->getPlayer();
    $item = $event->getItem();
    $name = mb_strtolower($player->getName());

    $nbt = $item->getNamedTag();
    if($nbt->hasTag("QuestMiner", \pocketmine\nbt\tag\IntTag::class)) {
        $number_quest = $nbt->getInt("QuestMiner");
        $config = Loader::$questsminer->get($player->getLowerCaseName())["quest_{$number_quest}"];

        if($config["status"] == true) {
            return;
        }

        if ($number_quest > 1) {
            $number_quest_next = $number_quest - 1;

            if (Loader::$questsminer->get($player->getLowerCaseName())["quest_{$number_quest_next}"]["status"] == false) {
                $itemKey = "nodoubleclick";
                if(!isset($this->itemClicks[$name][$itemKey])){
                    return $this->itemClicks[$name][$itemKey] = 1;
                } else if($this->itemClicks[$name][$itemKey] === 1){
                    unset($this->itemClicks[$name][$itemKey]);
                }
                $player->sendTitle("§r§l§bШАХТЕР", "§rВыполните сначала квест §a#".$number_quest_next." ");
                ChestAPI::getInstance()->closeInventory($player);
                return;
            }
        }

        if ($config["counts"] >= $config["max_counts"]) {
            // Обновление статуса квеста на выполненный
            Loader::$questsminer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.status", true);
            Loader::$questsminer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.in_progress", false); // Завершение квеста
            Loader::$questsminer->save();

            // Награждение игрока
            switch ($number_quest) {
                case 1:
                    Loader::getInstance()->addMoney($player, 100000);
                    $player->sendMessage("\n§l§f        КВЕСТ ПРОЙДЕН     \n\n§r§7• §rВы прошли§7: §r§l§bКВЕСТ ШАХТЕРА §f#１§r\n§7╠ §rВам доступен§7: §r§l§bКВЕСТ ШАХТЕРА §f#２§r\n§7╚ §rНаграда за квест§7: §l§a100,000м.§r\n\n");
                    break;
                // Добавьте остальные награды и сообщения аналогично
                case 10:
                    $miner = "minershlem";
                    Loader::getInstance()->giveKits($player, $miner);
                    break;
            }

            ChestAPI::getInstance()->closeInventory($player);
            return;
        }

        // Начало выполнения квеста
        Loader::$questsminer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.in_progress", true);
        Loader::$questsminer->save();

        $items = $this->countItemsInInventory($player, $config["id"]);

        $itemKey = "questdoubleclick123";
        if(!isset($this->itemClicks[$name][$itemKey])){
            return $this->itemClicks[$name][$itemKey] = 1;
        } else if($this->itemClicks[$name][$itemKey] === 1){
            unset($this->itemClicks[$name][$itemKey]);
        }

        if($player->getInventory()->contains(Item::get($config["id"], 0, $items))) {
            $player->getInventory()->removeItem(Item::get($config["id"], 0, $items));
            Loader::$questsminer->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.counts", $config["counts"] + $items);
            Loader::$questsminer->save();        }
        return;
    }
}


    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        $array = Loader::$questsminer->get($player->getLowerCaseName());

        if (strpos($itemName, "§r§l§bКВЕСТЫ ШАХТЕРА§r") !== false) {
            $itemKey = "minequests";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),

                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aMINER§r"),
            ]);

            for ($i = 1; $i <= 5; $i++) {
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ ШАХТЕРА §f#{$i}§r\n\n§r §rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§r §rНаграда§7: §a\n\n§r §rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§r §rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestMiner", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(10 + $i, $item);
            }
            for ($i = 6;  $i <= 10; $i++){
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ ШАХТЕРА §f#{$i}§r\n\n§r §rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§r §rНаграда§7: §a\n\n§r §rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§r §rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestMiner", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(14 + $i, $item);
            }
            for ($i = 11;  $i <= 15; $i++){
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ ШАХТЕРА §f#{$i}§r\n\n§r §rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§r §rНаграда§7: §a\n\n§r §rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§r §rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestMiner", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(18 + $i, $item);
            }

            for ($i = 16;  $i <= 20; $i++){
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§bКВЕСТ ШАХТЕРА §f#{$i}§r\n\n§r §rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§r §rНаграда§7: §a\n\n§r §rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§r §rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestMiner", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(22 + $i, $item);
            }
        }

        $itemsell = [
            "§r§l§bАлмаз§r" => [
                "price" => 60,
                "id_item" => "264",
                "data" => 0,
            ],
            "§r§l§bЖелезо§r" => [
                "price" => 40,
                "id_item" => "265",
                "data" => 0,
            ],
            "§r§l§bЗолото§r" => [
                "price" => 50,
                "id_item" => "266",
                "data" => 0, 
            ],
            "§r§l§bУголь§r" => [
                "price" => 30,
                "id_item" => "263",
                "data" => 0, 
            ],
            "§r§l§bРедстоун§r" => [
                "price" => 35,
                "id_item" => "331",
                "data" => 0, 
            ],
            "§r§l§bИзумруд§r" => [
                "price" => 100,
                "id_item" => "388",
                "data" => 0, 
            ],
            "§r§l§bЛазурит§r" => [
                "price" => 30,
                "id_item" => "351",
                "data" => 4,
            ],
            "§r§l§bБулыжник§r" => [
                "price" => 10,
                "id_item" => "4",
                "data" => 0, 
            ],
            "§r§l§bОбсидиан§r" => [
                "price" => 25,
                "id_item" => "49",
                "data" => 0, 
            ],
        ];

        foreach ($itemsell as $itemNameMine => $info) {
            if (strpos($itemName, $itemNameMine) !== false) {
                if(!isset($this->itemClicks[$name][$info["id_item"]])){
                    $this->itemClicks[$name][$info["id_item"]] = 1;
                } elseif($this->itemClicks[$name][$info["id_item"]] === 1){
                    unset($this->itemClicks[$name][$info["id_item"]]);
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

                    $player->sendMessage("§l§bШАХТЕР §8:: §rВы продали {$itemNameMine} §7x{$totalCount} §rза §a". number_format($moneyEarned) ."§2$");
                    $player->getLevel()->addSound(new PopSound($player));
                } else {
                    $player->getLevel()->addSound(new AnvilFallSound($player));
                }
                break; 
            }
        }
    }

    public function createQuests(Player $player): bool {
        $quests = [
            "Снопы сена" => 170,
            "Булыжник" => 4, 
            "Дуб" => 17,
            "Железо" => 265,
            "Пшеница" => 296,
            "Нитки" => 287,
            "Палки" => 280,
            "Морковь" => 391,
            "Ломтик арбуза" => 360,
            "Тыквы" => 86,
            "Пустой пузырёк" => 374,
            "Золотое яблоко" => 322,
            "Семена пшеницы" => 295,
            "Алмазы" => 264,
            "Уголь" => 263,
            "Хлеб" => 297,
            "Доски дуба" => 5,
            "Блок Снега" => 80,
            "Тыквы" => 86,
            "Кварц" => 406,
            "Эндер Перлы" => 368,
            "Снежок" => 332,
        ];

        $questNames = array_keys($quests);
        shuffle($questNames);

        if (!Loader::$questsminer->exists($player->getLowerCaseName())) {
            $questData = [];

            for ($i = 0; $i < 20; $i++) {
                if (isset($questNames[$i])) {
                    $item_name = $questNames[$i];
                    $item_id = $quests[$item_name];
                    $rand_maxcount = rand(500, 2000);

                    $questData["quest_" . ($i + 1)] = [
                        "item_name" => $item_name,
                        "counts" => 0,
                        "max_counts" => $rand_maxcount,
                        "id" => $item_id,
                        "status" => false,
                        "in_progress" => false
                    ];
                }
            }

            Loader::$questsminer->set($player->getLowerCaseName(), $questData);
            Loader::$questsminer->save();
            return true;
        } else {
            return false;
        }
        return false; 
    }

    public function getStatusQuest(Player $player, string $quest): string {
        $questData = Loader::$questsminer->get($player->getLowerCaseName())[$quest];

        if ($questData["status"]) {
            return self::QUEST_TRUE_STATUS; 
        } elseif ($questData["in_progress"]) {
            return self::QUEST_OPEN_STATUS; 
        } else {
            return self::QUEST_FALSE_STATUS;
        }
    }
}