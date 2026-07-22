<?php

declare(strict_types=1);

namespace timurkaundefined\tops;

use api\Loader;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;
use pocketmine\command\{CommandSender, Command};
use pocketmine\utils\Config;
use timurkaundefined\gametimer\GameTimer;
use timurkaundefined\tops\utils\Leaderboard;
use timurkaundefined\tops\utils\particle\FloatingTextParticle;
use function array_search;
use function class_exists;
use function is_numeric;
use function max;
use function min;
use function number_format;
use function str_split;

function toValidString(int $number, array $forms){
	$cases = [2, 0, 1, 1, 1, 2];
	return $forms[($number % 100 > 4 and $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}

class Tops extends PluginBase implements Listener{

	/** @var Config */
	private $kills;
	private $getMonetaryUnit = "$";

	public static $topStatus; // Убедитесь, что это массив

	/** @var bool */
	private $hasEconomy, $hasGameTimer;

	private static $cachedTops = [];
	public $tops = [];

	/** @var Vector3 */
	private $mostRichPlayers, $bestKillers, $mostActivePlayers, $bestDuels;
	/** @var Vector3 */
	private $mostDuels, $mostKillsClans, $bestMiner, $mostDonaters;

	/** @var mixed|null */
	private $hasClan = null;
	/** @var mixed|null */
	private $duels = null;

	public function onEnable(){
		if(!is_dir($dataFolder = $this->getDataFolder())){
			mkdir($dataFolder, 0777, true);
		}
		$this->kills = new Config($dataFolder .'kills.json', Config::JSON);

		$this->hasEconomy = class_exists('\api\Loader', true);
		$this->hasGameTimer = class_exists('\timurkaundefined\gametimer\GameTimer', true);
		$this->hasClan = $this->getServer()->getPluginManager()->getPlugin("Clans");
		$this->duels = $this->getServer()->getPluginManager()->getPlugin("DeadAPI");

		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "onUpdate"]), 20 * 15);

		$this->mostRichPlayers = new Vector3(34, 38.5, -46);
		$this->bestKillers = new Vector3(38, 38.5, -46);
		$this->mostActivePlayers = new Vector3(30, 38.5, -46);
		$this->mostDuels = new Vector3(26, 38.5, -45);
		$this->mostKillsClans = new Vector3(42, 38.5, -44);
		$this->bestMiner = new Vector3(6, 36.5, 1);
		$this->mostDonaters = new Vector3(46, 40, -39);
		//$this->mostDonaters = new Vector3(31, 36.5, -19);
	}


	public function onUpdate(): void {
        if ($this->hasGameTimer) {
            Leaderboard::add('most-active-players', $this->mostActivePlayers, $this->mostActivePlayers());
         }
            Leaderboard::add('best-killers', $this->bestKillers, $this->bestKillers());
            Leaderboard::add('most-rich-players', $this->mostRichPlayers, $this->mostRichPlayers());
            Leaderboard::add('duels-players', $this->mostDuels, $this->mostDuels());
            Leaderboard::add('clans-players', $this->mostKillsClans, $this->mostKillsClans());
            Leaderboard::add('topdonater-players', $this->mostDonaters, $this->mostDonaters());
     }

	public function duelsWins(string $playerName) {
		$playerNameLower = strtolower($playerName);
		$reward = $this->duels->reward->get($playerNameLower . "_wins");
	}

	private function getCorrectEnding($number): string {
		$lastDigit = $number % 10;
		$lastTwoDigits = $number % 100;

		if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
			return "побед";
		}

		switch ($lastDigit) {
			case 1:
			return "победу";

			case 0:
			return "побед";

			case 2:
			case 3:
			case 4:
			return "победы";
			default:
			return "победу";
		}
	}

	public function mostDonaters(): string {
		$db = Loader::getInstance()->db;
		$result = $db->query("SELECT name, topdonater FROM datebase ORDER BY topdonater DESC LIMIT 9");

		$leaderboard = "§r §l§6ТОП ДОНАТЕРОВ ПРОЕКТА §r§f";
		$notEmpty = 0;

		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$playerName = $row['name'];
			$topdonater = $row['topdonater'];
			$leaderboard .= "\n§e№" . $this->toPrettyNumber(++$notEmpty) . " §r§8[". Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($playerName)) ."§r§8] §6{$playerName} §rзадонатил §6". number_format($topdonater) ." §rР.§r";
		}

		if ($notEmpty === 0) {
			$leaderboard .= "\n§7Свободное место";
		}
		return $leaderboard;
	}

	public function mostDuels(): string {
		$db = Loader::getInstance()->db;
		$result = $db->query("SELECT name, win FROM datebase ORDER BY win DESC LIMIT 5");

		$leaderboard = "§r §l§aТОП ИГРОКИ ПО ПОБЕДАМ §r§f";
		$notEmpty = 0;

		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$playerName = $row['name'];
			$win = $row['win'];
			$leaderboard .= "\n§e№" . $this->toPrettyNumber(++$notEmpty) . " §r§r§8[". Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($playerName)) ."§r§8] §a{$playerName} §rимеет §a". $win ." §r" . $this->getCorrectEnding($win) . "§r";
		}

		if ($notEmpty === 0) {
			$leaderboard .= "\n§7Свободное место";
		}
		return $leaderboard;
	}

	public function mostRichPlayers(): string {
		$db = Loader::getInstance()->db;
		$result = $db->query("SELECT name, money FROM datebase ORDER BY money DESC LIMIT 5");

		$leaderboard = "§r§f⩐ §l§eТОП БОГАЧИ СЕРВЕРА §r§f⩐";
		$notEmpty = 0;

		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$playerName = $row['name'];
			$money = $row['money'];
			$leaderboard .= "\n§e№" . $this->toPrettyNumber(++$notEmpty) . " §r§r§8[". Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($playerName)) ."§r§8] §a{$playerName} §rимеет §a" . number_format($money) . " §2$";
		}

		if ($notEmpty === 0) {
			$leaderboard .= "\n§7Свободное место";
		}
		return $leaderboard;
	}


	public function mostActivePlayers() : string{
		$cache = GameTimer::getInstance()->dataToArray();
		$leaderboard = "§r §l§bТОП ПО ВРЕМЕНИ В ИГРЕ §r§f";
		$notEmpty = false;
		for($i = 1; $i <= 5 and !empty($cache); ++$i){
			if(($playerName = array_search($timePlayed = max($cache), $cache, true)) === false){
				break;
			}
			unset($cache[$playerName]);
			$leaderboard .= "\n§e№". $this->toPrettyNumber($i). ". §r§8[". Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($playerName)) ."§r§8] §b{$playerName} §rнаиграл(а)§b ". GameTimer::convertTimeOptimized($timePlayed);
			$notEmpty = true;
		}
		if(!$notEmpty){
			$leaderboard .= "\n§7Свободное место";
		}
		return $leaderboard;
	}

	public function bestKillers(): string {
		$db = Loader::getInstance()->db;
		$result = $db->query("SELECT name, kills FROM datebase ORDER BY kills DESC LIMIT 5");

		$leaderboard = "§r§f §l§cТОП УБИЙЦЫ СЕРВЕРА §r§f";
		$notEmpty = 0;

		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			$playerName = $row['name'];
			$kills = $row['kills'];
			$leaderboard .= "\n§e№". $this->toPrettyNumber(++$notEmpty). " §r§8[". Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($playerName)) ."§r§8] §c{$playerName} §rубил(а) §c". number_format($kills) ."§r ". toValidString($kills, ["игрока", "игроков", "игроков"]);
		}

		if ($notEmpty === 0) {
			$leaderboard .= "\n§7Свободное место";
		}
		return $leaderboard;
	}

	

	public function mostKillsClans() : string{
    $clans = Loader::$clans->getAll();
    $kills = [];
    $data = [];

    foreach ($clans as $clan) {
        // Сохраняем количество убийств и уровень клана
        $kills[$clan['name']] = [
            'kills' => $clan['kills'],
            'level' => $clan['level']  // Добавляем уровень
        ];
    }

    arsort($kills);
    $leaderboard = "§r§f §l§cТОП КЛАНЫ ПО УБИЙСТВУ/УРОВНЮ §r§f";
    $count = 0;

    foreach ($kills as $name => $killData) {
        $count++;
        // Извлекаем количество убийств и уровень клана
        $killsCount = $killData['kills'];
        $level = $killData['level'];

        // Формируем строку для отображения
        $leaderboard .= "\n§e№". $this->toPrettyNumber($count) . " §rКлан §b" . $name . " §rимеет §c" . number_format($killsCount) . "§7/§e{$level}§r§f";
        
        // Ограничение на вывод первых 5 кланов
        if ($count == 5) {
            break;
        }
    }

    // Если кланов нет, указываем об этом
    if (count($clans) === 0) {
        $leaderboard .= "\n§7Свободное место";
    }
    return $leaderboard;
}


	private function toPrettyNumber(int $number) : string{
		$str = "";
		foreach(str_split((string)$number) as $char){
			$str .= is_numeric($char) ? ["０", "", "", "", "", "", "", "", "", "９"][(int)$char] : $char;
			//$str .= is_numeric($char) ? ["§c⓿", "§a❶", "§e❷", "§6❸", "§c❹", "§5❺", "❻", "❼", "❽", "❾"][(int)$char] : $char;
		}
		return $str;
	}
	private $wasKilledBefore = [];

	/** @noinspection PhpUnused */
	public function handlePlayerJoin(PlayerJoinEvent $event){
		Leaderboard::showAllToPlayer($event->getPlayer());
	}

	/**
     * Обработчик выхода игрока
     */
    public function handlePlayerQuit(PlayerQuitEvent $event) {
        $playerName = mb_strtolower($event->getPlayer()->getName());    
    }

	/**
     * Обработчик взаимодействия игрока с блоком
     */

	public function onDisable(){
		$this->kills->save();
	}
}