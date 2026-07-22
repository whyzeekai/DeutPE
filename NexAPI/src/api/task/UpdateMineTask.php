<?php

declare(strict_types=1);

namespace api\task;

use api\Loader;

use pocketmine\scheduler\Task;

class UpdateMineTask extends Task {

    private $plugin;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        $this->plugin->updateMine();
    }
}