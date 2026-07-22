<?php

namespace Duels;
//x1ndosik
use pocketmine\level\sound\MinecraftSound;

use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;

use pocketmine\level\Position;
use pocketmine\level\Level;

use pocketmine\entity\Entity;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\math\Vector3;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;

use BossBar\Main as BossBar;
use API\Loader;
use \timurkaundefined\gametimer\GameTimer as GameTimer;

class Arena
{
	const STATUS_WAITING = 0x01;
	const STATUS_GAME = 0x02;
	const STATUS_END = 0x05;
	const STATUS_RELOAD = 0x10;
	
	public $pg, $status = self::STATUS_WAITING, $players = [], $countdown = 30, $time = 300, $endtime = 5;
	public $winner = '...';
	public $kitsname;
	public $arena;
	public $server;
	public $lobby;
	public $pos1;
	public $pos2;
	public $playerResources = [];

	 public function __construct(Main $pg, array $data){
		$this->pg = $pg;
		
		$this->arena = $data["arena"];
		$this->server = $data["server"];
		
		$this->lobby = $data["lobby"];
		
		$this->pos1 = $data["pos1"];
		$this->pos2 = $data["pos2"]; 
	}

	public function dateserver() {
        $date = time();

        $month_en_to_ru = [
                'Jan' => 'янв',
                'Feb' => 'фев',
                'Mar' => 'мар',
                'Apr' => 'апр',
                'May' => 'май',
                'Jun' => 'июн',
                'Jul' => 'июл',
                'Aug' => 'авг',
                'Sep' => 'сен',
                'Oct' => 'окт',
                'Nov' => 'ноя',
                'Dec' => 'дек',
        ];

        $date_en = date("d M. Y", $date);
        $date_ru = str_replace(array_keys($month_en_to_ru), array_values($month_en_to_ru), $date_en);

        return $date_ru;
	}

