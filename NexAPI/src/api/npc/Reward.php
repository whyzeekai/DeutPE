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

use \timurkaundefined\gametimer\GameTimer as GameTimer;

class Reward implements Listener {

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

        if ($player->isOp() && $message === ".setnpcreward") {
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
            $npc->setNameTag("          §l§bНаграды\n§rНажмите на NPC для просмотра!");
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

        if ($player instanceof Player && strpos($entity->getNameTag(), "§l§bНаграды") !== false) {
            $event->setCancelled();

            if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§r⩕ §r§fВыключите §aкреатив§r пожалуйста!");
                return true;
            }

            $pk = AnimatePacket::create($entity->getId(), AnimatePacket::ACTION_CRITICAL_HIT);
            Server::getInstance()->broadcastPacket($entity->getViewers(), $pk);
            $currentGameTime = GameTimer::getGameTime($player);
            $rewardData = Loader::getInstance()->rewards->get($player->getName());

            $chest = ChestAPI::getInstance()->openChest($player, [
                 "0-8" => Item::get(102),
                 "45-54" => Item::get(102),
                 49 => Item::get(340)->setCustomName("§r§l§fИНФОРМАЦИЯ\n\n§rВы наиграли§7: §a". Loader::getInstance()->parseTime($currentGameTime) ."\n\n§rСпасибо за игру на сервере!"),
            ], "§b§lНаграды §8:: §rПолучай бонусы за игру!", ChestAPI::DOUBLE_CHEST);
            if(is_bool($chest)) return;
            $inventory = $chest["inventory"];

            if (GameTimer::getGameTime($player) >= 7200) {
                if (isset($rewardData["status_reward1"]) && $rewardData["status_reward1"]) {
                    $inventory->setItem(20, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№1\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §a100,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(20, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№1\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §a100,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(20, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№1\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §a100,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 9000) {
                if (isset($rewardData["status_reward2"]) && $rewardData["status_reward2"]) {
                    $inventory->setItem(21, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№2\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §a125,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 30м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(21, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№2\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §a125,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 30м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(21, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№2\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §a125,000§r§f⩐\n§rНужно наиграть§7: §a2ч. 30м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 11880) {
                if (isset($rewardData["status_reward3"]) && $rewardData["status_reward3"]) {
                    $inventory->setItem(22, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№3\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §a15§r§fруб.\n§rНужно наиграть§7: §a3ч. 30м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(22, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№3\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §a15§r§fруб.\n§rНужно наиграть§7: §a3ч. 30м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(22, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№3\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §a15§r§fруб.\n§rНужно наиграть§7: §a3ч. 30м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 21600) {
                if (isset($rewardData["status_reward4"]) && $rewardData["status_reward4"]) {
                    $inventory->setItem(23, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№4\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §l§bПрефикс§7-§bКейс §r§7x1§f\n§rНужно наиграть§7: §a6ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(23, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№4\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §l§bПрефикс§7-§bКейс §r§7x1§f\n§rНужно наиграть§7: §a6ч. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(23, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№4\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §l§bПрефикс§7-§bКейс §r§7x1§f\n§rНужно наиграть§7: §a6ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 36000) {
                if (isset($rewardData["status_reward5"]) && $rewardData["status_reward5"]) {
                    $inventory->setItem(24, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№5\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §a500,000§r§f⩐\n§rНужно наиграть§7: §a10ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(24, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№5\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §a500,000§r§f⩐\n§rНужно наиграть§7: §a10ч. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(24, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№5\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §a500,000§r§f⩐\n§rНужно наиграть§7: §a10ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 86400) {
                if (isset($rewardData["status_reward6"]) && $rewardData["status_reward6"]) {
                    $inventory->setItem(29, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№6\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §eМонет-Кейс §7х1§f\n§rНужно наиграть§7: §a1д. 0ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(29, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№6\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §eМонет-Кейс §7х1§f\n§rНужно наиграть§7: §a1д. 0ч. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(29, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№6\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §eМонет-Кейс §7х1§f\n§rНужно наиграть§7: §a1д. 0ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 129600) {
                if (isset($rewardData["status_reward7"]) && $rewardData["status_reward7"]) {
                    $inventory->setItem(30, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№7\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §d3,500exp\n§rНужно наиграть§7: §a1д. 12ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(30, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№7\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §d3,500exp\n§rНужно наиграть§7: §a1д. 12. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(30, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№7\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §d3,500exp§f\n§rНужно наиграть§7: §a1д. 12ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 172800) {
                if (isset($rewardData["status_reward8"]) && $rewardData["status_reward8"]) {
                    $inventory->setItem(31, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№8\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §a50§r§fруб\n§rНужно наиграть§7: §a2д. 0ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(31, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№8\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §a50§r§fруб\n§rНужно наиграть§7: §a2д. 0. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(31, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№8\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §a50§r§fруб\n§rНужно наиграть§7: §a2д. 0ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 345600) {
                if (isset($rewardData["status_reward9"]) && $rewardData["status_reward9"]) {
                    $inventory->setItem(32, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№9\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §l§dКронос§f\n§rНужно наиграть§7: §a4д. 0ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(32, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№9\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §l§dКронос§f\n§rНужно наиграть§7: §a4д. 0. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(32, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№9\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §l§dКронос§f\n§rНужно наиграть§7: §a4д. 0ч. 0м.\n\n§cНаграда еще не доступна!"));
            }

            if (GameTimer::getGameTime($player) >= 518400) {
                if (isset($rewardData["status_reward10"]) && $rewardData["status_reward10"]) {
                    $inventory->setItem(33, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№10\n\n§rСтатус§7: §l§aВЗЯТА\n\n§rНаграда§7: §aДонат-Кейс §7х1§f\n§rНужно наиграть§7: §a6д. 0ч. 0м.\n\n§aНаграда уже получена!"));
                } else {
                    $inventory->setItem(33, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№10\n\n§rСтатус§7: §l§aДОСТУПЕН\n\n§rНаграда§7: §aДонат-Кейс §7х1§f\n§rНужно наиграть§7: §a6д. 0. 0м.\n\n§7Нажмите дважды, чтобы взять!"));
                }
            } else {
                $inventory->setItem(33, Item::get(342, 0, 1)->setCustomName("§r§l§bНАГРАДА ЗА ВРЕМЯ §f№10\n\n§rСтатус§7: §l§cНЕДОСТУПЕН\n\n§rНаграда§7: §aДонат-Кейс §7х1§f\n§rНужно наиграть§7: §a6д. 0ч. 0м.\n\n§cНаграда еще не доступна!"));
            }
        }
    }

    public function handleClickInventory(InventoryClickEvent $event){
        $player = $event->getWhoClicked();
        $item = $event->getItem();
        $itemName = $item->getCustomName();
        $playerName = $player->getName();
        $name = strtolower($player->getName());
        $currentGameTime = GameTimer::getGameTime($player);

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№1\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 7200;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addMoney($player, 100000);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §a100,000§r§f⩐");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№2\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 9000;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addMoney($player, 125000);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §a125,000§r§f⩐");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№3\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 11880;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addRub($player, 15);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §a15§r§fруб.");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№4\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 21600;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addPrefixkey($player, 1);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш аккаунт зачислено §l§bПрефикс§7-§bКейс §r§7x1§f");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№5\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 36000;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addMoney($player, 500000);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §a500,000§r§f⩐");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№6\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 86400;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addRub($player, 15);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш аккаунт зачислено §eМонет-Кейс §7х1§f");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                $rewardData["status_reward6"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№7\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 129600;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addExp($player, 3500);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §d3,500exp§f");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                $rewardData["status_reward6"] = true;
                $rewardData["status_reward7"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№8\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 172800;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->addRub($player, 50);
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш баланс зачислено §a50§r§fруб");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                $rewardData["status_reward6"] = true;
                $rewardData["status_reward7"] = true;
                $rewardData["status_reward8"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№9\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 345600;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->setGroup(trim($playerName), "Kronos");
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш аккаунт зачислен §l§dКронос§f");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                $rewardData["status_reward6"] = true;
                $rewardData["status_reward7"] = true;
                $rewardData["status_reward8"] = true;
                $rewardData["status_reward9"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }

        if(strpos($itemName, "§r§l§bНАГРАДА ЗА ВРЕМЯ §f№10\n\n§rСтатус§7: §l§aДОСТУПЕН") !== false) {
            $requestedTime = 518400;

            if (GameTimer::getGameTime($player) >= $requestedTime) {
                Loader::getInstance()->setGroup(trim($playerName), "Kronos");
                $player->sendMessage("§l§bНаграды §8:: §rНа ваш аккаунт зачислен §l§dКронос§f");
                ChestAPI::getInstance()->closeInventory($player);
                $rewardData["status_reward1"] = true;
                $rewardData["status_reward2"] = true;
                $rewardData["status_reward3"] = true;
                $rewardData["status_reward4"] = true;
                $rewardData["status_reward5"] = true;
                $rewardData["status_reward6"] = true;
                $rewardData["status_reward7"] = true;
                $rewardData["status_reward8"] = true;
                $rewardData["status_reward9"] = true;
                $rewardData["status_reward10"] = true;
                Loader::getInstance()->rewards->set($player->getName(), $rewardData);
                Loader::getInstance()->rewards->save();
            } else {
                $player->sendTitle("§r§l§c⩕", "§rНаиграйте нужное время!");
                ChestAPI::getInstance()->closeInventory($player);
            }
        }
    }
}