<?php

declare(strict_types=1);

namespace timurkaundefined\casino;

use API\Loader;

use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\level\sound\ExpPickupSound;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\MainLogger;
use pocketmine\utils\Random;
use timurkaundefined\casino\animation\SpinningAnimation;
use timurkaundefined\casino\entity\Croupier;
use timurkaundefined\casino\entity\Lightning;
use timurkaundefined\casino\inventory\PersonalDoubleInventory;
use timurkaundefined\casino\particle\FloatingTextAPI;
use timurkaundefined\casino\tile\VirtualCasinoChest;
use timurkaundefined\casino\utils\Helper;
use wmpe\wAuth;
use function array_keys;
use function array_map;
use function array_rand;
use function array_shift;
use function array_sum;
use function class_exists;
use function is_numeric;
use function max;
use function number_format;
use function strtolower;

class Casino extends PluginBase{

	public const NPC_NAMETAG = "          §l§dКАЗИНО§r\n§rНажмите на NPC, для участия!";

	public const CHEST_WINDOW_TITLE = '                 §l§dРУЛЕТКА   '; //название окна меню ставок

	public const MIN_PLAYERS = 2; //checkDisabl

	public const SECONDS_WAITING = 10; //время ожидания до начала игры в секундах

	/** настройка ставок */
	public const AVAILABLE_BETS = [
		5000 => [
			'chance' => 1,
			'itemId' => ItemIds::GOLD_NUGGET
		],

		25000 => [
			'chance' => 5,
			'itemId' => ItemIds::COAL
		],

		50000 => [
			'chance' => 10,
			'itemId' => ItemIds::IRON_INGOT
		],

		100000 => [
			'chance' => 15,
			'itemId' => ItemIds::GOLD_INGOT
		],

		250000 => [
			'chance' => 25,
			'itemId' => ItemIds::DIAMOND
		],

		500000 => [
			'chance' => 50,
			'itemId' => ItemIds::EMERALD
		],

		1000000 => [
			'chance' => 60,
			'itemId' => ItemIds::NETHER_BRICK
		],
	];

	public const STATUS_WAITING = 0;
	public const STATUS_RUNNING = 1;

	/** @var Casino */
	private static $instance;

	/** @var Location|null */
	private $warpLocation;

	/** @var ClosureTask|null */
	private $task;

	/** @var Random */
	private $random;

	/** @var string */
	private $prettyJackpot;

	/** @var SpinningAnimation */
	private $animation;

	/** @var int */
	private $time, $status, $jackpot, $abortSeconds;

	/** @var array */
	private $players = [], $knownCroupier = [];

	/** @var Config */
	private $moneyCheaters;

	/** @var bool */
	private $checkAuthed;

	/** @var array */
	private $hiddenPlayers = [], $newShownPlayers = [];

	/** @var AxisAlignedBB|null */
	private $boundingBox = null;

	public function onEnable(){
		Server::getInstance()->getPluginManager()->registerEvents(new EventHandler(), $this);

		Tile::registerTile(VirtualCasinoChest::class);
		Entity::registerEntity(Croupier::class, true);
		Entity::registerEntity(Lightning::class, true);

		$this->doReload();

		if(!is_dir($dataFolder = $this->getDataFolder())){
			mkdir($dataFolder, 0777, true);
		}
		$this->moneyCheaters = new Config($dataFolder . 'money-cheaters.json', Config::JSON);

		$this->checkAuthed = class_exists('\wmpe\wAuth', true);
	}

	public function onUpdate() : void{
		if(($playersCount = count($this->players)) > 0){
			if($this->isWaiting()){
				if($playersCount < Casino::MIN_PLAYERS){
					if(++$this->abortSeconds >= 120){
						foreach($this->players as $data){
							$recipient = $data['player'];
							if(!$recipient instanceof Player){
								continue;
							}
							$recipient->sendMessage('§c§lКАЗИНО §8:: §rРозыгрыш отменён т.к. не набралось нужное число участников!');
							Helper::sendSound($recipient, 'note.bass', true);
						}
						$this->doReload();
					}
				}elseif(--$this->time > 0){
					if($this->time % 3 === 0){
						$this->tryDisqualifePlayers();
					}
					FloatingTextAPI::updateOne('timeToStart');
				}else{
					$this->startGame();
				}
			}
			$this->checkBBCollision();
		}else{
			$this->doReload();
		}
	}

	public function addPlayer(Player $player, int $bet) : void{
		$this->players[$player->getLowerCaseName()] = [
			'player' => $player,
			'bet' => $bet
		];
		$player->sendMessage('§a§lКАЗИНО §8:: §rТы поставил(-а) §6' . Helper::toPrettyString(number_format($bet)) . "$");
		$player->dataPacket((new ExpPickupSound($player))->encode());

		$this->tryInitTask();
		$this->onPlayersChanged(true);
	}

