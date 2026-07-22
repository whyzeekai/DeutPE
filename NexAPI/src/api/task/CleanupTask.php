<?php

declare(strict_types=1);

namespace API\task;

use API\Loader;

use pocketmine\scheduler\Task;

class CleanupTask extends Task {

    private $plugin;

    public function __construct(Loader $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        $this->plugin->cleanupReports();
    }
}