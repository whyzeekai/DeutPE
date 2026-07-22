<?php

declare(strict_types=1);

namespace timurkaundefined\casino\particle;

use onebone\economyapi\EconomyAPI;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\SetEntityDataPacket;
use pocketmine\Server;
use timurkaundefined\casino\Casino;
use timurkaundefined\casino\utils\Helper;
use function array_map;
use function number_format;
use function strpos;
use function var_dump;

class IndividualFloatingText extends Vector3{

	/** @var string */
	protected $title;

	/** @var int */
	protected $entityId;

	/** @var Player[] */
	protected $viewers = [];

	public function __construct(Vector3 $pos, string $title){
		parent::__construct($pos->x, $pos->y, $pos->z);

		$this->title = $title;
		$this->entityId = Entity::$entityCount++;
	}

	public function getVisibleTitle(Player $player) : string{
		if($this->title === 'jackpot'){
			return '§fТекущий банк розыгрыша: §e'. Casino::getInstance()->getPrettyJackpot();
		}elseif($this->title === 'players-count'){
			$count = Casino::getInstance()->getPlayersCount();
			return '§fУчастники: §6'. $count .' §e'. Helper::toPlural($count, ['игрок', 'игрока', 'игроков']);
		}elseif($this->title === 'bet'){
			$bet = Casino::getInstance()->getPlayerBet($player);
			if($bet !== null){
				$bet = '§d' . number_format($bet) . '§5$';
			}else{
				$bet = '§cНет';
			}
			return '§fТвоя ставка: ' . $bet;
		}elseif($this->title === 'chance'){
			return '§fТвой шанс победить: §e'. Casino::getInstance()->getWinChance($player);
		}elseif($this->title[0] === '*'){
			return '§b*';
		}else{
			if(Casino::getInstance()->getPlayersCount() < Casino::MIN_PLAYERS){
				return '§eЖду игроков...';
			}else{
				$timeToStart = Casino::getInstance()->getTimeToStart();
				if($timeToStart <= Casino::SECONDS_WAITING){
					return '§e'. Helper::secondsToString($timeToStart);
				}else{
					return '§eГотовлюсь начинать...';
				}
			}
		}
	}

	public function updateToAll() : void{
		foreach($this->viewers as $player){
			$pk = new SetEntityDataPacket();
			$pk->eid = $this->entityId;
			$pk->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->getVisibleTitle($player)]];
			$player->dataPacket($pk);
		}
	}

	public function spawnTo(Player $player) : void{
		if(!isset($this->viewers[$playerName = $player->getName()])){
			$pk = new AddEntityPacket();
			$pk->type = 39;

			$pk->eid = $this->entityId;
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$flags = (
				(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
				(1 << Entity::DATA_FLAG_IMMOBILE) |
				(1 << Entity::DATA_FLAG_SILENT)
			);
			$pk->metadata = [
				Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
				Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $this->getVisibleTitle($player)],
				Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.0]
			];
			$player->dataPacket($pk);
			$this->viewers[$playerName] = $player;
		}
	}

	public function despawnFrom(Player $player, bool $sendPacket = true) : void{
		if($sendPacket){
			$pk = new RemoveEntityPacket();
			$pk->eid = $this->entityId;
			$player->dataPacket($pk);
		}

		unset($this->viewers[$player->getName()]);
	}

	public function spawnToAll() : void{
		array_map(function(Player $player) : void{
			$this->spawnTo($player);
		}, Server::getInstance()->getOnlinePlayers());
	}

	public function despawnFromAll() : void{
		array_map(function(Player $player) : void{
			$this->despawnFrom($player);
		}, $this->viewers);
	}
}
