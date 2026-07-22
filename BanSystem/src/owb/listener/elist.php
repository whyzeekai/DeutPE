<?php

namespace owb\listener;

use owb\api\database;
use owb\api\utils;
use owb\main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerPreLoginEvent;

class elist implements Listener
{

    public function construct(main $main)
    {
        $this->main = $main;
    }

    public function onPreLogin(PlayerPreLoginEvent $event)
    {
        $player = $event->getPlayer();
        $nickname = strtolower($player->getName());
        if (!main::getInstance()->bd->query("SELECT * FROM `owbff` WHERE `nickname`='$nickname'")->fetchArray()) {
            main::getInstance()->bd->query("INSERT INTO owbff(nickname, vk_id, bantime, reasonban, dateban, banned, wallban, playerip, baniptime, reasonbanip, wallbanip, mutetime, reasonmute, muted) VALUES ('$nickname', '', '', '', '', '', '', '', '', '', '', '', '', '');");
        }
    }

    public function onBan(PlayerPreLoginEvent $event)
    {
        $player = $event->getPlayer();
        $nickname = strtolower($player->getName());
        $ip = $player->getAddress();
        if (database::getData($nickname, 'bantime') > time()) {
            $raw_time = database::getData($nickname, 'bantime') - time();
            $time = utils::parseTime($raw_time);
            $dateban = database::getData($nickname, 'dateban');
            $banned = database::getData($nickname, 'banned');
            $reason = database::getData($nickname, 'reasonban');
            $msg = str_replace(["{nickname}", "{reason}", "{dateban}", "{banned}", "{time}"], [$nickname, $reason, $dateban, $banned, $time], main::getInstance()->messages->get('owb-ban'));
            $player->kick($msg, false);
        }
    }

    public function onMute(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();
        $nickname = strtolower($player->getName());
        if (database::getData($nickname, 'mutetime') > time()) {
            $raw_time = database::getData($nickname, 'mutetime') - time();
            $time = utils::parseTime($raw_time);
            $reason = database::getData($nickname, 'reasonmute');
            $muted = database::getData($nickname, 'muted');
            $msg = str_replace(["{reasonmute}", "{muted}", "{mutetime}"], [$reason, $muted, $time], main::getInstance()->messages->get('owb-mute'));
            $player->sendMessage(main::$tag_msg . $msg);
            $event->setCancelled();
            return false;
        }
    }
}