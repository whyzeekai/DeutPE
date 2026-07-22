<?php

declare(strict_types=1);

namespace timurkaundefined\casino\animation\object;

use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddItemEntityPacket;
use pocketmine\network\mcpe\protocol\MoveEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\utils\TextFormat;
use timurkaundefined\casino\animation\SpinningAnimation;
use timurkaundefined\casino\Casino;
use timurkaundefined\casino\utils\Helper;
use function cos;
use function mb_strlen;
use function sin;
use function strlen;

class FloatingItem{

	public const CIRCLE_RADIUS = 2.6;

	/** @var int */
	public static $staticEntityId = -102;

	/** @var SpinningAnimation */
	private $animation;

	/** @var Vector3 */
	private $vector3, $center;

	/** @var Item */
	private $item;

	/** @var int */
	private $side, $direction, $entityId, $ticksLived;

	/** @var float */
	private $a, $startA;

	/** @var bool */
	private $isGeneral = false;

	public function __construct(SpinningAnimation $animation, Item $item, int $side, int $circleOffset, int $direction){
		$this->animation = $animation;
		$this->center = Casino::getInstance()->findCroupier()->asVector3()->add(0, self::CIRCLE_RADIUS + 0.35);
		$this->item = $item;
		$this->side = $side;
		$this->direction = $direction;
		$this->ticksLived = 0;

		$this->a = $circleOffset * $animation->getSnakeLength() * 0.06225;
		$this->startA = $this->a;

		$this->vector3 = new Vector3(0, 0, 0);

		$this->entityId = --self::$staticEntityId;

		$this->move();
	}

	public function move() : void{
		++$this->ticksLived;

		$diff = $this->a - $this->startA;

		$add = null;
		$speedMap = [
			2 => 0.11,
			4 => 0.135,
			6 => 0.15,
			8 => 0.17,
			9 => 0.155,
			10 => 0.12,
			11 => 0.08,
			12 => 0.055,
			13 => 0.04,
			14 => 0.032,
		];
		foreach($speedMap as $diffMax => $speed){
			if($diff < $diffMax){
				$add = $speed;
				break;
			}
		}

		if($add === null){
			$this->animation->flagForFinish();
		}

		$this->a += $add ?? 0.0305;

		$r = FloatingItem::CIRCLE_RADIUS;
		$from = $this->center;

		$cosA = $r * cos($this->a) * $this->direction;
		$sinA = $r * sin($this->a);

		if($this->side === 0){
			$x = $from->x + -$sinA;
			$y = $from->y + $sinA;
			$z = $from->z + $cosA;
		}elseif($this->side === 1){
			$x = $from->x + $cosA;
			$y = $from->y + $sinA;
			$z = $from->z + -$sinA;
		}elseif($this->side === 2){
			$x = $from->x + $sinA;
			$y = $from->y + $sinA;
			$z = $from->z + $cosA;
		}else{
			$x = $from->x + $cosA;
			$y = $from->y + $sinA;
			$z = $from->z + $sinA;
		}

		$this->vector3->x = $x;
		$this->vector3->y = $y;
		$this->vector3->z = $z;
	}

	public function getSpawnPacket() : AddItemEntityPacket{
		$title = $this->item->getCustomName();
		$cleanTile = TextFormat::clean($title);
		if(strlen($cleanTile) !== mb_strlen($cleanTile)){
			$title = ' ' . $title;
		}

		$pk = new class() extends AddItemEntityPacket{
			public function encode(){
				$this->reset();
				$this->putEntityId($this->eid);
				$this->putEntityId($this->eid);
				$this->putSlot($this->item);
				$this->putVector3f($this->x, $this->y, $this->z);
				$this->putVector3f($this->speedX, $this->speedY, $this->speedZ);
				$this->putEntityMetadata($this->metadata);
			}
		};
		$pk->eid = $this->entityId;
		$pk->x = $this->vector3->x;
		$pk->y = $this->vector3->y;
		$pk->z = $this->vector3->z;
		$pk->speedX = $pk->speedY = $pk->speedZ = 0;
		$pk->item = $this->item;
		$flags = (
			(1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
			(1 << Entity::DATA_FLAG_IMMOBILE)
		);
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, Helper::applyUTFSymbols($title)]
		];

		return $pk;
	}

	public function getMovePackets() : array{
		$packets = [];

		$pk = new MoveEntityPacket();
		$pk->eid = $this->entityId;
		$pk->x = $this->vector3->x;
		$pk->y = $this->vector3->y;
		$pk->z = $this->vector3->z;
		$pk->yaw = $pk->pitch = $pk->headYaw = 0.0;
		$pk->onGround = false;
		$pk->teleported = false;
		$packets[] = $pk;

		if($this->isGeneral and $this->ticksLived % 5 === 0){
			$r = 240;
			$g = 240;
			$b = 255;
			$packets[] = (new GenericParticle($this->animation->collisionVector3, Particle::TYPE_WITCH_SPELL,
				0xff000000 | ($r << 16) | ($g << 8) | $b
			))->encode();
		}

		return $packets;
	}

	public function getRemovePacket() : RemoveEntityPacket{
		$pk = new RemoveEntityPacket();
		$pk->eid = $this->entityId;

		return $pk;
	}

	public function setGeneral() : void{
		$this->isGeneral = true;
	}

	public function close() : void{
		$this->vector3 = $this->center = $this->item = $this->side = null;
	}

	public function asVector3() : Vector3{
		return $this->vector3;
	}

	public function getEntityId() : int{
		return $this->entityId;
	}
}