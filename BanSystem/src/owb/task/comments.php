<?php

namespace owb\task;

use owb\api\vk;
use owb\main;
use pocketmine\scheduler\Task;

class comments extends Task
{

    public function onRun($currentTick)
    {
        $result = main::getInstance()->bd->query("SELECT `nickname`, `wallban` FROM `owbff` WHERE `wallban` != ''");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            vk::parsingCommentsBan($row["wallban"], $row["nickname"]);
        }
    }
}