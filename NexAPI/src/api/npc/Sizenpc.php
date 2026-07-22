<?php

declare(strict_types=1);

namespace api\npc;

use api\Loader;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\entity\{Human, Witch, Villager};
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

class Sizenpc implements Listener {

    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function handleEntitySpawn(EntitySpawnEvent $event) {
        $entity = $event->getEntity();

        if ($entity->getNameTag() === "§l§cБРОДЯГА") {
            return; // Игнорируем конкретное имя

        }

        // Обработка для Human, Witch и Villager 
        if (($entity instanceof Human || $entity instanceof Witch || $entity instanceof Villager) && !$entity instanceof Player) {
            if ($entity->getNameTag() === "          §r§rНажми на NPC для просмотра!") {
                return; // Игнорируем специфическое имя
            }

            $scale = ($entity instanceof Villager) ? 2 : 1.3;
            $entity->setScale($scale);
            $entity->getDataPropertyManager()->setFloat(Entity::DATA_BOUNDING_BOX_HEIGHT, 2);
        }
    }

    public function onDamage(EntityDamageEvent $event) {
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            $entity = $event->getEntity();

            if ($damager instanceof Player && $damager->getName() === "Wassabi" && $damager->getInventory()->getItemInHand()->getId() === Item::STICK) {
                if (strpos($entity->getName(), "NPC") !== false) {
                    $entity->close();
                }
            }
        }
    }
}