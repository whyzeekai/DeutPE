<?php

namespace owb\commands;

use owb\api\vk;
use owb\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class kickCmd extends Command
{
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
        parent::__construct("kick", "Кикнуть игрока с сервера");
    }

    public function execute(CommandSender $s, $label, array $args)
    {
        if(!$s->hasPermission("api.cmd.kick"))
        {
            $s->sendMessage("§c§l► §rУ вас нету §cправ§r на эту команду, купите донат §aᴘᴀʏ.мᴡɪx-ᴘᴇ.ʀᴜ§r");
            return true;
        }

        if (!isset($args[0]) or !isset($args[1])) return $s->sendMessage("§l§7► §rИспользование §a/kick §7(ник) (причина)");
        if (!($p = $this->main->getServer()->getPlayer($args[0])) instanceof Player) return $s->sendMessage("§7⦗§cБан§7-§cСистема§7⦘ §a☞ §fИгрок §aне онлайн!");
        $reason = implode(" ", array_slice($args, 1));
        $this->main->getServer()->broadcastMessage("§l§7► §c" . $p->getName() . "§r§fбыл кикнут игроком §a " . $s->getName() . "§r§f, По причине§7:§a " . $reason);
        $p->kick("Вас §cвыгнали§r§f с сервера\nКикнул: §a" . $s->getName() . "\n§r§fПричина: §c " . $reason, false);
        vk::sendChat("Игрок " . $p->getName() . " был кикнут игроком " . $s->getName() . "\nПричина кика: " . $reason, vk::$id_chat);
    }
}