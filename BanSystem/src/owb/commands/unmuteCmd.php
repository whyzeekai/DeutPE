<?php

namespace owb\commands;

use owb\api\database;
use owb\api\vk;
use owb\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class unmuteCmd extends  Command
{
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
        parent::__construct("unmute", "Размутить игрока");
    }

    public function execute(CommandSender $s, $label, array $args)
    {
        if(!$s->hasPermission("api.cmd.unmute"))
        {
            $s->sendMessage("§c§l► §rУ вас нету §cправ§r на эту команду, купите донат §aᴘᴀʏ.мᴡɪx-ᴘᴇ.ʀᴜ§r");
            return true;
        }
        if (count($args) < 2) {
            $s->sendMessage("§l§7► §rИспользование §a/unmute §7(ник) (причина)");
            return true;
        }
        $muted = $this->main->getServer()->getPlayer($args[0]);
        if (!($muted)) {
            $s->sendMessage("§l§7► §rНа сервере нету данного игрока!");
            return false;
        }
        $reason = $args[1];
        $nickname = strtolower($muted->getName());
        $this->main->getServer()->broadcastMessage("§l§7► §a" . $s->getName() . " §rразблокировал чат игрока §c" . $muted->getName() . "§r, По причине§7: §a" . $reason);
        database::pardonMute($nickname);
        vk::sendChat("Игрок " . $s->getName() . " разаблокировал чат игроку " . $muted->getName() . "\nПричина размута: " . $reason, vk::$id_chat);
    }
}