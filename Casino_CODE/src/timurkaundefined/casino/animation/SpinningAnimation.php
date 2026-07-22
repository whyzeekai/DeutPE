<?php

declare(strict_types=1);

namespace timurkaundefined\casino\animation;

use pocketmine\level\particle\Particle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use timurkaundefined\casino\animation\object\FloatingItem;
use timurkaundefined\casino\Casino;
use timurkaundefined\casino\utils\Helper;
use function array_filter;
use function count;
use function getrandmax;
use function rand;

class SpinningAnimation extends BasicAnimation{

	/** @var bool */
	private $flagForFinish = false;

	public function flagForFinish() : void{
		$this->flagForFinish = true;
	}

	public function hasFlaggedForFinish() : bool{
		return $this->flagForFinish;
	}

	/** @var int|null */
	public $finalEntityId = null;

	public function setFinalEntityId(int $entityId) : void{
		$this->finalEntityId = $entityId;
	}

	public function getFinalEntityId() : ?int{
		return $this->finalEntityId;
	}

	/** @var Vector3 */
	public $collisionVector3;

	public function start() : void{
		$task = new class($this) extends Task{

			/** @var SpinningAnimation */
			private $animation;

			/** @var FloatingItem[] */
			private $floatingItems = [];

			/** @var Player[] */
			private $viewers;

			/** @var int|null */
			private $closeTicks = null;

			/** @var AxisAlignedBB */
			private $collisionBB;

			public function __construct(SpinningAnimation $animation){
				$this->animation = $animation;

				$directions = $animation->generateDirections();

				$batch = new BatchPacket();

				foreach($animation->getItems() as $key => $item){
					$floatingItem = new FloatingItem(
						$animation, $item, Casino::getInstance()->findCroupier()->getDirection(), $key, $directions[$key]
					);
					if($key === $this->animation->getSnakeRewardOffset()){
						$floatingItem->setGeneral();
					}
					$this->floatingItems[] = $floatingItem;

					$batch->addPacket($floatingItem->getSpawnPacket());
				}

				$batch->addPacket(Helper::getSoundPacket($animation->getCroupier()->asVector3(), 'random.pop'));

				$this->viewers = array_filter($this->animation->getCroupier()->getLevel()->getPlayers(), function(Player $player) : bool{
					return $player->distanceSquared($this->animation->getCroupier()->asVector3()) < 441;
				});
				$this->sendToViewers($batch);

				$this->initBoundingBox();
				$this->initSoundsTask();
			}

			public function onRun($currentTick){
				if($this->closeTicks !== null){
					if(--$this->closeTicks === 0){
						$this->cancel();
					}
					return;
				}

				if($this->animation->hasFlaggedForFinish()){

					foreach($this->floatingItems as $floatingItem){
						if(!$this->collisionBB->isVectorInside($floatingItem->asVector3())){
							continue;
						}

						$this->finishLocal($floatingItem);
						break;
					}
				}

				$batch = new BatchPacket();

				foreach($this->floatingItems as $floatingItem){
					$floatingItem->move();
					foreach($floatingItem->getMovePackets() as $pk){
						$batch->addPacket($pk);
					}
				}

				$this->sendToViewers($batch);
			}

			private function finishLocal(FloatingItem $floatingItem) : void{
				Casino::getInstance()->addReward();

				$batch = new BatchPacket();

				$this->animation->setFinalEntityId($floatingItem->getEntityId());
				$this->closeTicks = 60;

				foreach($this->floatingItems as $key => $floatingItem){
					if($floatingItem->getEntityId() !== $this->animation->getFinalEntityId()){
						$batch->addPacket($floatingItem->getRemovePacket());

						$floatingItem->close();
						unset($this->floatingItems[$key]);
					}
				}

				$vector3 = $this->animation->getCroupier()->asVector3();
				$pk = new BlockEventPacket();
				$pk->x = $vector3->x;
				$pk->y = $vector3->y;
				$pk->z = $vector3->z;
				$pk->case1 = 1;
				$pk->case2 = 2;
				$batch->addPacket($pk);

				$pk = new LevelSoundEventPacket();
				$pk->sound = LevelSoundEventPacket::SOUND_CHEST_OPEN;
				$pk->pitch = 1;
				$pk->extraData = -1;
				$pk->unknownBool = false;
				$pk->x = $vector3->x;
				$pk->y = $vector3->y;
				$pk->z = $vector3->z;
				$batch->addPacket($pk);

				$this->sendToViewers($batch);
			}

			public function finishAllThings() : void{
				$batch = new BatchPacket();
				$pk = new RemoveEntityPacket();
				$pk->eid = $this->animation->finalEntityId;
				$batch->addPacket($pk);

				$vector3 = $this->animation->collisionVector3;

				$getRandomVector = function() : Vector3{
					$x = rand() / getrandmax() * 2 - 1;
					$y = rand() / getrandmax() * 2 - 1;
					$z = rand() / getrandmax() * 2 - 1;
					$v = new Vector3($x, $y, $z);
					return $v->normalize();
				};

				$pk = new LevelEventPacket();
				$pk->evid = LevelEventPacket::EVENT_ADD_PARTICLE_MASK | Particle::TYPE_EVAPORATION;
				$pk->data = 1;
				$pk->isEncoded = false;
				$pk->x = $vector3->x;
				$pk->y = $vector3->y;
				$pk->z = $vector3->z;
				$batch->addPacket($pk);

				$pk = new LevelEventPacket();
				$pk->evid = LevelEventPacket::EVENT_ADD_PARTICLE_MASK | Particle::TYPE_FLAME;
				$pk->data = 1;
				for($i = 0; $i < 5; ++$i){
					$v3 = $vector3->add($getRandomVector()->multiply(0.4));
					$pk->isEncoded = false;
					$pk->x = $v3->x;
					$pk->y = $v3->y;
					$pk->z = $v3->z;
					$batch->addPacket($pk);
				}

				$this->sendToViewers($batch);

				$this->animation->finish();
				$this->floatingItems = $this->viewers = [];
			}

			public function cancel(){
				Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());

				$this->finishAllThings();
			}

			public function sendToViewers(DataPacket $pk) : void{
				foreach($this->viewers as $key => $player){
					if($player instanceof Player and $player->isOnline() and !$player->isClosed()){
						$player->dataPacket($pk);
					}else{
						unset($this->viewers[$key]);
					}
				}
			}

			public function initBoundingBox() : void{
				$r = FloatingItem::CIRCLE_RADIUS;
				$xAdd = $zAdd = 0;
				$direction = $this->animation->getCroupier()->getDirection();
				if($direction === 0){
					$xAdd = 1;
				}elseif($direction === 1){
					$zAdd = 1;
				}elseif($direction === 2){
					$xAdd = -1;
				}else{
					$zAdd = -1;
				}
				$v3 = $this->animation->getCroupier()->asVector3()->add($r * $xAdd, 0.3, $r * $zAdd);
				[$x, $y, $z] = [$v3->x, $v3->y, $v3->z];
				$this->collisionBB = (new AxisAlignedBB($x, $y, $z, $x, $y, $z))->expand(0.06, 0.06, 0.06);

				$this->animation->collisionVector3 = $v3->subtract(0, 0.04);
			}

			public function initSoundsTask() : void{
				$task = new class($this->animation) extends Task{

					/** @var Player[] */
					private $recipients;

					/** @var SpinningAnimation */
					private $animation;

					/** @var int */
					private $currentTick;

					public function __construct(SpinningAnimation $animation){
						$this->animation = $animation;
						$this->currentTick = 0;

						$v3 = $animation->getCroupier()->asVector3();
						$this->recipients = array_filter($animation->getCroupier()->getLevel()->getPlayers(), function(Player $player) use ($v3) : bool{
							return $player->distanceSquared($v3) < 394;
						});
					}

					public function onRun($currentTick){
						if($this->animation->hasFlaggedForFinish()){
							Server::getInstance()->getScheduler()->cancelTask($this->getTaskId());
						}
						$a = [
							[85, 4],
							[0, 2]
						];
						++$this->currentTick;
						foreach($a as [$needTicks, $ticksDiff]){
							if($this->currentTick >= $needTicks){
								break;
							}
						}
						if($this->currentTick % $ticksDiff !== 0){
							return;
						}
						foreach($this->recipients as $key => $player){
							if(!$player instanceof Player or !$player->isOnline()){
								unset($this->recipients[$key]);
								continue;
							}
							Helper::sendSound($player, 'note.harp', true, 2, 0.95);
						}
					}
				};
				Server::getInstance()->getScheduler()->scheduleRepeatingTask($task, 1);
			}
		};
		Server::getInstance()->getScheduler()->scheduleRepeatingTask($task, 1);
	}

	public function finish() : void{
		Casino::getInstance()->doReload();
		$this->items = [];
		$this->flagForFinish = false;
		$this->finalEntityId = null;
	}

	public function getSnakeLength() : int{
		return 10;
	}

	public function getSnakeRewardOffset() : int{
		return 5;
	}

	public function generateDirections() : array{
		$itemsCount = count($this->getItems());

		$directions = [];
		if(rand(0, 1)){
			for($i = 0; $i < $itemsCount; ++$i){
				$directions[] = -1;
			}
		}else{
			for($i = 0; $i < $itemsCount; ++$i){
				$directions[] = 1;
			}
		}
		return $directions;
	}
}