	public function tick(){
		switch($this->status):
		case self::STATUS_WAITING:
		if(count($this->players) == 1){
			foreach($this->players as $name => $pl){
				$p = $this->pg->getServer()->getPlayer($pl);
				$p->setFood(20);
				$p->setXpLevel(10);
			}
			$this->countdown = 10;
		}elseif(count($this->players) == 2){
			$this->countdown--;
			foreach($this->players as $name => $pl){
				$p = $this->pg->getServer()->getPlayer($pl);
				$p->setFood(20);
				$p->setXpLevel(max(0, $this->countdown));
				if($this->countdown == 10){
					$p->getLevel()->addSound(new MinecraftSound($p->getPosition(), "random.levelup"));
				}
				if($this->countdown <= 10 and $this->countdown >= 6){
					$this->addSound($p, 71);
				}
				if($this->countdown == 5){
		            $this->pg->getServer()->getPlayer($this->players[0])->teleport(new Vector3(2986, 65, 3000));
		            $this->pg->getServer()->getPlayer($this->players[1])->teleport(new Vector3(3014, 65, 3000));
					$p->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_IMMOBILE, true);
				}
				if($this->countdown <= 5 and $this->countdown >= 1){
					$this->addSound($p, 72);
					$p->sendMessage("§r§l§6➛ §rБитва начнётся через §a{$this->countdown}с.");
					$p->getInventory()->clearAll();
					$p->getArmorInventory()->clearAll();
					$p->getDataPropertyManager()->setFloat(Player::DATA_SCALE, 1.0);
				}
				if($this->countdown == 0) {
					$this->status = self::STATUS_GAME;
					foreach ($this->players as $name => $pl) {
						$p = $this->pg->getServer()->getPlayer($pl);
						$p->getInventory()->clearAll();
						$p->getArmorInventory()->clearAll();
						$p->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_IMMOBILE, false);
						$p->addTitle("§l§eＤＵＥＬＳ", "§rУдачи вам в бою!");
					}

					$kits = array("combo", "default");
					$rand = array_rand($kits); 
					$kit = $kits[$rand];

					foreach ($this->players as $name => $pl) {
						$p = $this->pg->getServer()->getPlayer($pl);
						$this->giveKit($p, $kit);
						$this->kitsname = $kit;
						$p->setXpLevel(0);
						$p->sendMessage("§r§l§6➛ §rВам выдан набор§7: §a{$kit}");
					}
				}
			}
		}
		break;
		case self::STATUS_GAME:
		if ($this->time === 150) {
            foreach ($this->players as $name => $pl) {
                $p = $this->pg->getServer()->getPlayer($pl);
                if ($p instanceof Player) {
                   $p->sendMessage("§l§aДУЭЛЬ БОНУС §8:: §rДо конца §aдуэли§r, осталось §a2м. 30c.§r, всем выдано по §a1 тотему§r!"); 
                   $p->getInventory()->addItem(Item::get(450, 0, 1)); 
               }
            }
        }

        if ($this->time === 60) {
            foreach ($this->players as $name => $pl) {
                $p = $this->pg->getServer()->getPlayer($pl);
                if ($p instanceof Player) {
                   $p->sendMessage("§l§aДУЭЛЬ БОНУС §8:: §rДо конца §aдуэли§r, осталось §a1м§r, всем выдан набор §acombo§r!"); 
                   $this->giveKit($p, "combo");
               }
            }
        }

        foreach ($this->players as $name => $pl) {
        	$p = $this->pg->getServer()->getPlayer($pl);
        	$opponent = $this->pg->getServer()->getPlayer(Main::getInstance()->checkOpponent($p));
        	$nexvill = str_repeat(" ", 78);
        	$ping = "§7{$p->getPing()}";
        	$date_ru = $this->dateserver();
        	$o = count($this->pg->getServer()->getOnlinePlayers());
        	$p->sendTip(
                "{$nexvill}§l§bＭｅｅｔ§fＷｉｘ §eＤＵＥＬＳ§r\n" .
                "{$nexvill}  §7{$date_ru} §7" . $ping . "ms§r\n\n" .
                "{$nexvill} §7  §fВы§7: §a{$p->getName()}\n" .
                "{$nexvill} §7  §fПротивник§7: §c{$opponent->getName()}\n\n" .
                "{$nexvill} §7  §fДо конца§7: §a". Loader::getInstance()->parseTime(Main::getInstance()->checkTimeInArena()) ."§r\n" .
                "{$nexvill} §7  §fПобедили§7: §a". Loader::getInstance()->getWin($p) ." §rраз!\n\n" .
                "{$nexvill} §7  §fНабор§7: §a{$this->kitsname}\n" .
                "{$nexvill} §7  §fУдачи вам в бою!\n\n" .
                "{$nexvill} §r §rОнлайн§7: §e{$o}§7/§6100§r\n\n\n\n\n\n\n\n\n\n\n"
            );
        }

		if(count($this->players) == 0) $this->status = self::STATUS_RELOAD;
		if(count($this->players) == 1){
			foreach($this->players as $name => $pl){
				$p = $this->pg->getServer()->getPlayer($pl);
				$this->status = self::STATUS_END;
				
				$this->addSound($p, 44);
				$this->winner = strtolower($p->getName());
				$p->sendMessage("§f► §cПротивник вышел из игры!");
				$p->getInventory()->clearAll(); 
				$p->addTitle("§l§aYOU WINNER!", "§aВы автоматически победили!");
				Loader::getInstance()->duels1->setTitle("§cнеизвестно §rпротив §cнеизвестно");
				Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
			}
		}
		if($this->time == 0 && count($this->players) == 2){
			foreach($this->players as $name => $pl){
				$p = $this->pg->getServer()->getPlayer($pl);
				$this->status = self::STATUS_END;
				
				$this->addSound($p, 44);

				$p->sendMessage("§f► §cВремя закончилось, победитель не был найден!");
				$p->addTitle("§l§cTHE END!§r");
				$p->getInventory()->clearAll();
				$p->getArmorInventory()->clearAll(); 
				Loader::getInstance()->duels1->setTitle("§cнеизвестно §rпротив §cнеизвестно");
				Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
			}
		}
		
		$this->time--;
		
		foreach($this->players as $name => $pl){
			$p = $this->pg->getServer()->getPlayer($pl);
			$p->setFood(20);
		}
		break;
		case self::STATUS_END:
		if(count($this->players) == 0) $this->status = self::STATUS_RELOAD;
		
		$this->endtime--;
		
		foreach($this->players as $name => $pl){
			$p = $this->pg->getServer()->getPlayer($pl);
			$p->setFood(20);
		}

		if($this->endtime == 0) $this->status = self::STATUS_RELOAD;
		
		break;
		case self::STATUS_RELOAD:
		foreach($this->players as $name => $pl){
			$p = $this->pg->getServer()->getPlayer($pl);
			$this->removePlayer($p, "game");
			$this->status = self::STATUS_WAITING;
			$this->endtime = 5;
			$this->time = 300;
		}
		break;
		
		endswitch;
	}

	public function addPlayer(Player $p){
		$playTime = GameTimer::getGameTime($p);
        $requiredPlayTime = 600;

		if ($playTime < $requiredPlayTime) {
            $p->sendMessage("§l§eＤＵＥＬＳ §8:: §rДля входа в §l§eдуэль§r, нужно наиграть §a10м§r. Вы наиграли: §a" .Loader::getInstance()->parseTime($playTime) . "§r");
            $p->addEffect(new EffectInstance(Effect::getEffect(16), 99999999, 0, false));
            return;
        }

		$this->savePlayerResources($p);
		$playerName = strtolower($p->getName());
		$p->setFood(20);
		$p->setHealth(20);
		$p->getInventory()->clearAll();
		$p->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1));
		$os = Loader::AVAILABLE_OS[$p->getDeviceOS()];
		$name = strtolower($p->getName());

		$this->addSound($p, 89);
		$p->teleport($this->pg->getServer()->getLevelByName("duels")->getSafeSpawn());
		$p->teleport(new Vector3(2999, 81, 3000));
		$p->addEffect(new EffectInstance(Effect::getEffect(16), 99999999, 0, false));
		$p->setNameTag("§7[§l§cПРОТИВНИК§7] §l§4{$p->getName()} §r{$os}");
		$p->sendMessage("§r§l§6➛ §rВы §rприсоединился к арене §aBURNGRIEF.FUN§r");
		$p->getInventory()->setItem(8, Item::get(324, 0, 1)->setCustomName("§r§l§8[§l§cВЫЙТИ§8]"));
		//$p->getInventory()->setItem(1, Item::get(54, 0, 1)->setCustomName("§r§l§eВЫБОР НАБОРА"));

		if(count($this->players) == 0){
			$this->players[0] = strtolower($p->getName());
			Server::getInstance()->BroadcastMessage("§l§eＤＵＥＬＳ §8:: §rИгрок §a{$playerName} §rприсоединился к дуэли §7(§a1§7/§a2§7)");
			$p->getInventory()->setItem(8, Item::get(324, 0, 1)->setCustomName("§r§l§8[§l§cВЫЙТИ§8]"));
			Loader::getInstance()->hotbar[$name] = 0;
			Loader::getInstance()->duels1->setTitle("§r §c{$playerName} §rпротив §cнеизвестно §f§r");
			Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);

		} elseif(count($this->players) == 1){
			if(!isset($this->players[0])){
				$this->players[0] = strtolower($p->getName());
			} elseif(!isset($this->players[1])){
				$this->players[1] = strtolower($p->getName());
			}

			$opponentName = isset($this->players[0]) && $this->players[0] !== strtolower($p->getName()) ? $this->players[0] : $this->players[1];
			Server::getInstance()->BroadcastMessage("§l§eＤＵＥＬＳ §8:: §rИгрок §a{$playerName} §rприсоединился к дуэли §7(§a2§7/§a2§7)");
			$p->getInventory()->setItem(8, Item::get(324, 0, 1)->setCustomName("§r§l§8[§l§cВЫЙТИ§8]"));
			Loader::getInstance()->hotbar[$name] = 0;
			Loader::getInstance()->duels1->setTitle("§r §c{$this->players[0]} §rпротив §c{$this->players[1]} §f§r");
			Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
		}
	}

	protected function getPlayerLadderSize(Player $player): float {
		return $player->getDataProperty(Player::DATA_SCALE);
	}

	public function removePlayer(Player $p, string $reason){
		if (($key = array_search(strtolower($p->getName()), $this->players)) !== false) {
			unset($this->players[$key]);
		}

		$p->setFood(20);
		$p->setHealth(20);
		$p->setGamemode(Player::SURVIVAL);
		$p->getInventory()->clearAll();
		$p->getArmorInventory()->clearAll();
		$p->getInventory()->setItemInHand(Item::get(Item::AIR, 0, 1));
		$p->setXpLevel(0);
		$p->setDataFlag(Entity::DATA_FLAGS, Entity::DATA_FLAG_IMMOBILE, false);
		$p->teleport(new Position(9.5166, 35, -29.7749, Server::getInstance()->getLevelByName("spawn")));
		$p->addEffect(new EffectInstance(Effect::getEffect(16), 99999999, 0, false));
		$this->giveColorPlayer($p);
		$this->returnPlayerResources($p);
		$this->winner = '...';
		BossBar::getInstance()->setTitle($p, BossBar::getInstance()->entityRuntimeId);
		unset(Loader::getInstance()->hotbar[strtolower($p->getName())]);
		unset(Loader::getInstance()->pvp[$p->getLowerCaseName()]);

		if ($reason === 'quit') {
			$remainingPlayers = count($this->players);
			Server::getInstance()->BroadcastMessage("§l§eＤＵＥＬＳ §8:: §rИгрок §a{$p->getName()} §rвышел из дуэли §7(§a{$remainingPlayers}§7/§a2§7)");

			if ($remainingPlayers == 1) {
				$remainingPlayer = reset($this->players);
                Loader::getInstance()->duels1->setTitle("§r §c{$remainingPlayer} §rпротив §cнеизвестно §f§r");
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
            }
        } elseif ($reason === 'game') {
        	$remainingPlayers = count($this->players);
        	if ($remainingPlayers == 1) {
        		$remainingPlayer = reset($this->players);
        		Loader::getInstance()->duels1->setTitle("§r §c{$remainingPlayer} §rпротив §cнеизвестно §f§r");
        		Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
        	}
        }
    }


