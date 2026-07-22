<?php

namespace WorldGuardian;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

use api\Loader;

class WorldGuardian extends PluginBase implements CommandExecutor, Listener{
	public $db, $pos1 = [], $pos2 = [];

	public $config;
	public $xgroup;
	public $sells;


	//For EconomyJob | Region Checking
	public function regionHere($x, $y, $z, $level){
		$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		if($count['count']) return true;else return false;

	}

	private function getSoldRegions() {
		$soldRegions = [];
		$result = $this->db->query("SELECT Region, Owner, Price FROM AREAS WHERE Sell != 0")->fetchAll(SQLITE3_ASSOC);

		foreach ($result as $row) {
			$soldRegions[] = [
				'name' => $row['Region'],
				'owner' => $row['Owner'],
				'price' => $row['Price']
			];
		}
		return $soldRegions;
	}

	public function onBlockBreak(BlockBreakEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$x = round($block->getX());
		$y = round($block->getY());
		$z = round($block->getZ());
		$level = $block->getLevel()->getName();
		$username = strtolower($player->getName());
		$item = $event->getItem()->getID();
		$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);

		$find = $x.":".$y.":".$z.":".$level;
        if(isset($this->sells[$find])) {
            $region = $this->sells[$find]["region"];
                if($result !== false && $username != $result['Owner'] && !$player->isOp()) {
                    $player->sendMessage("§l§6➛ §rВы §cне владелец §rэтого региона§7!");
				    $event->setCancelled(true);
				    return;
                }

            $this->db->query("UPDATE AREAS SET Sell = '0' WHERE Region = '$region'");
            $this->sells[$find] = null;
            unset($this->sells[$find]);
            $player->sendMessage("§l§6➛ §rРегион§a {$region} §rуспешно снят с §aпродажи§r!");
        }

		if ($result !== false && isset($result["Region"]) && ($result["Region"] == "mine123" or $result["Region"] == "mine")) {
			if($player->getGamemode() === Player::CREATIVE) {
                $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r чтобы ломать §aшахту§r!");
                $event->setCancelled(true);
            }
        }

        if ($result !== false && isset($result["Region"]) && ($result["Region"] == "spawn" || $result["Region"] == "pvpzona" || $result["Region"] == "pvpzona2")) {
        	if ($player->getName() !== "Wassabi") {
        		$event->setCancelled(true);
        	}
        }