	public function removePlayer($player) : void{
		$playerName = $player instanceof Player ? $player->getLowerCaseName() : $player;
		if(!isset($this->players[$playerName])){
			return;
		}
		if($this->isWaiting()){
			unset($this->players[$playerName]);
		}else{
			$this->players[$playerName]['player'] = null;
		}

		$this->onPlayersChanged();
	}

	public function onPlayersChanged(bool $startMessage = false) : void{
		$this->jackpot = array_sum(array_map(function(array $data) : int{
			return $data['bet'];
		}, $this->players));
		$this->prettyJackpot = Helper::toPrettyString(number_format($this->jackpot)) . '§6' . "$";

		if(($playersCount = count($this->players)) < Casino::MIN_PLAYERS and $playersCount > 0){
			$this->time = Casino::SECONDS_WAITING + 1;
		}elseif($startMessage){
			$playersToQuickStart = 7;

			if($playersCount === Casino::MIN_PLAYERS){
				foreach($this->players as $data){
					$recipient = $data['player'];
					if(!$recipient instanceof Player){
						continue;
					}
					$recipient->sendTitle('§bРОЗЫГРЫШ', '§aСкоро начнётся!', 20, 20, 50);
					$prettySeconds = Helper::prettySeconds(Casino::SECONDS_WAITING);
					$recipient->sendMessage('§a§lКАЗИНО §8:: §rСобрано нужное количество игроков! Рулетка начнётся через §b' . $prettySeconds . '§e!');
					Helper::sendSound($recipient, 'note.harp', true, 1.2);
					Server::getInstance()->broadcastMessage("\n\n§c§lВнимание!§r §fВ казино действует §dКОММИСИЯ §fсоставляет налог§7: §d10%\n\n");
				}
			}elseif($playersCount === $playersToQuickStart and $this->time > ($quickSeconds = 10)){
				$this->time = $quickSeconds;
				foreach($this->players as $data){
					$recipient = $data['player'];
					if(!$recipient instanceof Player){
						continue;
					}
					$recipient->sendTitle('§dРОЗЫГРЫШ', '§fВремя ускорено!', 20, 20, 50);
					$prettySeconds = Helper::prettySeconds($this->time);
					$recipient->sendMessage('§c§lКАЗИНО §8:: §rВ розыгрыше участвует много игроков! Игра начнётся через §b' . $prettySeconds . '§e!');
					Helper::sendSound($recipient, 'note.harp', true, 1.2);
					Server::getInstance()->broadcastMessage("\n\n§c§lВнимание!§r §fВ казино действует §dКОММИСИЯ §fсоставляет налог§7: §d10%\n\n");
				}
			}

			if($playersCount === (Casino::MIN_PLAYERS - 3)){
				foreach(Server::getInstance()->getOnlinePlayers() as $player){
					if($this->getPlayerBet($player) !== null){
						continue;
					}
					$player->sendMessage("\n\n§c§lВнимание!§r §fПрими участие §dв розыгрыше §fна спавне§7: §d/ruletka\n\n");
				}
			}
		}

		foreach($this->inventoryViewers as [, , , $inventory, $player]){
			if($inventory !== null){
				$this->fillInventory($inventory, $player, false);
			}
		}

		FloatingTextAPI::callbackToAll('updateToAll');

		$this->abortSeconds = 0;
	}

	public function startGame() : void{
		$this->tryDisqualifePlayers();

		$this->animation->randomizeSnake($this->selectWinningPlayer(), $this->players);
		$this->animation->start();

		$this->status = Casino::STATUS_RUNNING;

		$this->findCroupier()->startAnimation();

		FloatingTextAPI::despawnFromAll('timeToStart');
	}

	public function isInCasinoGame($player) : bool{
		$playerName = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
		return $this->isRunning() and isset($this->players[$playerName]['bet']);
	}

	public function getPlayerBet($player) : ?int{
		$playerName = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
		return $this->players[$playerName]['bet'] ?? null;
	}

	public function getPlayerInstance($player) : ?Player{
		$playerName = $player instanceof Player ? $player->getLowerCaseName() : strtolower($player);
		return $this->players[$playerName]['player'] ?? null;
	}

	public function getWinChance(Player $player){
		$bet = $this->getPlayerBet($player);
		if($bet === null){
			return '§cТы не участвуешь';
		}
		/*$allPercents = 0;
		foreach($this->players as $data){
			$allPercents += Casino::AVAILABLE_BETS[$data['bet']]['chance'];
		}
		$chance = round(Casino::AVAILABLE_BETS[$bet]['chance'] / $allPercents * 100);
		$prettyChance = Helper::toPrettyString((string)$chance);*/
		$prettyChance = Helper::toPrettyString((string)self::findBetChance($bet));
		return '§8~§a' . $prettyChance . '§f%';
	}