public function giveColorPlayer(Player $player){
	$playerName = $player->getName();
    $lvl = Loader::getInstance()->getLvl($player);
    $os = Loader::AVAILABLE_OS[$player->getDeviceOS()];
    $clan = Loader::getInstance()->getPrefixClans($player);
   $groupName = Loader::getInstance()->getPrefix($player);

        $likes = Loader::getInstance()->rep->exists($playerName) 
                 ? Loader::getInstance()->rep->get($playerName)["likes"] 
                 : 0;

        $dislikes = Loader::getInstance()->rep->exists($playerName) 
                    ? Loader::getInstance()->rep->get($playerName)["dislikes"] 
                    : 0;

        $titulStatus = Loader::getInstance()->getTitul($player, "titulstatus");

        if ($titulStatus == "on") {
            $titul = Loader::getInstance()->getTitul($player, "titul");
            $player->setNameTag("§8「§e{$lvl}§l§e§r§8」 §8「{$titul}§8」 §r{$player->getName()}\n§8「§b{$clan}§r§8」 §8「§l§a{$os}§r§8」 §8「§r+§a{$likes}§8」 §8「§r-§c{$dislikes}§8」");
            $player->setDisplayName("§r{$os} §8「{$titul}§8」 §r{$player->getName()}");
        } else {
            $player->setNameTag("§8「§e{$lvl}§l§e§r§8」 §8「{$groupName}§8」 §r{$player->getName()}\n§8「§b{$clan}§r§8」 §8「§l§a{$os}§r§8」 §8「§r+§a{$likes}§8」 §8「§r-§c{$dislikes}§8」");
            $player->setDisplayName("§r{$os} §8「{$groupName}§8」 §r{$player->getName()}");
        }
}


