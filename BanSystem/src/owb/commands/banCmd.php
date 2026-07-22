<?php

namespace owb\commands;

use owb\api\database;
use owb\api\utils;
use owb\api\vk;
use owb\main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class BanCmd extends Command
{
    private $main;

    public function __construct(Main $main)
    {
        $this->main = $main;
        parent::__construct("ban", "Забанить игрока", "/ban <имя>");
    }

    public function execute(CommandSender $sender, $label, array $args)
    {
        if(!$sender->hasPermission("api.cmd.ban"))
        {
            $sender->sendMessage("§c§l► §rУ вас нету §cправ§r на эту команду, купите донат §aburngrieff.fun§r");
            return true;
        }
        if (count($args) < 3) return $sender->sendMessage("§l§7► §rИспользование §a/ban §7(ник) (время) (причина)");
        $banned = $this->main->getServer()->getPlayer($args[0]);
        if (!($banned)) return $sender->sendMessage("§l§7► §rНа сервере нету данного игрока!");
        $reason = implode(" ", array_slice($args, 2));
        $get_time = utils::convertingFormedTimeToSec($args[1]);
        if (!$get_time) {
            $sender->sendMessage("§l§7► §rИспользование §a1с/1м/1д/1г§r (Пример: /ban test 1д читы) - на 1 день забанен!");
            return false;
        }
        $time = utils::parseTime($get_time);
        $nickname = strtolower($banned->getName());
        database::addData($nickname, "bantime", time() + $get_time);
        database::addData($nickname, "banned", $sender->getName());
        database::addData($nickname, "dateban", date("d-m-Y H:i"));
        database::addData($nickname, "reasonban", $reason);
        $this->main->getServer()->broadcastMessage("§l§7► §r§a{$sender->getName()} §rзабанил §rигрока §c{$banned->getName()}§r, по причине: §a{$reason}§r, на: §a{$time}\n§l§7► §rОжидаем доказательства в комментарии под постом, в нашей группе §a@MeetWix_bans"); //сообщение всем игрокам
        $msg = str_replace(["{nickname}", "{reason}", "{dateban}", "{banned}", "{time}"], [$nickname, $reason, date("d-m-Y H:i"), $sender->getName(), $time], main::getInstance()->messages->get('owb-ban')); //в конфиге messages.yml
        $banned->kick($msg, false);
        $response = vk::sendToWall("Игрок {$sender->getName()} забанил игрока {$banned->getName()}, по причине: {$reason}, на: {$time}\n\n— Ожидаем доказательства в комментарии, время ожидания 2 часа, после чего ваш аккаунт будет заблокирован!"); //текст сообщения поста на стену группы
        $data = json_decode($response, true);
        $id_post = $data['response']['post_id'];
        database::addData($nickname, "wallban", $id_post);
        vk::sendToChat("Уважаемый департамент Администраций, необходимо проверить данный пост!\nДля одобрения пишем в комментарии '+', для отказа '-'", vk::$id_chat);
    }
}