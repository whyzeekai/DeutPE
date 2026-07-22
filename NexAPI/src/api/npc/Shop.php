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
use pocketmine\item\enchantment\Enchantment;

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

use pocketmine\utils\Utils;
use pocketmine\utils\Config;

class Shop implements Listener {

    public $itemClicks = [];
    public $lastOpenings = [];
    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function recordOpening($playerName, $caseName, $reward, $time, $id, $damage) {
        global $lastOpenings;
        array_unshift($this->lastOpenings, [
            'player' => $playerName,
            'case' => $caseName,
            'reward' => $reward,
            'time' => $time,
            'id' => $id,
            'damage' => $damage        
        ]);
        if (count($this->lastOpenings) > 7) {
            array_pop($this->lastOpenings);
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

        if ($player->isOp() && $message === ".techrestart") {
            Loader::getInstance()->time = 60;
            Server::getInstance()->broadcastMessage("§l§aРЕСТАРТ§8 :: §rЧерез §a60 секунд§r, произойдёт технический рестарт!");
        }

        if ($player->isOp() && $message === ".setnpcshop") {
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
            $npc->setNameTag("          §l§aМагазин\n§r§rНажми на NPC для просмотра!");
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§aМагазин") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);
            Shop::openMenu($player, "shop");
        }
    }

    public function openMenu(Player $player, string $category) {
        if ($category === "shop") {
            $chest = ChestAPI::getInstance()->openChest($player, [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ], "                 §8§lМагазин", ChestAPI::DOUBLE_CHEST);
            if (is_bool($chest)) return;
            $inventory = $chest["inventory"];

            $inventory->setItem(20, Item::get(276, 0, 1)->setCustomName("§r§l§bSWORD_FLY §r(#6601)\n\n§rЦена§7:§r §a200 рублей\n\n§rСпособность меча§7:\n§cС помощью меча вы сможете летать\n§l§cКД§7: §r§a2м. 0с.\n\n§7Нажмите дважды, чтобы купить!"));

           /* $menuSlot = 19; 
            if (count($this->lastOpenings) > 0) {
                foreach ($this->lastOpenings as $entry) {
                    $text = "§rИгрок: §a{$entry['player']}\n" .
                    "§rКупил: {$entry['case']}\n" .
                    "§rЦена: §a{$entry['reward']} РУБ.\n" .
                    "§rВремя: §7{$entry['time']}";
                    $inventory->setItem($menuSlot++, Item::get((int)$entry['id'], (int)$entry['damage'], 1)->setCustomName($text));
                }
            } else {
                $inventory->setItem(22, Item::get(218, 14, 1)->setCustomName("§r§cНет данных о последних покупок."));
            }
            */
        }
    }
    

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());
        $inventory = $event->getInventory();
        $rub = Loader::getInstance()->getRub($player);
        $playerRub = Loader::getInstance()->getRub($player);


        $itembuysword = [
            "§r(#6601)" => [
                "name" => "§r§l§bSWORD_FLУ",
                "price" => 200,
                "id_item" => "276",
                "data" => 0,
                "quantity" => 1,
            ],
        ];