public function savePlayerResources(Player $player): void {
    $armorInventory = $player->getArmorInventory();
    $resources = [
        'inventory' => $player->getInventory()->getContents(),
        'helmet' => $armorInventory->getHelmet(),
        'chestplate' => $armorInventory->getChestplate(),
        'leggings' => $armorInventory->getLeggings(),
        'boots' => $armorInventory->getBoots(),
        'offhand' => $player->getOffHandInventory()->getItemInOffHand()
    ];

    $this->playerResources[strtolower($player->getName())] = $resources;
}
public function returnPlayerResources(Player $player): void {
    // Проверка наличия сохраненных ресурсов игрока
    if (isset($this->playerResources[strtolower($player->getName())])) {
        $resources = $this->playerResources[strtolower($player->getName())];
        $armorInventory = $player->getArmorInventory();

        // Восстановление инвентаря игрока
        $player->getInventory()->setContents($resources['inventory']);
        $armorInventory->setHelmet($resources['helmet']);
        $armorInventory->setChestplate($resources['chestplate']);
        $armorInventory->setLeggings($resources['leggings']);
        $armorInventory->setBoots($resources['boots']);
        $player->getOffHandInventory()->setItemInOffHand($resources['offhand']);

        // Удаление сохраненных ресурсов игрока
        unset($this->playerResources[strtolower($player->getName())]);
    }
}
	public function win(Player $win, Player $los){
		$this->winner = strtolower($win->getName());
		$win->getInventory()->clearAll();
		$win->getArmorInventory()->clearAll();
		$los->getInventory()->clearAll();
		$los->getArmorInventory()->clearAll();

		$los->removeAllEffects();
		$win->removeAllEffects();

		$los->setGamemode(Player::SPECTATOR);
		$wins = "1";

		Server::getInstance()->BroadcastMessage("§l§eＤＵＥＬＳ §8:: §rИгрок §a{$win->getName()} §rпобедил игрока §a{$los->getName()}");

		$los->addTitle("§l§cYOU LOSE!§r", "§a{$win->getName()} §eпобедил дуэль!");
		$los->teleport($win->getPosition());

		$win->addTitle("§l§aYOU WINNER!", "§eВы победили игрока §a{$los->getName()}§e!");
		$win->setMotion(new Vector3(0, 2, 0));

		$rand1 = mt_rand(15000, 35000);
		$rand2 = mt_rand(5000, 15000);
		$exp = mt_rand(10, 50);
		$playerName = $win->getName(); // или другой уникальный идентификатор
		$money = Loader::getInstance()->sub->get($playerName) ? $rand1 : $rand2;
		$moneyFormat = Loader::getInstance()->sub->get($playerName) ? "§7(§rТ.к у вас есть §aподписка§r, вы получаете §aх2§r приз§7)" : "";

		//$win->sendMessage("§l§eＤＵＥＬＳ §8:: §rЗа победу вы получаете §a{$money}§2$");
		$win->sendMessage("§a---------------------------------");
		$win->sendMessage("            §l§aПОБЕДА");
		$win->sendMessage("       §rПобедитель: §a{$win->getName()}");
		$win->sendMessage("  §rПолучаете: §a+{$money} §2м§r, и, §5{$exp} §dexp");
		$win->sendMessage("{$moneyFormat}");
		$win->sendMessage("§a---------------------------------");
		Loader::getInstance()->addMoney($win, $money);
		Loader::getInstance()->addExp($win, $exp);

		Loader::getInstance()->duels1->setTitle("§r §cнеизвестно §rпротив §cнеизвестно §f§r");
		Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);

		$this->addSound($los, 47);
		$this->addSound($win, 45);

		$los->setHealth(20);
		$win->setHealth(20);

		$this->status = self::STATUS_END;

		Loader::getInstance()->addWin($win, 1);
	}


	public function inArena(Player $p){
		if(array_search(strtolower($p->getName()), $this->players) !== false){
			return 1;
		}else{
			return 0;
		}
	}

	public function giveKit(Player $player, string $kit) {
		switch ($kit) {
			case 'combo':
			    $player->getInventory()->clearAll();
			    $player->getArmorInventory()->clearAll();
				$player->getInventory()->addItem(Item::get(364, 0, 64)); 
			break;

			case 'combo_zevs':
			    $player->getInventory()->clearAll();
			    $player->getArmorInventory()->clearAll();
			    $player->getInventory()->addItem(Item::get(438, 16, 1)); 
                $player->getInventory()->addItem(Item::get(438, 33, 1)); 
				$player->getInventory()->addItem(Item::get(364, 0, 64)); 
				$player->getInventory()->addItem(Item::get(397, 0, 1)->setCustomName("§r§l§bШАР ЗЕВСА"));
			break;

			case 'default':
				$player->getInventory()->clearAll();
				$player->getArmorInventory()->clearAll();
				$op = Item::get(276, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(9), 3)); 
                $player->getInventory()->addItem($op); 
                $op = Item::get(261, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(19), 2)); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(21), 1)); 
                $player->getInventory()->addItem($op); 
                $op = Item::get(310, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5)); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(1), 2)); 
                $player->getArmorInventory()->setHelmet($op); 
                $op = Item::get(311, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 6)); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(1), 2)); 
                $player->getArmorInventory()->setChestplate($op); 
                $op = Item::get(312, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5)); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(1), 2)); 
                $player->getArmorInventory()->setLeggings($op); 
                $op = Item::get(313, 0, 1); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(0), 5)); 
                $op->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(1), 2)); 
                $player->getArmorInventory()->setBoots($op); 
                $player->getInventory()->addItem(Item::get(466, 0, 1)); 
                $player->getInventory()->addItem(Item::get(320, 0, 32));
                $player->getInventory()->addItem(Item::get(368, 0, 3));
                $player->getInventory()->addItem(Item::get(450, 0, 2));   
                $player->getInventory()->addItem(Item::get(438, 16, 1)); 
                $player->getInventory()->addItem(Item::get(438, 33, 1)); 
                $player->getInventory()->addItem(Item::get(262, 0, 64)); 
                $player->getInventory()->sendContents($player); 
                $player->getArmorInventory()->sendContents($player); 
                $player->getOffHandInventory()->sendContents($player);
			break;
		}
	}

	public function addSound(Player $p, int $id){
		$pk = LevelSoundEventPacket::nonActorSound($id, $p->asVector3(), false);
		$p->dataPacket($pk);
	}
}
