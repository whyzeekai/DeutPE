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

class Pass implements Listener {

    public $itemClicks = [];
    /** @var Loader */
    private $loader;

    const QUEST_FALSE_STATUS = "§l§cНЕДОСТУПЕН§r\n\n§r §rВыполняйте §aквесты§r и\n открывайте следующие §aквесты§r!";
    const QUEST_OPEN_STATUS = "§l§eВЫПОЛНЯЕТСЯ§r\n\n§7Нажми дважды, чтобы сдать!";
    const QUEST_TRUE_STATUS = "§l§aВЫПОЛНЕН§r\n";

    private function toPrettyNumber(int $number) : string{
        $str = "";
        foreach(str_split((string)$number) as $char){
            $str .= is_numeric($char) ? ["０", "１", "２", "３", "４", "５", "６", "７", "８", "９"][(int)$char] : $char;
        }
        return $str;
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

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    private function openMenuPass(Player $player) {
        $chest = ChestAPI::getInstance()->openChest($player, [
            "0-8" => Item::get(102),
            "45-54" => Item::get(102),
        ], "§7§lＰＡＳＳ", ChestAPI::DOUBLE_CHEST);

        if(is_bool($chest)) return;
        $inventory = $chest["inventory"];

        if (Loader::getInstance()->getPass($player) <= 1) {
            $inventory->setItem(20, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§eВЕСЕННИЙ ПАСС§r\n\n§rСтатус§7: §cНедоступен\n§rКвестов§7: §a5шт§r\n\n§rПасс можно купить на §aсайте\n§a". Loader::WEBSITE ."§r, пасс окупаемый!\nпройдя квесты вы получаете §aбонусы§r!\nа также привилегию §a§lПАТРИК§r §7[НАВСЕГДА§7]"));
        } else {
            $inventory->setItem(20, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§eВЕСЕННИЙ ПАСС§r\n\n§rСтатус§7: §aДоступен\n§rКвестов§7: §a5шт§r\n\n§7Нажмите дважды, чтобы перейти!"));
        }
        $inventory->setItem(24, Item::get(159, 14, 1)->setCustomName("§r§cСкоро добавим..."));
    }

    private function rewardQuests($quest) {
        if ($quest == 1) {
            return "§a100§rрублей";
        } elseif ($quest == 2) {
            return "§a350,000§2$";
        } elseif ($quest == 3) {
            return "§l§eВесенний§7-§eКейс§r";
        } elseif ($quest == 4) {
            return "§a200§rруб + §l§aДонат§7-§aКейс§r";
        } elseif ($quest == 5) {
            return "§a§lПАТРИК§r §7[НАВСЕГДА§7]";
        } else {
            return "§cНету";
        }
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

        if ($player->isOp() && $message === ".setnpcpass") {
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
            $npc->setNameTag("pass");
            $npc->setNameTagVisible(false);
            $npc->setNameTagAlwaysVisible(false);
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "pass") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);

            $this->openMenuPass($player);
        }
    }

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        if (strpos($itemName, "§rВЕРНУТЬСЯ В МЕНЮ §aPASS§r")) {
            $itemKey = "menu";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            $this->openMenuPass($player);
        }

        if (strpos($itemName, "§r§l§eВЕСЕННИЙ ПАСС§r\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "donatepass";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if($this->createQuests($player)) {
                Server::getInstance()->getLogger()->warning("[Pass] Recording player {$player->getName()} in datebase.");
            }

            $array = Loader::$questsdonatepass->get($player->getLowerCaseName());

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aPASS§r"),
            ]);

