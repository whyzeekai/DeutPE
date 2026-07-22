<?php

declare(strict_types=1);

namespace api\event;

use api\Loader;

use pocketmine\Server;
use pocketmine\Player;

use pocketmine\utils\Config;

use pocketmine\event\Listener;

use pocketmine\utils\TextFormat;

use pocketmine\plugin\Plugin;

use pocketmine\math\Vector3;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerChatEvent;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;

use pocketmine\event\inventory\InventoryOpenEvent;

use pocketmine\command\CommandSender; 
use pocketmine\command\Command;

class Auth implements Listener {

    const HELPCMD = "\n\n§7╔ §fСоветуем поставить защиту на аккаунт §a/device\n§7╠ §rПоставить §aчанки§r под себя §a/chunk\n§7╚ §rНа сервере есть §aудобная§r система наборов§a /kits\n";

    public $auth;
    /** @var Loader */
    private $loader;
    private $logined = array();
    private $positions;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function onDisable(){
        $this->logined = array();
    }

    public function hasPosition($nick){
        $nick = strtolower($nick);
        return Loader::getInstance()->positions->exists($nick);
    }

    public function addPosition(Vector3 $position, $nick){
        $x = $position->getX();
        $y = $position->getY();
        $z = $position->getZ();
        $pos = $x.":".$y.":".$z;
        Loader::getInstance()->positions->set(strtolower($nick), $pos);
        Loader::getInstance()->positions->save();
    }

    public function removePosition($nick){
        $nick = strtolower($nick);
        Loader::getInstance()->positions->remove($nick);
        Loader::getInstance()->positions->save();
    }
    
    public function getPosition($string){
        $pos = explode(":", $string);
        return new Vector3($pos[0], $pos[1], $pos[2]);
    }
    
    public function isLogined($nick): bool{
        return in_array(strtolower($nick), $this->logined);
    }

    public function onJoin(PlayerJoinEvent $e){
        $player = $e->getPlayer();
        $nick = strtolower($player->getName());
        $online = count(Server::getInstance()->getOnlinePlayers());
        if(Loader::getInstance()->auth->exists($nick)){
            $ip = $player->getAddress();
            $cid = $player->getClientId();
            $password = Loader::getInstance()->auth->get($nick)["password"];
            $website = Loader::WEBSITE;
            $vk = Loader::VK;
            $servername = Loader::SERVERNAME_FORMAT;
            $info = Loader::getInstance()->auth->get($nick);
            $last_ip = $info["ip"];
            $last_cid = $info["cid"];
            if($ip == $last_ip){
                $this->logined[] = $nick;
                $msg = "\n§7╔ §fДобро пожаловать на {$servername}\n§7╠ §f§rВы успешно вошли §aавтоматически §rна сервер.\n§7╚ §fНаша Группа VK§7: {$vk} §7| §rНаш сайт§7: {$website}" . self::HELPCMD;
                $player->sendMessage($msg);

                $m = Loader::getInstance()->auth->get($nick);
                $m["ip"] = $player->getAddress();
                $m["cid"] = $player->getClientId();
                Loader::getInstance()->auth->set($nick, $m);
                Loader::getInstance()->auth->save();
            }else{
                if($cid === $last_cid){
                    $this->logined[] = $nick;
                    $msg = "\n§7╔ §fДобро пожаловать на {$servername}\n§7╠ §f§rВы успешно вошли §aавтоматически §rна сервер.\n§7╚ §fНаша Группа VK§7: {$vk} §7| §rНаш сайт§7: {$website}" . self::HELPCMD;
                    $player->sendMessage($msg);

                    $m = Loader::getInstance()->auth->get($nick);
                    $m["ip"] = $player->getAddress();
                    $m["cid"] = $player->getClientId();
                    Loader::getInstance()->auth->set($nick, $m);
                    Loader::getInstance()->auth->save();
                }else{
                    $msg = "\n§7╔ §fДобро пожаловать на {$servername}\n§7╠ §f§rЧтобы §aавторизироваться §rнужно ввести пароль в чат.\n§7╚ §fНаша Группа VK§7: {$vk} §7| §rНаш сайт§7: {$website}\n\n" . self::HELPCMD;
                    $player->sendMessage($msg);
                }
            }
        }else{
            $servername = Loader::SERVERNAME_FORMAT;
            $website = Loader::WEBSITE;
            $vk = Loader::VK;
            $msg = "\n§7╔ §fДобро пожаловать на {$servername}\n§7╠ §f§rЧтобы §aзарегестрироваться §rнужно ввести пароль в чат.\n§7╚ §fНаша Группа VK§7: {$vk} §7| §rНаш сайт§7: {$website}\n\n";
            $player->sendMessage($msg);
            $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
        }
    }

