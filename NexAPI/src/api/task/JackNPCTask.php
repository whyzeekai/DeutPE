<?php

declare(strict_types=1);

namespace API\task;

use API\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\entity\Human;
use pocketmine\level\particle\DustParticle;
use pocketmine\math\Vector3;

use pocketmine\scheduler\Task;

class JackNPCTask extends Task {

    private $plugin;
    public $time = 0;
    public $rotationSpeed = 25;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        $this->npckosmtik();
        $this->npcarena();
    }

    public function sizenpc() {
    $radius = 4; // радиус проверки
    $level = Server::getInstance()->getDefaultLevel(); // уровень с игроками
    $players = $level->getPlayers();

    foreach (Server::getInstance()->getLevels() as $level) {
        foreach ($level->getEntities() as $entity) {
            if (strpos($entity->getNameTag(), "pass") !== false) {
                $playerInRange = null;

                // Проверяем наличие игроков в радиусе
                foreach ($players as $player) {
                    $distanceSq = $player->getPosition()->distanceSquared($entity->getPosition());

                    if ($distanceSq <= $radius * $radius) {
                        $playerInRange = $player; // нашли игрока в радиусе
                        break; // можно выйти, т.к. достаточно проверить хотя бы одного
                    }
                }

                if ($playerInRange !== null) {
                    // Есть игрок в радиусе
                    if (!$entity->isSneaking()) {
                        $entity->setSneaking(true);
                    }
                    // Атака игрока у NPC
                    $entity->attack($playerInRange, 1);
                } else {
                    // Нет игроков в радиусе
                    if ($entity->isSneaking()) {
                        $entity->setSneaking(false);
                    }
                }
            }
        }
    }
}



    public function npckosmtik() {
        foreach (Server::getInstance()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity->getNameTag() == "arenaworld") {
                    $this->createParticleCircleKosmetik($entity);
                }
            }
        }
    }

    public function npcarena() {
        foreach (Server::getInstance()->getLevels() as $level) {
            foreach ($level->getEntities() as $entity) {
                if ($entity->getNameTag() == "duelworld") {
                    $this->createParticleCircle($entity);
                }
            }
        }
    }
    
    public function createParticleCircle($entity) {
        $radius = 1.5;
        $heightOffset = 0.3;

        for ($angle = 0; $angle < 360; $angle += 10) {
            $x = $radius * cos(deg2rad($angle));
            $z = $radius * sin(deg2rad($angle));
            $y = $entity->getY() + $heightOffset;

            $entity->getLevel()->addParticle(new \pocketmine\level\particle\DustParticle(new \pocketmine\math\Vector3($entity->getX() + $x, $y, $entity->getZ() + $z), 255, 255, 0)); // Синий цвет (R, G, B)
        }
    }

    public function createParticleCircleKosmetik($entity) {
        $radius = 1.5;
        $heightOffset = 0.3;

        for ($angle = 0; $angle < 360; $angle += 10) {
            $x = $radius * cos(deg2rad($angle));
            $z = $radius * sin(deg2rad($angle));
            $y = $entity->getY() + $heightOffset;

            $entity->getLevel()->addParticle(new \pocketmine\level\particle\DustParticle(new \pocketmine\math\Vector3($entity->getX() + $x, $y, $entity->getZ() + $z), 0, 255, 0)); // Синий цвет (R, G, B)
        }
    }
}