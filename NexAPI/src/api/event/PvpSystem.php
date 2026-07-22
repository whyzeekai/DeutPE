<?php

declare(strict_types=1);

namespace API\event;

use api\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;

use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;

use pocketmine\item\Item;

use pocketmine\level\Position;
use pocketmine\math\Vector3;
use \timurkaundefined\gametimer\GameTimer as GameTimer;


class PvpSystem implements Listener {

    /** @var Loader */
    private $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function onNDeath(PlayerDeathEvent $event) {
        $playerName = $event->getPlayer()->getLowerCaseName();

        if(array_key_exists($playerName, Loader::getInstance()->pvp)) {
            unset(Loader::getInstance()->pvp[$playerName]);
        }
    }

    public function onNQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();
        $lowerName = $player->getLowerCaseName();
        $pvp = Loader::getInstance()->pvp;

        if (!isset($pvp[$lowerName]) || time() > $pvp[$lowerName]) {
            unset($pvp[$lowerName]);
            Loader::getInstance()->pvp = $pvp;
        } else {
            $player->kill();
        }
    }


    public function onCommandPreprocessEvent(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getLowerCaseName();
        $message = $event->getMessage();

        if (!isset(Loader::getInstance()->pvp[$playerName]) or time() > Loader::getInstance()->pvp[$playerName]) {
            unset(Loader::getInstance()->pvp[$playerName]);
        } else {
            $firstChar = substr($message, 0, 1);

            if ($firstChar == "/apc" or $firstChar == "/ap") {
                $event->setCancelled(false);
            } elseif ($firstChar === "/") {
                $event->setCancelled();
                $player->sendMessage("§l§cᴘᴠᴘ §l§7► §r§fУ вас режим §c§lБОЯ §rиспользовать §cкоманды§r нельзя!");
            }
        }
    }


    public function EntityDamageEvent(EntityDamageEvent $event) {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    $playTime = GameTimer::getGameTime($damager);
                    $requiredPlayTime = 300; 

            $ping = $entity->getPing();
            $pingColor = "§a";
            if ($ping > 150 && $ping <= 250) {
                $pingColor = "§e";
            } elseif ($ping > 250) {
                $pingColor = "§c";
            }

            $time = Loader::getInstance()->time;
            $level = $damager->getLevel()->getName();
            $levele = $entity->getLevel()->getName();
            $leveld = $damager->getLevel()->getName();
            $x = $entity->getFloorX();
            $y = $entity->getFloorY();
            $z = $entity->getFloorZ();

            $xd = $damager->getFloorX();
            $yd = $damager->getFloorY();
            $zd = $damager->getFloorZ();

            $xe = $entity->getFloorX();
            $ye = $entity->getFloorY();
            $ze = $entity->getFloorZ();

                if ($time <= 30) {
                    $damager->sendTitle("§l§cＰＶＰ ＳＹＳＴＥＭ", "§rЧерез несколько секунд рестарт сервера, пвп отключено!");
                    $damager->sendMessage("§l§cᴘᴠᴘ §l§7► §r§fПерезагрузка сервера через §a0м. {$time}§а.");
                    $event->setCancelled(true);
                    return true;
                }

                if ($damager->getGamemode() == 1 && $entity->getGamemode() == 0) {
                    $event->setCancelled();
                    $damager->sendPopup("§l§7► §r§fВы не можете драться в режиме Креатив! §7§l◄§r");
                    return;
                }

                if ($damager->getAllowFlight(true)) {
                    $event->setCancelled();
                    $damager->sendPopup("§l§7► §r§fВы не можете драться в режиме Полета! §7§l◄§r");
                    return;
                }

                if ($entity->getAllowFlight()) {
                    $event->setCancelled();
                    $damager->sendPopup("§l§7► §r§fУ игрока включен режим Полета! §7§l◄§r");
                    return;
                }

                $result = Loader::getInstance()->rg->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
                $resultd_check = Loader::getInstance()->rg->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $xd AND $xd <= Pos2X) AND (Pos1Y <= $yd AND $yd <= Pos2Y) AND (Pos1Z <= $zd AND $zd <= Pos2Z) AND Level = '" . $leveld . "';")->fetchArray(SQLITE3_ASSOC);
                $resultd = Loader::getInstance()->rg->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $xd AND $xd <= Pos2X) AND (Pos1Y <= $yd AND $yd <= Pos2Y) AND (Pos1Z <= $zd AND $zd <= Pos2Z) AND Level = '" . $leveld . "';")->fetchArray(SQLITE3_ASSOC);
                $pvpd_flag = false;
                $pvpd_flag_check = false;
                if($resultd !== false && isset($resultd['Region'])){
                    $pvpd_flag = Loader::getInstance()->rg->db->query("SELECT * FROM FLAGS WHERE Region = '" . $resultd['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
                    $pvpd_flag_check = Loader::getInstance()->rg->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $resultd['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
                }
                $resulte_check = Loader::getInstance()->rg->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $xe AND $xe <= Pos2X) AND (Pos1Y <= $ye AND $ye <= Pos2Y) AND (Pos1Z <= $ze AND $ze <= Pos2Z) AND Level = '" . $levele . "';")->fetchArray(SQLITE3_ASSOC);
                $resulte = Loader::getInstance()->rg->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $xe AND $xe <= Pos2X) AND (Pos1Y <= $ye AND $ye <= Pos2Y) AND (Pos1Z <= $ze AND $ze <= Pos2Z) AND Level = '" . $levele . "';")->fetchArray(SQLITE3_ASSOC);
                $pvpe_flag = false;
                $pvpe_flag_check = false;
                if($resulte !== false && isset($resulte['Region'])){
                    $pvpe_flag = Loader::getInstance()->rg->db->query("SELECT * FROM FLAGS WHERE Region = '" . $resulte['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
                    $pvpe_flag_check = Loader::getInstance()->rg->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $resulte['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
                }

                if ($entity instanceof Player && $damager instanceof Player) {
                    $resultd_check_count = ($resultd_check !== false && isset($resultd_check['count'])) ? $resultd_check['count'] : 0;
                    $pvpd_flag_check_count = ($pvpd_flag_check !== false && isset($pvpd_flag_check['count'])) ? $pvpd_flag_check['count'] : 0;
                    $resulte_check_count = ($resulte_check !== false && isset($resulte_check['count'])) ? $resulte_check['count'] : 0;
                    $pvpe_flag_check_count = ($pvpe_flag_check !== false && isset($pvpe_flag_check['count'])) ? $pvpe_flag_check['count'] : 0;
                    
                    if (($resultd_check_count && $pvpd_flag_check_count) || ($resulte_check_count && $pvpe_flag_check_count)) {
                        $pvpd_flag_value = ($pvpd_flag !== false && isset($pvpd_flag['Value'])) ? $pvpd_flag['Value'] : null;
                        $pvpe_flag_value = ($pvpe_flag !== false && isset($pvpe_flag['Value'])) ? $pvpe_flag['Value'] : null;
                        
                        if ($pvpd_flag_value == "deny" && $pvpe_flag_value != "deny") {
                            $event->setCancelled(true);
                            return true;
                        }

                        if ($pvpd_flag_value == "deny" && $pvpe_flag_value == "deny") {
                            $event->setCancelled(true);
                            return true;
                        }

                        if ($pvpd_flag_value != "deny" && $pvpe_flag_value == "deny") {
                            $event->setCancelled(true);
                            return true;
                        }
                    }
                }

                if ($result !== false && isset($result["Region"]) && ($result["Region"] == "spawn" or $result["Region"] == "mine")) {
                    $event->setCancelled(true);
                    return true;
                }

                $os = Loader::AVAILABLE_OS[$entity->getDeviceOS()];
                $health = $entity->getHealth();
                $damager->sendPopup("§r⨞ §r§fВраг§7: §a{$entity->getName()} §c{$health}❤ §7| §r{$pingColor}{$ping}ms §7| §r§fУстройство§7: §l{$os} §r§f⨟");

                if ($playTime < $requiredPlayTime) {
                    $damager->sendMessage("§l§cPVPBLOCK §7§l► §rДля входа в пвп, нужно наиграть §a5м§r. Вы наиграли: §a" . Loader::getInstance()->parseTime($playTime) . "§r");
                    $event->setCancelled(true); 
                    return;
                }

                $playTimeEntity = GameTimer::getGameTime($entity);
                if ($playTimeEntity < $requiredPlayTime) {
                    $damager->sendMessage("§l§cPVPBLOCK §7§l► §rВы не можете §aатаковать§r этого игрока, у него §l§aзащита новичка§r!§r");
                    $event->setCancelled(true); 
                    return;
                }
            }
        }
        // Убедитесь, что это событие EntityDamageByEntityEvent перед вызовом getDamager()
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager(); //179 строка
            $entityName = mb_strtolower($entity->getName());
            $damagerName = mb_strtolower($damager->getName());

            if ($entityName === $damagerName) {
                //$event->setCancelled(true);
            } else {
                $currentTime = time();
                $pvpTime = $currentTime + 20;

                $loaderInstance = Loader::getInstance();
                $loaderInstance->pvp[$entityName] = $pvpTime;
                $loaderInstance->pvp[$damagerName] = $pvpTime;
                $loaderInstance->addOpponent($entityName, $damagerName);
                $loaderInstance->addOpponent($damagerName, $entityName);
            }
        }
    }
}
}