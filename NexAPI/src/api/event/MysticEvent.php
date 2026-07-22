<?php

declare(strict_types=1);

namespace API\event;

use api\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\item\Item;

use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerMoveEvent;

use pocketmine\event\entity\EntityDamageEvent;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;

class MysticEvent implements Listener {

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }
}