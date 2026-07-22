<?php

declare(strict_types=1);

namespace api\event;

use api\Loader;
use DateTime;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\item\Item;

use pocketmine\block\Block;

use pocketmine\event\Listener;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;

use pocketmine\sound\Sound;
use pocketmine\sound\AnvilUseSound;

use pocketmine\math\Vector3;

use pocketmine\level\Level;
use pocketmine\level\Position;

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
use pocketmine\event\entity\EntityDeathEvent;

use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\entity\Arrow;
use pocketmine\entity\projectile\EnderPearl;

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

use pocketmine\event\level\ChunkLoadEvent;
use pocketmine\event\level\ChunkPopulateEvent;

use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;

//\n§r⩕ §rИгрок §a

use pocketmine\utils\Utils;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;
use api\vkapi\VKHELPER;
use \timurkaundefined\gametimer\GameTimer as GameTimer;

use pocketmine\event\server\DataPacketReceiveEvent;

use pocketmine\level\particle\DustParticle;

class Event implements Listener {

    /** @var Loader */
    private $loader;

    private $clicks = [];
    private $maxClicks = 1000;
    private $pearlCooldown = 20;
    private $itemClicks = [];

    private static $cooldownUse = [];
    private $timer = []; 
    private $cooldowns = [];

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function HandlePlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();

        if ($event->getBlock()->getId() === 145) {
            $event->setCancelled(); 

            if (Loader::getInstance()->getMoney($player) < 1000) {
                $player->sendMessage("§l§aREPAIR §8:: §rДля починки предмета нужно§a 1,000§2$");
                return; 
            }

            $item = $player->getInventory()->getItemInHand();

            if ($item->getDamage() < 1) {
                $player->sendMessage("§l§aREPAIR §8:: §rПредмет не нуждается в починке!");
                return;
            }

            $item->setDamage(0);
            $player->getInventory()->setItemInHand($item);
            Loader::getInstance()->remMoney($player, 1000); 
            $player->sendMessage("§l§aREPAIR §8:: §rПредмет успешно §aпочинен");
        }

