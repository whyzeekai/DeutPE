<?php

declare(strict_types=1);

namespace timurkaundefined\casino\animation;

use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use timurkaundefined\casino\Casino;
use timurkaundefined\casino\entity\Croupier;
use function array_keys;
use function array_shift;
use function count;
use function shuffle;

abstract class BasicAnimation{

	/** @var Croupier */
	protected $croupier;

	/** @var Item[] */
	protected $items = [];

	public function __construct(Croupier $croupier){
		$this->croupier = $croupier;
	}

	public function addItem(Item $item) : void{
		$this->items[] = $item;
	}

	public function getItems() : array{
		return $this->items;
	}

	public function getCroupier() : Croupier{
		return $this->croupier;
	}

	abstract public function start() : void;

	abstract public function finish() : void;

	abstract public function getSnakeLength() : int;

	abstract public function getSnakeRewardOffset() : int;

	public function randomizeSnake(string $winningPlayer, array $allPlayers) : void{
		$allPlayerKeys = array_keys($allPlayers);
		shuffle($allPlayerKeys);

		$snake = [];
		$length = $this->getSnakeLength();
		$rewardsCount = count($allPlayerKeys);

		for($i = 0; $i < $length; ++$i){
			$snake[] = $allPlayerKeys[$i % $rewardsCount];
		}

		$iterate = function() use (&$snake) : void{
			$v = array_shift($snake);
			$snake[] = $v;
		};

		$attempts = 20;
		while($winningPlayer !== $snake[$this->getSnakeRewardOffset()]){
			$iterate();

			if(--$attempts === 0){
				break;
			}
		}

		//$key = array_search($winningPlayer, $snake, true);
		//\pocketmine\Server::getInstance()->broadcastMessage('§7Ожидаемый победитель §6► ' . $winningPlayer .' ('. $key .')');
		//$snakeStr = implode('§7, §f', $snake);
		//\pocketmine\Server::getInstance()->broadcastMessage('§7цепочка §6► §f' . $snakeStr);

		foreach($snake as $playerName){
			$playerName = (string)$playerName;

			$bet = Casino::getInstance()->getPlayerBet($playerName);

			$player = Casino::getInstance()->getPlayerInstance($playerName);
			$playerName = $player instanceof Player ? $player->getName() : $playerName;
			$chance = Casino::findBetChance($bet);

			$item = (new Item(ItemIds::SKULL, 1))->setCustomName('§d' . $playerName . ' §f- §6' . $chance . '%');
			$this->addItem($item);
		}
	}
}