	public function findCroupier() : ?Croupier{
		$level = Server::getInstance()->getLevel($this->knownCroupier[0] ?? -1);
		if($level === null){
			return null;
		}
		$entity = $level->getEntity($this->knownCroupier[1]);
		return $entity instanceof Croupier ? $entity : null;
	}

	public function addReward() : void{
    $winner = null;
    foreach($this->players as $playerName => $data){
        if(!isset($data['winner'])){
            continue;
        }
        $winner = $playerName;
        break;
    }
    if($winner !== null){
        $player = $this->players[$winner]['player'];

        // Вычисляем сумму выплаты с учетом комиссии 10%
        $netReward = $this->jackpot * 0.9;

        // Выплатить сумму после учета комиссии
        Loader::getInstance()->addMoneyy($winner, $netReward);

        $playerName = $player instanceof Player ? $player->getDisplayName() : $winner;
        $prettyChance = Helper::toPrettyString((string)self::findBetChance($this->players[$winner]['bet']));
        $prettyJackpot = Helper::toPrettyString(number_format($netReward));
        $monetaryUnit = "$";

        $message = "\n\n§l§6| §r" . $playerName . " §fвыиграл(-а) джекпот§a " . $prettyJackpot . "§2" . $monetaryUnit . "\n§l§6| §rС шансом: §a" . $prettyChance . "% §7/§r Коммисия §fсоставляет: §d10%\n\n";

        foreach(Server::getInstance()->getOnlinePlayers() as $recipient){
            $recipient->sendMessage($message);
        }
        MainLogger::getLogger()->info($message);

        /** @noinspection PhpDeprecationInspection */
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player, $prettyJackpot, $monetaryUnit) : void{
            foreach(Server::getInstance()->getOnlinePlayers() as $recipient){
                Helper::sendSound($recipient, 'random.levelup', true, 1.75);
            }
            if($player instanceof Player and $player->isOnline()){
                $player->sendTitle(' §a§lПОБЕДА!', '§f+ §a' . $prettyJackpot . '§f' . $monetaryUnit, 20, 20, 60);
            }
        }), 15);

        // Обработка проигравших
        foreach($this->players as $playerName => $data){
            if($playerName === $winner){
                continue;
            }
            $playerMoney = Loader::getInstance()->getMoneyy($playerName);
            $bet = $data['bet'];
            $recipient = $data['player'];

            if($recipient instanceof Player){
                $recipient->sendMessage('§a§lКАЗИНО §8:: §rК сожалению, вы проиграли §6' . number_format($bet) . $monetaryUnit);
                Helper::sendSound($recipient, 'mob.elderguardian.death', true);
            }

            if($playerMoney >= $bet){
                Loader::getInstance()->remMoneyy($playerName, $bet);
            } else {
                Loader::getInstance()->remMoneyy($playerName, $playerMoney);
                $debt = $bet - $playerMoney;
                $needMoney = (int)($debt * 1.5);
                Helper::sendSound($recipient, 'mob.wither.death', true);
                $recipient->sendMessage('§c§lКАЗИНО §8:: §rЗа попытку обмануть систему наложен штраф! Вы должны серверу §d' . $needMoney . $monetaryUnit);

                $this->moneyCheaters->set($recipientName = $recipient->getLowerCaseName(),
                    (int)($this->moneyCheaters->get($recipientName, 0) + $needMoney)
                );
            }
        }
    }
    Helper::createFirework($this->findCroupier()->asVector3());
}


	public function selectWinningPlayer() : ?string{
		$this->random->nextSignedInt();
		$chances = array_map(function(array $data){
			$bet = $data['bet'];
			return self::findBetChance($bet);
		}, $this->players);
		$max_chance = array_sum($chances);
		$sum = 0;
		$chance = $this->random->nextRange(1, $max_chance);
		foreach($chances as $playerName => $_chance){
			$sum += $_chance;
			if($sum < $chance){
				continue;
			}
			$this->players[$playerName]['winner'] = 1;
			return (string)$playerName;
		}
		return array_rand($chances);
	}

	public function getPlayersCount() : int{
		return count($this->players);
	}

	public function getJackpot() : int{
		return $this->jackpot;
	}

	public function getPrettyJackpot() : string{
		return $this->prettyJackpot;
	}

	public function isFull() : bool{
		if($this->animation === null){
			return false; // Если анимация не инициализирована, игра не полна
		}
		return count($this->players) >= $this->animation->getSnakeLength();
	}

	public function getTimeToStart() : int{
		return $this->time;
	}

	public function isWaiting() : bool{
		return $this->status === Casino::STATUS_WAITING;
	}

	public function isRunning() : bool{
		return $this->status === Casino::STATUS_RUNNING;
	}

	public function hasPlayers() : bool{
		return $this->isRunning() or !empty($this->players);
	}

	public function tryDisqualifePlayers() : void{
		foreach($this->players as $playerName => $data){
			if(Loader::getInstance()->getMoneyy($playerName) >= $data['bet']){
				continue;
			}
			$player = $data['player'];
			if($player instanceof Player){
				$player->sendMessage('§c§lКАЗИНО §8:: §rВы исключены из рулетки т.к. у вас §6недостаточно денег§e!');
			}
			$this->removePlayer($playerName);
		}
	}

	public function tryInitTask() : void{
		if($this->task !== null and Server::getInstance()->getScheduler()->isQueued($this->task->getTaskId())){
			return;
		}
		/** @noinspection PhpDeprecationInspection */
		$this->task = Server::getInstance()->getScheduler()->scheduleRepeatingTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) : void{
			$this->onUpdate();
		}), 20);
		FloatingTextAPI::callbackToAll('spawnToAll');
	}

	public function doReload() : void{
		$this->random = new Random();

		if(($croupier = $this->findCroupier()) !== null){
			$croupier->finishAnimation();
		}

		if($this->task !== null and Server::getInstance()->getScheduler()->isQueued($this->task->getTaskId())){
			Server::getInstance()->getScheduler()->cancelTask($this->task->getTaskId());
		}
		$this->task = null;

		$this->jackpot = $this->abortSeconds = 0;
		$this->prettyJackpot = '';

		$this->players = [];
		$this->time = Casino::SECONDS_WAITING + 1;
		$this->status = Casino::STATUS_WAITING;

		FloatingTextAPI::callbackToAll('despawnFromAll');

		/** @var Player $player */
		foreach($this->hiddenPlayers as $player){
			$player->sendPopup('§7Вы вновь видны другим игрокам!');
			foreach(Server::getInstance()->getOnlinePlayers() as $p){
				$p->showPlayer($player);
			}
		}
		$this->hiddenPlayers = [];
	}

	/** @var array */
	private $inventoryViewers = [];

	public function openCasino(Player $player, ?int $bet = null) : void {

		$dateTimeZone = new \DateTimeZone('Europe/Moscow');
		$now = new \DateTime('now', $dateTimeZone);
		$currentHour = (int) $now->format('H');

		if ($currentHour < 15 || $currentHour >= 20) {
			$player->sendMessage('§l§c§lКАЗИНО §8:: §rДоступно только с §a15:00§r до §a20:00§r по МСК!');
			return;
		}

		if ($this->isRunning()) {
        $player->sendPopup('§l§c§lКАЗИНО §8:: §rСейчас идёт игра! Подожди её окончания!');
        return;
    }

    // Проверка, слишком ли много ставок
    if ($this->getPlayerBet($player) === null && $this->isFull()) {
        $player->sendMessage('§c§lКАЗИНО §8:: §rСейчас слишком много ставок! Подожди окончания рулетки!');
        return;
    }

    $vector3 = (function() use ($player) : Vector3 {
        $vector3 = $player->floor();
        for ($x = -1; $x <= 1; ++$x) {
            foreach([-2, -1, 1, 2] as $y) {
                for ($z = -1; $z <= 1; ++$z) {
                    $temporalVector3 = $vector3->add($x, $y, $z);
                    if ($player->level->getTile($temporalVector3) === null) {
                        return $temporalVector3;
                    }
                }
            }
        }
        return $vector3->add(0, 4); // Убедимся, что мы не выходим за пределы
    })();

    // Получение блоков, которые будут заменены
    $blockReplaced = ($level = $player->getLevel())->getBlock($vector3);
    $pairVector3 = $vector3->getSide(Vector3::SIDE_WEST);
    $blockReplaced2 = $level->getBlock($pairVector3);

    // Обновление блоков на клиенте
    $this->updateBlockImmediately($player, Block::get(BlockIds::CHEST, 2, Position::fromObject($vector3)));
    $this->updateBlockImmediately($player, Block::get(BlockIds::CHEST, 2, Position::fromObject($pairVector3)));

    $title = Casino::CHEST_WINDOW_TITLE;

    // Создание вирутального сундука
    /** @var VirtualCasinoChest $chest */
    $chest = Tile::createTile(
        'VirtualCasinoChest',
        $level,
        Helper::createTileNBT('Chest', $title, $vector3, $pairVector3),
        $player->getName()
    );

    /** @var VirtualCasinoChest $chest2 */
    $chest2 = Tile::createTile(
        'VirtualCasinoChest',
        $level,
        Helper::createTileNBT('Chest', $title, $pairVector3, $vector3),
        $player->getName()
    );

    $inventory = new PersonalDoubleInventory($chest, $chest2, $player->getName());

    $chest->setDoubleInventory($inventory);
    $chest->setShouldBeSpawned();
    $chest2->setShouldBeSpawned();

    // Спавн сундуков для игрока
    $chest->spawnTo($player);
    $chest2->spawnTo($player);

    // Хранение информации о зрителе инвентаря
    $this->inventoryViewers[$player->getName()] = [[$chest, $chest2], [$blockReplaced, $blockReplaced2], $vector3, $inventory, $player];

    // Заполнение инвентаря
    $this->fillInventory($inventory, $player);

    /** @noinspection PhpDeprecationInspection */
    Server::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player, $inventory) : void {
        if (!$player instanceof Player || !$player->isOnline()) {
            return;
        }
        $this->openWindow($inventory, $player); // Открытие окна инвентаря
    }), 6);
}


	public function fillInventory(PersonalDoubleInventory $inventory, Player $player, bool $isFull = true) : void{
		if($isFull){
			$this->intuitiveFillSides($inventory);
		}else{
			if($inventory->getLeftSide() !== null and $inventory->getRightSide() !== null){
				for($i = 10; $i < 17; ++$i){
					$inventory->setItem($i, new Item(0));
				}

				$exceptSlots = [35 => 1, 36 => 1];
				for($i = 28; $i < 44; ++$i){
					if(isset($exceptSlots[$i])){
						continue;
					}
					$inventory->setItem($i, new Item(0));
				}
			}
		}

		$item = null;
		if($this->getPlayerBet($player) !== null){
			$item = (new Item(ItemIds::BED, 14))->setCustomName(
				"§r§c§lОтказаться§r\n\n" .

				"§fНажми сюда §6два раза §fчтобы\n" .
				"§fпокинуть розыгрыш!"
			);
			$nbt = $item->getNamedTag();
			$nbt->quit = new ByteTag('quit', 1);
			$item->setNamedTag($nbt);
		}elseif(!$isFull){
			$item = (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7▲ Участники рулетки ▲");
		}
		if($item !== null){
			$this->fillWindowSlot($inventory, 49, $item);
		}

		$betSlot = 10;
		$playerMoney = Loader::getInstance()->getMoney($player);
		$monetaryUnit = "$";
		$bet = $this->getPlayerBet($player);

		//$maxBet = number_format(max(array_keys(Casino::AVAILABLE_BETS)));

		$pluralPlayers = Helper::toPlural(Casino::MIN_PLAYERS, ['игрока', 'игроков', 'игроков']);

		foreach(Casino::AVAILABLE_BETS as $amount => $data){
			$prettyAmount = Helper::toPrettyString(number_format($amount));
			$hasMoney = $playerMoney >= $amount;

			if($this->getPlayerBet($player) === $amount){
				$action = '§cЭто твоя ставка!';
			}elseif($hasMoney){
				$action = '§aНажми два раза для выбора!';
			}else{
				$action = '§cУ тебя недостаточно денег!';
			}

			$item = (new Item($data['itemId']))->setCustomName(
				'§r§fСтавка ' . ($hasMoney ? '§e' : '§c') . $prettyAmount . $monetaryUnit . "\n\n" .

				'§f• §7Шанс на победу: §d' . self::findBetChance($bet) . '§5%' .
				($bet !== null ? "\n" . '§f• §7Твоя ставка: §d' . number_format($bet) . '§5' . $monetaryUnit : null) . "\n\n" .

				'§7Игра начинается от §d' . Casino::MIN_PLAYERS . ' §7' . $pluralPlayers . "\n\n" .

				$action
			);

			$nbt = $item->getNamedTag();
			$nbt->bet = new IntTag('bet', $amount);

			$this->fillWindowSlot($inventory, $betSlot++, $item->setNamedTag($nbt));
		}

		$playerSlots = [28, 29, 30, 31, 32, 33, 34, 37, 38, 39, 40, 41, 42, 43];
		$slotIndex = 0;
		foreach($this->players as $playerName => $data){
			/** @var Player $player */
			$player = $data['player'];
			$playerName = $player instanceof Player ? $player->getDisplayName() : $playerName;
			$bet = $data['bet'];
			$this->fillWindowSlot($inventory, $playerSlots[$slotIndex++], (new Item(ItemIds::SKULL, 3))->setCustomName(
				'§r' . $playerName . "\n\n" .
				'§7Ставка: §d' . number_format($bet) . '§5' . $monetaryUnit . "\n\n" .
				'§7Шанс на победу: §8~§d' . self::findBetChance($bet) . '§5%'
			));
		}
	}

	public function intuitiveFillSides(PersonalDoubleInventory $inventory){
		for($i = 0; $i < 9; ++$i){
			$this->fillWindowSlot($inventory, $i, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7▼ Сделать ставку ▼"));
		}
		$this->fillWindowSlot($inventory, 9, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7Сделать ставку ▶"));
		$this->fillWindowSlot($inventory, 17, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7◀ Сделать ставку"));
		for($i = 18; $i < 27; ++$i){
			$this->fillWindowSlot($inventory, $i, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName(
				"§r§7Сделать ставку ▲\n" .
				"§7Участники рулетки ▼"
			));
		}
		$this->fillWindowSlot($inventory, 27, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7Участники рулетки ▶"));
		$this->fillWindowSlot($inventory, 36, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7Участники рулетки ▶"));
		$this->fillWindowSlot($inventory, 35, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7◀ Участники рулетки"));
		$this->fillWindowSlot($inventory, 44, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7◀ Участники рулетки"));
		for($i = 45; $i < 54; ++$i){
			$this->fillWindowSlot($inventory, $i, (new Item(BlockIds::STAINED_GLASS_PANE, 10))->setCustomName("§r§7▲ Участники рулетки ▲"));
		}
	}

	public function openWindow(PersonalDoubleInventory $inventory, Player $player) : void{
		if(!$this->isViewingCasino($player)){
			$this->removeBlockAndTile($player);
			if($player->getLowerCaseName() === 'gg_script_kill'){
				$player->sendMessage('error! removed block and tile');
			}
			return;
		}
		$player->addWindow($inventory);
	}

	public function isViewingCasino(Player $player) : bool{
		if(!isset($this->inventoryViewers[$playerName = $player->getName()])){
			return false;
		}
		/** @var Vector3 $holderV3 */
		$holderV3 = $this->inventoryViewers[$playerName][2];
		return $holderV3->distanceSquared($player) < 26;
	}

	public function fillWindowSlot(PersonalDoubleInventory $inventory, int $slot, Item $item) : void{
		$nbt = $item->getNamedTag() ?? new CompoundTag();
		$nbt->setByte("dirtyItem", 1);
		$nbt->setByte("casinoItem", 1);
		$inventory->setItem($slot, $item->setNamedTag($nbt));
	}

	private function updateBlockImmediately(Player $recipient, Block $block) : void{
		$pk = new UpdateBlockPacket();
		$pk->blockId = BlockIds::CHEST;
		$pk->blockData = 0;
		$pk->x = $block->x;
		$pk->z = $block->z;
		$pk->y = $block->y;
		$pk->flags = UpdateBlockPacket::FLAG_ALL;
		$recipient->dataPacket($pk);
	}

	public function onInventoryInvalidation(Player $player, ?ChestInventory $left, ?ChestInventory $right) : void{
		$player->sendMessage('forced invalidation');
		$sendPacket = function(Vector3 $vector3) use ($player) : void{
			$pk = new UpdateBlockPacket();
			$pk->blockId = $pk->blockData = 0;
			$pk->x = $vector3->x;
			$pk->z = $vector3->z;
			$pk->y = $vector3->y;
			$pk->flags = UpdateBlockPacket::FLAG_ALL;
			$player->dataPacket($pk);
		};
		if($left instanceof ChestInventory){
			$leftHolder = $left->getHolder();
			if($leftHolder !== null){
				$sendPacket($leftHolder->asVector3());
			}
		}
		if($right instanceof ChestInventory){
			$rightHolder = $right->getHolder();
			if($rightHolder !== null){
				$sendPacket($rightHolder->asVector3());
			}
		}
	}

	public function addToDelayedClose(Player $player) : void{
		if(!$this->isViewingCasino($player)){
			return;
		}
		/** @noinspection PhpDeprecationInspection */
		Server::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player) : void{
			$this->removeBlockAndTile($player);
		}), 2);
	}

	/** @noinspection PhpUnused */
	public function removeBlockAndTile(?Player $player = null){
		if(!$player instanceof Player){
			return;
		}

		$playerName = $player->getName();

		if(!isset($this->inventoryViewers[$playerName])){
			return;
		}

		$tiles = $this->inventoryViewers[$playerName][0];
		$blocksReplaced = $this->inventoryViewers[$playerName][1];
		if($player->isValid() and $player->isOnline()){
			$player->getLevel()->sendBlocks([$player], $blocksReplaced, UpdateBlockPacket::FLAG_ALL_PRIORITY);
		}
		foreach($tiles as $tile){
			if($tile instanceof VirtualCasinoChest and !$tile->closed){
				$tile->close();
			}
		}
		unset($this->inventoryViewers[$playerName]);
	}

	public function storeCroupierThings(Croupier $croupier) : void{
		$from = $croupier->asVector3()->add(0, $croupier->getEyeHeight());
		$directionVector = $croupier->getDirectionVector();
		[$directionX, $directionY, $directionZ] = [$directionVector->getX(), $directionVector->getY(), $directionVector->getZ()];
		$i = 4.75;
		$vector3 = $from->add($directionX * $i, $directionY * $i, $directionZ * $i);
		$direction = ($croupier->yaw + 180) % 360;
		Casino::getInstance()->saveWarpLocation(Location::fromObject($vector3, null, $direction));
		Casino::getInstance()->saveCroupierId($croupier->getLevel()->getId(), $croupier->getId());


		$this->boundingBox = new AxisAlignedBB(
			$from->getFloorX() - $i - 2.4,
			$from->getFloorY() - $i - 2.4,
			$from->getFloorZ() - $i - 2.4,

			$from->getFloorX() + $i + 2.4,
			$from->getFloorY() + $i + 2.4,
			$from->getFloorZ() + $i + 2.4
		);

		$i = 5;
		$adjustPos = function() use ($croupier, $i) : Vector3{
			$rotation = ($croupier->yaw - 90) % 360;
			if($rotation < 0){
				$rotation += 360.0;
			}
			if((0 <= $rotation and $rotation < 45) or (315 <= $rotation and $rotation < 360)){
				return new Vector3(0, 0, $i); //North
			}elseif(45 <= $rotation and $rotation < 135){
				return new Vector3(-$i, 0, 0); //East
			}elseif(135 <= $rotation and $rotation < 225){
				return new Vector3(0, 0, -$i); //South
			}elseif(225 <= $rotation and $rotation < 315){
				return new Vector3($i, 0, 0); //West
			}else return new Vector3(0, 0, 0);
		};

		$startPos = $from->add($adjustPos());
		$i = 0;
		$nextPos = function() use ($startPos, &$i) : Vector3{
			return $startPos->add(0, 2.35 - $i++ * 0.28);
		};

		FloatingTextAPI::store('jackpot', $nextPos());
		FloatingTextAPI::store('*line-break1', $nextPos());
		FloatingTextAPI::store('players-count', $nextPos());
		FloatingTextAPI::store('*line-break2', $nextPos());
		FloatingTextAPI::store('bet', $nextPos());
		FloatingTextAPI::store('*line-break3', $nextPos());
		FloatingTextAPI::store('chance', $nextPos());

		FloatingTextAPI::store('timeToStart', $croupier->asVector3()->add(0, 3.4));
	}

	public function saveCroupierId(int $levelId, int $croupierId) : void{
		$this->knownCroupier = [$levelId, $croupierId];

		$this->animation = new SpinningAnimation($this->findCroupier());
	}

	public function saveWarpLocation(Location $location) : void{
		$this->warpLocation = $location;
	}


public function onCommand(CommandSender $player, Command $command, $commandLabel, array $args){
    if(!$player instanceof Player){
        $player->sendMessage('§c► §aКоманду §b/' . $commandLabel . ' §aможно использовать только в игре!');
        return true;
    }
    if($command->getName() === 'ruletka'){
        if($this->warpLocation === null){
            $player->sendMessage('§c§lКАЗИНО §8:: §rТелепортация невозможна! Варп рулетки не установлен!');
            return true;
        }
        $player->teleport($this->warpLocation);
        $player->sendTitle('§dРУЛЕТКА', '§eТелепортация...', 20, 20, 50);
        Server::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player) : void{
            if(!$player instanceof Player or !$player->isOnline()){
                return;
            }
            Helper::sendSound($player, 'mob.endermen.portal', true);
        }), 4);
    } elseif($command->getName() === 'bet'){
    	$dateTimeZone = new \DateTimeZone('Europe/Moscow');
		$now = new \DateTime('now', $dateTimeZone);
		$currentHour = (int) $now->format('H');

		if ($currentHour < 15 || $currentHour >= 20) {
			$player->sendMessage('§l§c§lКАЗИНО §8:: §rДоступно только с §a15:00§r до §a20:00§r по МСК!');
			return;
		}

        if($this->isRunning()){
            $player->sendMessage('§a§lКАЗИНО §8:: §rНа Данный момент идёт игра! Подожди её окончания!');
            return true;
        }
        if($this->checkAuthed and !wAuth::getInstance()->hasLogined($player)){
            $player->sendPopup('§7► §eВы не авторизованы!');
            return true;
        }
        if($this->getPlayerBet($player) === null and $this->isFull()){
            $player->sendMessage('§c§lКАЗИНО §8:: §rСейчас слишком много игроков');
            return true;
        }

        $bet = array_shift($args);
        if(!is_numeric($bet)){
            $player->sendMessage('§a§lКАЗИНО §8:: §rCтавка должна быть числом!');
            return true;
        }
        $minBetAmount = min(array_keys(Casino::AVAILABLE_BETS));
        $maxBetAmount = max(array_keys(Casino::AVAILABLE_BETS));
        $bet = (int)$bet;
        if($bet < $minBetAmount){
            $minBetAmount = Helper::toPrettyString(number_format($minBetAmount));
            $player->sendMessage('§a§lКАЗИНО §8:: §rМинимальная сумма, которую можно поставить: §d'. $minBetAmount .'$');
            return true;
        }
        if($bet > $maxBetAmount){
            $maxBetAmount = Helper::toPrettyString(number_format($maxBetAmount));
            $player->sendMessage('§a§lКАЗИНО §8:: §rМаксимальная сумма, которую можно поставить: §d'. $maxBetAmount .'$');
            return true;
        }
        if(Loader::getInstance()->getMoney($player) < $bet){
            $player->sendMessage('§c§lКАЗИНО §8:: §rУ тебя недостаточно денег!');
            return true;
        }
        $this->addPlayer($player, $bet);
        return true;
    }
    return true;
}


	public function onLoad(){
		self::$instance = $this;
	}

	public static function getInstance() : Casino{
		return self::$instance;
	}

	public function handleMoneyAdd(string $playerName, int $amount) : void{
		if(!$this->moneyCheaters->exists($playerName)){
			return;
		}
		$debt = (int)$this->moneyCheaters->get($playerName, 0);
		$newDebt = $debt - $amount;
		$player = Server::getInstance()->getPlayerExact($playerName);
		if($newDebt >= 0){
			$this->moneyCheaters->set($playerName, $newDebt);
			$moneyReduce = $amount;
		}else{
			$moneyReduce = $debt;
			$this->moneyCheaters->remove($playerName);
		}
		/** @noinspection PhpDeprecationInspection */
		Server::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(function(int $currentTick) use ($player, $playerName, $moneyReduce) : void{
			Loader::getInstance()->remMoneyy($playerName, $moneyReduce);
			$monetaryUnit = "$";
			if($player instanceof Player and $player->isOnline()){
				$player->sendMessage('§6* §cСписание из долга суммы в размере §a' . number_format($moneyReduce) . $monetaryUnit);
				$currentDebt = (int)$this->moneyCheaters->get($playerName, 0);
				if($currentDebt > 0){
					$player->sendMessage('§6* §cВы остались должны §6' . number_format($currentDebt) . $monetaryUnit);
				}else{
					$player->sendMessage('§6* §cВаш долг полностью списан!');
				}
			}
		}), 1);
	}

	public function weakShowPlayer(Player $player) : void{
		if(!isset($this->hiddenPlayers[$playerId = $player->getId()])){
			return;
		}
		unset($this->hiddenPlayers[$playerId]);
		$this->newShownPlayers[] = $player->getRawUniqueId();
	}

	public function checkBBCollision() : void{
		if($this->boundingBox === null){
			return;
		}
		$levelId = $this->knownCroupier[0] ?? -1;
		$shownPlayers = $hiddenPlayers = [];
		$hasCollided = function(Player $player) use ($levelId) : bool{
			return $player->level->getId() === $levelId and $this->boundingBox->isVectorInside($player);
		};
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			$playerId = $player->getId();
			if($hasCollided($player)){
				if(!isset($this->hiddenPlayers[$playerId])){
					$this->hiddenPlayers[$playerId] = $hiddenPlayers[$playerId] = $player;
					$player->sendPopup('§7Вы скрыты от всех игроков!');
				}
			}elseif(isset($this->hiddenPlayers[$playerId])){
				unset($this->hiddenPlayers[$playerId]);
				$shownPlayers[$playerId] = $player;
				$player->sendPopup('§7Вы вновь видны другим игрокам!');
			}
		}
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			foreach($hiddenPlayers as $id => $p){
				if($id === $player->getId()){
					continue;
				}
				$player->hidePlayer($p);
			}
			foreach($shownPlayers as $id => $p){
				if($id === $player->getId()){
					continue;
				}
				$player->showPlayer($p);
			}
			foreach($this->newShownPlayers as $uuid){
				(function() use ($uuid) : void{
					unset($this->{'hiddenPlayers'}[$uuid]);
				})->call($player);
			}
		}
		$this->newShownPlayers = [];
	}

	public static function findBetChance($bet){
		$chance = Casino::AVAILABLE_BETS[min(array_keys(Casino::AVAILABLE_BETS))]['chance'];
		foreach(Casino::AVAILABLE_BETS as $_bet => $data){
			if($bet >= $_bet){
				$chance = $data['chance'];
			}
		}
		return $chance;
	}
}
