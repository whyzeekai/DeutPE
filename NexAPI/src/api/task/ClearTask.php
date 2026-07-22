<?php

declare(strict_types=1);

namespace api\task;

use api\Loader;

use pocketmine\item\Item;

use pocketmine\scheduler\Task;

use pocketmine\Server;

use pocketmine\entity\Entity;
use pocketmine\entity\Human;

class ClearTask extends Task {

    private $seconds = 180;

    public function onRun($currentTick) {
        $this->seconds--;

        switch ($this->seconds) {
            case 60:
                Server::getInstance()->broadcastPopup("§r§f §r§fЧерез §a1 минуту §fбудут удалены §aпредметы §fс земли! §r§f §r§f");
                break;

            case 30:
                Server::getInstance()->broadcastPopup("§r§f §r§fЧерез §a30 секунд §fбудут удалены §aпредметы §fс земли! §r§f §r§f");
                break;

            case 10:
                Server::getInstance()->broadcastPopup("§r§f §r§fЧерез §a10 секунд §fбудут удалены §aпредметы §fс земли! §r§f §r§f");
                break;

            case 0:
                $count = $this->clearEntities();
                Server::getInstance()->broadcastPopup("§r§f §r§fБыло удалено §a{$count} §fпредметов с земли! §r§f §r§f");
                $this->seconds = 180;
                break;
        }
    }

    private function clearEntities(): int {
        $count = 0;
        foreach (Server::getInstance()->getLevels() as $level) {
            $entities = $level->getEntities();
            foreach ($entities as $entity) {
                if ($entity instanceof Entity && !($entity instanceof Human) && $entity->getNameTag() === "") {
                    $entity->close();
                    $count++;
                }
            }
        }
        return $count;
    }
}
