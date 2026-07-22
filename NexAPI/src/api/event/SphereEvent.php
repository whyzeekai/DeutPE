<?php

declare(strict_types=1);

namespace API\event;

use API\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\item\Item;

use pocketmine\math\Vector3;

use pocketmine\level\Position;

use pocketmine\block\Block;

use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\CallbackTask;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class SphereEvent implements Listener {

    public $cooldowns = [];
    private $armorStates = [];
    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function HandlePlayerInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $itemInHand = $player->getInventory()->getItemInHand();
        $offHandItem = $player->getOffHandInventory()->getItem(0);

        if ($offHandItem->getId() == 0 && ($itemInHand->getId() == Item::TOTEM || $itemInHand->getId() == Item::SKULL)) {
            $player->getOffHandInventory()->setItem(0, $itemInHand);
            $player->getInventory()->setItemInHand(Item::get(0));
        }

        if ($item->getId() == 276 and $item->getCustomName() == "§r§l§bSWORD_FLУ") {
            if (isset($this->cooldowns[$player->getName()])) {
                if (time() < $this->cooldowns[$player->getName()]) {
                    $remaining = $this->cooldowns[$player->getName()] - time();
                    $player->sendPopup("§rПодождите еще: §a{$remaining}с.");
                    return;
                }
            }
            $player->setMotion($player->getDirectionVector()->multiply(2));
            $this->cooldowns[$player->getName()] = time() + 120;
        }

        if ($item->getId() == 399 and $item->getCustomName() == "§rФейерверк") {
            if($player->getArmorInventory()->getChestplate()->getId() == 444){
                if ($player->getLevel()->getName() == "proxyworld") {
                    $player->sendMessage("§l§bАРЕНА §8:: §rНа §aарене§r использование §aфейерверка§r запрещено!");
                    return true;
                }

                if (isset(Loader::getInstance()->pvp[$player->getLowerCaseName()])) {
                    $player->sendMessage("§l§PVP §8:: §r§fУ вас режим §c§lБОЯ §rиспользовать §cфейерверк§r нельзя!");
                    return true;
                }
                $player->setMotion($player->getDirectionVector()->multiply(2));
                $player->getInventory()->removeItem(Item::get(399, 0, 1));
            } else {
                $player->sendMessage("§l§cОШИБКА §8:: §rПожалуйста, наденьте §aэлитры§r чтобы использовать §bфейерверк§r!");
            }
        }
    }

    public function HandleBlockPlace(BlockPlaceEvent $event): void {
    	$player = $event->getPlayer();
    	$item = $event->getItem();
    	$itemInHand = $player->getInventory()->getItemInHand();
    	$offHandItem = $player->getOffHandInventory()->getItem(0);
    	$restrictedId = 397;

    	if ($item->getId() === $restrictedId || $itemInHand->getId() === $restrictedId || $offHandItem->getId() === $restrictedId) {
    		$event->setCancelled(true);
    		return;
    	}

    	$restrictedItems = range(398, 403);
    	if (in_array($item->getId(), $restrictedItems, true) && $itemInHand->getId() === $item->getId()) {
    		$event->setCancelled(true);
    		return;
    	}
    }

    public function HandleEntityDamage(EntityDamageEvent $event): void {
        if ($event instanceof EntityDamageByEntityEvent) { 
            if ($event->getDamager() instanceof Player) {
                $player = $event->getDamager();
                $item = $player->getOffhandInventory()->getItemInOffHand();

                if ($item->getId() === Item::SKULL && $item->getCustomName() === "§r§l§bШАР ЗЕВСА") {
                    if (isset($this->cooldowns[$player->getName()])) {
                        if (time() < $this->cooldowns[$player->getName()]) {
                            $remaining = $this->cooldowns[$player->getName()] - time();
                            return;
                        }
                    }

                    if (mt_rand(1, 100) <= 5) {
                        $target = $event->getEntity(); 

                        if ($target instanceof Player) {
                            $level = $target->getLevel();
                            $position = $target->getPosition();
                            $target->addEffect(new EffectInstance(Effect::getEffect(9), 20 * 10, 0));   
                            $target->setOnFire(8);
                            $level->spawnLightning(new \pocketmine\math\Vector3($position->getX(), $position->getY(), $position->getZ()));
                            $this->cooldowns[$player->getName()] = time() + 120;
                        }
                    }
                }

                if ($item->getId() === Item::SKULL && $item->getCustomName() === "§r§l§dШАР ДРАКУЛЫ") {
                    if (isset($this->cooldowns[$player->getName()])) {
                        if (time() < $this->cooldowns[$player->getName()]) {
                            $remaining = $this->cooldowns[$player->getName()] - time();
                            return;
                        }
                    }

                    if (mt_rand(1, 100) <= 5) {
                        $target = $event->getEntity(); 

                        if ($target instanceof Player) {
                            $level = $target->getLevel();
                            $position = $target->getPosition();
                            $target->addEffect(new EffectInstance(Effect::getEffect(9), 20 * 6, 0));   
                            $this->cooldowns[$player->getName()] = time() + 120;
                        }
                    }
                }

                if ($item->getId() === Item::SKULL && $item->getCustomName() === "§r§l§eШАР АИДА") {
                    if (isset($this->cooldowns[$player->getName()])) {
                        if (time() < $this->cooldowns[$player->getName()]) {
                            $remaining = $this->cooldowns[$player->getName()] - time();
                            return;
                        }
                    }

                    if (mt_rand(1, 100) <= 10) {
                        $target = $event->getEntity(); 

                        if ($target instanceof Player) {
                            $level = $target->getLevel();
                            $position = $target->getPosition();
                            $target->addEffect(new EffectInstance(Effect::getEffect(9), 20 * 10, 0));   
                            $target->setOnFire(8);
                            $this->cooldowns[$player->getName()] = time() + 120;
                        }
                    }
                }

                if ($item->getId() === Item::SKULL && $item->getCustomName() === "§r§l§cШАР МОРОЗА") {
                    if (isset($this->cooldowns[$player->getName()])) {
                        if (time() < $this->cooldowns[$player->getName()]) {
                            $remaining = $this->cooldowns[$player->getName()] - time();
                            return;
                        }
                    }

                    if (mt_rand(1, 100) <= 5) {
                        $target = $event->getEntity(); 

                        if ($target instanceof Player) {
                            $level = $target->getLevel();
                            $position = $target->getPosition();
                            $target->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 1));   
                            $this->cooldowns[$player->getName()] = time() + 120;
                        }
                    }
                }

                if ($item->getId() === Item::SKULL && $item->getCustomName() === "§r§l§aШАР ПАТРИКА") {
                    if (isset($this->cooldowns[$player->getName()])) {
                        if (time() < $this->cooldowns[$player->getName()]) {
                            $remaining = $this->cooldowns[$player->getName()] - time();
                            return;
                        }
                    }

                    if (mt_rand(1, 100) <= 5) {
                        $target = $event->getEntity(); 

                        if ($target instanceof Player) {
                            $level = $target->getLevel();
                            $position = $target->getPosition();
                            $target->addEffect(new EffectInstance(Effect::getEffect(9), 20 * 8, 1)); 
                            $target->addEffect(new EffectInstance(Effect::getEffect(9), 20 * 8, 15));     
                            $this->cooldowns[$player->getName()] = time() + 120;
                        }
                    }
                }
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();

        $armorInventory = $player->getArmorInventory();
        $helmet = $armorInventory->getHelmet();
        $chestplate = $armorInventory->getChestplate();
        $leggings = $armorInventory->getLeggings();
        $boots = $armorInventory->getBoots();

        if($helmet !== null && $chestplate !== null && $leggings !== null && $boots !== null &&
            $helmet->getCustomName() == "§r§dDRAGON_ARMOR_POWER" and $chestplate->getCustomName() == "§r§dDRAGON_ARMOR_POWER" and $leggings->getCustomName() == "§r§dDRAGON_ARMOR_POWER" and 
            $boots->getCustomName() == "§r§dDRAGON_ARMOR_POWER")
        {
            $effect = new EffectInstance(Effect::getEffect(5), 20 * 7, 1);
            $player->addEffect($effect);
        }

        if($helmet !== null && $chestplate !== null && $leggings !== null && $boots !== null &&
            $helmet->getCustomName() == "§r§aDRAGON_ARMOR_JUMP" and $chestplate->getCustomName() == "§r§aDRAGON_ARMOR_JUMP" and $leggings->getCustomName() == "§r§aDRAGON_ARMOR_JUMP" and $boots->getCustomName() == "§r§aDRAGON_ARMOR_JUMP")
        {
            $effect = new EffectInstance(Effect::getEffect(8), 20 * 7, 1);
            $player->addEffect($effect);
        }

        if($helmet !== null && $chestplate !== null && $leggings !== null && $boots !== null &&
            $helmet->getCustomName() == "§r§bDRAGON_ARMOR_SPEED" and $chestplate->getCustomName() == "§r§bDRAGON_ARMOR_SPEED" and $leggings->getCustomName() == "§r§bDRAGON_ARMOR_SPEED" and $boots->getCustomName() == "§r§bDRAGON_ARMOR_SPEED")
        {
            $effect = new EffectInstance(Effect::getEffect(1), 20 * 7, 0);
            $player->addEffect($effect);
        }

        if($helmet !== null && $chestplate !== null && $leggings !== null && $boots !== null &&
            $helmet->getCustomName() == "§r§9DRAGON_ARMOR_VANISH" and $chestplate->getCustomName() == "§r§9DRAGON_ARMOR_VANISH" and $leggings->getCustomName() == "§r§9DRAGON_ARMOR_VANISH" and $boots->getCustomName() == "§r§9DRAGON_ARMOR_VANISH")
        {
            $effect = new EffectInstance(Effect::getEffect(14), 20 * 7, 0);
            $player->addEffect($effect);
        }
    }
}