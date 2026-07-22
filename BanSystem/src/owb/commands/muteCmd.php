<?php

namespace owb\commands;

use owb\api\database;
use owb\api\utils;
use owb\api\vk;
use owb\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class muteCmd extends Command
{
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
        parent::__construct("mute", "Замутить игрока");
    }

    public function execute(CommandSender $s, $label, array $args)
    {
        if(!$s->hasPermission("api.cmd.mute"))
        {
            $s->sendMessage("§c§l► §rУ вас нету §cправ§r на эту команду, купите донат §aᴘᴀʏ.мᴡɪx-ᴘᴇ.ʀᴜ§r");
            return true;
        }
        if (count($args) < 3) {
            $s->sendMessage("§l§7► §rИспользование §a/mute §7(ник) (время) (причина)");
            return true;
        }
        $muted = $this->main->getServer()->getPlayer($args[0]);
        if (!($muted)) {
            $s->sendMessage("l§7► §rНа сервере нету данного игрока!");
            return false;
        }
        $get_time = utils::convertingFormedTimeToSec($args[1]);
        $time = utils::parseTime($get_time);
        if (!$get_time) {
            $s->sendMessage("§l§7► §rИспользование §a1с/1м/1д/1г§r (Пример: /ban test 1д читы) - на 1 день забанен!");
            return false;
        }
        $reason = implode(" ", array_slice($args, 2));
        $nickname = strtolower($muted->getName());
        $this->main->getServer()->broadcastMessage("§l§7► §r§r§a" . $s->getName() . " §rзаблокировал чат §rигроку §c" . $muted->getName() . " §rпо причине: §a" . $reason . " §rна: §a" . $time);
        database::addData($nickname, 'muted', $s->getName());
        database::addData($nickname, 'mutetime', time() + $get_time);
        database::addData($nickname, 'reasonmute', $reason);
        vk::sendChat("Игрок " . $s->getName() . " заблокировал чат игроку " . $muted->getName() . "\nВремя мута: " . $time . "\nПричина мута: " . $reason, vk::$id_chat);
    }
}