            for ($i = 1; $i <= 5; $i++) {
                $item = Item::get(340, 0, 1);
                $item->setCustomName("§r§l§eВЕСЕННИЙ КВЕСТ§r §f№". $this->toPrettyNumber($i). "\n\n§rПредмет§7: §a{$array["quest_{$i}"]["item_name"]}\n\n§rНаграда§7: ". $this->rewardQuests($i) ."\n\n§rСдано§7:§a {$array["quest_{$i}"]["counts"]}§7/§a{$array["quest_{$i}"]["max_counts"]}\n\n§rСтатус§7: §a".$this->getStatusQuest($player, "quest_{$i}"));
                $nbt = $item->getNamedTag();
                $nbt->setInt("QuestDonatePass", $i);
                $item->setNamedTag($nbt);
                $event->getInventory()->setItem(19 + $i, $item);
            }
        }
    }

    public function onInventoryClickEventMiner(InventoryClickEvent $event) {
        $inventory = $event->getInventory(); 
        $player = $event->getPlayer();
        $item = $event->getItem();
        $name = mb_strtolower($player->getName());

        $nbt = $item->getNamedTag();
        if($nbt->hasTag("QuestDonatePass", \pocketmine\nbt\tag\IntTag::class)) {
            $number_quest = $nbt->getInt("QuestDonatePass");
            $config = Loader::$questsdonatepass->get($player->getLowerCaseName())["quest_{$number_quest}"];

            if($config["status"] == true) {
                return;
            }

            if ($number_quest > 1) {
                $number_quest_next = $number_quest - 1;

                if (Loader::$questsdonatepass->get($player->getLowerCaseName())["quest_{$number_quest_next}"]["status"] == false) {
                    $itemKey = "donatepassclick2";
                    if(!isset($this->itemClicks[$name][$itemKey])){
                        return $this->itemClicks[$name][$itemKey] = 1;
                    } else if($this->itemClicks[$name][$itemKey] === 1){
                        unset($this->itemClicks[$name][$itemKey]);
                    }

                    $player->sendTitle("§e§lＰＡＳＳ", "§rВыполните сначала квест §a#".$number_quest_next." ");
                    ChestAPI::getInstance()->closeInventory($player);
                    return;
                }
            }

            if ($config["counts"] >= $config["max_counts"]) {
                Loader::$questsdonatepass->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.status", true);
                Loader::$questsdonatepass->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.in_progress", false); // Завершение квеста
                Loader::$questsdonatepass->save();

                switch ($number_quest) {
                    case 1:
                        Loader::getInstance()->addRub($player, 100);
                    break;

                    case 2:
                        Loader::getInstance()->addMoney($player, 350000);
                    break;

                    case 3:
                        Loader::getInstance()->addNewyearKey($player, 1);
                    break;

                    case 4:
                        Loader::getInstance()->addRub($player, 200);
                        Loader::getInstance()->addDonateKey($player, 1);
                    break;

                    case 5:
                        $santa = "Patrik";
                        $time = "навсегда";
                        Loader::getInstance()->setGroup($player, $santa, $time);
                    break;
                }

                ChestAPI::getInstance()->closeInventory($player);
                return;
            }

            Loader::$questsdonatepass->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.in_progress", true);
            Loader::$questsdonatepass->save();

            $items = $this->countItemsInInventory($player, $config["id"]);

            $itemKey = "questdoubleclick123pass";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            } else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if($player->getInventory()->contains(Item::get($config["id"], 0, $items))) {
                $player->getInventory()->removeItem(Item::get($config["id"], 0, $items));
                Loader::$questsdonatepass->setNested("{$player->getLowerCaseName()}.quest_{$number_quest}.counts", $config["counts"] + $items);
                Loader::$questsdonatepass->save();        
            }
            return;
        }
    }

    public function createQuests(Player $player): bool {
        $quests = [
            "Снопы сена" => 170,
            "Булыжник" => 4, 
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
            "Блок Снега" => 80,
            "Тыквы" => 86,
            "Эндер Перлы" => 368,
            "Снежок" => 332,
        ];

        $questNames = array_keys($quests);
        shuffle($questNames);

        if (!Loader::$questsdonatepass->exists($player->getLowerCaseName())) {
            $questData = [];

            for ($i = 0; $i < 5; $i++) {
                if (isset($questNames[$i])) {
                    $item_name = $questNames[$i];
                    $item_id = $quests[$item_name];
                    $rand_maxcount = rand(1000, 2600);

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

            Loader::$questsdonatepass->set($player->getLowerCaseName(), $questData);
            Loader::$questsdonatepass->save();
            return true;
        } else {
            return false;
        }
        return false; 
    }

    public function getStatusQuest(Player $player, string $quest): string {
        $questData = Loader::$questsdonatepass->get($player->getLowerCaseName())[$quest];

        if ($questData["status"]) {
            return self::QUEST_TRUE_STATUS; 
        } elseif ($questData["in_progress"]) {
            return self::QUEST_OPEN_STATUS; 
        } else {
            return self::QUEST_FALSE_STATUS;
        }
    }
}