         if ($event->getBlock()->getId() === 116) {
            $event->setCancelled(); 

            if (Loader::getInstance()->getMoney($player) < 3000) {
                $player->sendMessage("§l§dENCH §8:: §rДля §aзачарование§r предмета нужно§a 3,000§2$");
                return; 
            }

            $item = $player->getInventory()->getItemInHand();
            if ($item->isArmor() || $item->isSword() || $item->isAxe() || $item->isPickaxe() || $item instanceof Bow) {
                $this->enchantItem($player, $item);
                Loader::getInstance()->remMoney($player, 3000); 
            } elseif ($item->isHoe() || $item->isShovel()) {
                $this->enchantTool($player, $item);
                Loader::getInstance()->remMoney($player, 3000); 
            } else {
                $player->sendMessage("§l§dENCH §8:: §rЭтот предмет нельзя зачаровать!"); 
            }
        }
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event) {
        if ($event->isCancelled()) return;
        if ($event->getEntity() instanceof EnderPearl) {
            $shooter = $event->getEntity()->getOwningEntity();

            if ($shooter instanceof Player) {
                $name = strtolower($shooter->getName());

                // Проверяем кулдаун
                if (isset($this->timer[$name]) && time() < $this->timer[$name]) {
                    $time = $this->timer[$name] - time();
                    $shooter->sendTitle("§l§cКУЛДАУН", "§rПодождите ещё §a{$time}с.", 10, 30, 10);
                    $event->setCancelled(true);
                    return;
                }
                
                // Устанавливаем кулдаун после успешного броска
                $this->timer[$name] = time() + $this->pearlCooldown;
            }
        }
    }


    public function playerCommandPreprocessEvent(PlayerCommandPreprocessEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        $nick = $player->getLowerCaseName();
        $time = time();

        $command = explode(" ", $message)[0];

        if ($message[0] == "/" or $message[0] == "./") {
            if ($command == "/cp") {
                return true;
            }

            if ($command == "/adminka" or $message[0] == "./setgroup") {
                if ($player) {
                    return true;
                }
            }

          /*  if(Loader::getInstance()->checks[strtolower($player->getName())]) {
                if(strpos($command, '/') === 0) {
                    $player->sendMessage("§l§aPROVERKA §8:: §r§cВо время проверки запрещено использовать команды!");
                    $event->setCancelled(); 
                    return false; 
                }
            }
        */

            $restrictedCommands = ["/kick", "/ban", "/mute", "/pardon", "/unmute"];
            if (in_array($command, $restrictedCommands)) {
                if ($player->hasPermission("helper.system")) {
                    return true; 
                }

                $playTime = GameTimer::getGameTime($player); 
                $requiredPlayTime = 3600; 

                if ($playTime < $requiredPlayTime) {
                    $remainingTime = $requiredPlayTime - $playTime;
                    $player->sendMessage("§l§6➛ §rДля §aиспользования§r, команды §a{$command}§r нужно наиграть §a1ч. 0м.§r");
                    $event->setCancelled(); 
                    return;
                }
            }

            $onlinePlayers = Server::getInstance()->getOnlinePlayers();

            Server::getInstance()->getLogger()->info("§c[CMD] §7► §r{$player->getDisplayName()} §r§7--> §a{$message}");

            foreach ($onlinePlayers as $p) {
                if ($p->hasPermission("api.cmd.console")) {
                    if (isset(Loader::getInstance()->console[$p->getLowerCaseName()])) {
                        $p->sendMessage("§l§bCONSOLE §8::§r {$player->getDisplayName()} §r§7--> §a{$message}");
                    }
                }
            }
        }
    }

    public function handleEntityConsumeTotem(\pocketmine\event\entity\EntityConsumeTotemEvent $e) {
        $entity = $e->getEntity();

        if ($entity instanceof Player) {
            $playerName = $entity->getLowerCaseName();

            if (isset(Loader::getInstance()->autototem[$playerName]) && Loader::getInstance()->autototem[$playerName] && $entity->getInventory()->contains(Item::get(450, 0, 1))) {
                $entity->getInventory()->removeItem(Item::get(450, 0, 1));

                $this->loader->getScheduler()->scheduleDelayedTask(
                    new \pocketmine\scheduler\CallbackTask([$this, 'setTotem'], [$entity]),
                    5
                );
            }
        }
    }

    public function setTotem(Player $player) {
        $item = Item::get(450, 0, 1);

        if ($player->getInventory()->contains($item)) {
            $player->getOffHandInventory()->setItemInOffHand($item);
            $player->sendPopup("§7♨ §r§l§eАвто§7-§eТотем§r§f сработал! §7♨");
        } else {
            $player->sendPopup("§7♨ §r§fТотемы закончились! §7♨");
        }
    }

    public function onInventoryClickKits(InventoryClickEvent $event) {
        $player = $event->getWhoClicked();
        $inventory = $event->getInventory();
        
        // Проверяем, открыт ли у игрока ChestAPI сундук
        $isChestAPIMenu = false;
        if (isset(\ChestAPI\ChestAPI::$players_in_chest[strtolower($player->getName())])) {
            $chestData = \ChestAPI\ChestAPI::$players_in_chest[strtolower($player->getName())];
            $chestInventory = $chestData['inventory'] ?? null;
            
            // Если клик был по инвентарю ChestAPI, отменяем событие
            if ($chestInventory !== null && $inventory === $chestInventory) {
                $isChestAPIMenu = true;
                $event->setCancelled(true);
            }
        }
        
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());
        $currentDate = time();

        if (strpos($itemName, "#9001") !== false) {
            $this->giveKit($player, "игрок", "CAMMON", 10800);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9002") !== false) {
            $this->giveKit($player, "кронос", "CAMMON", 10800);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9003") !== false) {
            $this->giveKit($player, "аристократ", "RARY", 21600);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9004") !== false) {
            $this->giveKit($player, "люцифер", "RARY", 21600);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9005") !== false) {
            $this->giveKit($player, "король", "EPIC", 43200);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9006") !== false) {
            $this->giveKit($player, "царь", "LEGENDARY", 43200);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9007") !== false) {
            $this->giveKit($player, "князь", "LEGENDARY", 86400);
            if ($isChestAPIMenu) $event->setCancelled(true);
        } elseif (strpos($itemName, "#9008") !== false) {
            $this->giveKit($player, "патрик", "MYSTIC", 172800);
            if ($isChestAPIMenu) $event->setCancelled(true);
        }

        if (strpos($itemName, "§r§l§dУстановить редкость шахты") !== false) {
            $itemKey = "dj34fdr";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $subData = Loader::getInstance()->sub->get($player->getName());

            if (isset($subData["rarity_time"]) && ($currentDate - $subData["rarity_time"]) < 7200) {
                $remainingTime = 7200 - ($currentDate - $subData["rarity_time"]);
                $player->sendMessage("§l§aSUB §8:: §rВы можете использовать снова через§a " . Loader::getInstance()->parseTime($remainingTime) ."§r");
                return true;
            }
                            
            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                20 => Item::get(270)->setCustomName("§r§l§aОбычная"),
                21 => Item::get(274)->setCustomName("§r§l§bРедкая"),
                22 => Item::get(257)->setCustomName("§r§l§dЭпическая"),
                23 => Item::get(278)->setCustomName("§r§l§6Легендарная"),
                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aSUB§r"),
            ]);
        }elseif (strpos($itemName, "§r§l§aОбычная") !== false) {
            $itemKey = "dj12334fdr";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            Loader::getInstance()->nextRarity = 50;
            Server::getInstance()->broadcastMessage("\n§l§6➛ §rИгрок §a{$player->getName()}§r установил редкость §aшахты§r, следущая редкость§7: §l§aОбычная§r");
            $subData["rarity_time"] = $currentDate;
            Loader::getInstance()->sub->set($player->getName(), $subData);
            Loader::getInstance()->sub->save();

        }elseif (strpos($itemName, "§r§l§bРедкая") !== false) {
            $itemKey = "dj331234fdr";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            Loader::getInstance()->nextRarity = 80;
            Server::getInstance()->broadcastMessage("\n§l§6➛ §rИгрок §a{$player->getName()}§r установил редкость §aшахты§r, следущая редкость§7: §r§l§bРедкая");
            $subData["rarity_time"] = $currentDate;
            Loader::getInstance()->sub->set($player->getName(), $subData);
            Loader::getInstance()->sub->save();

        }elseif (strpos($itemName, "§r§l§dЭпическая") !== false) {
            $itemKey = "d3123j34fdr";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            Loader::getInstance()->nextRarity = 95;
            Server::getInstance()->broadcastMessage("\n§l§6➛ §rИгрок §a{$player->getName()}§r установил редкость §aшахты§r, следущая редкость§7: §r§l§dЭпическая");
            $subData["rarity_time"] = $currentDate;
            Loader::getInstance()->sub->set($player->getName(), $subData);
            Loader::getInstance()->sub->save();

        }elseif (strpos($itemName, "§r§l§6Легендарная") !== false) {
            $itemKey = "dj2131234fdr";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            Loader::getInstance()->nextRarity = 100;
            Server::getInstance()->broadcastMessage("\n§l§6➛ §rИгрок §a{$player->getName()}§r установил редкость §aшахты§r, следущая редкость§7: §r§l§6Легендарная");
            $subData["rarity_time"] = $currentDate;
            Loader::getInstance()->sub->set($player->getName(), $subData);
            Loader::getInstance()->sub->save();
        }


        if (strpos($itemName, "§r§l§aМАГАЗИН КЛАНА\n\n§rМожно купить §aвещей§r") !== false) {
            $itemKey = "Loaderhop";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),

                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aCLAN§r"),
            ]);
        }

        if (strpos($itemName, "§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r") !== false) {
            $itemKey = "menuhelper";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                "0-53" => Item::get(102),
                "10-43" => Item::get(0),

                20 => Item::get(340)->setCustomName("§r§l§aВЫДАТЬ ХЕЛПЕРА\n\n§rСтатус§7: " . ($player->hasPermission("api.helper.setting") ? "§aДоступен" : "§cНедоступен"). "\n\n§7Нажмите дважды, чтобы перейти!"),
                31 => Item::get(340)->setCustomName("§r§l§aСПИСОК ХЕЛПЕРОВ\n\n§rСтатус§7: §aДоступен\n\n§7Нажмите дважды, чтобы перейти!"),
                24 => Item::get(340)->setCustomName("§r§l§aСПИСОК ЖАЛОБ\n\n§rСтатус§7: §aДоступен\n\n§7Нажмите дважды, чтобы перейти!"),
            ]);
        }

        if (strpos($itemName, "§r§a§r§a") !== false) {
            $itemKey = "sethelper";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $playerName = str_replace("§r§a§r§a", "", $item->getCustomName());
            $targetPlayer = Server::getInstance()->getPlayer(trim($playerName));
            $helpers = Loader::getInstance()->helpers->getAll();

            if ($targetPlayer instanceof Player) {
                if (array_key_exists($targetPlayer->getName(), $helpers)) {
                    $player->sendMessage("§l§6➛ §rИгрок §a{$targetPlayer->getName()}§r уже стоит на посту §l§bХелпер§r");
                    ChestAPI::getInstance()->closeInventory($player);
                    return; 
                }

                $helper = "Helper";
                $helper1 = "навсегда";
                Loader::getInstance()->setGroup($targetPlayer->getName(), $helper, $helper1);
                        
                VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Игрока {$targetPlayer->getName()} приняли на пост хелпера!");
                
                $player->sendMessage("§l§6➛ §rВы приняли §a{$targetPlayer->getName()}§r на должность §l§aхᴇᴧᴨᴇᴩ§r!");
                $helperData["warns"] = 0;
                Loader::getInstance()->helpers->set($targetPlayer->getName(), $helperData);
                Loader::getInstance()->helpers->save();
                ChestAPI::getInstance()->closeInventory($player);
            } else {
                $player->sendMessage("§l§6➛ §rИгрок не найден!");
            }
        }

        if (strpos($itemName, "§r§r §8[§l§bХелпер§r§8] §r") !== false) {
            $itemKey = "sethelperwarnmenu";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($player->hasPermission("api.helper.setting")) {

                $playerName = str_replace("§r§r §8[§l§bХелпер§r§8] §r", "", $item->getCustomName());
                $targetPlayer = Server::getInstance()->getPlayer(trim($playerName));
                $inventory = $event->getInventory();

                if ($targetPlayer instanceof Player) {
                    for ($i = 0; $i < 54; $i++) {
                        if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                            $inventory->setItem($i, Item::get(102));
                        } elseif ($i >= 10 && $i <= 43) {
                            $inventory->setItem($i, Item::get(0));
                        }
                    }

                    ChestAPI::getInstance()->setInventory($event->getInventory(), [
                        20 => Item::get(35, 5, 1)->setCustomName("§r§l§aWARN §r{$targetPlayer->getName()}")->setLore(["\n§r§7Нажмите чтобы, выдать варн!"]),
                        24 => Item::get(35, 14, 1)->setCustomName("§r§l§cUNWARN §r{$targetPlayer->getName()}")->setLore(["\n§r§7Нажмите чтобы, снять варн!"]),
                        31 => Item::get(35, 8, 1)->setCustomName("§r§l§7СНЯТЬ §r{$targetPlayer->getName()}")->setLore(["\n§r§7Нажмите чтобы, снять хелпера!"]),
                        49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
                    ]);
                } else {
                    for ($i = 0; $i < 54; $i++) {
                        if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                            $inventory->setItem($i, Item::get(102));
                        } elseif ($i >= 10 && $i <= 43) {
                            $inventory->setItem($i, Item::get(0));
                        }
                    }

                    $helpers = Loader::getInstance()->helpers->getAll();

                    if (isset($helpers[$playerName])) {

                        ChestAPI::getInstance()->setInventory($event->getInventory(), [
                            20 => Item::get(35, 5, 1)->setCustomName("§r§l§aWARN §r{$playerName}")->setLore(["\n§r§7Нажмите чтобы, выдать варн!"]),
                            24 => Item::get(35, 14, 1)->setCustomName("§r§l§cUNWARN §r{$playerName}")->setLore(["\n§r§7Нажмите чтобы, снять варн!"]),
                            31 => Item::get(35, 8, 1)->setCustomName("§r§l§7СНЯТЬ §r{$playerName}")->setLore(["\n§r§7Нажмите чтобы, снять хелпера!"]),
                            49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
                        ]);
                    }
                }
            }
        }

        if (strpos($itemName, "§r§l§aWARN §r") !== false) {
            $itemKey = "setwarn";
            if (!isset($this->itemClicks[$name][$itemKey])) {
                return $this->itemClicks[$name][$itemKey] = 1;
            } else if ($this->itemClicks[$name][$itemKey] === 1) {
                unset($this->itemClicks[$name][$itemKey]);
            }

            $playerName = str_replace("§r§l§aWARN §r", "", $item->getCustomName());
            $targetPlayer = Server::getInstance()->getPlayer(trim($playerName));
            $helperData = Loader::getInstance()->helpers->get(trim($playerName));

            if ($helperData !== null) {
                if ($targetPlayer instanceof Player) {
                    $warns = isset($helperData["warns"]) ? $helperData["warns"] : 0;
                    $warns++;
                    $helperData["warns"] = $warns;
                    Loader::getInstance()->helpers->set($targetPlayer->getName(), $helperData);
                    Loader::getInstance()->helpers->save();

                    if ($warns >= 3) {
                        Loader::getInstance()->setGroup($targetPlayer->getName(), "User");
                        $player->sendMessage("§7§l► §rХелпер §a{$targetPlayer->getName()}§r был снят с должности из-за §a3/3 варнов.");
                        VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Хелпера {$targetPlayer->getName()} сняли с поста, 3/3 варнов!");
                        Loader::getInstance()->helpers->remove($targetPlayer->getName());
                        Loader::getInstance()->helpers->save();
                    } else {
                        $player->sendMessage("§l§6➛ §rХелпер §a{$targetPlayer->getName()}§r получил §aварн§r, текущее количество варнов§7: §a{$warns}/3§r");
                        VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Хелперу {$targetPlayer->getName()} выдали варн, текущее количество варнов {$warns}/3");
                    }
                } else {
                    $warns = isset($helperData["warns"]) ? $helperData["warns"] : 0;
                    $warns++;
                    $helperData["warns"] = $warns;
                    Loader::getInstance()->helpers->set(trim($playerName), $helperData);
                    Loader::getInstance()->helpers->save();

                    if ($warns >= 3) {
                        Loader::getInstance()->setGroup(trim($playerName), "User", "навсегда");
                        $player->sendMessage("§l§6➛ §rХелпер §a{$playerName}§r был снят с должности из-за §a3/3 варнов.");
                        VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Хелпера {$playerName} сняли с поста, 3/3 варнов!");
                        Loader::getInstance()->helpers->remove(trim($playerName));
                        Loader::getInstance()->helpers->save();
                    } else {
                        $player->sendMessage("§l§6➛ §rХелпер §a{$playerName}§r получил §aварн§r, текущее количество варнов§7: §a{$warns}/3§r");
                        VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Хелперу {$playerName} выдали варн, текущее количество варнов {$warns}/3");
                    }
                }
            } else {
                $player->sendMessage("§l§6➛ §rИгрок {$playerName} не найден в системе хелперов!");
            }
            ChestAPI::getInstance()->closeInventory($player);
        }elseif (strpos($itemName, "§r§l§cUNWARN §r") !== false) {
            $itemKey = "setunwarn";
            if (!isset($this->itemClicks[$name][$itemKey])) {
                return $this->itemClicks[$name][$itemKey] = 1;
            } else if ($this->itemClicks[$name][$itemKey] === 1) {
                unset($this->itemClicks[$name][$itemKey]);
            }

            $playerName = str_replace("§r§l§cUNWARN §r", "", $item->getCustomName());
            $helperData = Loader::getInstance()->helpers->get(trim($playerName));

            if ($helperData !== null) {
                $warns = isset($helperData["warns"]) ? $helperData["warns"] : 0;

                if ($warns > 0) {
                    $warns--;
                    $helperData["warns"] = $warns;
                    Loader::getInstance()->helpers->set(trim($playerName), $helperData);
                    Loader::getInstance()->helpers->save();
                    $player->sendMessage("§l§6➛ §rВарн хелперу §a{$playerName}§r снят, текущее количество варнов§7: §a{$warns}/3§r");
                    VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Варн снят у хелпера {$playerName}, текущее количество варнов {$warns}/3");
                } else {
                    $player->sendMessage("§l§6➛ §rУ хелпера §a{$playerName}§r нет варнов для снятия!");
                }
            } else {
                $player->sendMessage("§l§6➛ §rИгрок {$playerName} не найден в системе хелперов!");
            }
            ChestAPI::getInstance()->closeInventory($player);
        } elseif (strpos($itemName, "§r§l§7СНЯТЬ §r") !== false) {
            $itemKey = "setremovehelper";
            if (!isset($this->itemClicks[$name][$itemKey])) {
                return $this->itemClicks[$name][$itemKey] = 1;
            } else if ($this->itemClicks[$name][$itemKey] === 1) {
                unset($this->itemClicks[$name][$itemKey]);
            }

            $playerName = str_replace("§r§l§7СНЯТЬ §r", "", $item->getCustomName());
            $helperData = Loader::getInstance()->helpers->get(trim($playerName));

            if ($helperData !== null) {
                Loader::getInstance()->helpers->remove(trim($playerName));
                Loader::getInstance()->helpers->save();
                Loader::getInstance()->setGroup(trim($playerName), "User", "навсегда");
                $player->sendMessage("§l§6➛ §rХелпер §a{$playerName}§r был полностью снят с должности!");
                VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Хелпер {$playerName} был снят с должности!");
            } else {
                $player->sendMessage("§l§6➛ §rИгрок {$playerName} не найден в системе хелперов!");
            }
            ChestAPI::getInstance()->closeInventory($player);
        }

        if (strpos($itemName, "§r§l§aСПИСОК ЖАЛОБ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "helperreports";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $reports = Loader::getInstance()->reportsConfig->get("reports", []);

            $inventory = $event->getInventory();

            for ($i = 0; $i < 54; $i++) {
                if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                    $inventory->setItem($i, Item::get(102));
                } elseif ($i >= 10 && $i <= 43) {
                    $inventory->setItem($i, Item::get(0));
                }
            }

            if (empty($reports)) {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cНет текущих жалоб."));
            } else {
                $slot = 9;

                foreach ($reports as $report) {
                    ++$slot;

                    $date = date("d.m.y", $report['date']);
                    $reportString = "§r§7♨ §rНомер жалобы§7: §a#{$report['id']}\n\n" .
                    "§7• §f§rЖалуется §a{$report['reporter']}\n" .
                    "§7• §f§rНарушитель §a{$report['target']}\n\n" .
                    "§7• §f§rПричина жалобы§7: §c{$report['reason']}\n\n" .
                    "§7• §f§rДата подачи жалобы§7: §a{$date}";

                    if ($inventory->getItem($slot)->getId() === 0) {
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName($reportString));
                    }
                }
                ChestAPI::getInstance()->setInventory($event->getInventory(), [
                    49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
                ]);
            }
        }

        if (strpos($itemName, "§r§l§aВЫДАТЬ ХЕЛПЕРА\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "helperset";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory = $event->getInventory();

            for ($i = 0; $i < 54; $i++) {
                if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                    $inventory->setItem($i, Item::get(102));
                } elseif ($i >= 10 && $i <= 43) {
                    $inventory->setItem($i, Item::get(0));
                }
            }

            $onlinePlayers = Server::getInstance()->getOnlinePlayers();
            $slot = 9;

            if (empty($onlinePlayers)) {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cНа сервере нету игроков."));
            } else {
                foreach ($onlinePlayers as $player) {
                    ++$slot;

                    if ($inventory->getItem($slot)->getId() === 0) {
                        $headItem = Item::get(397, 3, 1)->setCustomName("§r§a§r§a{$player->getName()}")->setLore(["\n§r§7Нажмите дважды, чтобы принять!"]);
                        $inventory->setItem($slot, $headItem);
                    }
                }
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
            ]);
        }

        if (strpos($itemName, "§r§r §8[§l§cЧСП§r§8] §r") !== false) {
            $itemKey = "sethelperwarnmenu123";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            if ($player->hasPermission("api.helper.setting")) {

                $playerName = str_replace("§r§r §8[§l§cЧСП§r§8] §r", "", $item->getCustomName());
                $helperData = Loader::getInstance()->helpers->get(trim($playerName));
                Loader::getInstance()->blacklist->remove(trim($playerName));
                Loader::getInstance()->blacklist->save();
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if (strpos($itemName, "§r§l§aЧСП ИГРОКИ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "blacklist";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory = $event->getInventory();

            for ($i = 0; $i < 54; $i++) {
                if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                    $inventory->setItem($i, Item::get(102));
                } elseif ($i >= 10 && $i <= 43) {
                    $inventory->setItem($i, Item::get(0));
                }
            }

            $list = Loader::getInstance()->blacklist->getAll();
            $slot = 9;

            if (is_array($list) && !empty($list)) {
                foreach ($list as $helperName => $data) {
                    ++$slot;
                    if ($inventory->getItem($slot)->getId() === 0) {
                        $reason = isset($data["reason"]) ? $data["reason"] : "Не указано";
                        $dataa = isset($data["date"]) ? $data["date"] : "Не указано";
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r§r §8[§l§cЧСП§r§8] §r{$helperName}")->setLore(["\n§rПричина§7: §c{$reason}\n\n§rДата§7: §a{$dataa}\n\n§7(Нажмите, на голову два раза\nчтобы снять этого игрока с чсп!)"]));
                    }
                }
            } else {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cНет доступных чсп-игроков!"));
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
            ]);
        }

        if (strpos($itemName, "§r§l§aСПИСОК ХЕЛПЕРОВ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "helperlist";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $inventory = $event->getInventory();

            for ($i = 0; $i < 54; $i++) {
                if ($i < 9 || $i % 9 === 0 || $i % 9 === 8 || $i >= 45) {
                    $inventory->setItem($i, Item::get(102));
                } elseif ($i >= 10 && $i <= 43) {
                    $inventory->setItem($i, Item::get(0));
                }
            }

            $helpers = Loader::getInstance()->helpers->getAll();
            $slot = 9;

            if (is_array($helpers) && !empty($helpers)) {
                foreach ($helpers as $helperName => $data) {
                    ++$slot;

                    $playerObj = Server::getInstance()->getPlayerExact($helperName);
                    $status = $playerObj !== null && $playerObj->isOnline() ? "§aОнлайн" : "§cОффлайн";
                    $warns = isset($data["warns"]) ? $data["warns"] : 0;

                    if ($inventory->getItem($slot)->getId() === 0) {
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r§r §8[§l§bХелпер§r§8] §r{$helperName}")->setLore(["\n§rВарны§7: §a{$warns}§7/§a3\n\n§rСтатус§7: §r{$status}"]));
                    }
                }
            } else {
                $inventory->setItem(22, Item::get(159, 14, 1)->setCustomName("§r§cНет доступных хелперов."));
            }

            ChestAPI::getInstance()->setInventory($event->getInventory(), [
                49 => Item::get(324)->setCustomName("§rВЕРНУТЬСЯ В МЕНЮ §aHELPER§r"),
            ]);
        }

        if (strpos($itemName, "§r§l§aРАНДОМНАЯ ТЕЛЕПОТРАЦИЯ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "rtp1";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $player->sendTitle("§l§e•••", "§rПодождите сейчас вас телепортирует!");

            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, 'randomRtpWorld'], [$player]), 20 * 3);
            ChestAPI::getInstance()->closeInventory($player);
        }

        if (strpos($itemName, "§r§l§aТЕЛЕПОРТАЦИЯ РЯДОМ С ИГРОКОМ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "rtp2";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $player->sendTitle("§l§e•••", "§rПодождите сейчас вас телепортирует!");

            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, 'randomRtpPlayer'], [$player]), 20 * 3);
            ChestAPI::getInstance()->closeInventory($player);
        }

        if (strpos($itemName, "§r§l§aДАЛЕКАЯ РАНДОМНАЯ ТЕЛЕПОТРАЦИЯ\n\n§rСтатус§7: §aДоступен") !== false) {
            $itemKey = "rtp3";
            if(!isset($this->itemClicks[$name][$itemKey])){
                return $this->itemClicks[$name][$itemKey] = 1;
            }else if($this->itemClicks[$name][$itemKey] === 1){
                unset($this->itemClicks[$name][$itemKey]);
            }

            $player->sendTitle("§l§e•••", "§rПодождите сейчас вас телепортирует!");

            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, 'randomRtpWorld2'], [$player]), 20 * 3);
            ChestAPI::getInstance()->closeInventory($player);
        }
        
        // Всегда отменяем событие, если игрок находится в ChestAPI меню
        if ($isChestAPIMenu) {
            $event->setCancelled(true);
        }
    }

    private function giveKit(Player $player, string $kitName, string $rarity, int $cooldown) {
        $playerName = $player->getName();
        $name = strtolower($player->getName());

        $itemKey = "kit";
        if(!isset($this->itemClicks[$name][$itemKey])){
            return $this->itemClicks[$name][$itemKey] = 1;
        }else if($this->itemClicks[$name][$itemKey] === 1){
            unset($this->itemClicks[$name][$itemKey]);
        }


        $permissionName = $kitName === "патрик" ? "patrik" : 
        ($kitName === "игрок" ? "user" : 
        ($kitName === "кронос" ? "kronos" : 
        ($kitName === "аристократ" ? "arist" : 
        ($kitName === "люцифер" ? "luc" : 
        ($kitName === "король" ? "korol" : 
        ($kitName === "царь" ? "king" : 
        ($kitName === "князь" ? "knayz" : $kitName)))))));

        if (!$player->hasPermission("api.kit." . $permissionName)) {
            $player->sendMessage("§l§eKIT §8:: §rУ вас нет §aправ§r на использование набора §a{$kitName}§r.");
            ChestAPI::getInstance()->closeInventory($player);
            return true;
        }

        $kitkd = Loader::getInstance()->kits->get($player->getName());
        $currentDate = time();

       if (isset($kitkd["kit_" . $kitName]) && ($currentDate - $kitkd["kit_" . $kitName]) < $cooldown) {
            $remainingTime = $cooldown - ($currentDate - $kitkd["kit_" . $kitName]);
            $player->sendMessage("§l§eKIT §8:: §rВы уже брали §aнабор§r, попробуйте через§a " . Loader::getInstance()->parseTime($remainingTime) ."§r");
            ChestAPI::getInstance()->closeInventory($player);
            return true;

        } elseif (isset($kitkd["kit_" . $kitName]) && ($currentDate - $kitkd["kit_" . $kitName]) >= $cooldown) {
            $kitkd["rarity_" . $rarity] = false;
            Loader::getInstance()->kits->set($player->getName(), $kitkd);
            Loader::getInstance()->kits->save();
        }

        if (isset($kitkd["rarity_" . $rarity]) && $kitkd["rarity_" . $rarity] === true) {
            switch ($rarity) {
                case 'MYSTIC':
                    $color = '§d';
                    break;

                case 'LEGENDARY':
                    $color = '§6';
                    break;

                case 'EPIC':
                    $color = '§d';
                    break;

                case 'CAMMON':
                    $color = '§a';
                    break;

                case 'RARY':
                    $color = '§9';
                    break;

                default:
                    $color = '§a';
                }

                $player->sendMessage("§l§eKIT §8:: §rВы уже брали §aнабор§r, с редкостью §l{$color}{$rarity}");
                ChestAPI::getInstance()->closeInventory($player);
                return;
            }

            switch ($kitName) {

                case 'игрок':
                    $helmet = Item::get(306, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $helmet->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(307, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $chestplate->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(308, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $leggings->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(309, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $boots->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(257, 0, 1);
                    $axe = Item::get(258, 0, 1);
                    $shovel = Item::get(256, 0, 1);
                    $sword = Item::get(267, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 1));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 2));
                    $sword->setCustomName("§r§fНабор §e§lИГРОКА§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 3);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 1);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 4);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 8);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 16);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 8); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 8);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 8); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $totems = Item::get(450, 0, 1);
                    $player->getInventory()->addItem($totems);
                break;

                case 'король':
                    $helmet = Item::get(306, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $helmet->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(307, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $chestplate->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(308, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $leggings->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(309, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $boots->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(257, 0, 1);
                    $axe = Item::get(258, 0, 1);
                    $shovel = Item::get(256, 0, 1);
                    $sword = Item::get(267, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 1));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 2));
                    $sword->setCustomName("§r§fНабор §d§lКОРОЛЬ§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 16);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 8);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 16);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 8);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 32);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 16); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 8);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 16); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $totems = Item::get(450, 0, 2);
                    $player->getInventory()->addItem($totems);
                break;

                case 'аристократ':
                    $helmet = Item::get(306, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $helmet->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(307, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $chestplate->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(308, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $leggings->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(309, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $boots->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(257, 0, 1);
                    $axe = Item::get(258, 0, 1);
                    $shovel = Item::get(256, 0, 1);
                    $sword = Item::get(267, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 1));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 2));
                    $sword->setCustomName("§r§fНабор §6§lАРИСТОКРАТА§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 16);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 8);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 16);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 8);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 32);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 16); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 8);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 16); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $totems = Item::get(450, 0, 2);
                    $player->getInventory()->addItem($totems);
                break;

                case 'люцифер':
                    $helmet = Item::get(310, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $helmet->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(311, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $chestplate->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(312, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $leggings->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(313, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $boots->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(278, 0, 1);
                    $axe = Item::get(279, 0, 1);
                    $shovel = Item::get(277, 0, 1);
                    $sword = Item::get(276, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 1));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 2));
                    $sword->setCustomName("§r§fНабор §c§lЛЮЦИФЕРА§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 32);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 16);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 32);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 16);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 48);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 32); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 16);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 32); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $elitre = Item::get(444, 0, 1); 
                    $player->getInventory()->addItem($elitre);

                    $totems = Item::get(450, 0, 3);
                    $player->getInventory()->addItem($totems);
                break;

                case 'король':
                    $helmet = Item::get(310, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $helmet->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(311, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $chestplate->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(312, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $leggings->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(313, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 3));
                    $boots->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(278, 0, 1);
                    $axe = Item::get(279, 0, 1);
                    $shovel = Item::get(277, 0, 1);
                    $sword = Item::get(276, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 1));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 2));
                    $sword->setCustomName("§r§fНабор §d§lКОРОЛЯ§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 32);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 16);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 32);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 16);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 48);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 32); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 16);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 48); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $elitre = Item::get(444, 0, 1); 
                    $player->getInventory()->addItem($elitre);

                    $totems = Item::get(450, 0, 3);
                    $player->getInventory()->addItem($totems);
                break;

                case 'царь':
                    $helmet = Item::get(310, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 4));
                    $helmet->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(311, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 4));
                    $chestplate->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(312, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 4));
                    $leggings->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(313, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 4));
                    $boots->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(278, 0, 1);
                    $axe = Item::get(279, 0, 1);
                    $shovel = Item::get(277, 0, 1);
                    $sword = Item::get(276, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 2));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 3));
                    $sword->setCustomName("§r§fНабор §b§lЦАРЯ§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 48);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 16);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 48);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 16);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 48);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 32); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 32);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 64); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $elitre = Item::get(444, 0, 1); 
                    $player->getInventory()->addItem($elitre);

                    $totems = Item::get(450, 0, 4);
                    $player->getInventory()->addItem($totems);
                break;

                case 'князь':
                    $helmet = Item::get(310, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $helmet->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(311, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $chestplate->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(312, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $leggings->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(313, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $boots->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(278, 0, 1);
                    $axe = Item::get(279, 0, 1);
                    $shovel = Item::get(277, 0, 1);
                    $sword = Item::get(276, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 2));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 3));
                    $sword->setCustomName("§r§fНабор §c§lКНЯЗЯ§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 64);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 32);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 64);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 32);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 64);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 48); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 32);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 64); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $elitre = Item::get(444, 0, 1); 
                    $player->getInventory()->addItem($elitre);

                    $totems = Item::get(450, 0, 4);
                    $player->getInventory()->addItem($totems);

                    $totemdonate = Item::get(450, 0, 1);
                    $totemdonate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $totemdonate->setCustomName("§r§l§7§aТАЛИСМАН ЧЕРЕПАХИ §7[§e✩§7]");
                    $player->getInventory()->addItem($totemdonate);
                break;

                case 'патрик':
                    $helmet = Item::get(310, 0, 1);
                    $helmet->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $helmet->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fШлем игрока §a" . $player->getName());

                    $chestplate = Item::get(311, 0, 1);
                    $chestplate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $chestplate->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fНагрудник игрока §a" . $player->getName());

                    $leggings = Item::get(312, 0, 1);
                    $leggings->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $leggings->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fПоножи игрока §a" . $player->getName());

                    $boots = Item::get(313, 0, 1);
                    $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5));
                    $boots->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fБотинки игрока §a" . $player->getName());

                    $player->getInventory()->addItem($helmet, $chestplate, $leggings, $boots);

                    $pickaxe = Item::get(278, 0, 1);
                    $axe = Item::get(279, 0, 1);
                    $shovel = Item::get(277, 0, 1);
                    $sword = Item::get(276, 0, 1);

                    $pickaxe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $pickaxe->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fКирка игрока §a" . $player->getName());

                    $axe->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $axe->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fТопор игрока §a" . $player->getName());

                    $shovel->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
                    $shovel->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fЛопата игрока §a" . $player->getName());

                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 3));
                    $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(14), 3));
                    $sword->setCustomName("§r§fНабор §a§lПАТРИКА§r§7 | §r§fМеч игрока §a" . $player->getName());
                    $player->getInventory()->addItem($sword);
                    $player->getInventory()->addItem($pickaxe);
                    $player->getInventory()->addItem($axe);
                    $player->getInventory()->addItem($shovel);

                    $goldenApples = Item::get(322, 0, 64);
                    $player->getInventory()->addItem($goldenApples);

                    $enchantedApples = Item::get(466, 0, 64);
                    $player->getInventory()->addItem($enchantedApples);

                    $chorusFruit = Item::get(432, 0, 64);
                    $player->getInventory()->addItem($chorusFruit);

                    $goldenCarrot = Item::get(396, 0, 32);
                    $player->getInventory()->addItem($goldenCarrot);

                    $steak = Item::get(364, 0, 64);
                    $player->getInventory()->addItem($steak);

                    $bow = Item::get(261, 0, 1);
                    $bow->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(51), 1));
                    $player->getInventory()->addItem($bow);

                    $arrows = Item::get(262, 0, 64); 
                    $player->getInventory()->addItem($arrows);

                    $enderPearls = Item::get(368, 0, 64);
                    $player->getInventory()->addItem($enderPearls);

                    $firework = Item::get(399, 0, 128); 
                    $firework->setCustomName("§rФейерверк");
                    $player->getInventory()->addItem($firework);

                    $elitre = Item::get(444, 0, 1); 
                    $player->getInventory()->addItem($elitre);

                    $totems = Item::get(450, 0, 6);
                    $player->getInventory()->addItem($totems);

                    $totemdonate = Item::get(450, 0, 1);
                    $totemdonate->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 1));
                    $totemdonate->setCustomName("§r§l§7§aТАЛИСМАН ЧЕРЕПАХИ §7[§e✩§7]");
                    $player->getInventory()->addItem($totemdonate);

                    $sphere = Item::get(397, 2, 1);
                    $sphere->setCustomName("§r§l§aШАР ПАТРИКА");
                    $player->getInventory()->addItem($sphere);
                break;
        }

        $kitkd = Loader::getInstance()->kits->get($player->getName(), []);
        $kitkd["kit_" . $kitName] = $currentDate;
        $kitkd["rarity_" . $rarity] = true;
        Loader::getInstance()->kits->set($player->getName(), $kitkd);
        Loader::getInstance()->kits->save();
        ChestAPI::getInstance()->closeInventory($player);
    }

    public function onHandlePacket(DataPacketReceiveEvent $event) {
        $pk = $event->getPacket();

        if ($pk instanceof RespawnPacket) {
            $event->getPlayer()->addEffect(
                new EffectInstance(Effect::getEffect(16), 9999999, 2, false)
            );
            $event->getPlayer()->dataPacket($pk);

            $player = $event->getPlayer();
            $world = Server::getInstance()->getLevelByName("spawn");
            if ($world !== null) {
                $player->teleport($world->getSafeSpawn());
            }
        }
    }

    public function onBlockBreakClan(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if ($player->isCreative()) {
            if ($block->getId() === Block::BEDROCK) {
                $event->setCancelled(true);
                return;
            }
        }

        if (Loader::isInClan(strtolower($player->getName()))) {
            $clanData = Loader::getPlayerClan($player);
            if ($clanData) {
                $clanData['xp'] = ($clanData['xp'] ?? 0) + 1;
                Loader::$clans->set(strtolower($clanData['name']), $clanData);
                Loader::$clans->save();
            }
        }
    }

    public function onPlayerChatClan(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $message = $event->getMessage();

        if (substr($message, 0, 1) === '#') {
            $message = substr($message, 1);
            $clanData = Loader::getInstance()->getPlayerClan($player);

            if ($clanData) {
                $clanMembers = $clanData['members'];
                $clanOfficers = $clanData['officers'];
                $clanLeader = $clanData['leader'];

                foreach ($clanMembers as $memberName => $memberData) {
                    $member = Server::getInstance()->getPlayer($memberName);
                    if ($member) {
                        if ($memberName === strtolower($player->getName())) {
                            $rolePrefix = "§7[§l§aУЧАСТНИК§r§7]";
                        } elseif (in_array($memberName, $clanOfficers)) {
                            $rolePrefix = "§7[§l§dХЕЛПЕР§r§7]";
                        } elseif ($memberName === strtolower($clanLeader)) {
                            $rolePrefix = "§7[§l§cВЛАДЕЛЕЦ§r§7]";
                        } else {
                            $rolePrefix = "§7[§l§aУЧАСТНИК§r§7]";
                        }

                    $member->sendMessage("{$rolePrefix} §a{$player->getName()}§7: §r" . $message);
                }
            }

            foreach ($clanOfficers as $officerName => $officerData) {
                $officer = Server::getInstance()->getPlayer($officerName);
                if ($officer) {
                    $officer->sendMessage("§7[§l§aКЛАН ЧАТ§r§7] §a{$player->getName()}§7: §r" . $message);
                }
            }

        
            $leader = Server::getInstance()->getPlayer($clanLeader);
            if ($leader) {
                $leader->sendMessage("§7[§l§aКЛАН ЧАТ§r§7] §a{$player->getName()}§7: §r" . $message);
            }
            $event->setCancelled(true);
        }else{
            $player->sendMessage("§l§eПОДСКАЗКА §l§8:: §rЭтот §aсимвол§r, отвечает за отправку §aсообщения§r в §aклан-чат§r!");
        }
    }}

    public function handlePlayerDeath(PlayerDeathEvent $event) {
        $player = $event->getPlayer();
        $cause = $player->getLastDamageCause();

        if ($cause instanceof EntityDamageByEntityEvent) {
            $attacker = $cause->getDamager();

            if (Loader::isInClan(strtolower($attacker->getName()))) { 
                $clan = Loader::getPlayerClan($attacker); 
                $clan['kills'] += 1;

                $attackerName = strtolower($attacker->getName());
                $expGained = mt_rand(10, 100); 
                $coins = mt_rand(10, 200);
                $clan['xp'] = ($clan['xp'] ?? 0) + $expGained;
                $clan['coins'] = ($clan['coins'] ?? 0) + $coins;

                if (Loader::isOfficer($attacker)) {
                    $clan['officers'][$attackerName]['kills'] += 1; 
                } elseif (Loader::isMember($attacker)) {
                    $clan['members'][$attackerName]['kills'] += 1; 
                } 

                while ($clan['xp'] >= 10000) {
                    $money = mt_rand(1000, 50000);
                    $clan['xp'] -= 10000; 
                    $clan['level'] += 1;  
                    $clan['coins'] += $money; 
                    $lvlupMessage = "\n\n§l§a           УРОВЕНЬ КЛАНА ПОВЫШЕН!\n        §r§7 - §rС §7[§e" . ($clan['level'] - 1) . "ур.§7] §8--> §7[§e" . $clan['level'] . "ур.§7]\n       §r§7 - §rКлану добавлено§a +" . number_format($money) . "§2$ §7(CLAN COINS)\n       §r§7 - §rДобавлю еще.....\n\n";

                        foreach ($clan['members'] as $memberName => $memberData) {
                            $member = Server::getInstance()->getPlayer($memberName);
                            if ($member) {
                                $member->sendMessage($lvlupMessage);
                            }
                        }

                        foreach ($clan['officers'] as $officerName => $officerData) {
                            $officer = Server::getInstance()->getPlayer($officerName);
                            if ($officer) {
                                $officer->sendMessage($lvlupMessage);
                            }
                        }

                        $leaderName = strtolower($clan["leader"]);
                        $leader = Server::getInstance()->getPlayer($leaderName);
                        if ($leader) {
                            $leader->sendMessage($lvlupMessage);
                        }
                    }

                Loader::$clans->set(strtolower($clan['name']), $clan); 
                Loader::$clans->save();

                $message = "§l§aCLAN §8:: §rИгрок§c " . ucfirst($attacker->getName()) . " §rубил игрока, §d+{$expGained}exp§r, §c+1§r";

                foreach ($clan['members'] as $memberName => $memberData) {
                    $member = Server::getInstance()->getPlayer($memberName);
                    if ($member) {
                        $member->sendMessage("§a" . $message);
                    }
                }

                foreach ($clan['officers'] as $officerName => $officerData) {
                    $officer = Server::getInstance()->getPlayer($officerName);
                    if ($officer) {
                        $officer->sendMessage("§a" . $message);
                    }
                }

                $leaderName = strtolower($clan["leader"]);
                $leader = Server::getInstance()->getPlayer($leaderName);
                if ($leader) {
                    $leader->sendMessage("§a" . $message);
                }
            }
        }
    }

    public function onEntityDamage(EntityDamageEvent $event) {
        $entity = $event->getEntity();

        if ($entity instanceof Player && $entity->getY() < 0) {
            $event->setCancelled(true);

            $level = Server::getInstance()->getLevelByName("spawn");
            $safeSpawn = $level->getSafeSpawn();
            $entity->teleport(new Position($safeSpawn->getX(), $safeSpawn->getY(), $safeSpawn->getZ(), $level));
            $entity->sendMessage("§l§cERROR §8:: §rВы телепортированы на спавн, мир был не прогружен в точки куда вас телепортирует!");
        }
    }


    public function handleEntityDamage(EntityDamageEvent $e){
        if(!$e instanceof EntityDamageByEntityEvent and !$e instanceof EntityDamageByChildEntityEvent) return;
        if(!$e->getEntity() instanceof Player or !$e->getDamager() instanceof Player) return;
        $d = $e->getDamager();
        if($d->getLevel()->getFolderName() == 'duels' or $d->getLevel()->getFolderName() == 'spawn') return;
        $ent = $e->getEntity();
        if(!Loader::isInClan($d->getName()) or !Loader::isInClan($ent->getName())) return;
        if(Loader::getPlayerClan($d->getName()) == Loader::getPlayerClan($ent->getName())){
            $e->setCancelled();
            $d->sendPopup("§r §cЭтот игрок с твоего клана §r§f");
        } 
    } 

    public function handleEat(PlayerItemConsumeEvent $event) {
        $player = $event->getPlayer();
        $playerName = strtolower($player->getName());

        $item = $event->getItem();
        $itemId = $item->getId();

        $currentTime = time();

        $cooldownTimes = [
            322 => 20,
            466 => 60,
            432 => 20, 
        ];

        if (isset($cooldownTimes[$itemId])) {
            if (isset(self::$cooldownUse[$itemId][$playerName])) {
                $remainingTime = self::$cooldownUse[$itemId][$playerName] - $currentTime;

                if ($remainingTime > 0) {
                    $player->addTitle("§r⩕", "§rПодождите ещё §a" . $remainingTime. "с.§r");
                    $event->setCancelled();
                }
            }
            self::$cooldownUse[$itemId][$playerName] = $currentTime + $cooldownTimes[$itemId];
        }

        if ($item->getId() === 396) {
            $effect = new EffectInstance(Effect::getEffect(Effect::SPEED), 20 * 10, 1, true);
            $player->addEffect($effect);
            $player->sendPopup("§rМорковка выдала вам §aскорость§r!");
        }
    }

    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $x = $player->getFloorX();
        $y = $player->getFloorY();
        $z = $player->getFloorZ();
        $levelName = $player->getLevel()->getFolderName();
        $radius = 4000;
        $radiusdefault = 600;
        //$player->sendPopup("К ({$player->x}, {$player->y}, {$player->z}) О");

        if ($levelName === "world") {
            if ($z <= -$radius) {
                $player->teleport(new Vector3($x, $y, -$radius + 5));
                $player->sendTitle("§r⩕⩕⩕§r", "§fТут находиться барьер!");
            } elseif ($z >= $radius) {
                $player->teleport(new Vector3($x, $y, $radius - 5));
                $player->sendTitle("§r⩕⩕⩕§r", "§fТут находиться барьер!");
            } elseif ($x >= $radius) {
                $player->teleport(new Vector3($radius - 5, $y, $z));
                $player->sendTitle("§r⩕⩕⩕§r", "§fТут находиться барьер!");
            } elseif ($x <= -$radius) {
                $player->teleport(new Vector3(-$radius + 5, $y, $z));
                $player->sendTitle("§r⩕⩕⩕§r", "§fТут находиться барьер!");
            }
        }
    }

   /* public function onPlayerDeath(PlayerDeathEvent $event) {
        $event->setDeathMessage(null);
        $player = $event->getEntity();
        $targetName = $player->getName();
        $level = $player->getLevel();
        $c = $player->getLastDamageCause();
        $killerName = null;

        if ($c instanceof EntityDamageByEntityEvent) {
            $damager = $c->getDamager();
            if ($damager instanceof Player) {
                $killerName = $damager->getName();
                $money = mt_rand(100, 150);
                $exp = mt_rand(2, 15);
                Server::getInstance()->broadcastMessage("§r §rИгрок §c{$targetName} §r§fбыл убит §r§fигроком§a {$killerName}");

                Loader::getInstance()->addMoney($damager, $money);
                Loader::getInstance()->addDeath($player, 1);
                Loader::getInstance()->addKills($damager, 1);
                Loader::getInstance()->addExp($damager, $exp);
                $damager->addTitle("§l§cУБИЙСТВО§r", "§a+{$money}§2$ §d+{$exp} опыт(а)");
                //$player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
                $player->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
            }
        } else {
            Server::getInstance()->broadcastMessage("§r §r§a{$targetName} §r§fумер.");
           // $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
            $player->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
            Loader::getInstance()->addDeath($player, 1);
        }

        if ($killerName !== null) {
            $killerOrders = Loader::getInstance()->killer->getAll();
            if (isset($killerOrders[$targetName])) {
                $order = $killerOrders[$targetName];
                $executorName = $order['executor'];
                $rewardAmount = $order['amount'];

                if ($killerName === $executorName) {
                    Loader::getInstance()->addMoney($damager, $rewardAmount);
                    Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$killerName} §rубил игрока §c{$targetName} §rи получил §a" . number_format($rewardAmount) . "§r§f⩐\n\n");
                }
                Loader::getInstance()->killer->remove($targetName);
                Loader::getInstance()->killer->save();
            }
        }
        

        if ($player->hasPermission("api.cmd.back")) {
            global $xcordd, $ycordd, $zcordd, $worldd;
            $xcordd[$player->getName()] = $player->getX();
            $ycordd[$player->getName()] = $player->getY();
            $zcordd[$player->getName()] = $player->getZ();
            $worldd[$player->getName()] = $player->getLevel();
            $player->sendMessage("§l§aBACK §8:: §rПиши: §a/back§r §rдля возвращения на место смерти");
            $player->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
        }
    }
    */

    public function onPlayerDeath(PlayerDeathEvent $e) {
        $e->setDeathMessage(null);
        $p = $e->getEntity();
        $targetName = $p->getName();
        $level = $p->getLevel();
        $c = $p->getLastDamageCause();
        $killerName = null;

        if ($p instanceof Player) {
            $name = $p->getName();
            $level = $p->getLevel();
            $c = $p->getLastDamageCause();

            if ($c instanceof EntityDamageByEntityEvent) {
                $d = $c->getDamager();

                if ($d instanceof Player) {
                    $dName = $d->getName();
                    $money = mt_rand(100, 150);
                    $exp = mt_rand(2, 15);

                    Server::getInstance()->broadcastMessage("§r §rИгрок §c" . $name . " §r§fбыл убит §r§fигроком§a " . $dName . "");
                    $level->addSound(new \pocketmine\level\sound\GhastSound($d), [$d]);
                    Loader::getInstance()->addMoney($d, $money);
                    Loader::getInstance()->addDeath($p, 1);
                    Loader::getInstance()->addKills($d, 1);
                    Loader::getInstance()->addExp($d, $exp);
                    $d->addTitle("§l§cУБИЙСТВО§r", "§a+{$money}§2$ §d+{$exp} опыт(а)");
                    $p->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
                }
            } else {
                Server::getInstance()->broadcastMessage("§r §r§a" . $name . " §r§fумер(-ла).");
                $p->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
                Loader::getInstance()->addDeath($p, 1);
                $p->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
                $level->addSound(new \pocketmine\level\sound\GhastSound($p), [$p]);
            }

            if ($p->hasPermission("api.cmd.back")) {
                global $xcordd, $ycordd, $zcordd, $worldd;
                $xcordd[$p->getName()] = $p->getX();
                $ycordd[$p->getName()] = $p->getY();
                $zcordd[$p->getName()] = $p->getZ();
                $worldd[$p->getName()] = $p->getLevel();
                $p->sendMessage("§l§aBACK §8:: §rПиши: §a/back§r §rдля возвращения на место смерти");
                $p->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
            }

            if ($level->getName() === "proxyworld") {
                $p->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
                $p->addEffect(new EffectInstance(Effect::getEffect(16), 9999999, 2, false));
            }

            if ($killerName !== null) {
                $killerName = $d->getName();
                $killerOrders = Loader::getInstance()->killer->getAll();
                if (isset($killerOrders[$targetName])) {
                    $order = $killerOrders[$targetName];
                    $executorName = $order['executor'];
                    $rewardAmount = $order['amount'];
                    if ($killerName === $executorName) {
                        Loader::getInstance()->addMoney($d, $rewardAmount);
                        Server::getInstance()->broadcastMessage("\n\n§l§cКИЛЛЕР §8:: §rИгрок §a{$killerName} §rубил игрока §c{$targetName} §rи получил §a" . number_format($rewardAmount) . "§r§f⩐\n\n");
                    }
                    Loader::getInstance()->killer->remove($targetName);
                    Loader::getInstance()->killer->save();
                }
            }
        }
    }
    

    private function generateCaptcha(Player $player): void {
        $captchaString = $this->generateRandomString(4);
        $this->captcha[$player->getName()] = $captchaString;

        $player->sendMessage("§r §rВведите капчу в чат §a{$captchaString}");
        $player->sendTitle("§l§aКАПЧА", "§rВведите в чат капчу!");
    }

    private function generateRandomString($length = 4): string { // Длина по умолчанию 4
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $message = $event->getMessage();

        if (isset($this->captcha[$playerName]) && $this->captcha[$playerName] !== null) {
            $input = $event->getMessage();

            if ($input === $this->captcha[$playerName]) {
                $player->sendMessage("§r⨺ §r§fКапча §aпринята§r, продолжайте §aкликать§r!");
                $this->captcha[$playerName] = null;
                $event->setCancelled(); 
            } else {
                $event->setCancelled(); 
                $player->sendMessage("§r §r§fНеправильная §cкапча§r, попробуйте еще раз!");
            }
        }
    }

    public function handlePlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        Loader::getInstance()->updatePermissions($player, Loader::getInstance()->getGroup($player->getName()));

        $event->setJoinMessage(null);
        $player->addEffect(new EffectInstance(Effect::getEffect(16), 20 * 99999999, 3));

        if($player->getLevel()->getFolderName() == "proxyworld") { 
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn()); 
        } 

        if ($player->isCreative()) {
            $player->setGamemode(0);
        }

        Loader::getInstance()->updateMineText($player);
        Loader::getInstance()->updateText($player);
        Loader::getInstance()->updateTextPromocode($player);

        if ($player->hasPermission("api.join.use")) {
            $donater = Loader::getInstance()->getPrefix($player);
            $titul = Loader::getInstance()->getPrefixTitul($player);
            $titulStatus = Loader::getInstance()->getTitul($player, "titulstatus");
            $sub = Loader::getInstance()->getSub($player);

            $cosmeticData = Loader::getInstance()->cosmetic->get($player->getName());

            if (isset($cosmeticData["join_1"]) && $cosmeticData["join_1"] && (!isset($cosmeticData["join1_status"]) ||$cosmeticData["join1_status"])) {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} {$cosmeticData['join1_message']}§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} {$cosmeticData['join1_message']}§r";
            } elseif (isset($cosmeticData["join_2"]) && $cosmeticData["join_2"] && (!isset($cosmeticData["join2_status"]) || $cosmeticData["join2_status"])) {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} {$cosmeticData['join2_message']}§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} {$cosmeticData['join2_message']}§r";
            } elseif (isset($cosmeticData["join_3"]) && $cosmeticData["join_3"] && (!isset($cosmeticData["join3_status"]) || $cosmeticData["join3_status"])) {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} {$cosmeticData['join3_message']}§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} {$cosmeticData['join3_message']}§r";
            } elseif (isset($cosmeticData["join_4"]) && $cosmeticData["join_4"] && (!isset($cosmeticData["join4_status"]) || $cosmeticData["join4_status"])) {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} {$cosmeticData['join4_message']}§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} {$cosmeticData['join4_message']}§r";
            } elseif (isset($cosmeticData["join_5"]) && $cosmeticData["join_5"] && (!isset($cosmeticData["join5_status"]) || $cosmeticData["join5_status"])) {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} {$cosmeticData['join5_message']}§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} {$cosmeticData['join5_message']}§r";
            } else {
                $message = $titulStatus == "on" 
                ? "§r §8[{$titul}{$sub}§r§8] §r{$playerName} §rприсоединился к игре.§r" 
                : "§r §8[{$donater}{$sub}§r§8] §r{$playerName} §rприсоединился к игре.§r";
            }
            Server::getInstance()->broadcastMessage($message);
        }
    }

    public function handlePlayerQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $event->setQuitMessage(null);
        
        // Очищаем кулдаун эндер пёрла при выходе игрока
        $name = strtolower($playerName);
        if(isset($this->timer[$name])){
            unset($this->timer[$name]);
        }

        if($player->getLevel()->getFolderName() == "proxyworld") { 
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn()); 
        }

        if ($player->hasPermission("api.join.use")) {
            $donater = Loader::getInstance()->getPrefix($player);
            $titul = Loader::getInstance()->getPrefixTitul($player);
            $titulStatus = Loader::getInstance()->getTitul($player, "titulstatus");

            $message = $titulStatus == "on" 
            ? "§r §8[{$titul}§r§8] §r{$playerName} §rвышел из игры." 
            : "§r §8[{$donater}§r§8] §r{$playerName} §rвышел из игры.";
            Server::getInstance()->BroadcastMessage($message);
        }
    }

    public function createdbPlayer(PlayerPreLoginEvent $ev) {
        $player = mb_strtolower($ev->getPlayer()->getName());
        $pla = $ev->getPlayer();
        $name = mb_strtolower($ev->getPlayer()->getName());
        $date = (new DateTime())->format('Y-m-d H:i:s');

        if (!Loader::getInstance()->db->query("SELECT * FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC)) {
            Loader::getInstance()->db->query("INSERT INTO datebase (name, money, lvl, exp, kills, death, rub, key, lvlminer, expminer, lvls, topdonater, pass, win) VALUES ('$name', 0, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0);");
        }

        if (!Loader::getInstance()->dbc->query("SELECT * FROM cases WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC)) {
            Loader::getInstance()->dbc->query("INSERT INTO cases (name, donatecase, prefixcase, newyearcase, moneycase) VALUES ('$name', 0, 0, 0, 0);");
        }

        $p = $ev->getPlayer();
        if(Loader::getInstance()->device->exists(strtolower($p->getName()))){
            if(Loader::getInstance()->device->get(strtolower($p->getName())) != $p->getDeviceModel()){
                $p->kick("§l§aDEVICE §8:: §r§7DATE ". $date ."\n§7- §rНа данном акаунте включена защита по §aустройству! §7-§r", false);
            }
        }
    }

    public function handleSignChange(SignChangeEvent $event) {
        $lines = $event->getLines();
        if (isset($lines[0]) && strtolower($lines[0]) === "textbonus1") {
            $event->setLine(0, "§l§r §8[§dБонус Эффект§8]§r");
            $event->setLine(1, "§r§f §fНажмите на сундук ");
            $event->setLine(2, "§r§d". Loader::SERVERNAME_FORMAT ."§r");
            $event->setLine(3, "");
        }

        if (isset($lines[0]) && strtolower($lines[0]) === "textbonus2") {
            $event->setLine(0, "§l§r §8[§aБонус§8]§r");
            $event->setLine(1, "§r§f §fНажмите на сундук ");
            $event->setLine(2, "§r§a". Loader::SERVERNAME_FORMAT ."§r");
            $event->setLine(3, "");
        }

        if (isset($lines[0]) && strtolower($lines[0]) === "textbonus3") {
            $event->setLine(0, "§l§r §8[§cБонус§8]§r");
            $event->setLine(1, "§r§f §fНажмите на сундук ");
            $event->setLine(2, "§r§c". Loader::SERVERNAME_FORMAT ."§r");
            $event->setLine(3, "");
        }

        if (isset($lines[0]) && strtolower($lines[0]) === "textbonus4") {
            $event->setLine(0, "§l§r §8[§bБесплатный сет§8]§r");
            $event->setLine(1, "§r§f §fНажмите на сундук ");
            $event->setLine(2, "§r§b". Loader::SERVERNAME_FORMAT ."§r");
            $event->setLine(3, "");
        }
    }

    public function onPlaceBlock(BlockPlaceEvent $event) : void {

        $player = $event->getPlayer();

        $block = $event->getBlock();
        $blockId = $block->getId();
        
        $blockClick = $event->getBlockAgainst();
        $blockClickId = $blockClick->getId();

        if ($block->getId() === Block::TNT) {
            $event->setCancelled();
        }

        if (($blockClickId === 63 || $blockClickId === 68) && $block->getId() === 63) {

            $player->sendMessage("§r⩕ §rВы не можете ставить §aтабличку§r на §aтабличку§r!");
            $event->setCancelled();
            return;

        }

        if (($blockClickId === 63 || $blockClickId === 68) && $block->getId() === 199) {

            $player->sendMessage("§r⩕ §rВы не можете ставить §aрамку§r на §aтабличку§r!");
            $event->setCancelled();
            return;

        }

        if ($blockClickId === 171 && $block->getId() === 63) {

            $player->sendMessage("§r⩕ §rВы не можете ставить §aтабличку§r на §aковрик§r!");
            $event->setCancelled();
            return;

        }

        if ($blockClickId == 208 && $block->getId() === 199) {

            $player->sendMessage("§r⩕ §rВы не можете ставить §aрамки§r на §aстержень края§r!");
            $event->setCancelled();
            return;

        }
    }

    public function onBucket(PlayerBucketEmptyEvent $event) {
        $player = $event->getPlayer();

        if ($player->isOp()) {
            $player->sendMessage("§r §rВедро было использовано!");
        }else{
            $event->setCancelled(); 
            $player->sendMessage("§r⩕ §rНельзя §aиспользовать§r вёдра!");
        }
    }

    public function onHandlePlayerInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $item = $event->getItem();
        if($player->getGamemode() === Player::CREATIVE){
            $item = $event->getBlock();
            if( $item->getId() == 54 || $item->getId() == 61 || $item->getId() == 62 || $item->getId() == 154 || $item->getId() == 146 || $item->getId() == 130 || $item->getId() == 218 || $item->getId() == 23 || $item->getId() == 125 || $item->getId() == 389 || $item->getId() == 379)
                if(!$event->getPlayer()->hasPermission("api.use.creative")){
                    $event->setCancelled(true);
                    $player->sendPopup("§r⩕ §rНельзя §aоткрывать§r§f, этот §aпредмет§r§f в креативе! §r⩕ §r");
                }
        }
    }

    public function onPickup(InventoryPickupItemEvent $event){
        $player = current($event->getViewers());

        if ($player->isFlying() || $player->getAllowFlight()) {
            $player->sendPopup("§r⩕ §rВыключите §aрежим полёта§r§f, чтобы §aподбирать§r§f вещи! §r§f⩕ §r");
            $event->setCancelled();
        }
    }

    public function AntiFrame(\pocketmine\event\block\ItemFrameDropItemEvent $event) {

        $player = $event->getPlayer();

        if ($player->isCreative()) {
            $player->sendMessage("§r⩕ §rНельзя §aизменять§r рамку в режиме§a креатив§r!");
            $event->setCancelled();
        }
    }

    public function onDrop(PlayerDropItemEvent $event) : void {
        $player = $event->getPlayer();

        if ($player->isCreative()) {
            $player->sendPopup("§r⩕ §r§fВы не можете §aвыбрасывать§r§f вещи в §aтворческом режиме§r! §f§r⩕§r");
            $event->setCancelled();
            return;

        }
    }

    public function PlayerInteract(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $gm = $player->getGamemode();

        if($gm == 1){
            if(!$event->getItem()->getId() == 438 or 441) return;
            $player->sendMessage("§r⩕ §rНельзя §aиспользовать§r взрывные зелья в §aкреативе§r!");
            $event->setCancelled();
        }
    }

    public function onnn(BlockBreakEvent $ev) {
        $block = $ev->getBlock();
        $player = $ev->getPlayer();
        $tile = $block->getLevel()->getTile($block);
        $blockId = $block->getId();
        if ($player->isCreative()) {
            return true;
        }
        if ($tile instanceof Chest) {
            if (count($tile->getInventory()->getViewers()) >= 1) {
                $ev->getPlayer()->sendMessage("§r⩕ §rВы не можете сломать §aсундук§r, так как он §aоткрыт§r!");
                $ev->setCancelled(true);
            }
        }

        if ($blockId == 218 || $blockId == 205) {
            if (count($tile->getInventory()->getViewers()) >= 1) {
                $ev->getPlayer()->sendMessage("§r⩕ §rВы не можете сломать §aшалкер§r, так как он §aоткрыт§r!");
                $ev->setCancelled(true);
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        if($player->getGamemode() === Player::CREATIVE){
            $item = $event->getItem();
            if($item->getId() === Item::WHEAT_SEEDS || $item->getId() === Item::BEETROOT_SEEDS || $item->getId() === Item::POTATO || $item->getId() === Item::CARROT || $item->getId() === Item::NETHER_WART){
                $event->setCancelled(true); 
            }
        }
        if($player->getGamemode() === Player::CREATIVE){
            $item = $event->getItem();
            if( $item->getId() == 201 || $item->getId() == 218 || $item->getId() == 56 || $item->getId() == 57 || $item->getId() == 52 || $item->getId() == 116 || $item->getId() == 41 || $item->getId() == 42 || $item->getId() == 22 || $item->getId() == 173 || $item->getId() == 133 || $item->getId() == 152 || $item->getId() == 16 || $item->getId() == 15 || $item->getId() == 14 || $item->getId() == 21 || $item->getId() == 73 || $item->getId() == 129 || $item->getId() == 103 || $item->getId() == 86 || $item->getId() == 91 || $item->getId() == 170 || $item->getId() == 295 || $item->getId() == 361 || $item->getId() == 362 || $item->getId() == 458 || $item->getId() == 391 || $item->getId() == 30 || $item->getId() == 6 || $item->getId() == 379 || $item->getId() == 46 || $item->getId() == 130 || $item->getId() == 389 || $item->getId() == 79 || $item->getId() == 174 || $item->getId() == 80 || $item->getId() == 145 || $item->getId() == 7)
                if(!$event->getPlayer()->hasPermission("api.use.creative")){
                    $event->setCancelled(true);
                    $player->sendMessage("§r⩕ §rНельзя cтавить §a".$event->getItem()->getName()." §rв креативе!");
            }
        }
    }

    public function randomRtpPlayer(Player $player): void {
        $world = Server::getInstance()->getLevelByName("world");

        if (isset($this->loader->pvp[$player->getLowerCaseName()])) {
            $player->sendMessage("§l§cPVP §8:: §r§fУ вас режим §c§lБОЯ §rтелепортация отменена...");
            return;
        }

        if ($world === null) {
            $player->sendMessage("§cМир 'world' не найден!");
            return;
        }

        $onlinePlayers = Server::getInstance()->getOnlinePlayers();

        if (count($onlinePlayers) < 2) {
            $player->sendMessage("§l§6➛ §rНа сервере должен быть онлайн, не менее 2 игроков!");
            ChestAPI::getInstance()->closeInventory($player);
            return;
        }

        if (mt_rand(1, 100) <= 30) { // 30% шанс
            $onlinePlayers = array_filter($onlinePlayers, function($p) use ($player) {
                return $p !== $player;
            });

            $targetPlayer = $onlinePlayers[array_rand($onlinePlayers)];
            $radius = mt_rand(50, 80);
            $x = $targetPlayer->getX() + rand(-$radius, $radius);
            $z = $targetPlayer->getZ() + rand(-$radius, $radius);
            $yOffset = rand(-5, 5);
            $y = (int) $targetPlayer->getY() + $yOffset;

            if ($y < 0) {
                $y = 0; 
            } elseif ($y > 255) {
                $y = 255; 
            }

            $player->teleport(new Position($x, $y, $z, $world));
            $player->sendMessage("§l§6➛ §rВы телепортированы рядом с §a{$targetPlayer->getName()}§r в размере §a{$radius} блоков.");
            $targetPlayer->sendMessage("§l§6➛ §rК вам телепортировался §a{$player->getName()}§r, он от вас в §a{$radius} блоков.");
            $player->sendTitle("§r§l§aТЕЛЕПОРТАЦИЯ К ИГРОКУ", "§a{$targetPlayer->getName()} §rв радиусе §a{$radius} блоков!");
        } else {
            $x = rand(-3000, 3000);
            $z = rand(-3000, 3000);
            $y = $world->getHighestBlockAt($x, $z);

            $player->teleport(new Position($x, $y, $z, $world));
            $player->sendTitle("§r§l§aРАНДОМНАЯ ТЕЛЕПОТРАЦИЯ", "§a{$x}§7, §a{$y}§7, §a{$x}§r - не удача!");
        }
    }

    public function randomRtpWorld(Player $player): void {
        $world = Server::getInstance()->getLevelByName("world");

        if (isset($this->loader->pvp[$player->getLowerCaseName()])) {
            $player->sendMessage("§l§cPVP §8:: §r§fУ вас режим §c§lБОЯ §rтелепортация отменена...");
            return;
        }

        if ($world === null) {
            $player->sendMessage("§cМир 'world' не найден!");
            return;
        }

        $x = rand(-1500, 1500);
        $z = rand(-1500, 1500);
        $y = $world->getHighestBlockAt($x, $z);

        $player->teleport(new Position($x, $y, $z, $world));
        $player->sendTitle("§r§l§aРАНДОМНАЯ ТЕЛЕПОТРАЦИЯ", "§8(§a{$x}§7, §a{$y}§7, §a{$x}§7)");
    }

    public function randomRtpWorld2(Player $player): void {
        $world = Server::getInstance()->getLevelByName("world");

        if (isset($this->loader->pvp[$player->getLowerCaseName()])) {
            $player->sendMessage("§l§cPVP §8:: §r§fУ вас режим §c§lБОЯ §rтелепортация отменена...");
            return;
        }

        if ($world === null) {
            $player->sendMessage("§cМир 'world' не найден!");
            return;
        }

        $x = rand(-3000, 3000);
        $z = rand(-3000, 3000);
        $y = $world->getHighestBlockAt($x, $z);

        $player->teleport(new Position($x, $y, $z, $world));
        $player->sendTitle("§r§l§aДАЛЕКАЯ РАНДОМНАЯ ТЕЛЕПОТРАЦИЯ", "§8(§a{$x}§7, §a{$y}§7, §a{$x}§7)");
    }

    private function clearEntities(): int {
        $count = 0;

        $spawnLevel = Server::getInstance()->getDefaultLevel(); 

        foreach (Server::getInstance()->getLevels() as $level) {
            if ($level->getName() === $spawnLevel->getName()) {
                $entities = $level->getEntities();
                foreach ($entities as $entity) {
                    if ($entity instanceof Entity && !($entity instanceof Human) && $entity->getNameTag() === "") {
                        $entity->close();
                        $count++;
                    }
                }
            }
        }
        return $count;
    }

    private function enchantItem(Player $player, $item) {
    $player->sendMessage("§l§dENCH §8:: §rПредмет успешно §aзачарован§r!");
    
    $enchantments = [];
    
    // Добавляем различные зачарования для мечей, топоров и доспехов
    switch (true) {
        case $item->isSword():
            $enchantments = [
                Enchantment::getEnchantment(9),  // Sharpness
                Enchantment::getEnchantment(13), // Knockback
                Enchantment::getEnchantment(20), // Fire Aspect
                Enchantment::getEnchantment(21), // Looting
            ];
            break;
        case $item->isAxe():
            $enchantments = [
                Enchantment::getEnchantment(17), // Efficiency
                Enchantment::getEnchantment(15), // Silk Touch
                Enchantment::getEnchantment(6),  // Fortune
            ];
            break;
        case $item instanceof Bow:
            $enchantments = [
                Enchantment::getEnchantment(19), // Power
                Enchantment::getEnchantment(5),  // Flame
                Enchantment::getEnchantment(22), // Infinity
            ];
            break;
        case $item->isPickaxe():
            $enchantments = [
                Enchantment::getEnchantment(17), // Efficiency
                Enchantment::getEnchantment(15), // Silk Touch
                Enchantment::getEnchantment(6),  // Fortune
            ];
            break;
        case $item->isArmor():
            $enchantments = [
                Enchantment::getEnchantment(0),  // Protection
                Enchantment::getEnchantment(1),  // Fire Protection
                Enchantment::getEnchantment(2),  // Feather Falling
                Enchantment::getEnchantment(6),  // Thorns
            ];
            break;
        default:
            return; 
    }
    
    // Выбираем случайное зачарование
    $enchantment = $enchantments[array_rand($enchantments)];
    
    $currentLevel = $item->getEnchantmentLevel($enchantment);
    $newLevel = mt_rand(1, ($currentLevel + 1) > $enchantment->getMaxLevel() ? $enchantment->getMaxLevel() : ($currentLevel + 1));
    $item->addEnchantment(new EnchantmentInstance($enchantment, $newLevel)); 
    $player->getInventory()->setItemInHand($item);
}

private function enchantTool(Player $player, $item) {
    $player->sendMessage("§l§dENCH §8:: §rПредмет успешно §aзачарован§r!");

    $enchantments = [
        Enchantment::getEnchantment(17), // Efficiency
        Enchantment::getEnchantment(15), // Silk Touch
        Enchantment::getEnchantment(6),  // Fortune
        Enchantment::getEnchantment(22), // Unbreaking
    ];

    // Выбираем случайное зачарование
    $enchantment = $enchantments[array_rand($enchantments)]; 
    
    $currentLevel = $item->getEnchantmentLevel($enchantment);
    $newLevel = mt_rand(1, ($currentLevel + 1) > $enchantment->getMaxLevel() ? $enchantment->getMaxLevel() : ($currentLevel + 1));
    $item->addEnchantment(new EnchantmentInstance($enchantment, $newLevel)); 
    $player->getInventory()->setItemInHand($item);
}
}