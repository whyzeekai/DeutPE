<?php

namespace Duels;

use API\Loader as Restart;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\command\{Command, CommandSender};
use pocketmine\Server;
use pocketmine\Player;
use timurkaundefined\multicommands\MultiCommands;

use API\Loader;

class Main extends PluginBase implements Listener
{
	public $arenas = [];
	public $pg;
	public static $instance;


	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		$this->getScheduler()->scheduleRepeatingTask(new Task($this), 20);
		$this->getServer()->getPluginManager()->registerEvents(new Events($this), $this);

        // Create necessary directories
        @mkdir($this->getDataFolder() . "arenas", 0755, true);

        $arenas = ["duels"];
		foreach($arenas as $arena){
			$this->loadArena($arena);
		}
	}

	public function onLoad(){
		self::$instance = $this;
	}

	public static function getInstance(){
		return self::$instance;
	}

	public function loadArena($arena){
		if(isset($this->arenas[$arena])){
			unset($this->arenas[$arena]);
			
			$cfg = (new Config($this->getDataFolder() ."arenas/{$arena}.yml", Config::YAML, ["arena" => $arena, "server" => "mini-d1", "lobby" => ["x" => 0, "y" => 0, "z" => 0], "pos1" => ["x" => 0, "y" => 0, "z" => 0], "pos2" => ["x" => 0, "y" => 0, "z" => 0]]))->getAll();
			
			$this->arenas[$arena] = new Arena($this, $cfg);
			
			$this->getServer()->unloadLevel($this->getServer()->getLevelByName($arena));
			$this->getServer()->loadLevel($arena);
		}else{
			$cfg = (new Config($this->getDataFolder() ."arenas/{$arena}.yml", Config::YAML, ["arena" => $arena, "server" => "mini-d1", "lobby" => ["x" => 0, "y" => 0, "z" => 0], "pos1" => ["x" => 0, "y" => 0, "z" => 0], "pos2" => ["x" => 0, "y" => 0, "z" => 0]]))->getAll();
			
			$this->arenas[$arena] = new Arena($this, $cfg);
			
			$this->getServer()->loadLevel($arena);		}
	}

	public function onCommand(CommandSender $p, Command $cmd, $label, array $args) {
		if ($cmd->getName() == "duel") {
    	//$p->sendMessage("§l§eＤＵＥＬＳ §8:: §rЗайти на дуэль §a/duel join§r");
    }

        // Обработка команды duel join
        if (isset($args[0]) && $args[0] === "join") {
            // Проверка на режим игры
            if ($p->isCreative()) {
                $p->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                return;
            }

            // Проверка на невидимость
            if ($p->isInvisible()) {
                return $p->sendMessage("§l§eＤＵＥＬＳ §8:: §rВ режиме Невидимости на дуэли зайти нельзя!");
            }

            // Проверка на перезагрузку сервера
            if (Restart::getInstance()->time < 315) {
                return $p->sendMessage("§l§eＤＵＥＬＳ §8:: §rВ §l§eдуэли§r зайти нельзя, скоро §aперезагрузка сервера§r!");
            }

            foreach ($this->arenas as $arena) {
                if ($arena->inArena($p) != 1) {
                    if ($arena->status == Arena::STATUS_WAITING) {
                        if (count($arena->players) < 2) {
                            $arena->addPlayer($p);
                            return true;
                        } else {
                            $p->sendMessage("§l§eＤＵＥＬＳ §8:: §r§rПодождите пожалуйста §l§cдуэли§r заняты!");
                            return true;
                        }
                    } else {
                        $p->sendMessage("§l§eＤＵＥＬＳ §8:: §r§rПодождите пожалуйста §l§cдуэли§r заняты!");
                        return true;
                    }
                }
            }
            $p->sendMessage("§l§6➛ §rВы находитесь в другой дуэли или находитесь в арене.");
            return true;
        }
     }

	private function duelInfo(Player $player) {
		$config = Loader::getInstance()->reward;

		$playerName = strtolower($player->getName());
        Loader::getInstance()->getWin($playerName);

        $playerStatsMessage = "§l§6➛ §rСтатистика дуэль игрока §a" . $player->getName() . "§r:\n" .
                          "§l§6➛ Выйграл §a" . Loader::getInstance()->getWin($playerName) . " §rраз!";
        $player->sendMessage($playerStatsMessage);
    }

	public function checkPlayersInArena(){
		foreach($this->arenas as $arena){
			return count($arena->players);
		}
	}
	public function checkPlayerInArena(Player $player){
		foreach($this->arenas as $arena){
			return $arena->inArena($player);
		}
	}
	public function checkCountDownInArena(){
		foreach($this->arenas as $arena){
			return $arena->countdown;
		}
	}
	public function checkEndTimeInArena(){
		foreach($this->arenas as $arena){
			return $arena->endtime;
		}
	}
	public function checkTimeInArena(){
		foreach($this->arenas as $arena){
			return $arena->time;
		}
	}
	public function checkWinner($player){
		foreach($this->arenas as $arena){
			if (strtolower($arena->winner) == strtolower($player->getName())){
				$winner = "Вы";
			} else {
				$winner = $arena->winner;
			}
			return $winner;
		}
	}
	public function checkOpponent($player){
		foreach($this->arenas as $arena){
			return ($arena->players[0] == strtolower($player->getName())) ? $arena->players[1] : $arena->players[0];
		}
	}
	public function checkPlayerZero(){
		foreach($this->arenas as $arena){
			return $arena->players[0];
		}
	}
	public function checkPlayerOne(){
		foreach($this->arenas as $arena){
			return $arena->players[1];
		}
	}
}