		if($item == 104){
			$this->pos1[$username] = [$x, $y, $z, $level];
			$player->sendMessage("§r§7Первая точка установлена на §r§7[ §l§a{$x}§r§7, §l§a{$y}§r§7, §l§a{$z} §7]");

			if(isset($this->pos1[$username]) && isset($this->pos2[$username]) && $this->pos1[$username][3] == $this->pos2[$username][3]){
				$pos1 = $this->pos1[$username];
				$pos2 = $this->pos2[$username];
				$min[0] = min($pos1[0], $pos2[0]);
				$max[0] = max($pos1[0], $pos2[0]);
				$min[1] = min($pos1[1], $pos2[1]);
				$max[1] = max($pos1[1], $pos2[1]);
				$min[2] = min($pos1[2], $pos2[2]);
				$max[2] = max($pos1[2], $pos2[2]);
				$count = $this->countBlocks($min[0], $min[1], $min[2], $max[0], $max[1], $max[2]);
				$player->sendMessage("§r§7Выбрано§l§a {$count} §r§7блоков§7.");
			}
			$event->setCancelled(true);
		}else{
			if ($result !== false && isset($result['Region'])) {
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Region = '" . $result['Region'] . "' AND Name = '$username'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'build' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				$chest_access_flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'chest-access' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);

				if($username != $result['Owner'] && !$player->isOp() && $member !== false && isset($member['count']) && $flag !== false && isset($flag['count']) && !$member['count'] && !$flag['count']){
					$player->sendPopup("§c§l✘ §r§fНельзя §aвзаимодействовать§r§f с данным регионом §c§l✘§r");
					$event->setCancelled(true);
				}
			}
		}
	}

	/**
	 * @param EntityDamageEvent $event
	 * @priority LOWEST
	 * @ignoreCancelled false
	 */
	public function onEntityDamageByEntity(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($event instanceof EntityDamageByEntityEvent){
			$damager = $event->getDamager();
			$leveld = $damager->getLevel()->getName();
			$xd = round($damager->getX());
			$yd = round($damager->getY());
			$zd = round($damager->getZ());
			$resultd_check = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $xd AND $xd <= Pos2X) AND (Pos1Y <= $yd AND $yd <= Pos2Y) AND (Pos1Z <= $zd AND $zd <= Pos2Z) AND Level = '" . $leveld . "';")->fetchArray(SQLITE3_ASSOC);
			$resultd = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $xd AND $xd <= Pos2X) AND (Pos1Y <= $yd AND $yd <= Pos2Y) AND (Pos1Z <= $zd AND $zd <= Pos2Z) AND Level = '" . $leveld . "';")->fetchArray(SQLITE3_ASSOC);
			
			$pvpd_flag = false;
			$pvpd_flag_check = false;
			if($resultd !== false && is_array($resultd) && isset($resultd['Region'])){
				$pvpd_flag = $this->db->query("SELECT * FROM FLAGS WHERE Region = '" . $resultd['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
				$pvpd_flag_check = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $resultd['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
			}
			
			$levele = $entity->getLevel()->getName();
			$xe = round($entity->getX());
			$ye = round($entity->getY());
			$ze = round($entity->getZ());
			$resulte_check = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $xe AND $xe <= Pos2X) AND (Pos1Y <= $ye AND $ye <= Pos2Y) AND (Pos1Z <= $ze AND $ze <= Pos2Z) AND Level = '" . $levele . "';")->fetchArray(SQLITE3_ASSOC);
			$resulte = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $xe AND $xe <= Pos2X) AND (Pos1Y <= $ye AND $ye <= Pos2Y) AND (Pos1Z <= $ze AND $ze <= Pos2Z) AND Level = '" . $levele . "';")->fetchArray(SQLITE3_ASSOC);
			
			$pvpe_flag = false;
			$pvpe_flag_check = false;
			if($resulte !== false && is_array($resulte) && isset($resulte['Region'])){
				$pvpe_flag = $this->db->query("SELECT * FROM FLAGS WHERE Region = '" . $resulte['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
				$pvpe_flag_check = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $resulte['Region'] . "' AND Flag = 'pvp'")->fetchArray(SQLITE3_ASSOC);
			}

			if($entity instanceof Player && $damager instanceof Player){
				$resultd_check_valid = $resultd_check !== false && is_array($resultd_check) && isset($resultd_check['count']) && $resultd_check['count'];
				$pvpd_flag_check_valid = $pvpd_flag_check !== false && is_array($pvpd_flag_check) && isset($pvpd_flag_check['count']) && $pvpd_flag_check['count'];
				$resulte_check_valid = $resulte_check !== false && is_array($resulte_check) && isset($resulte_check['count']) && $resulte_check['count'];
				$pvpe_flag_check_valid = $pvpe_flag_check !== false && is_array($pvpe_flag_check) && isset($pvpe_flag_check['count']) && $pvpe_flag_check['count'];
				
				if(($resultd_check_valid && $pvpd_flag_check_valid) || ($resulte_check_valid && $pvpe_flag_check_valid)){
					$pvpd_value = ($pvpd_flag !== false && is_array($pvpd_flag) && isset($pvpd_flag['Value'])) ? $pvpd_flag['Value'] : null;
					$pvpe_value = ($pvpe_flag !== false && is_array($pvpe_flag) && isset($pvpe_flag['Value'])) ? $pvpe_flag['Value'] : null;
					
					if($pvpd_value == "deny" && $pvpe_value != "deny"){
						$event->setCancelled(true);
						$damager->sendPopup("§r§aВ этом регионе запрещено PVP!");
					}
					if($pvpd_value == "deny" && $pvpe_value == "deny"){
						$event->setCancelled(true);
						$damager->sendPopup("§r§aВ этом регионе запрещено PVP!");
					}
					if($pvpd_value != "deny" && $pvpe_value == "deny"){
						$event->setCancelled(true);
						$damager->sendPopup("§r§aВ этом регионе запрещено PVP!");
					}
				}
			}
		}
	}


	/**
	 * @param EntityDamageEvent $event
	 * @priority LOWEST
	 * @ignoreCancelled false
	 */
	public function onEntityDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$x = round($entity->getX());
			$y = round($entity->getY());
			$z = round($entity->getZ());
			$level = $entity->getLevel()->getName();
			$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			
			if ($result !== false && isset($result["Region"])) {
				if ($result["Region"] == "spawn" || $result["Region"] == "spawn1") {
					$event->setCancelled(true);
				}
				
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'invincible' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				
				if($count !== false && isset($count['count']) && $flag !== false && isset($flag['count']) && $count['count'] && $flag['count']){
					$event->setCancelled(true);
				}
			}
		}
	}


	public function onPlayerChat(PlayerChatEvent $event){
		$player = $event->getPlayer();
		$x = round($player->getX());
		$y = round($player->getY());
		$z = round($player->getZ());
		$level = $player->getLevel()->getName();
		$username = strtolower($player->getName());
		$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		if($result !== false && is_array($result) && isset($result['Region']) && $count !== false && is_array($count) && isset($count['count'])){
			$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'send-chat' AND Value = 'deny'")->fetchArray(SQLITE3_ASSOC);
			if($count['count'] && $flag !== false && is_array($flag) && isset($flag['count']) && $flag['count'] && !$player->isOp()){
				$event->setCancelled(true);
			}
		}
	}


	public function onPlayerDropItem(PlayerDropItemEvent $event){
		$player = $event->getPlayer();
		$x = round($player->getX());
		$y = round($player->getY());
		$z = round($player->getZ());
		$level = $player->getLevel()->getName();
		$username = strtolower($player->getName());
		$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		if($result !== false && is_array($result) && isset($result['Region'])){
			$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'item-drop' AND Value = 'deny'")->fetchArray(SQLITE3_ASSOC);
			if($flag !== false && is_array($flag) && isset($flag['count']) && $flag['count']){
				$player->sendTitle("§r§aВ этом регионе запрещено выбрасывать мусор!");
				$event->setCancelled(true);
			}
		}
	}


	public function onBlockPlace(BlockPlaceEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$bid = $block->getId();
		$x = round($block->getX());
		$y = round($block->getY());
		$z = round($block->getZ());
		$level = $block->getLevel()->getName();
		$username = strtolower($player->getName());
		$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		if ($result !== false && isset($result['Region'])) {
			$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Region = '" . $result['Region'] . "' AND Name = '$username'")->fetchArray(SQLITE3_ASSOC);
			$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'build' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
		}

		if ($result !== false && isset($result["Region"]) && ($result["Region"] == "spawn" || $result["Region"] == "pvpzona" || $result["Region"] == "pvpzona2")) {
        	if ($player->getName() !== "Wassabi") {
        		$event->setCancelled(true);
        	}
        }

        if($bid == 290 || $bid == 291 || $bid == 292 || $bid == 293 || $bid == 294){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'spawner-use' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup("§c§l✘ §r§fНельзя использовать §aмотыгу§r в этом регионе! §c§l✘§r");
						return true;
					}
				}
			}
		}
        
		if($result !== false and $username != $result['Owner'] and !$player->isOp() and !$member['count'] and !$flag['count']){
			$player->sendPopup("§c§l✘ §r§fНельзя §aвзаимодействовать§r§f с данным регионом §c§l✘§r");
			$event->setCancelled(true);
		}
	}


	public function onInteract(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$bid = $block->getId();
		$spawner = $block->getId();
		$x = round($block->getX());
		$y = round($block->getY());
		$z = round($block->getZ());
		$level = $block->getLevel()->getName();
		$username = mb_strtolower($player->getName());
		$item = $event->getItem()->getId();
		$money = Loader::getInstance()->getMoney($player);
		$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
		$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);


		$find = $x . ":" . $y . ":" . $z . ":" . $level;
		if(isset($this->sells[$find])){
			$region = $this->sells[$find]["region"];
			$xgroup = $this->xgroup->getAll();
			$user_group = $this->getPlayerGroup($player->getName());

			if(isset($xgroup[$user_group]) && is_array($xgroup[$user_group])) $group = $user_group;
		    else $group = "User";

			if($result == false) {
                $player->sendMessage("§l§6➛ §rЭтот регион §cбольше не продается§r!");
                $event->setCancelled(true);
                return;
            }
            if($username == $result['Owner']){
                $player->sendMessage("§l§6➛ §rВы §cне можете покупать §fрегион у самого себя§r!");
                return;
            }
            if(!$count['count']) {
                $player->sendMessage("§l§6➛ §rЭтот регион больше §cне продается§r!");
                return;
            }
            if($money < $this->sells[$find]["price"]) {
                $player->sendMessage("§l§6➛ §rУ вас §cне хватает денег §rна покупку данной территории§r. §rОтправляйтесь на работу§7, §fбездельник §a:)");
                $event->setCancelled(true);
                return;
            }

			if(($this->sells[$find]["blocks"] > $xgroup[$group]['max_region_count_blocks']) && !$player->isOp()){
				$player->sendMessage("§l§6➛§r Вы заприватили больше количество блоков чем у вас §сдоступно! §7( §a({$this->sells[$find]["blocks"]})§r§7/§l§a({$xgroup[$group]['max_region_count_blocks']}) §r§7)");
				$event->setCancelled(true);
				return;
			}

			$rg_count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Owner = '$username'")->fetchArray();
			if($rg_count['count'] >= $xgroup[$group]["max_regions_num"] and !$player->isOp()){
				$player->sendMessage("§l§6➛§r Вы не можете создать более§a {$xgroup[$group]['max_regions_num']} §r§rрегионов.\n");
				$player->sendMessage("§l§6➛§r У вас уже есть§a {$rg_count['count']} §r§7регионов§7.\n§l§6➛§r Больше для данной привилегии §aне доступно§7.\n§l§6➛§r Купить привелегию на сайте§7: §aonewix-pe.fun§7.");
				$event->setCancelled(true);
				return;
			}
			Loader::getInstance()->addMoneyy($result['Owner'], $this->sells[$find]["price"]);
			$this->db->exec("DELETE FROM MEMBERS WHERE Region = '$region'; DELETE FROM FLAGS WHERE Region = '$region'");
			$this->db->query("UPDATE AREAS SET Sell = '0' WHERE Region = '$region'");
            $this->db->query("UPDATE AREAS SET Owner = '$username' WHERE Region = '$region'");
            Loader::getInstance()->remMoney($player, $this->sells[$find]["price"]);
            $this->sells[$find] = null;
            unset($this->sells[$find]);
            $player->sendMessage("§l§6➛ §rВы §aуспешно приобрели §fрегион§a {$region}§7. §rНе забудьте сломать §fтабличку!");
		}


		if($item == 104){
			$this->pos2[$username] = [$x, $y, $z, $level];
			$player->sendMessage("§r§7Вторая точка установлена на §r§7[ §l§a{$x}§r§7, §l§a{$y}§r§7, §l§a{$z} §r§7]");
			if(isset($this->pos1[$username]) && isset($this->pos2[$username]) && $this->pos1[$username][3] == $this->pos2[$username][3]){
				$pos1 = $this->pos1[$username];
				$pos2 = $this->pos2[$username];
				$min[0] = min($pos1[0], $pos2[0]);
				$max[0] = max($pos1[0], $pos2[0]);
				$min[1] = min($pos1[1], $pos2[1]);
				$max[1] = max($pos1[1], $pos2[1]);
				$min[2] = min($pos1[2], $pos2[2]);
				$max[2] = max($pos1[2], $pos2[2]);
				$count = $this->countBlocks($min[0], $min[1], $min[2], $max[0], $max[1], $max[2]);
				$player->sendMessage("§r§7Выбрано§l§a {$count} §r§7блоков§7.");
			}
			$event->setCancelled(true);
		}

		if($bid == 54 || $bid == 218 || $bid == 61 || $bid == 145 || $bid == 369){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'chest-access' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
			$player->sendPopup("§c§l✘ §r§fНельзя §aвзаимодействовать§r§f с данным регионом §c§l✘§r");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($item == 256 || $item == 269 || $item == 284 || $item == 277 || $item == 273){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
			             $player->sendPopup("§c§l✘ §r§fВы не можете это лопату в регионе! §c§l✘ §r§f");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($bid == 64 || $bid == 71 || $bid == 324 || $bid == 330){
			$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'use' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup(" ");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($bid == 61 || $bid == 62){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'use' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
			$player->sendPopup("§c§l✘ §r§fНельзя §aвзаимодействовать§r§f с данным регионом §c§l✘§r");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($item == 280){
			if($count['count']){
				$count_blocks = $this->countBlocks($result['Pos1X'], $result['Pos1Y'], $result['Pos1Z'], $result['Pos2X'], $result['Pos2Y'], $result['Pos2Z']);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '" . $result['Region'] . "' AND Flag = 'info' AND Value = 'deny'")->fetchArray(SQLITE3_ASSOC);
				if(!$flag['count'] || $username == $result['Owner'] || $player->isOp()){
					$player->sendMessage("\n§l§6➛§r Информация о регионе §a{$result['Region']}\n§l§6➛§r Владелец§7: §a{$result['Owner']}\n§l§6➛§r Количество блоков§7: §a{$count_blocks}\n");
					if($result['Sell'] == 0) $player->sendTip(" ");else $player->sendTip(" ");
				}else{
					$player->sendMessage("§l§6➛§r Информация об этом регионе §l§aСкрыта§7.");
				}
			}elseif(!$count['count']){
				$player->sendMessage("§l§6➛§r Приват §aне найден");
			}
		}

		/*if($bid == 52){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'spawner-use' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup("§c§l✘ §r§fНельзя использовать §aспавнер§r в регионе! §c§l✘§r");
						return true;
					}
				}
			}
		}
		**/
	}

	public function getSells(){
		return $this->sells;
	}

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$dbf = fopen($this->getDataFolder() . "regions.sqlite3", 'a');
		fwrite($dbf, "");
		fclose($dbf);
		$this->saveDefaultConfig();
		$this->sells = (new Config($this->getDataFolder(). "sells.yml", Config::YAML))->getAll();
		if(file_exists($this->getDataFolder() . "config.yml")){
			$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		}else{
			$this->config = new Config($this->getDataFolder() . "config.yml", Config::YAML, ['enable_permission_plugin_support' => true, 'permission_plugin' => 'PurePerms', 'default_group' => 'Player', 'use_world_parameter' => false]);
		}


		if (file_exists($this->getDataFolder() . "xgroups.yml")) {
            $this->xgroup = new Config($this->getDataFolder() . "xgroups.yml", Config::YAML);
        } else {
            $this->xgroup = new Config($this->getDataFolder() . "xgroups.yml", Config::YAML, array(
				"User" => array('max_regions_num' => 1,'max_region_count_blocks' => 5000),
				"Kronos" => array('max_regions_num' => 3,'max_region_count_blocks' => 15000),
				"Aristocrat" => array('max_regions_num' => 3,'max_region_count_blocks' => 35000),
				"Lucefer" => array('max_regions_num' => 6,'max_region_count_blocks' => 80000),
				"korol" => array('max_regions_num' => 8,'max_region_count_blocks' => 100000),
				"Gresh" => array('max_regions_num' => 10,'max_region_count_blocks' => 150000),
				"Knayz" => array('max_regions_num' => 15,'max_region_count_blocks' => 200000),
				"Patrik" => array('max_regions_num' => 20,'max_region_count_blocks' => 300000),
				"Yt" => array('max_regions_num' => 5,'max_region_count_blocks' => 200000),
				"Moderator" => array('max_regions_num' => 5,'max_region_count_blocks' => 100000),
                "Helper" => array('max_regions_num' => 5,'max_region_count_blocks' => 100000)
                 ));
        }
		$this->loadDB();
		$this->config->save();
		$this->xgroup->save();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}


	public function getPlayerGroup($username) {
        $ranks = $this->getServer()->getPluginManager()->getPlugin("NexAPI");

        $player = $this->getServer()->getPlayer($username);

        $rank = $ranks->getGroup($player->getName());
        return $rank;

    }


	public function onTouch(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$x = round($block->getX());
		$y = round($block->getY());
		$z = round($block->getZ());
		$level = $block->getLevel()->getName();
		$username = strtolower($player->getName());
		$item = $event->getItem();
		$id = $item->getId();

		if($id == 290 || $id == 291 || $id == 292 || $id == 293 || $id == 294){
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'spawner-use' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup("§c§l✘ §r§fНельзя использовать §aмотыгу§r в этом регионе! §c§l✘§r");
						return true;
					}
				}
			}
		}

		if($id == 351 && $item->getDamage() == 15){
			$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'bone-meal' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup(" ");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($id == 325){
			$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'bucket' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup(" ");
						$event->setCancelled(true);
					}
				}
			}
		}

		if($id == 259){
			$result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
			if($count['count']){
				$member = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username' AND Region = '" . $result['Region'] . "'")->fetchArray(SQLITE3_ASSOC);
				$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Flag = 'lighter' AND Region = '" . $result['Region'] . "' AND Value = 'allow'")->fetchArray(SQLITE3_ASSOC);
				if(!$member['count'] && !$flag['count'] && $username != $result['Owner']){
					if(!$player->isOp()){
						$player->sendPopup(" ");
						$event->setCancelled(true);
					}
				}
			}
		}
	}


	public function countBlocks($x1, $y1, $z1, $x2, $y2, $z2){
		$count = abs(($x2 - $x1 + 1) * ($y2 - $y1 + 1) * ($z2 - $z1 + 1));
		return $count;
	}


	public function loadDB(){
		@mkdir($this->getDataFolder());
		$this->db = new \SQLite3($this->getDataFolder() . "regions.sqlite3");
		$this->db->exec("CREATE TABLE IF NOT EXISTS AREAS(Region TEXT,Owner TEXT NOT NULL,Pos1X INTEGER NOT NULL,Pos1Y INTEGER NOT NULL,Pos1Z INTEGER NOT NULL,Pos2X INTEGER NOT NULL,Pos2Y INTEGER NOT NULL,Pos2Z INTEGER NOT NULL,Level TEXT NOT NULL,Sell INTEGER NOT NULL);CREATE TABLE IF NOT EXISTS MEMBERS(Name TEXT NOT NULL,Region TEXT NOT NULL);CREATE TABLE IF NOT EXISTS FLAGS(Region TEXT NOT NULL,Flag TEXT NOT NULL,Value TEXT NOT NULL);");
	}


	public function onDisable(){
		$this->db->close();
		$config = (new Config($this->getDataFolder() . "sells.yml", Config::YAML));
		$config->setAll($this->sells);
		$config->save();
	}


	private function member($player, $username){
		$result = $this->db->query("SELECT * FROM MEMBERS WHERE Name = '$username'");
		$result_check = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Name = '$username'")->fetchArray(SQLITE3_ASSOC);
		if($result_check['count']){
			$player->sendMessage("§l§6➛§r Вы добавлены в следующие регионы§7:");
			while($list = $result->fetchArray(SQLITE3_ASSOC)){
				$player->sendMessage("§l§6➛§r Регионы §a{$list['Region']}");
			}
		}else{
			$player->sendMessage("§l§6➛§r Вас никто не добавлял в свой §aРегион§7.");
		}
	}


	private function addmember($player, $username, $region, $member){
		if(!$player instanceof Player){
			return;
		}
		if(!$player->isOp()){
			$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$region' AND Owner = '$username'")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region' AND Owner = '$username'")->fetchArray(SQLITE3_ASSOC);
		}else{
			$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
		}

		if($count['count']){
			$check = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Region = '$region' AND Name = '$member'")->fetchArray(SQLITE3_ASSOC);
			if(!$check['count']){
				$this->db->query("INSERT INTO MEMBERS (Region, Name) VALUES ('$region','$member')");
				$player->sendMessage("§l§6➛§r Игрок§a {$member} §rбыл добавлен в ваш регион§7.");
			}else $player->sendMessage("§l§6➛§r Игрок§a {$member} §rуже добавлен в ваш регион§7.");
		}else $player->sendMessage("§l§6➛§r Региона§a {$region} §aне существует§7.");
	}


	private function removemember($player, $username, $region, $member){
		if(!$player->isOp()){
			$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$region' AND Owner = '$username'")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region' AND Owner = '$username'")->fetchArray(SQLITE3_ASSOC);
		}else{
			$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
		}

		if($count['count']){
			$check = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Region = '$region' AND Name = '$member'")->fetchArray(SQLITE3_ASSOC);
			if($check['count']){
				$this->db->query("DELETE FROM MEMBERS WHERE Region = '$region' AND Name = '$member'");
				$player->sendMessage("§l§6➛§r Игрок§a {$member} §r§rбыл исключён из вашего региона§7.");
			}else $player->sendMessage("§l§6➛§r Игрок§a {$member} §r§rне прописан в вашем регионе§7.");
		}else $player->sendMessage("§l§6➛§r Региона§a {$region} §rне существует§7.");
	}


	private function flag($player, $username, $region, $flag, $value){
		if(!$player->isOp()){
			$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Owner = '$username' AND Region = '$region'")->fetchArray(SQLITE3_ASSOC);
		}else $count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);

		if($count['count']){
			if($flag == "pvp" || $flag == "build" || $flag == "chest-access" || $flag == "spawner-use" || $flag == "use" || $flag == "info" || $flag == "bone-meal" || $flag == "bucket" || $flag == "lighter" || $flag == "send-chat" || $flag == "item-drop" || ($flag == "invincible" && $player->isOp())){
				if($value == "allow" || $value == "deny"){
					$check_flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '$region' AND Flag = '$flag'")->fetchArray(SQLITE3_ASSOC);
					if($check_flag['count']) $this->db->query("UPDATE FLAGS SET Value = '$value' WHERE Region = '$region' AND Flag = '$flag'");else $this->db->query("INSERT INTO FLAGS (Region, Flag, Value) VALUES ('$region', '$flag', '$value')");
					$player->sendMessage("§l§6➛§r Установлено значение §a'{$value}' §rдля флага§7 '{$flag}'");

				}else $player->sendMessage("§l§6➛ §rЗначение может быть только §a'allow' §8[§aразрешить§8] §7или §a'deny' §8[§aзапретить§8].");
			}else{
				$player->sendMessage("§l§6➛§r Существующие флаги: §apvp§f, §abuild§f, §achest-access§7, §ause§7, §ainfo§7,§a bone-meal§7, §abucket§7, §alighter§7,§a send-chat§7, §aitem-drop§7, §aspawner-use");
				if($player->isOp()) $player->sendMessage("§r§7Флаги для администраторов§7: §apvp");
				if(($flag == "ddddd") && !$player->isOp()) $player->sendMessage("§r§7Вы §aне можете §7устанавливать этот флаг§7.");
			}
		}else $player->sendMessage("§l§6➛§r Регион§a {$region} §aне существует§7.");
	}


	private function leaveregion($player, $username, $region){
		$check = $this->db->query("SELECT COUNT(*) as count FROM MEMBERS WHERE Region = '$region' AND Name = '$username'")->fetchArray(SQLITE3_ASSOC);
		if($check['count']){
			$this->db->query("DELETE FROM MEMBERS WHERE Name = '$username' AND Region = '$region'");
			$player->sendMessage("§l§6➛ §rВы покинули регион§a {$region}");
		}else $player->sendMessage("§l§6➛§r Вы не прописаны в регионе§a {$region}");
	}


	private function claim($player, $username, $region){
		$level = $player->getLevel()->getName();
		$xgroup = $this->xgroup->getAll();
		$user_group = $this->getPlayerGroup($username);

		if(isset($xgroup[$user_group]) && is_array($xgroup[$user_group])){
			$group = $user_group;
		}else{
			$group = "User";
		}

		if(preg_match("/^[a-zA-Z0-9_]+$/", $region)){
			$check = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
			if(!$check['count']){
				if(!isset($this->pos1[$username]) || !isset($this->pos2[$username])){
					$player->sendMessage("§l§6➛§r Выделите область региона!\n§l§6➛§r §a/rg pos1§7 |§a /rg pos2");
					return true;
				}

				if($this->pos1[$username][3] !== $this->pos2[$username][3]){
					$player->sendMessage("§l§6➛§r Выбранные точки в разных мирах§7!");
					return true;
				}
				$pos1 = $this->pos1[$username];
				$pos2 = $this->pos2[$username];
				$min[0] = min($pos1[0], $pos2[0]);
				$max[0] = max($pos1[0], $pos2[0]);
				$min[1] = min($pos1[1], $pos2[1]);
				$max[1] = max($pos1[1], $pos2[1]);
				$min[2] = min($pos1[2], $pos2[2]);
				$max[2] = max($pos1[2], $pos2[2]);
				$count = $this->countBlocks($min[0], $min[1], $min[2], $max[0], $max[1], $max[2]);
				$result = $this->db->query("SELECT * FROM AREAS WHERE Pos2X >= $min[0] AND Pos1X <= $max[0] AND Pos2Y >= $min[1] AND Pos1Y <= $max[1] AND Pos2Z >= $min[2] AND Pos1Z <= $max[2] AND Level = '" . $pos1[3] . "';")->fetchArray(SQLITE3_ASSOC);
				if($result !== false && !$player->isOp()){
					$player->sendMessage("§l§6➛ §rЭтот регион пересекает границу региона§a {$result['Region']}");
					return true;

				}elseif(($count > $xgroup[$group]['max_region_count_blocks']) && !$player->isOp()){
					$player->sendMessage("§l§6➛§r Максимальное допустимое количество блоков региона§a {$xgroup[$group]['max_region_count_blocks']}\n§l§6➛§r Вы выделили §a{$count}");
					return true;
				}

				$level = $pos1[3];
				$rg_count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Owner = '$username'")->fetchArray();
				if($rg_count['count'] < $xgroup[$group]["max_regions_num"] || $player->isOp()){
					$this->db->exec("INSERT INTO AREAS (Owner, Pos1X, Pos1Y, Pos1Z, Pos2X, Pos2Y, Pos2Z, Level, Region, Sell) VALUES ('$username', $min[0], $min[1], $min[2], $max[0], $max[1], $max[2], '$level', '$region', '0')");
					unset($this->pos1[$username]);
					unset($this->pos2[$username]);
					$player->sendMessage("§l§6➛ §rНовый регион успешно создан с названием§a {$region}");

				}else{
					$player->sendMessage("§l§6➛§rВы не можете создать более§a {$xgroup[$group]['max_regions_num']} §rрегионов§7.\n§l§6➛§rВы уже создали§a {$rg_count['count']} §rрегионов§7.");
				}
			}else{
				$player->sendMessage("§l§6➛§rРегион с названием§a {$region} §aуже существует§7!");
			}
		}else{
			$player->sendMessage("§l§6➛§rНекорректное название региона §7!\n§l§6➛§rДопускаются только буквы латинского алфавита§7, цифры и §7нижнее подчёркивание§7.");
		}
	}


	private function unclaim($player, $username, $region){
		if($player->isOp()){
			$rg_count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray();
		}else{
			$rg_count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Owner = '$username' AND Region = '$region'")->fetchArray();
		}

		if($rg_count['count']){
			$this->db->exec("DELETE FROM AREAS WHERE Region = '$region'; DELETE FROM MEMBERS WHERE Region = '$region'; DELETE FROM FLAGS WHERE Region = '$region'");
			$player->sendMessage("§l§6➛§r Вы удалили регион§a {$region}");
		}else $player->sendMessage("§l§6➛§r Регион§a {$region} §7не существует§7.");
	}


	private function rglist($player, $username, $who){
		if($player->isOp()) $list_sql = "SELECT * FROM AREAS WHERE Owner = '$who'";else $list_sql = "SELECT * FROM AREAS WHERE Owner = '$username'";

		$query = $this->db->query($list_sql);
		$player->sendMessage("§l§6➛§r Регионы игрока§7:");
		while($row = $query->fetchArray()){
			$player->sendMessage("§7- §a" . $row['Region']);
		}
	}


	public function onCommand(CommandSender $sender, Command $cmd, $label, array $args){
		foreach($args as $arg){
			if(preg_match('/\'/', $arg) == 1){
				break;
				$sender->sendMessage("Попытка взлома");
				return false;
			}
		}

		$username = strtolower($sender->getName());
		$c = $cmd->getName();
		$player = $this->getServer()->getPlayer($username);

		if($c == "rg" or $c == "region"){
			if(isset($args[0])){
				switch($args[0]){

					case "city":
						if(isset($args[1])){
							switch($args[1]){
								case "spawn2":
									$sender->sendMessage("§r✎ Вы телепортировались в город §aСредневековъя");
									$sender->teleport(new Position(-444, 66, 294));
									if($args[1] !== "middle_ages"){
										$sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg city§a Название Города\n§l§6➛§r§r Города сервера§7:§a На сервере нету городов...");
									}
								break;
								case "spawn1":
									$sender->sendMessage("§r✎ Вы телепортировались в город §aМодерн");
									$sender->teleport(new Position(-156, 66, 68));
									if($args[1] !== "modern"){
										$sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg city§a Название Города\n§l§6➛§r§r Города сервера§7:§a На сервере нету городов...");
									}
								break;
							}
						}else{
							$sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg city§a Название Города\n§l§6➛§r§r Города сервера§7:§a На сервере нету городов...");
						}
					break;

					case 'addmember':
						if(isset($args[1]) and isset($args[2])){
							$region = strtolower($args[1]);
							$member = strtolower($args[2]);
							$this->addmember($player, $username, $region, $member);
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg addmember§7 (§aНазвание региона§7) (§aНикйнем§7)\n§l§6➛§r§a Будьте осторожны, не добавляйте игроков, которых вы не знаете. Они могут сломать ваш дом.");
					break;

					case 'removemember':
						if(isset($args[1]) and isset($args[2])){
							$region = strtolower($args[1]);
							$member = strtolower($args[2]);
							$this->removemember($player, $username, $region, $member);
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg removemember§7 (§aРегион§7) (§aНикйнем§7)\n§l§6➛ §rЧтобы точно удалить игрока из региона, никнейм пишите полностью.");
					break;

					case 'flag':
						if($sender->hasPermission("flag.g")){
							if(isset($args[1]) and isset($args[2]) and isset($args[3])){
								$region = strtolower($args[1]);
								$flag = strtolower($args[2]);
								$value = strtolower($args[3]);
								$this->flag($player, $username, $region, $flag, $value);
							}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg flag§7 (§aРегион§7) (§aНазвание флага§7) (§7allow,deny§7)\n§l§6➛ §rФлаги сервера§7: §apvp§f, §abuild§f, §achest-access§7, §ause§7, §ainfo§7,§a bone-meal§7, §abucket§7, §alighter§7,§a send-chat§7, §aitem-drop§7, §aspawner-use");
							break;
						}

					case 'leave':
						if(isset($args[1])){
							$region = strtolower($args[1]);
							$this->leaveregion($player, $username, $region);
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg leave§7 (§aРегион§7)\n§l§6➛ §rЕсли вдруг забыли название региона, можно посмотреть по команде §a/rg list");
					break;

					case 'wand':
						$id = Item::get(271, 0, 1);
						$player->getInventory()->addItem($id);
						$player->sendMessage("§r§7 §rДолгий тап §8[§aсломать блок§8] §7первая точка привата\n§r§7 §rОбычный тап: вторая §aточка привата");
					break;

					case 'create':
					case "claim":
						if(isset($args[1])){
							$region = strtolower($args[1]);
							$region = str_replace('"', "", $region);
							$region = str_replace("'", "", $region);
							$region = str_replace("or", "", $region);
							$region = str_replace("like", "", $region);
							$region = str_replace("where", "", $region);
							$region = str_replace("update", "", $region);
							$region = str_replace("remove", "", $region);
							$region = str_replace("limit", "", $region);
							$this->claim($player, $username, $region);
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg create§7 (§aНазвание§7)");
					break;

					case "remove":
					case "delete":
					case "unclaim":
						if(isset($args[1])){
							$region = strtolower($args[1]);
							$region = str_replace('"', "", $region);
							$region = str_replace("'", "", $region);
							$region = str_replace("or", "", $region);
							$region = str_replace("like", "", $region);
							$region = str_replace("where", "", $region);
							$region = str_replace("update", "", $region);
							$region = str_replace("remove", "", $region);
							$region = str_replace("limit", "", $region);
							$this->unclaim($player, $username, $region);
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg remove§7 (§aРегион§7)");
					break;

					case "pos2":
						$x = round($player->getX());
						$y = round($player->getY());
						$z = round($player->getZ());
						$level = $player->getLevel()->getName();
						$this->pos2[$username] = [$x, $y, $z, $level];
						$player->sendMessage("§l§6➛ §rВторая точка установлена на §r§7( §a{$x}§r§7, §a{$y}§r§7, §a{$z} §7)");
						if(isset($this->pos1[$username]) && isset($this->pos2[$username]) && $this->pos1[$username][3] == $this->pos2[$username][3]){
							$pos1 = $this->pos1[$username];
							$pos2 = $this->pos2[$username];
							$min[0] = min($pos1[0], $pos2[0]);
							$max[0] = max($pos1[0], $pos2[0]);
							$min[1] = min($pos1[1], $pos2[1]);
							$max[1] = max($pos1[1], $pos2[1]);
							$min[2] = min($pos1[2], $pos2[2]);
							$max[2] = max($pos1[2], $pos2[2]);
							$count = $this->countBlocks($min[0], $min[1], $min[2], $max[0], $max[1], $max[2]);
							$player->sendMessage("§l§6➛ §rВыбрано§a {$count} §r§rблоков§r!");
						}
					break;

					case "pos1":
						$x = round($player->getX());
						$y = round($player->getY());
						$z = round($player->getZ());
						$level = $player->getLevel()->getName();
						$this->pos1[$username] = [$x, $y, $z, $level];
						$player->sendMessage("§l§6➛§r Первая точка установлена на §r§7( §a{$x}§r§7, §a{$y}§r§7, §a{$z} §7)");
						if(isset($this->pos1[$username]) && isset($this->pos2[$username]) && $this->pos1[$username][3] == $this->pos2[$username][3]){
							$pos1 = $this->pos1[$username];
							$pos2 = $this->pos2[$username];
							$min[0] = min($pos1[0], $pos2[0]);
							$max[0] = max($pos1[0], $pos2[0]);
							$min[1] = min($pos1[1], $pos2[1]);
							$max[1] = max($pos1[1], $pos2[1]);
							$min[2] = min($pos1[2], $pos2[2]);
							$max[2] = max($pos1[2], $pos2[2]);
							$count = $this->countBlocks($min[0], $min[1], $min[2], $max[0], $max[1], $max[2]);
							$player->sendMessage("§l§6➛ §rВыбрано§a {$count} §rблоков§r!");
						}
					break;

					case "info":
						if(isset($args[1])){
							$subcommand = strtolower($args[1]);
							$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$subcommand'")->fetchArray(SQLITE3_ASSOC);
							$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$subcommand'")->fetchArray(SQLITE3_ASSOC);

							if($count['count']){
								$count_blocks = $this->countBlocks($result['Pos1X'], $result['Pos1Y'], $result['Pos1Z'], $result['Pos2X'], $result['Pos2Y'], $result['Pos2Z']);
								$flag = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '$subcommand' AND Flag = 'info' AND Value = 'deny'")->fetchArray(SQLITE3_ASSOC);

								if(!$flag['count'] || $username == $result['Owner'] || $player->isOp()){
									$player->sendMessage("§l§6➛ §rИнформация о регионе§7: §a{$result['Region']}\n§l§6➛ §rВладелец§7: §a{$result['Owner']}\n§a§l§6➛ §rКоличество блоков§7: §a{$count_blocks}\n§a§l§6➛ §rПервая точка§7: §a{$result['Pos1X']} {$result['Pos1Y']} {$result['Pos1Z']}\n§l§6➛ §rВторая точка§7: §a{$result['Pos2X']} {$result['Pos2Y']} {$result['Pos2Z']}");
									if($result['Sell'] == 0) ;
								}else{
									$player->sendMessage("§l§6➛ §rИнформация об этом регионе §aскрыта§7.");
								}
							}else $player->sendMessage("§l§6➛ §rРегиона§a {$subcommand} §aне существует§7.");
						}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg info§7 (§aРегион§7)");
					break;

					case "list":
						if(isset($args[1])) $this->rglist($player, $username, $args[1]);else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg list§7 (§aНикйнем§7)");
					break;


                    case "1tp":
                    case "1teleport":
                         if (count($args) < 2) {
                         	return $player->sendMessage("§l§6➛ §rИспользование §a/rg tp §7(§aназвание§7)");
                         }

                         $region = strtolower($args[1]);
                         $count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region' AND Sell != 0")->fetchArray(SQLITE3_ASSOC);

                         if ($count['count']) {
                         	$result = $this->db->query("SELECT * FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);

                         	$sellPos = explode(':', $result['Sell']);
                         	$x = (int)$sellPos[0];
                         	$y = (int)$sellPos[1];
                         	$z = (int)$sellPos[2];
                         	$level = $this->getServer()->getLevelByName($result['Level']);
                         	$player->teleport(new Position($x, $y, $z, $level));
                         	$player->sendMessage("§l§6➛§r Вы успешно телепортировались к региону§a $region");
                         } else {
                         	$player->sendMessage("§l§6➛ §rДанный регион не §aпродаётся§r!");
                         }
                    break;

                    case "listsell":
                         if (!$sender instanceof Player) {
                         	$sender->sendMessage("§cЭта команда доступна только для игроков.");
                         	return true;
                         }

                         $playerName = strtolower($sender->getName());
                         $soldRegions = $this->getSoldRegions();

                         if (empty($soldRegions)) {
                         	$sender->sendMessage("§l§6➛ §rНа данный момент нет проданных регионов.");
                         } else {
                         	$sender->sendMessage("§l§6➛ §rСписок проданных регионов:");
                         	foreach ($soldRegions as $region) {
                         		$sender->sendMessage("§aРегион: §f{$region['name']} §aЦена: §f" . number_format($region['price']) . " монет §aПродавец: §f{$region['owner']}");
                         	}
                         }
                    break;

					case "flags":
						if(isset($args[1])){
							$region = strtolower($args[1]);
							$count = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
							if($count['count']){
								$flags = $this->db->query("SELECT Flag,Value FROM FLAGS WHERE Region = '$region'");
								$count_flags = $this->db->query("SELECT COUNT(*) as count FROM FLAGS WHERE Region = '$region'")->fetchArray(SQLITE3_ASSOC);
								if($count_flags['count'] > 0){
									while($flags_list = $flags->fetchArray()){
										$player->sendMessage("§a{$flags_list['Flag']}: §a{$flags_list['Value']}");
									}
								}else $player->sendMessage("");
							}else $player->sendMessage("");
						}else $sender->sendMessage("");
					break;

					case "help":
						$player->sendMessage("§l§6➛ §rПомощь по привату.\n");
						$player->sendMessage(" §7➡ §a/rg info §7(§aрегион§7) §r- Узнать информацию о указанном §aрегионе.");
						$player->sendMessage(" §7➡ §a/rg addmember §7(§aрегион§7) §7(§aникнейм§7) §r- Добавить игрока §aв регион.");
						$player->sendMessage(" §7➡ §a/rg removemember §7(§aрегион§7) §7(§aникнейм§7) §r- Исключить игрока §aиз региона.");
						$player->sendMessage(" §7➡ §a/rg pos1 §rи§a /rg pos2 §r- Установить точки начала и конца §aнового региона");
						$player->sendMessage(" §7➡ §a/rg create §7(§aрегион§7) §r- Создать §aновый регион.");
						$player->sendMessage(" §7➡ §a/rg flag §7(§aрегион§7) §r- Выключить/включить какой либо флаг в §aрегионе");
                                                $player->sendMessage(" §7➡ §a/rg list §r - Посмотреть список §aрегионов.");
                                                $player->sendMessage(" §7➡ §a/rg leave §r - Выйти из региона игрока.");
					break;

				}
			}else $sender->sendMessage("§l§6➛ §rИспользование§7: §a/rg help");
		}
	}

	public function onSignChange(SignChangeEvent $event) {
    $player = $event->getPlayer();
    $name = strtolower($player->getName());
    $block = $event->getBlock();
    $level = $block->getLevel()->getName();
    $x = (int) $block->getX();
    $y = (int) $block->getY();
    $z = (int) $block->getZ();

    switch(mb_strtolower($event->getLine(0))) {
        case "sellreg":
        case "продаю":
        case "regsell":
        case "продается":
        case "продаётся":
        case "ргселл":
        case "продам":
            if (!is_numeric($event->getLine(1)) || (int)$event->getLine(1) <= 0) {
                $player->sendMessage("§l§6➛ §rВторая строчка §cдолжна состоять из положительных цифр§7. §fТам §aуказывается цена§r!");
                $event->setCancelled();
                return;
            }
            
            $result = $this->db->query("SELECT * FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
            $count  = $this->db->query("SELECT COUNT(*) as count FROM AREAS WHERE (Pos1X <= $x AND $x <= Pos2X) AND (Pos1Y <= $y AND $y <= Pos2Y) AND (Pos1Z <= $z AND $z <= Pos2Z) AND Level = '" . $level . "';")->fetchArray(SQLITE3_ASSOC);
            
            if ($count['count']) {
                if ($result['Owner'] != $name) {
                    $player->sendMessage("§l§6➛ §rВы §cне владелец §fданного региона§r!");
                    $event->setCancelled();
                    return;
                }

                $b = $event->getBlock();
                if ($result['Sell'] != 0) {
                    $sellPos = explode(':', $result['Sell']);
                    if ($b->x != $sellPos[0] || $b->y != $sellPos[1] || $b->z != $sellPos[2]) {
                        $player->sendMessage("§l§6➛ §rДанный регион §cуже продаётся§r!");
                        $player->sendMessage("§l§6➛ §rТабличка расположена на §a".$result['Sell']);
                        $event->setCancelled();
                        return;
                    }
                }

                $count_blocks = $this->countBlocks($result['Pos1X'], $result['Pos1Y'], $result['Pos1Z'], $result['Pos2X'], $result['Pos2Y'], $result['Pos2Z']);
                $region = $result['Region'];
                $bPos = implode(':', array($b->x, $b->y, $b->z));
                $this->db->query("UPDATE AREAS SET Sell = '$bPos' WHERE Region = '$region'");
                $price = (int) $event->getLine(1);
                
                // Проверка на окончание ввода цены
                if (strlen(trim($event->getLine(1))) > 0) {
                    // Сохраняем информацию о продаже в массив
                    $this->sells[$x.":".$y.":".$z.":".$level] = array(
                        "x" => $x,
                        "y" => $y,
                        "z" => $z,
                        "level" => $level,
                        "price" => $price,
                        "region" => $region,
                        "blocks" => $count_blocks
                    );

                    // BroadcastMessage
                    //$this->getServer()->broadcastMessage(
                      //  "§l§6➛ §rИгрок §a{$name}§r, продаёт регион §a{$region}§r, цена региона§7: §a". number_format($price) ." монет§r, посмотреть регион §a/rg tp {$region}§r"
                    //);
                }

                $event->setLine(0, "§l§fРГ §a{$region} §fПРОДАЁТСЯ");
                $event->setLine(1, "§l§fЦЕНА: §a". number_format($price) ." §fМонет");
                $event->setLine(2, "§l§fРАЗМЕР: §a". number_format($count_blocks) ." б.");
                $event->setLine(3, "§l§fПРОДАВЕЦ: §a{$name}");
                $player->sendMessage("§l§6➛ §rВы §aуспешно начали продавать §fсвой регион§r!");
            } else {
                $player->sendMessage("§l§6➛ §rТаблица §cдолжна быть §fна территории продаваемого региона§r!");
                $event->setCancelled();
            }
            break;
    }
}


}
