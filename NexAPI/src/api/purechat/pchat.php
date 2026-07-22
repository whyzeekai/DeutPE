<?php

namespace api\purechat;

use pocketmine\event\Listener;
use pocketmine\event\player\{ PlayerJoinEvent, PlayerChatEvent };
use api\Loader;

class pchat implements Listener {

    private $localChatRadius = 200; // Радиус локального чата в блоках
    /** @var Loader */
    private $pg;

    public function __construct(Loader $plugin) {
        $this->pg = $plugin;
    }

    public function getNameGroup(string $group): array {
        $groupList = [
            'kronos' => ['§l§dКронос§r', '§r'],
            'aristocrat' => ['§l§6Аристократ§r', '§r'],
            'user' => ['§l§eИгрок§r', '§r'],
            'lucefer' => ['§l§cЛюцифер§r', '§r'],
            'korol' => ['§l§dКороль§r', '§r'],
            'gresh' => ['§l§bЦарь§r', '§r'],
            'knayz' => ['§l§4Князь§r', '§r'],
            'admin' => ['§l§cВЛАДЕЛЕЦ', '§r'],
            'helper' => ['§l§bHELPER§r', '§r'],
            'santa' => ['§l§cSANTA§r', '§r'],
            'patrik' => ['§l§aПАТРИК§r', '§r'],
            'rabbit' => ['§l§9RABBIT§r', '§r'],
            'yt' => ['§l§cYou§fTube§r', '§r'],
            'support' => ['§l§2SUPPORT', '§r'],
            'kyrator' => ['§l§bKYRATOR', '§r'],
            'moderator' => ['§l§aМОДЕРАТОР§r', '§r']
        ];

        return $groupList[mb_strtolower($group)] ?? ['§r', '§r'];
    }

    public function updateTag($player) {
        $playerName = $player->getName();
        $lvl = $this->pg->getLvl($player);
        $os = Loader::AVAILABLE_OS[$player->getDeviceOS()];
        $clan = Loader::getInstance()->getPrefixClans($player);
        $group = Loader::getInstance()->getGroup($playerName);
        $groupName = $this->getNameGroup($group);
        $sub = Loader::getInstance()->getSub($player);

        $likes = Loader::getInstance()->rep->exists($playerName) 
                 ? Loader::getInstance()->rep->get($playerName)["likes"] 
                 : 0;

        $dislikes = Loader::getInstance()->rep->exists($playerName) 
                    ? Loader::getInstance()->rep->get($playerName)["dislikes"] 
                    : 0;

        $titulStatus = Loader::getInstance()->getTitul($player, "titulstatus");

        if ($titulStatus == "on") {
            $titul = Loader::getInstance()->getTitul($player, "titul");
            $player->setNameTag("§8「§e{$lvl}§l§e§r§8」 §8「{$titul}{$sub}§r§8」 §r{$player->getName()}\n§8「§b{$clan}§r§8」 §8「§l§a{$os}§r§8」 §8「§r+§a{$likes}§8」 §8「§r-§c{$dislikes}§8」");
            $player->setDisplayName("§r{$os} §8「{$titul}{$sub}§8」 §r{$player->getName()}");
        } else {
            $player->setNameTag("§8「§e{$lvl}§l§e§r§8」 §8「{$groupName[0]}{$sub}§r§8」 §r{$player->getName()}\n§8「§b{$clan}§r§8」 §8「§l§a{$os}§r§8」 §8「§r+§a{$likes}§8」 §8「§r-§c{$dislikes}§8」");
            $player->setDisplayName("§r{$os} §8「{$groupName[0]}{$sub}§8」 §r{$player->getName()}");
        }
    }

    public function messageNotification(string $message, string $color): string {
        $array_message = explode(" ", $message);

        foreach ($array_message as &$msg) {
            if (mb_substr($msg, 0, 1) === "@") {
                $playerName = mb_substr($msg, 1);
                $player = $this->pg->getServer()->getPlayer($playerName);
                if ($player !== null) {
                    $msg = "§l§e@" . $player->getName() . "§r" . $color;
                    $player->sendPopup("§rВас упомянули в §aчате§r!");
                }
            }
        }

        return implode(" ", $array_message);
    }

    public function onJoin(PlayerJoinEvent $event): void {
        $this->updateTag($event->getPlayer());
    }

    public function onTwoChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $msg = $event->getMessage();
        $group = Loader::getInstance()->getGroup($playerName);
        $lvl = $this->pg->getLvl($player);
        $groupName = $this->getNameGroup($group);
        $os = Loader::AVAILABLE_OS[$player->getDeviceOS()];
        $clan = Loader::getInstance()->getPrefixClans($player);
        $titulStatus = Loader::getInstance()->getTitul($player, "titulstatus");
        $sub = Loader::getInstance()->getSub($player);

        if ($titulStatus == "on") {
            $titul = Loader::getInstance()->getTitul($player, "titul");
            $event->setFormat("§r{$os} §8「§e{$lvl}§l§e§r§8」 §8「{$clan}§r§8」 §8「{$titul}{$sub}§r§8」 §r{$playerName} §r {$this->messageNotification($msg, $groupName[1])}");
        } else {
            $event->setFormat("§r{$os} §8「§e{$lvl}§l§e§r§8」 §8「{$clan}§r§8」 §8「{$groupName[0]}{$sub}§r§8」 §r{$playerName} §r {$this->messageNotification($msg, $groupName[1])}");
        }
    }
}
?>