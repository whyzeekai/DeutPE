<?php

declare(strict_types=1);

namespace api\event;

use api\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\item\Item;

use pocketmine\event\Listener;

use pocketmine\entity\Effect;

use pocketmine\sound\Sound;
use pocketmine\sound\AnvilUseSound;

use pocketmine\math\Vector3;

use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\block\Block;

use pocketmine\event\player\PlayerCommandPreprocessEvent;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\inventory\{
    InventoryOpenEvent,
    InventoryPickupItemEvent,
    InventoryClickEvent,
    InventoryCloseEvent
};

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerBucketEmptyEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

use pocketmine\event\entity\EntitySpawnEvent;

use pocketmine\entity\{Human, Witch, Villager};
use pocketmine\entity\Entity;

use pocketmine\event\block\SignChangeEvent;

use pocketmine\scheduler\CallbackTask;
use pocketmine\scheduler\Task;


use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\event\level\ChunkPopulateEvent;

use pocketmine\level\particle\{DestroyBlockParticle, DustParticle};

use pocketmine\utils\Utils;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;

class Cases implements Listener {
    /** @var Loader */
    private $loader;
    private $topreward;

    public $check = true;

    private $tapchest = [];
    private $chestPositions;
    public $lastOpenings = [];
    
    /** @var array */
    private $itemClicks = [];

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function recordOpening($playerName, $caseName, $reward, $time) {
        global $lastOpenings;
        array_unshift($this->lastOpenings, [
            'player' => $playerName,
            'case' => $caseName,
            'reward' => $reward,
            'time' => $time        
        ]);
        if (count($this->lastOpenings) > 7) {
            array_pop($this->lastOpenings);
        }
    }