        foreach ($itembuysword as $itemNameSword => $info) {
            if (strpos($itemName, $itemNameSword) === false) {
                continue;
            }

            $itemKey = $info['price'];
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($playerRub >= $info['price']) {
                Loader::getInstance()->remRub($player, $info['price']);

                $item1 = Item::get((int)$info["id_item"], (int)$info['data'], 1)->setCustomName($info['name']);
                $item1->addEnchantment(Enchantment::getEnchantment(9)->setLevel(5));
                $player->getInventory()->addItem($item1);

            } else {
                $notEnough = number_format($info['price'] - $playerRub);
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . " §cрублей"));
                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . " §rрублей");
            }
        }

        if (strpos($itemName, "§rВЕРНУТЬСЯ В МЕНЮ §aSHOP§r")) {
            $itemKey = "openMenu";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }
            Shop::openMenu($player, "shop");
        }

        if (strpos($itemName, "Донатная Броня") !== false) {
            $itemKey = "donatesword";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);
            $inventory->setItem(20, Item::get(311, 0, 1)->setCustomName("§r§l§dDRAGON_ARMOR_POWER §r(#9901)\n\n§rЦена§7:§r §a75 рублей\n\n§rСпособность брони§7:\n§cЕсли броня надета, дается,\n§7 - §aСила II\n\n§7Нажмите дважды, чтобы купить!"));

            $inventory->setItem(21, Item::get(311, 0, 1)->setCustomName("§r§l§aDRAGON_ARMOR_JUMP §r(#9902)\n\n§rЦена§7:§r §a75РУБ\n\n§rСпособность брони§7:\n§cЕсли броня надета, дается,\n§7 - §aПрыгучесть II\n\n§7Нажмите дважды, чтобы купить!"));

            $inventory->setItem(22, Item::get(311, 0, 1)->setCustomName("§r§l§bDRAGON_ARMOR_SPEED §r(#9903)\n\n§rЦена§7:§r §a75РУБ\n\n§rСпособность брони§7:\n§cЕсли броня надета, дается,\n§7 - §aСкорость\n\n§7Нажмите дважды, чтобы купить!"));

            $inventory->setItem(23, Item::get(311, 0, 1)->setCustomName(
                "§r§l§9DRAGON_ARMOR_VANISH §r(#9904)\n\n" .
                "§rЦена§7:§r §a125РУБ\n\n" .
                "§rСпособность брони§7:\n" .
                "§cЕсли броня надета, дается,\n§7 - §aНевидимость\n\n" .
                "§7Нажмите дважды, чтобы купить!"
            ));

        }

        $itembuybron = [
            "§r(#9901)" => [
                "name" => "§r§dDRAGON_ARMOR_POWER",
                "price" => 75,
                "id_item" => "310",
                "id_item2" => "311",
                "id_item3" => "312",
                "id_item4" => "313",
                "data" => 0,
            ],

            "§r(#9902)" => [
                "name" => "§r§aDRAGON_ARMOR_JUMP",
                "price" => 75,
                "id_item" => "310",
                "id_item2" => "311",
                "id_item3" => "312",
                "id_item4" => "313",
                "data" => 0,
            ],

            "§r(#9903)" => [
                "name" => "§r§bDRAGON_ARMOR_SPEED",
                "price" => 75,
                "id_item" => "310",
                "id_item2" => "311",
                "id_item3" => "312",
                "id_item4" => "313",
                "data" => 0,
            ],

            "§r(#9904)" => [
                "name" => "§r§9DRAGON_ARMOR_VANISH",
                "price" => 125,
                "id_item" => "310",
                "id_item2" => "311",
                "id_item3" => "312",
                "id_item4" => "313",
                "data" => 0,
            ],
        ];

        foreach ($itembuybron as $itemNameBron => $info) {
            if (strpos($itemName, $itemNameBron) === false) {
                continue;
            }

            $itemKey = $info['price'];
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory = $player->getInventory();
            $freeSlots = 0;
            for ($i = 0; $i < $inventory->getSize(); $i++) {
                if ($inventory->getItem($i)->getId() === 0) { 
                    $freeSlots++;
                }
            }

            if ($freeSlots < 4) {
                $player->sendMessage("§cПожалуйста, освободите 4 слота в инвентаре для покупки.");
                ChestAPI::getInstance()->closeInventory($player);
                return;
            }

            if ($playerRub >= $info['price']) {
                Loader::getInstance()->remRub($player, $info['price']);
                $item1 = Item::get((int)$info["id_item"], (int)$info['data'], 1)->setCustomName($info['name']);
                $item2 = Item::get((int)$info["id_item2"], (int)$info['data'], 1)->setCustomName($info['name']);
                $item3 = Item::get((int)$info["id_item3"], (int)$info['data'], 1)->setCustomName($info['name']);
                $item4 = Item::get((int)$info["id_item4"], (int)$info['data'], 1)->setCustomName($info['name']);

                $item1->addEnchantment(Enchantment::getEnchantment(0)->setLevel(6));
                $item2->addEnchantment(Enchantment::getEnchantment(0)->setLevel(6));
                $item3->addEnchantment(Enchantment::getEnchantment(0)->setLevel(6));
                $item4->addEnchantment(Enchantment::getEnchantment(0)->setLevel(6));

                $player->getInventory()->addItem($item1);
                $player->getInventory()->addItem($item2);
                $player->getInventory()->addItem($item3);
                $player->getInventory()->addItem($item4);

                //Shop::recordOpening($player->getName(), $info['name'], $info['price'], date('d.m H:i'), $info['id_item'], $info['data']);
            } else {
                $inventory = $event->getInventory();
                $notEnough = number_format($info['price'] - $playerRub);
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . " §cрублей"));
                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . " §rрублей");
            }
        }

        if (strpos($itemName, "Разное") !== false) {
            $itemKey = "mg";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));
            $inventory->setItem(22, Item::get(0, 0, 1));
            $inventory->setItem(23, Item::get(0, 0, 1));

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);

            $inventory->setItem(20, Item::get(399, 0, 1)->setCustomName("§r§l§7§fФЕЙЕРВЕРК §7x16 §r(#4001)\n\n§rЦена:§a 16,000§2$\n\n§rСпособность§7:\n§7 - §rНадев §aэлитры§r, и зажав §aфейерверк§r\n§7 - §rвы можете §aполететь§r!\n§7 - §rБез §aэлитров§r, нельзя полететь!\n§7 - §rТакже в режиме §aпвп§r нельзя §aлетать§r!\n\n§r§7Нажмите дважды, чтобы купить!"));

            $inventory->setItem(21, Item::get(444, 0, 1)->setCustomName("§r§l§7§fЭЛИТРЫ §7x1 §r(#4002)\n\n§rЦена:§a 50,000§2$\n\n§r§7Нажмите дважды, чтобы купить!"));

        }

        if (strpos($item->getCustomName(), "#4002") !== false) {
            $itemKey = "firework";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $price = "50000";
            $playerMoney = Loader::getInstance()->getMoney($player);
            $notEnough = number_format($price - $playerMoney);

            if(Loader::getInstance()->getMoney($player) >= $price) {
                Loader::getInstance()->remMoney($player, $price);
                $item = Item::get(444, 0, 1); 
                $player->getInventory()->addItem($item);
            }else{
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . "§2$"));

                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . "$");
            }
        }

        if (strpos($item->getCustomName(), "#4001") !== false) {
            $itemKey = "firework";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $price = "16000";
            $playerMoney = Loader::getInstance()->getMoney($player);
            $notEnough = number_format($price - $playerMoney);

            if(Loader::getInstance()->getMoney($player) >= $price) {
                Loader::getInstance()->remMoney($player, $price);
                $item = Item::get(399, 0, 16); 
                $item->setCustomName("§rФейерверк");
                $player->getInventory()->addItem($item);
            }else{
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . "§2$"));

                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . "$");
            }
        }

        $itembuy = [
            "Totem of Undying" => [
                "price" => 30000,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],

            "Ender Pearl" => [
                "price" => 12000,
                "id_item" => "368",
                "data" => 0,
                "quantity" => 4,
            ],

            "Golden Carrot" => [
                "price" => 15000,
                "id_item" => "396",
                "data" => 0,
                "quantity" => 8,
            ],

            "Steak" => [
                "price" => 8000,
                "id_item" => "364",
                "data" => 0,
                "quantity" => 32,
            ],

            "Enchanted Golden Apple" => [
                "price" => 50000,
                "id_item" => "466",
                "data" => 0,
                "quantity" => 8,
            ],

            "Golden Apple" => [
                "price" => 35000,
                "id_item" => "322",
                "data" => 0,
                "quantity" => 8,
            ],

            "Wheat Seeds" => [
                "price" => 8000,
                "id_item" => "295",
                "data" => 0,
                "quantity" => 8,
            ],

            "Pumpkin Seeds" => [
                "price" => 8000,
                "id_item" => "361",
                "data" => 0,
                "quantity" => 8,
            ],

            "Melon Seeds" => [
                "price" => 8000,
                "id_item" => "362",
                "data" => 0,
                "quantity" => 8,
            ],

            "Beetroot Seeds" => [
                "price" => 8000,
                "id_item" => "458",
                "data" => 0,
                "quantity" => 8,
            ],

            "Cocoa Beans" => [
                "price" => 8000,
                "id_item" => "351",
                "data" => 3,
                "quantity" => 8,
            ],

            "White Shulker Box" => [
                "price" => 100000,
                "id_item" => "218",
                "data" => 0,
                "quantity" => 1,
            ],
        ];

        foreach ($itembuy as $itemNameMine => $info) {
            if (strpos($itemName, $itemNameMine) !== false) {
                $itemKey = $info['id_item'];
                if(!isset($this->itemClicks[$name][$itemKey])){
                    return $this->itemClicks[$name][$itemKey] = 1;
                }else if($this->itemClicks[$name][$itemKey] === 1){
                    unset($this->itemClicks[$name][$itemKey]);
                }

                $price = $info['price'];
                $playerMoney = Loader::getInstance()->getMoney($player);

                $notEnough = number_format($price - $playerMoney);

                if(Loader::getInstance()->getMoney($player) >= $info['price']) {
                    Loader::getInstance()->remMoney($player, $info['price']);
                     $player->getInventory()->addItem(Item::get((int)$info["id_item"], (int)$info['data'], $info['quantity']));
                     //Shop::recordOpening($player->getName(), $itemNameMine, $info['price'], date('d.m H:i'), $info['id_item'], $info['data']);
                }else{
                    $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . "§2$"));

                    $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . "$");
                }
            }
        }

        $itembuydonate = [
            "§r(#0001)" => [
                "name" => "§r§l§7§aТАЛИСМАН ЧЕРЕПАХИ §7[§e✩§7]",
                "price" => 75,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],

            "§r(#0002)" => [
                "name" => "§r§l§7§6ТАЛИСМАН ГЕПАРДА §7[§e✩✩§7]",
                "price" => 125,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],

            "§r(#0003)" => [
                "name" => "§r§l§7§cТАЛИСМАН БЕРСЕРКА §7[§e✩✩✩§7]",
                "price" => 180,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],

            "§r(#0004)" => [
                "name" => "§r§l§7§eТАЛИСМАН ГРИФОНА §7[§e✩✩✩✩§7]",
                "price" => 250,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],

            "§r(#0005)" => [
                "name" => "§r§l§7§dТАЛИСМАН ДРАКОНА §7[§e✩✩✩✩✩§7]",
                "price" => 300,
                "id_item" => "450",
                "data" => 0,
                "quantity" => 1,
            ],
        ];

        foreach ($itembuydonate as $itemNameDonate => $info) {
            if (strpos($itemName, $itemNameDonate) === false) {
                continue;
            }

            $itemKey = $info['price'];
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($playerRub >= $info['price']) {
                Loader::getInstance()->remRub($player, $info['price']);
                $player->getInventory()->addItem(Item::get((int)$info["id_item"], (int)$info['data'], $info['quantity'])->setCustomName($info['name']));
                //Shop::recordOpening($player->getName(), $info['name'], $info['price'], date('d.m H:i'), $info['id_item'], $info['data']);
            } else {
                $notEnough = number_format($info['price'] - $playerRub);
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . " §cрублей"));
                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . " §rрублей");
            }
        }

        if (strpos($itemName, "Шары") !== false) {
            $itemKey = "shar";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);

            $inventory->setItem(20, Item::get(397, 0, 1)->setCustomName("§r§l§bШАР ЗЕВСА §7х1 §r(#3001)\n\n§rЦена§7: §a100 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость II\n §r§7- §aСила I\n §r§7- §cЗамедление I\n §r§7- §cПрыгучесть II\n\n§7(§7C шансом §a5%§7 при ударе игрока\nего ударит молния и выдаст отравление§7.\nШар должен §aнаходится§7 в левой руке\nчтобы §aспособности§7 работали!§7)\n\n§r§7Нажмите дважды, чтобы купить!"));
            $inventory->setItem(21, Item::get(397, 2, 1)->setCustomName("§r§l§dШАР ДРАКУЛЫ §7х1 §r(#3002)\n\n§rЦена§7: §a150 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость II\n §r§7- §aСила I\n §r§7- §cЗамедление I\n §r§7- §cГолод I\n\n§7(§7C шансом §a5%§7 при ударе игрока\nему выдаст отравление на 2-3с§7.\nШар должен §aнаходится§7 в левой руке\nчтобы §aспособности§7 работали!§7)\n\n§r§7Нажмите дважды, чтобы купить!"));
            $inventory->setItem(22, Item::get(397, 4, 1)->setCustomName("§r§l§eШАР АИДА §7х1 §r(#3003)\n\n§rЦена§7: §a175 §rрублей§r\n\n§rСпособность шара§7:\n §r§7- §aСкорость II\n §r§7- §aПрыгучесть I\n §r§7- §aРегенерация I\n §r§7- §cЗамедление I\n §r§7- §cГолод I\n\n§7(§7C шансом §a10%§7 при ударе игрока\nего подожгёт, и выдаст отравление§7\nШар должен §aнаходится§7 в левой руке\nчтобы §aспособности§7 работали!§7)\n\n§r§7Нажмите дважды, чтобы купить!"));
            $inventory->setItem(23, Item::get(397, 4, 1)->setCustomName("§r§l§cШАР МОРОЗА §7х1 §r(#3004)\n\n§rЦена§7: §a235 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость II\n §r§7- §aСила I\n §r§7- §aПлавное падение I\n §r§7- §cЗамедление I\n §r§7- §cГолод I\n\n§7(§7C шансом §a5%§7 при ударе игрока\nему выдается замедление§7.\nШар должен §aнаходится§7 в левой руке\nчтобы §aспособности§7 работали!§7)\n\n§r§7Нажмите дважды, чтобы купить!"));
            //$inventory->setItem(24, Item::get(397, 1, 1)->setCustomName("§r§l§7???\n\n§rЦена§7: §7???\n\n§rСпособность шара §7:\n  §7???\n\n§r§7Скоро будет доступен!"));

            $inventory->setItem(24, Item::get(397, 1, 1)->setCustomName("§r§l§aШАР ПАТРИКА §7х1 §r(#3006)\n\n§rЦена§7: §a350 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость II\n §r§7- §aСила I\n §r§7- §aРегенерация I\n §r§7- §aСпешка\n\n§7(§7C шансом §a5%§7 при ударе игрока\nему выдается слепота, и, отравление§7.\nШар должен §aнаходится§7 в левой руке\nчтобы §aспособности§7 работали!§7)\n\n§r§7Нажмите дважды, чтобы купить!"));
            //$inventory->setItem(32, Item::get(397, 2, 1)->setCustomName("§r§l§cШАР САНТЫ §7х1 §r(#3007)\n\n§rЦена§7: §a350 §rрублей\n\n§rСпособность шара §7:\n  §cСкоро сделаю\n\n§r§7Нажмите дважды, чтобы купить!"));

        }

        $itembuysphere = [
            "§r(#3001)" => [
                "name" => "§r§l§bШАР ЗЕВСА",
                "price" => 100,
                "id_item" => "397",
                "data" => 0,
            ],
            "§r(#3002)" => [
                "name" => "§r§l§dШАР ДРАКУЛЫ",
                "price" => 150,
                "id_item" => "397",
                "data" => 2,
            ],
            "§r(#3003)" => [
                "name" => "§r§l§eШАР АИДА",
                "price" => 175,
                "id_item" => "397",
                "data" => 4,
            ],
            "§r(#3004)" => [
                "name" => "§r§l§cШАР МОРОЗА",
                "price" => 235,
                "id_item" => "397",
                "data" => 4,
            ],
            "§r(#3005)" => [
                "name" => "§r§l§aШАР НЕПТУН",
                "price" => 275,
                "id_item" => "397",
                "data" => 1,
            ],
            "§r(#3006)" => [
                "name" => "§r§l§aШАР ПАТРИКА",
                "price" => 350,
                "id_item" => "397",
                "data" => 2,
            ],
            "§r(#3007)" => [
                "name" => "§r§l§cШАР САНТЫ",
                "price" => 350,
                "id_item" => "397",
                "data" => 2,
            ],
        ];

        foreach ($itembuysphere as $itemNameSphere => $info) {
            if (strpos($itemName, $itemNameSphere) === false) {
                continue;
            }

            $itemKey = $info['price'];
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($playerRub >= $info['price']) {
                Loader::getInstance()->remRub($player, $info['price']);
                $player->getInventory()->addItem(Item::get((int)$info["id_item"], (int)$info['data'], 1)->setCustomName($info['name']));
                //Shop::recordOpening($player->getName(), $info['name'], $info['price'], date('d.m H:i'), $info['id_item'], $info['data']);
            } else {
                $notEnough = number_format($info['price'] - $playerRub);
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . " §cрублей"));
                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . " §rрублей");
            }
        }

        if (strpos($itemName, "Донатные Головы") !== false) {
            $itemKey = "skull";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(22, Item::get(0, 0, 1));
            $inventory->setItem(23, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);

            $inventory->setItem(20, Item::get(397, 5, 1)->setCustomName("§r§l§dГОЛОВА ДРАКОНА §7х1 §r(#8301)\n\n§rЦена§7: §a250 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость I\n §r§7- §aСила II\n §r§7- §cЗамедление II\n\n§r§7Нажмите дважды, чтобы купить!"));
            $inventory->setItem(21, Item::get(397, 3, 1)->setCustomName("§r§l§bГОЛОВА СТИВА §7х1 §r(#8302)\n\n§rЦена§7: §a399 §rрублей\n\n§rСпособность шара§7:\n §r§7- §aСкорость I\n §r§7- §aСила III\n §r§7- §cЗамедление II\n §r§7- §cГолод I\n\n§r§7Нажмите дважды, чтобы купить!"));

        }

        $itembuyskulls = [
            "§r(#8301)" => [
                "name" => "§r§l§dГОЛОВА ДРАКОНА",
                "price" => 250,
                "id_item" => "397",
                "data" => 5,
            ],
            "§r(#8302)" => [
                "name" => "§r§l§bГОЛОВА СТИВА",
                "price" => 399,
                "id_item" => "397",
                "data" => 3,
            ],
        ];

        foreach ($itembuyskulls as $itemNameSkulls => $info) {
            if (strpos($itemName, $itemNameSkulls) === false) {
                continue;
            }

            $itemKey = $info['price'];
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($playerRub >= $info['price']) {
                Loader::getInstance()->remRub($player, $info['price']);
                $player->getInventory()->addItem(Item::get((int)$info["id_item"], (int)$info['data'], 1)->setCustomName($info['name']));
                //Shop::recordOpening($player->getName(), $info['name'], $info['price'], date('d.m H:i'), $info['id_item'], $info['data']);
            } else {
                $notEnough = number_format($info['price'] - $playerRub);
                $inventory->setItem($event->getSlot(), Item::get(159, 14, 1)->setCustomName("§r§cУ вас недостаточно средств!\n§cТебе не хватает §a" . $notEnough . " §cрублей"));
                $player->sendMessage("§l§6➛ §rТебе не хватает §a" . $notEnough . " §rрублей");
            }
        }

        if (strpos($itemName, "Талисманы") !== false) {
            $itemKey = "talisman";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);

            $inventory->setItem(20, Item::get(450, 0, 1)->setCustomName("§r§l§7§aТАЛИСМАН ЧЕРЕПАХИ §7[§e✩§7] §r(#0001)")->setLore(["\n§rЦена:§a 75 §rрублей\n\n§rСпособность талисмана§7:\n §r§7- §aСила II\n §r§7- §aРегенерация\n §r§7- §cСопротивление\n §r§7- §cМедлительность II\n\n§r§7Нажмите дважды, чтобы купить!"]));
            $inventory->setItem(21, Item::get(450, 0, 1)->setCustomName("§r§l§7§6ТАЛИСМАН ГЕПАРДА §7[§e✩✩§7] §r(#0002)")->setLore(["\n§rЦена:§a 125 §rрублей\n\n§rСпособность талисмана§7:\n §r§7- §aСкорость III\n §r§7- §aСила I\n §r§7- §aРегенерация\n §r§7- §cОтравление\n §r§7- §cГолод\n\n§r§7Нажмите дважды, чтобы купить!"]));
            $inventory->setItem(22, Item::get(450, 0, 1)->setCustomName("§r§l§7§cТАЛИСМАН БЕРСЕРКА §7[§e✩✩✩§7] §r(#0003)")->setLore(["\n§rЦена:§a 180 §rрублей\n\n§rСпособность талисмана§7:\n §r§7- §aСила III\n §r§7- §aОгнейстойкость\n §r§7- §cИссушение\n §r§7- §cЗамедление\n\n§r§7Нажмите дважды, чтобы купить!"]));
            $inventory->setItem(23, Item::get(450, 0, 1)->setCustomName("§r§l§7§eТАЛИСМАН ГРИФОНА §7[§e✩✩✩✩§7] §r(#0004)")->setLore(["\n§rЦена:§a 250 §rрублей\n\n§rСпособность талисмана§7:\n §r§7- §aСила II\n §r§7- §aСкорость I\n §r§7- §aРегенерация\n §r§7- §aОгнейстойкость\n §r§7- §cГолод\n §r§7- §cИссушение\n\n§r§7Нажмите дважды, чтобы купить!"]));
            $inventory->setItem(24, Item::get(450, 0, 1)->setCustomName("§r§l§7§dТАЛИСМАН ДРАКОНА §7[§e✩✩✩✩✩§7] §r(#0005)")->setLore(["\n§rЦена:§a 300 §rрублей\n\n§rСпособность талисмана§7:\n §r§7- §aСила II\n §r§7- §aСкорость II\n §r§7- §aРегенерация\n §r§7- §aОгнейстойкость\n §r§7- §cИссушение\n §r§7- §cЗамедление\n\n§r§7Нажмите дважды, чтобы купить!"]));

        }

        if (strpos($itemName, "Магазин вещей") !== false) {
            $itemKey = "shopitem";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
                "36-44" => Item::get(102),
                46 => Item::get(Item::CHEST)->setCustomName("§r§l§aМагазин вещей\n\n§rПрикупи себе §aвещей§r\nдля §aсражения§r, или для\nпостройки §aчего-либо§r!\n\n§7Нажмите дважды, чтобы перейти!"),
                47 => Item::get(Item::TOTEM)->setCustomName("§r§l§cТалисманы§r\n\n§7Нажмите дважды, чтобы перейти!"),
                52 => Item::get(Item::SKULL)->setCustomName("§r§l§aШары§r\n\n§7Нажмите дважды, чтобы перейти!"),
                51 => Item::get(Item::EMERALD)->setCustomName("§r§l§aРазное§r\n\n§7Нажмите дважды, чтобы перейти!"),
                50 => Item::get(311)->setCustomName("§r§l§bДонатная Броня§r\n\n§7Нажмите дважды, чтобы перейти!"),
                48 => Item::get(397, 5, 1)->setCustomName("§r§l§bДонатные Головы§r\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);

            $inventory->setItem(24, Item::get(0, 0, 1));
            $inventory->setItem(40, Item::get(0, 0, 1));
            $inventory->setItem(30, Item::get(0, 0, 1)); 
$inventory->setItem(19, Item::get(0, 0, 1)); 
            $inventory->setItem(20, Item::get(0, 0, 1));
            $inventory->setItem(32, Item::get(0, 0, 1));
            $inventory->setItem(12, Item::get(0, 0, 1));

            $inventory->setItem(24, Item::get(0, 0, 1));

            $inventory->setItem(21, Item::get(0, 0, 1));
            $inventory->setItem(22, Item::get(0, 0, 1));
            $inventory->setItem(23, Item::get(0, 0, 1));
            $inventory->setItem(24, Item::get(0, 0, 1));

            $item = Item::get(450, 0, 1);
            $item->setCustomName("§r§l§f{$item->getName()} §7x1§r\n\n§rЦена§7: §a30,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(9, $item);

            $item = Item::get(368, 0, 4);
            $item->setCustomName("§r§l§f{$item->getName()} §7x4§r\n\n§rЦена§7: §a12,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(10, $item);

            $item = Item::get(396, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a15,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(11, $item);

            $item = Item::get(364, 0, 32);
            $item->setCustomName("§r§l§f{$item->getName()} §7x32§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(12, $item);

            $item = Item::get(466, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a50,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(13, $item);

            $item = Item::get(322, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a35,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(14, $item);

            //СЕМЕНА
            $item = Item::get(295, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(15, $item);

            $item = Item::get(361, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(16, $item);

            $item = Item::get(362, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(17, $item);

            $item = Item::get(458, 0, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(18, $item);

            $item = Item::get(351, 3, 8);
            $item->setCustomName("§r§l§f{$item->getName()} §7x8§r\n\n§rЦена§7: §a8,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(19, $item);

            //shulkers
            $item = Item::get(218, 0, 1);
            $item->setCustomName("§r§l§f{$item->getName()} §7x1§r\n\n§rЦена§7: §a100,000§2$\n\n§7Нажмите дважды, чтобы купить!");
            $inventory->setItem(20, $item);
        }
    }
}