    public function onQuit(PlayerQuitEvent $e){
        $player = $e->getPlayer();
        $nick = strtolower($player->getName());
        if(in_array($nick, $this->logined)){
            foreach($this->logined as $key => $n){
                if($n == $nick){
                    unset($this->logined[$key]);
                }
            }
        }
    }

    public function onMove(PlayerMoveEvent $e){
        $player = $e->getPlayer();
        if($this->isLogined($player->getName())){
            if($this->hasPosition($player->getName())){
                $str = Loader::getInstance()->positions->get(strtolower($player->getName()));
                $pos = $this->getPosition($str);
                $player->teleport($pos);
                $this->removePosition($player->getName());
            }
        }else{
            $e->setCancelled(true);
        }
    }
    
    public function OnInteract(PlayerInteractEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $e->setCancelled(true);
        }
    }
    
    public function onBreak(BlockBreakEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $e->setCancelled(true);
        }
    }
    
    public function onPlace(BlockPlaceEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $e->setCancelled(true);
        }
    }
    public function OnCommandPreporcess(PlayerCommandPreprocessEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $msgText = $e->getMessage();
            if($msgText !== "" && $msgText[0] === "/"){
                $e->setCancelled(true);
                $msg = "§r §rЧтобы §aавторизироваться§r, введите §aпароль §rуказанный при регистрации!";
                $e->getPlayer()->sendMessage($msg);
            }
        }
    }
    
    public function onDrop(PlayerDropItemEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $e->setCancelled(true);
        }
    }
    
    public function onDamage(EntityDamageEvent $e){
        $player = $e->getEntity();
        if($player instanceof Player){
            if(!$this->isLogined($player->getName())){
                $e->setCancelled(true);
            }
        }
    }
    
    public function OnPreLogin(PlayerPreLoginEvent $e){
        $player = $e->getPlayer();
        $logined = false;
        foreach(Server::getInstance()->getOnlinePlayers() as $pl){
            if(strtolower($player->getName()) == strtolower($pl->getName())){
                $logined = true;
            }
        }
        if($logined){
            $e->setCancelled();
            $e->setKickMessage("", "§fИгрок с §aданным §fником уже на сервере!");
        }
    }
    
    public function onConsume(PlayerItemConsumeEvent $e){
        if(!$this->isLogined($e->getPlayer()->getName())){
            $e->setCancelled(true);
        }
    }
    
    public function onChat(PlayerChatEvent $e){
        $player = $e->getPlayer();
        $nick = strtolower($player->getName());
        $online = count(Server::getInstance()->getOnlinePlayers());
        if(!$this->isLogined($nick)){
            $e->setCancelled(true);
            if(Loader::getInstance()->auth->exists($nick)){
                $password = Loader::getInstance()->auth->get($nick)["password"];
                $msg = $e->getMessage();
                if($msg === $password){
                    $msg = "§r §rВы успешно §aавторизировались §rна сервере!";
                    $player->sendMessage($msg);
                    $this->logined[] = $nick;
                    $m = Loader::getInstance()->auth->get($nick);
                    $m["ip"] = $player->getAddress();
                    $m["cid"] = $player->getClientId();
                    Loader::getInstance()->auth->set($nick, $m);
                    Loader::getInstance()->auth->save();
                }else{
                    $msg = "§r §rПароль введен §cневерно. §rПопробуй еще раз! Забыли §eпароль §rот своего аккаунта? Обратись в тех. поддержку - §d@" . Loader::VK . "";
                    $player->sendMessage($msg);
                }
            }else{
                $password = $e->getMessage();
                $m = array("password" => $password, "ip" => $player->getAddress(), "cid" => $player->getClientId());
                Loader::getInstance()->auth->set($nick, $m);
                Loader::getInstance()->auth->save();
                $this->logined[] = $nick;
                $msg = "§r §rВы успешно §aзарегистрировались §rна сервере";
                $player->sendMessage($msg);
            }
        }
    }
}