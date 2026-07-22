<?php

namespace owb\commands;

use owb\api\database;
use owb\api\vk;
use owb\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class pardonCmd extends Command
{
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
        parent::__construct("pardon", "Разбанить игрока");
    }

    public function execute(CommandSender $s, $label, array $args)
    {
        if(!$s->hasPermission("api.cmd.pardon"))
        {
            $s->sendMessage("§c§l► §rУ вас нету §cправ§r на эту команду, купите донат §aᴘэhttps://burngrief.trademc.org/§r");
            return true;
        }
        if (count($args) < 2) {
            $s->sendMessage("§l§7► §rИспользование §a/pardon §7(ник) (причина)");
            return true;
        }
        $banned = strtolower($args[0]);
        $reason = $args[1];
        database::pardonBan($banned);
        $this->main->getServer()->broadcastMessage("§l§7► §a{$s->getName()} §rразбанил §rигрока §c{$banned}§r, По причине§7:§a {$reason}");
        $response = vk::sendToWall("Игрок {$s->getName()} разбанил игрока {$banned}\nПричина разбана: {$reason}\n\n— Ожидаем доказательства в комментарии, время ожидания 2 часа, после чего ваш аккаунт будет заблокирован!"); //текст сообщения поста на стену группы
        $data = json_decode($response, true);
        $id_post = $data['response']['post_id'];
        database::addData($banned, "wallbanip", $id_post);
        vk::sendChat("Игрок " . $s->getName() . " разбанил игрока " . $banned . " | Причина разбана: " . $reason, vk::$id_chat);
    }
}