    public function openMoneyCase($player, $sec = 5){
        $x = 49;
            $y = 36;
            $z = -13;
        $p = $player;

        if ($sec == 5) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openMoneyCase"], [$player, 4]), 20 * 2);
            $this->check = false;
            $this->changeNazad($player, $x, $y - 2, $z, 4);  
            Loader::getInstance()->casetext->setTitle("");
            Loader::getInstance()->casetext1->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);   
        }

        if ($sec == 4) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openMoneyCase"], [$player, 3]), 20 * 2);  
            $this->changeBlocksMoney($player, $x, $y - 2, $z, 1); 
        }

        if ($sec == 3) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openMoneyCase"], [$player, 2]), 20 * 2);  
             $this->changeBlocksMoney($player, $x, $y - 2, $z, 3); 
        }

        if ($sec == 2) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openMoneyCase"], [$player, 1]), 20 * 2);  
            $this->changeBlocksMoney($player, $x, $y - 2, $z, 4); 
            $text = array("25000", "50000", "75000", "100000", "125000", "150000", "175000", "200000", "250000");
            $rand = array_rand($text);
            $color = $text[$rand];

            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            Loader::getInstance()->addMoney($playerObject, $color);
            Cases::recordOpening($player, "§r§l§eДенежный§7-§eКейс", $color, date('d.m H:i'));


            Loader::getInstance()->casetext1->setTitle("§7> §rВыпало §a" . number_format((int)$color) . "§r⩐ §7<§r");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

            Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$player} §rвыиграл§a " . number_format((int)$color) . "§r⩐ §rв §r§l§eДенежнем§7-§eКейсе§r\n§r §rУспей купить кейсы, на нашем сайте: " . Loader::WEBSITE . "\n\n");
        }

        if ($sec == 1) {
           $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "openMoneyCase"], [$player, 0]), 20 * 1);            
            $this->check = true;
            $this->changeNazad($player, $x, $y - 2, $z, 4);  
            Loader::getInstance()->casetext->setTitle("§l§aКЕЙСЫ");
            Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }
    }

    public function onHandlePlayerInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $x = round($block->getX());
        $y = round($block->getY());
        $z = round($block->getZ());
        $playerName = $player->getName();
        $position = new Vector3($block->getX(), $block->getY(), $block->getZ());
        $slotIndex = 36;
        $topUsers = Loader::getInstance()->topreward->getAll();

        //$player->sendMessage("$x $y $z");

        if ($x == 49 && $y == 36 && $z == -13) {
            $event->setCancelled();

            if ($player->isCreative()) {
                $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                return;
            }

            if ($this->check === false) {
                $event->setCancelled();
                $player->sendMessage("§l§aКЕЙС §8:: §rПодождите кейс уже кто-то открывает!");
                return;
            }

            $chest = ChestAPI::getInstance()->openChest($player, [
                "0-8" => Item::get(102),
                "45-54" => Item::get(102),
            ], "§a§lКЕЙСЫ §8:: §rУдобная система кейсов!", ChestAPI::DOUBLE_CHEST);
            
            if (is_bool($chest)) return;
            $inventory = $chest["inventory"];

            $startSlot = 9;
            $cases = [
                [
                    'name' => '§r§l§aДонат§7-§aКейс§r',
                    'count' => Loader::getInstance()->getDonateKey($player),
                    'id' => 218,
                    'meta' => 5,
                ],

                [
                    'name' => '§r§l§bПрефикс§7-§bКейс§r',
                    'count' => Loader::getInstance()->getPrefixKey($player),
                    'id' => 218,
                    'meta' => 3,
                ],
                [
                    'name' => '§r§l§eВесенний§7-§eКейс§r',
                    'count' => Loader::getInstance()->getNewyearKey($player),
                    'id' => 218,
                    'meta' => 4,
                ],
                [
                    'name' => '§r§l§eДенежный§7-§eКейс§r',
                    'count' => Loader::getInstance()->getMoneyKey($player),
                    'id' => 218,
                    'meta' => 1,
                ],
            ];

            $hasCases = false;

            foreach ($cases as $case) {
                $caseCount = $case['count'];
                if ($caseCount > 0) {
                    $hasCases = true;
                    for ($i = 0; $i < $caseCount; $i++) {
                        $inventory->setItem($startSlot, 
                            Item::get($case['id'], $case['meta'], 1)->setCustomName("{$case['name']}\n\n §rУ вас есть §aкейс§r\n Вы §aможете§r его открыть.\n\n§r§7Нажмите дважды, чтобы открыть!"));
                        $startSlot++; 
                    }
                }
            }

            if (!$hasCases) {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cУ вас нет кейсов!\n\n§rНаш сайт ". Loader::WEBSITE));
            }

            $menuSlot = 46; 
            if (count($this->lastOpenings) > 0) {
                foreach ($this->lastOpenings as $entry) {
                    $text = "§rИгрок: §a{$entry['player']}\n" .
                    "§rКейс: {$entry['case']}\n" .
                    "§rВыпало: §a{$entry['reward']}\n" .
                    "§rВремя: §7{$entry['time']}";
                    $inventory->setItem($menuSlot++, Item::get(340, 0, 1)->setCustomName($text));
                }
            } else {
                $inventory->setItem(49, Item::get(218, 14, 1)->setCustomName("§r§cНет данных о последних открытиях."));
            }

            /*$inventory->setItem(19, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§aДонат§7-§aКейс§r\n\n§rУ вас §a" . Loader::getInstance()->getDonateKey($player) . "§r кейсов!\n\n§rВыпадает от§7:\n§l§dКроноса§r до §l§cЛюцифера§r\n\n§7Нажмите дважды, чтобы открыть!")); 
            $inventory->setItem(21, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§bПрефикс§7-§bКейс§r\n\n§rУ вас §a" . Loader::getInstance()->getPrefixKey($player) . "§r кейсов!\n\n§rВыпадает от§7:\n§l§aНубик§r до §l§6OG Buda§r\n\n§7Нажмите дважды, чтобы открыть!"));   
            $inventory->setItem(23, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§eВесенний§7-§eКейс§r\n\n§rУ вас §a" . Loader::getInstance()->getNewyearKey($player) . "§r кейсов!\n\n§rВыпадает от§7:\n§l§dКороль§r до §l§cPatrik§r\n\n§7Нажмите дважды, чтобы открыть!"));  
            $inventory->setItem(25, Item::get(Item::ENDER_CHEST)->setCustomName("§r§l§eДенежный§7-§eКейс§r\n\n§rУ вас §a" . Loader::getInstance()->getMoneyKey($player) . "§r кейсов!\n\n§rВыпадает от§7:\n§l§e25,000§r⩐§r до §l§e250,000§r⩐§r\n\n§7Нажмите дважды, чтобы открыть!"));  
            */
        }
    }


    public function onHandleInventoryClick(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        if (strpos($itemName, "§r§l§eДенежный§7-§eКейс§r") !== false) {
            $itemKey = "moneycase";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if (Loader::getInstance()->getMoneyKey($player) >= 1) {
                Loader::getInstance()->remMoneyKey($player, 1);
                $this->check = false;
                ChestAPI::getInstance()->closeInventory($player);
                $this->openMoneyCase($player->getName());
            }else{
                $player->sendMessage("§l§aКЕЙС §8:: §rУ вас нету кейсов, купить можно на ". Loader::WEBSITE);
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if (strpos($itemName, "§r§l§eВесенний§7-§eКейс§r") !== false) {
            $itemKey = "newyearcase";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $x = 49;
            $y = 36;
            $z = -13;

            if (Loader::getInstance()->getNewyearKey($player) >= 1) {
                Loader::getInstance()->remNewyearKey($player, 1);
                $this->check = false;
                ChestAPI::getInstance()->closeInventory($player);
                $this->onTimerNC($player->getName());
            }else{
                $player->sendMessage("§l§aКЕЙС §8:: §rУ вас нету кейсов, купить можно на ". Loader::WEBSITE);
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if (strpos($itemName, "§r§l§aДонат§7-§aКейс§r") !== false) {
            $itemKey = "donatecase";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $x = 49;
            $y = 36;
            $z = -13;

            if (Loader::getInstance()->getDonateKey($player) >= 1) {
                Loader::getInstance()->remDonateKey($player, 1);
                $this->check = false;
                ChestAPI::getInstance()->closeInventory($player);
                $this->onTimerDC($player->getName());
            }else{
                $player->sendMessage("§l§aКЕЙС §8:: §rУ вас нету кейсов, купить можно на ". Loader::WEBSITE);
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if (strpos($itemName, "§r§l§bПрефикс§7-§bКейс§r") !== false) {
            $itemKey = "moneycase";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $x = 49;
            $y = 36;
            $z = -13;

            if (Loader::getInstance()->getPrefixKey($player) >= 1) {
                Loader::getInstance()->remPrefixKey($player, 1);
                $this->check = false;
                ChestAPI::getInstance()->closeInventory($player);
                $this->onTimerPREFIX($player->getName());

            }else{
                $player->sendMessage("§l§aКЕЙС §8:: §rУ вас нету кейсов, купить можно на ". Loader::WEBSITE);
                ChestAPI::getInstance()->closeInventory($player);
            }
        }
    }

    public function onTimerNC($player, $sec = 10) {
        $x = 49;
        $y = 36;
        $z = -13;

        if ($sec == 10) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 9]), 20 * 1);
            $this->check = false;
            
        }

        if ($sec == 9) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 8]), 20 * 1);

            Loader::getInstance()->casetext->setTitle("");
            Loader::getInstance()->casetext1->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }

        if ($sec == 8) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 7]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);

        }

        if ($sec == 7) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 6]), 20 * 1);
            
        }

        if ($sec == 6) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 5]), 20 * 1);
            $this->changeBlocksSnow($player, $x, $y - 2, $z, 1);
            
            
        }

        if ($sec == 5) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 4]), 20 * 1);
            $this->changeBlocksSnow($player, $x, $y - 2, $z, 2);
            
        }

        if ($sec == 4) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 3]), 20 * 1);
            $this->changeBlocksSnow($player, $x, $y - 2, $z, 3);
            
            
        }

        if ($sec == 3) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 2]), 20 * 1);
            $this->changeBlocksSnow($player, $x, $y - 2, $z, 4);
            
            
        }

        if ($sec == 2) {

            $dropChance = rand(1, 100);

            if ($dropChance <= 100) {
                $item = "Korol";
                $name = "§l§dКороля§r";
                $higherGroups = ["Gresh", "Knayz", "Patrik", "Helper", "Admin", "Moderator"];
            } elseif ($dropChance <= 5) {
                $item = "Gresh";
                $name = "§l§bЦарь§r";
                $higherGroups = ["Knayz", "Patrik", "Helper", "Admin", "Moderator"];
            } elseif ($dropChance <= 3) {
                $item = "Knayz";
                $name = "§l§cКнязя§r";
                $higherGroups = ["Patrik", "Helper", "Admin", "Moderator"];
            } else {
                $item = "Patrik"; 
                $name = "§l§aПАТРИКА§r";
                $higherGroups = ["Helper", "Admin", "Moderator"];
            }

            $playerGroup = Loader::getInstance()->getGroup($player);
            $playerObject = Loader::getInstance()->getServer()->getPlayer($player);

            if ($playerObject !== null && in_array($playerGroup, $higherGroups)) {
                $coins = mt_rand(100, 150);
                $playerObject->sendMessage("§l§aКЕЙС §8:: §rВам выпал {$name}§r, у вас имеется §aпривилегия§r выше, вам выдано §a{$coins}§rруб.§r");
                Loader::getInstance()->addRub($playerObject, $coins);
                Loader::getInstance()->casetext1->setTitle("§7> §rВыпала §a{$coins}§r рублей! §r§7<");
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

                Cases::recordOpening($player, '§r§l§eВесенний§7-§eКейс§r', $name, date('d.m H:i'));

                $this->changeBlocksSnow($player, $x, $y - 2, $z, 4);
                
                $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 1]), 20 * 3);

                return;
            }
            $day = "навсегда";

            if (!in_array($playerGroup, $higherGroups)) {
                Loader::getInstance()->setGroup($player, $item, $day);
                Loader::getInstance()->casetext1->setTitle("§7> §rВыпала привилегия {$name} §r§7<");
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

                Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$player} §rвыиграл§a {$name} §r§7[НАВСЕГДА] §rв §r§l§eВесеннем§7-§eКейсе§r\n§r §rУспей купить кейсы, на нашем сайте: " . Loader::WEBSITE . "\n\n");
            }

            $this->changeBlocksSnow($player, $x, $y - 2, $z, 4);
            
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 1]), 20 * 3);
        }

        if ($sec == 1) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerNC"], [$player, 0]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);
            
            
            $this->check = true;
            $position = new Vector3($x, $y, $z);
            $belowPosition = new Vector3($x, $y - 1, $z);
            Loader::getInstance()->casetext->setTitle("§l§aКЕЙСЫ");
            Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }
    }

    public function onTimerPREFIX($player, $sec = 5){
        $x = 49;
            $y = 36;
            $z = -13;

        if ($sec == 5) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerPREFIX"], [$player, 4]), 20 * 2);
            $this->check = false;
            $this->changeNazad($player, $x, $y - 2, $z, 4);  

            Loader::getInstance()->casetext->setTitle("");
            Loader::getInstance()->casetext1->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);   
        }

        if ($sec == 4) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerPREFIX"], [$player, 3]), 20 * 2);  
            $this->changeBlocksPrefix($player, $x, $y - 2, $z, 2);        
        }

        if ($sec == 3) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerPREFIX"], [$player, 2]), 20 * 2);  
            $this->changeBlocksPrefix($player, $x, $y - 2, $z, 4);    
        }

        if ($sec == 2) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerPREFIX"], [$player, 1]), 20 * 2);  
            $this->changeBlocksPrefix($player, $x, $y - 2, $z, 4);   
            $text = array("§l§aНубик§r", "§l§6OGBuda§r", "§l§cSIGMA§r", "§l§aДОНАТЕР§r", "§l§eСКУФ§r", "§l§dРОЗОВЫЙ§r", "§l§dСЧАСТЛИВЫЙ", "§l§eOLD", "§l§bШАХТЕР", "§l§cLOVE", "§l§eВЕСНА", "§l§3СТАРШИЙ", "§l§bПРОФИ", "§l§cЛООООООЛ", "§l§6ЛЕГЕНДА", "§l§cЯ ЮТУБЕР", "§l§dСПАТЬ", "§l§4#MEETWIX", "§l§9КРОЛИК", "§l§eСОЛНЦЕ", "§l§bКТО Я?");
            $rand = array_rand($text);
            $color = $text[$rand];

            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            Loader::getInstance()->giveTitle($playerObject, $color);
            Cases::recordOpening($player, '§r§l§bПрефикс§7-§bКейс§r', $color, date('d.m H:i'));

            Loader::getInstance()->casetext1->setTitle("§7> §rВыпал префикс {$color} §7<§r");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

            Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$player} §rвыиграл префикс§a {$color} §rв §r§l§bПрефикс§7-§bКейсе§r\n§r §rУспей купить кейсы, на нашем сайте: " . Loader::WEBSITE . "\n\n");
        }

        if ($sec == 1) {
           $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerPREFIX"], [$player, 0]), 20 * 1);            
            $this->check = true;
            $this->changeNazad($player, $x, $y - 2, $z, 4);
            Loader::getInstance()->casetext->setTitle("§l§aКЕЙСЫ");
            Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }
    }

    public function onTimerMC($player, $sec = 10) {
        $x = 49;
            $y = 36;
            $z = -13;

        if ($sec == 10) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 9]), 20 * 1);
            $this->check = false;
            
        }

        if ($sec == 9) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 8]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);

            $position = new Vector3($x, $y, $z);
            $belowPosition = new Vector3($x, $y - 1, $z);
            Server::getInstance()->getLevelByName("spawn")->setBlock($position, Block::get(0), true);
            Server::getInstance()->getLevelByName("spawn")->setBlock($belowPosition, Block::get(0), true);

            Loader::getInstance()->casetext->setTitle("");
            Loader::getInstance()->casetext1->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

        }

        if ($sec == 8) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 7]), 20 * 2);
            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            if ($playerObject !== null) {
                $playerObject->teleport(new Position($x, $y - 1, $z, Server::getInstance()->getDefaultLevel()));
            }
        }

        if ($sec == 7) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 6]), 20 * 1);

            $level = Server::getInstance()->getLevelByName("spawn");
            $level->setBlock(new Vector3(-12, 33, -30), Block::get(Block::ENDER_CHEST), true);
            $level->setBlock(new Vector3(-9, 33, -33), Block::get(Block::ENDER_CHEST), true);
            $level->setBlock(new Vector3(-6, 33, -30), Block::get(Block::ENDER_CHEST), true);
            $level->setBlock(new Vector3(-9, 33, -27), Block::get(Block::ENDER_CHEST), true);

        }

        if ($sec == 6) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 5]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);

            Loader::getInstance()->flytextcase1->setTitle("§r§7???");
            Loader::getInstance()->flytextcase2->setTitle("§r§7???");
            Loader::getInstance()->flytextcase3->setTitle("§r§7???");
            Loader::getInstance()->flytextcase4->setTitle("§r§7???");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase1);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase2);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase3);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase4);

            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            if ($playerObject !== null) {
                $playerObject->sendMessage("§l§aКЕЙС §8:: §rВыберите любой сундук, у вас 15 секунд!");
            }
            
        }

        if ($sec == 5) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 4]), 20 * 1);
        }

        if ($sec == 4) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 3]), 20 * 2);
            $this->changeNazad($player, $x, $y - 2, $z, 4);
            
        }

        if ($sec == 3) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 2]), 20 * 15);

            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            if ($playerObject !== null) {
                $playerObject->sendMessage("§l§aКЕЙС §8:: §rВыберите любой сундук, у вас 15 секунд!");
            }
        }

        if ($sec == 2) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 1]), 20 * 1);
            $playerName = $player; 
            $playerObject = Server::getInstance()->getPlayer($playerName);

            if ($playerObject !== null) {
                $playerObject->teleport(new Position(-8, 33, -24, Server::getInstance()->getDefaultLevel()));
            }

            Loader::getInstance()->flytextcase1->setTitle("");
            Loader::getInstance()->flytextcase2->setTitle("");
            Loader::getInstance()->flytextcase3->setTitle("");
            Loader::getInstance()->flytextcase4->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase1);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase2);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase3);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->flytextcase4);

            $level = Server::getInstance()->getLevelByName("spawn");
            $level->setBlock(new Vector3(-12, 33, -30), Block::get(0), true);
            $level->setBlock(new Vector3(-9, 33, -33), Block::get(0), true);
            $level->setBlock(new Vector3(-6, 33, -30), Block::get(0), true);
            $level->setBlock(new Vector3(-9, 33, -27), Block::get(0), true);
        }

        if ($sec == 1) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerMC"], [$player, 0]), 20 * 1);            
            $this->check = true;
            $position = new Vector3($x, $y, $z);
            $belowPosition = new Vector3($x, $y - 1, $z);
            Server::getInstance()->getLevelByName("spawn")->setBlock($position, Block::get(Block::ENDER_CHEST), true);
            Server::getInstance()->getLevelByName("spawn")->setBlock($belowPosition, Block::get(Block::STONE_SLAB), true);

            Loader::getInstance()->casetext->setTitle("§l§aКЕЙСЫ");
            Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }
    }

    public function onTimerDC($player, $sec = 10) {
        $x = 49;
            $y = 36;
            $z = -13;

        if ($sec == 10) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 9]), 20 * 1);
            $this->check = false;
            
        }

        if ($sec == 9) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 8]), 20 * 1);

            Loader::getInstance()->casetext->setTitle("");
            Loader::getInstance()->casetext1->setTitle("");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }

        if ($sec == 8) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 7]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);

        }

        if ($sec == 7) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 6]), 20 * 1);
            
        }

        if ($sec == 6) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 5]), 20 * 1);
            $this->changeBlocks($player, $x, $y - 2, $z, 1);
            
            
        }

        if ($sec == 5) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 4]), 20 * 1);
            $this->changeBlocks($player, $x, $y - 2, $z, 2);
            
        }

        if ($sec == 4) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 3]), 20 * 1);
            $this->changeBlocks($player, $x, $y - 2, $z, 3);
            
            
        }

        if ($sec == 3) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 2]), 20 * 1);
            $this->changeBlocks($player, $x, $y - 2, $z, 4);
            
            
        }

        if ($sec == 2) {

            $dropChance = rand(1, 100);

            if ($dropChance <= 50) {
                $item = "Kronos";
                $name = "§l§dКронос";
                $higherGroups = ["Aristocrat", "Lucefer", "Gresh", "Knayz", "Patrik", "Helper", "Admin", "Moderator"];
            } elseif ($dropChance <= 75) {
                $item = "Aristocrat";
                $name = "§l§6Аристократ";
                $higherGroups = ["Lucefer", "Gresh", "Knayz", "Patrik", "Helper", "Admin", "Moderator"];
            } else {
                $item = "Lucefer"; 
                $name = "§l§cЛюцифер";
                $higherGroups = ["Gresh", "Knayz", "Patrik", "Helper", "Admin", "Moderator"];
            }


            $daysOptions = ["7", "14", "30", "навсегда"];
            $day1 = $daysOptions[array_rand($daysOptions)];

            if ($day1 === "7") {
                $day = "§7[7д.]§r";
            }

            if ($day1 === "14") {
                $day = "§7[14д.]§r";
            }

            if ($day1 === "30") {
                $day = "§7[30д.]§r";
            }

            if ($day1 === "навсегда") {
                $day = "§7[НАВСЕГДА]§r";
            }

            $playerGroup = Loader::getInstance()->getGroup($player);
            $playerObject = Loader::getInstance()->getServer()->getPlayer($player);

            if ($playerObject !== null && in_array($playerGroup, $higherGroups)) {
                $coins = mt_rand(5, 30);
                $playerObject->sendMessage("§l§aКЕЙС §8:: §rВам выпал {$name}§r, у вас имеется §aпривилегия§r выше, вам выдано §a{$coins}§rруб.§r");
                Loader::getInstance()->addRub($playerObject, $coins);
                Loader::getInstance()->casetext1->setTitle("§7> §rВыпала §a{$coins}§r рублей! §r§7<");
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

                $this->changeBlocks($player, $x, $y - 2, $z, 4);
                
                $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 1]), 20 * 3);

                return;
            }

            if (!in_array($playerGroup, $higherGroups)) {
                Loader::getInstance()->setGroup($player, $item, $day1);
                Loader::getInstance()->casetext1->setTitle("§7> §rВыпала привилегия {$name} §r§7<");
                Cases::recordOpening($player, '§r§l§aДонат§7-§aКейс§r', $name, date('d.m H:i'));
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);

                Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$player} §rвыиграл§a {$name}а §r{$day} §rв §r§l§aДонат§7-§aКейсе§r\n§r §rУспей купить кейсы, на нашем сайте: " . Loader::WEBSITE . "\n\n");
            }

            $this->changeBlocks($player, $x, $y - 2, $z, 4);
            
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 1]), 20 * 3);
        }

        if ($sec == 1) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "onTimerDC"], [$player, 0]), 20 * 1);
            $this->changeNazad($player, $x, $y - 2, $z, 4);
            
            
            $this->check = true;
            $position = new Vector3($x, $y, $z);
            $belowPosition = new Vector3($x, $y - 1, $z);
            Loader::getInstance()->casetext->setTitle("§l§aКЕЙСЫ");
            Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        }
    }

    private function changeBlocksSnow($player, $x, $y, $z, $radius) {
        $level = Server::getInstance()->getLevelByName("spawn");

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                if ($dx * $dx + $dz * $dz <= $radius * $radius) {
                    $blockPos = new Vector3($x + $dx, $y, $z + $dz);
                    $block = Server::getInstance()->getLevelByName("spawn")->getBlock($blockPos); // исправлено

                    if (mt_rand(0, 1) === 0) {
                        if ($block->getId() !== 159 || $block->getDamage() !== 13) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(159, 13), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(159, 13)));
                        }
                    } else {
                        if ($block->getId() !== 159 || $block->getDamage() !== 5) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(159, 5), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(159, 5)));
                        }
                    }
                }
            }
        }
    }

    private function changeBlocksPrefix($player, $x, $y, $z, $radius) {
        $level = Server::getInstance()->getLevelByName("spawn");

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                if ($dx * $dx + $dz * $dz <= $radius * $radius) {
                    $blockPos = new Vector3($x + $dx, $y, $z + $dz);
                    $block = Server::getInstance()->getLevelByName("spawn")->getBlock($blockPos); // исправлено

                    if (mt_rand(0, 1) === 0) {
                        if ($block->getId() !== 237 || $block->getDamage() !== 11) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(237, 11), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(237, 11)));
                        }
                    } else {
                        if ($block->getId() !== 35 || $block->getDamage() !== 11) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(35, 11), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(35, 11)));
                        }
                    }
                }
            }
        }
    }

    private function changeBlocksMoney($player, $x, $y, $z, $radius) {
        $level = Server::getInstance()->getLevelByName("spawn");

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                if ($dx * $dx + $dz * $dz <= $radius * $radius) {
                    $blockPos = new Vector3($x + $dx, $y, $z + $dz);
                    $block = Server::getInstance()->getLevelByName("spawn")->getBlock($blockPos); // исправлено

                    if (mt_rand(0, 1) === 0) {
                        if ($block->getId() !== 237 || $block->getDamage() !== 4) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(237, 4), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(237, 4)));
                        }
                    } else {
                        if ($block->getId() !== 237 || $block->getDamage() !== 1) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(35, 1), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(35, 1)));
                        }
                    }
                }
            }
        }
    }

    private function changeBlocks($player, $x, $y, $z, $radius) {
        $level = Server::getInstance()->getLevelByName("spawn");

        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                if ($dx * $dx + $dz * $dz <= $radius * $radius) {
                    $blockPos = new Vector3($x + $dx, $y, $z + $dz);
                    $block = Server::getInstance()->getLevelByName("spawn")->getBlock($blockPos); // исправлено

                    if (mt_rand(0, 2) === 0) {
                        if ($block->getId() !== Block::PRISMARINE) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(Block::PRISMARINE), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(Block::PRISMARINE)));
                        }
                    } elseif (mt_rand(0, 1) === 0) {
                        if ($block->getId() !== 168 || $block->getDamage() !== 2) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(168, 2), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(168, 2)));
                        }
                    } else { 
                        if ($block->getId() !== 168 || $block->getDamage() !== 1) {
                            Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(168, 1), true);
                            Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(168, 1)));
                        }
                    }
                }
            }
        }
    }

    private function changeNazad($player, $x, $y, $z, $radius) {
        for ($dx = -$radius; $dx <= $radius; $dx++) {
            for ($dz = -$radius; $dz <= $radius; $dz++) {
                if ($dx * $dx + $dz * $dz <= $radius * $radius) {
                    $blockPos = new Vector3($x + $dx, $y, $z + $dz);
                    Server::getInstance()->getLevelByName("spawn")->setBlock($blockPos, Block::get(2, 0));
                    Server::getInstance()->getDefaultLevel()->addParticle(new DestroyBlockParticle($blockPos, Block::get(2, 0)));
                }
            }
        }
    }
}