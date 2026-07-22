<?php

declare(strict_types=1);

namespace DenOrekhov\Marry\commands;

use pocketmine\command\Command;

use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\level\Position;
use pocketmine\entity\Villager;
use pocketmine\entity\Entity; 
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\StringTag; 
use pocketmine\level\particle\HeartParticle;
use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\particle\{FloatingTextParticle, GenericParticle, Particle};

use DenOrekhov\Marry\Loader;

class MarryCommand extends Command
{

	private $loader;
	
	public function __construct($commandName) {
		parent::__construct($commandName, $commandName);
	}

	public function execute(CommandSender $sender, $alias, array $args): bool {
		if(count($args) >= 1) {
			$arg1 = array_shift($args);

			if($arg1 === "help") {
				$sender->sendMessage("\n§7§l►§f ПОМОЩЬ ПО БРАКУ§7:");
				$sender->sendMessage("§7 -§r §r§a/marry help §7помощь по командам (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry info §7информация о браке (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry invite §7отправить предложение (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry home §7телепортироваться домой (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry sethome §7поставить точку дома (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry addchild (имя ребенка) §7завести ребенка (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry remchild §7кинуть ребенка (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry accept §7принять предложение (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry kiss §7поцеловать (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry tp §7телепортироваться (marry)");
				$sender->sendMessage("§7 -§r §r§a/marry divorce §7брак (marry)");
			} elseif($arg1 === "invite") {
				if(!Loader::getInstance()->getMarryManager()->isMarried($sender)) {
				 if(Loader::getInstance()->getServer()->getPluginManager()->getPlugin("NexAPI")->getMoney($sender) >= 5000){
					$arg2 = array_shift($args);	
					Loader::getInstance()->getServer()->getPluginManager()->getPlugin("NexAPI")->remMoney($sender, 5000);

					$to_the_sender = Server::getInstance()->getPlayer($arg2);

					if($sender != $to_the_sender) {
						if(!Loader::getInstance()->getMarryManager()->isMarried($to_the_sender)) {
							if($to_the_sender !== null) {
								Loader::$requests[$to_the_sender->getLowerCaseName()] = [$sender, time() + 240];
								$sender->sendMessage("§7§l►§r §fТы отправил игроку §e".$to_the_sender->getName()." §fпредложение руки и сердца.");
								$to_the_sender->sendMessage("§7§l►§r §fИгрок §e".$sender->getName()." §fсделал тебе предложение руки и сердца, §e/marry accept");
							} else {
								$sender->sendMessage("§7§l►§r §fИгрока нет на сервере или ты ввел неправильный никнейм.");
							}

						} else {
							$sender->sendMessage("§7§l►§r §fИгрок §e".$to_the_sender->getName()." §fуже состоит в браке");
						}

					} else {
						$sender->sendMessage("§7§l►§r §fНельзя отправить приглашение самому себе.");
					}

				} else {
					$sender->sendMessage("§7§l►§r §fУ вас недостаточно денег, вам нужно §a5000$ ");
				}

				} else {
					$sender->sendMessage("§7§l►§r §fУ тебя уже есть вторая половинка.");
				}

			} elseif($arg1 === "accept") {
				if(isset(Loader::$requests[$sender->getLowerCaseName()])) {
					if(Loader::$requests[$sender->getLowerCaseName()][1] > time()) {
						Loader::getInstance()->getMarryManager()->setMarried(Loader::$requests[$sender->getLowerCaseName()][0], $sender);
						$sender->sendMessage("§7§l►§r §fВы с игроком §e".Loader::$requests[$sender->getLowerCaseName()][0]->getName()." §fрассписались.");

						Loader::$requests[$sender->getLowerCaseName()][0]->sendMessage("§7§l►§r §fВы с игроком §e".$sender->getName()." §fрассписались.");

						Loader::getInstance()->getServer()->broadcastMessage("§7§l►§r §fПоздравляем игроков §e".$sender->getName()." §fи §e".Loader::$requests[$sender->getLowerCaseName()][0]->getName()." §fс свадьбой!");

						unset(Loader::$requests[$sender->getLowerCaseName()]);
					} else {
						$sender->sendMessage("§7§l►§r §fЗапрос руки и сердца от игрока §e".Loader::$requests[$sender->getLowerCaseName()][0]->getName()." §fистек.");

						unset(Loader::$requests[$sender->getLowerCaseName()]);
					}

				} else {
					$sender->sendMessage("§7§l►§r §fУ тебя нет активных запросов.");
				}

			} elseif($arg1 === "divorce") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					Loader::getInstance()->getMarryManager()->setDivorced($sender);
					if($two_half !== null) {
						$two_half->sendMessage("§7§l►§r §fТвой партнер - §e".$sender->getName()." §fразвелся с тобой.");
						$sender->sendMessage("§c§l►§r §fТы развелся с игроком §c".$two_half->getName());
					} else {
						$sender->sendMessage("§c§l►§r §fТы развелся с игроком §c".$two_half_nickname);
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "info") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					if($two_half !== null) {
						$sender->sendMessage("\n§7§l► §fИНФОРМАЦИЯ О ВАШЕМ БРАКЕ§7:");
					    $sender->sendMessage(" §r§7- §rВы§7: §a{$sender->getName()}");
					    $sender->sendMessage(" §r§7- §rВаш партнер§7: §a{$two_half->getName()} §8[§aОнлайн§8]\n");
					}else{
						$sender->sendMessage("\n§7§l► §fИНФОРМАЦИЯ О ВАШЕМ БРАКЕ§7:");
					    $sender->sendMessage(" §r§7- §rВы§7: §a{$sender->getName()}");
					    $sender->sendMessage(" §r§7- §rВаш партнер§7: §a". $two_half_nickname." §8[§cОффлайн§8]\n");
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "sethome") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					Loader::getInstance()->getMarryManager()->setHome($sender, $sender->x, $sender->y, $sender->z, $sender->level);

					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					if($two_half !== null) {
						$two_half->sendMessage("§7§l►§r §fТвой партнер - §e".$sender->getName()." §fизменил точку общего дома.");
					} 

					$sender->sendMessage("§7§l►§r §fТы установил точку дома");
				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "home") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$home = Loader::getInstance()->getMarryManager()->getHome($sender);
					$home = str_replace(':', ' ', $home);
					$home = explode(' ', $home);

					$sender->teleport(new Position($home[0], $home[1], $home[2], Server::getInstance()->getLevelByName($home[3])));
				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "tp") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					if($two_half !== null) {
						$sender->teleport(new Position($two_half->x, $two_half->y, $two_half->z, $two_half->level));

						$sender->sendMessage("§7§l►§r §fТы успешно телепортировался к второй половинке.");
						$two_half->sendMessage("§7§l►§r §fК тебе тп твоя вторая половинка.");
					} else {
						$sender->sendMessage("§7§l►§r §f§7§l►§r §fТвоя вторая половинка не в сети.");
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "kiss") {
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					if($two_half !== null) {
						$sender->sendMessage("§7§l►§r §fТы поцеловал своего партнера.");
						$two_half->sendMessage("§7§l►§r §fТебя полецовал твой партнер.");

						$x = $two_half->getX();
						$y = $two_half->getY();
						$z = $two_half->getZ();

						for($i = 0; $i <= 25; $i++)
                        {
                        $pos = new Vector3($x + mt_rand(-2, 2), $y + mt_rand(-1, 1), $z + mt_rand(-2, 2));
                        $paricle = new HeartParticle($pos); 
                        $two_half->getLevel()->addParticle($paricle);
                        }
					} else {
						$sender->sendMessage("§7§l►§r §fТвоя вторая половинка не в сети.");
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} elseif($arg1 === "addchild") {
				if($sender->hasPermission("death.addchild")){
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					if(!isset($this->child[strtolower($sender->getName())])) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					$sender->teleport(new Position($two_half->x, $two_half->y, $two_half->z, $two_half->level));

					if($two_half !== null) {
						$nbt = new CompoundTag("", [
							new ListTag("Pos", [
								new DoubleTag("", $sender->getX()),
								new DoubleTag("", $sender->getY()),
								new DoubleTag("", $sender->getZ())
							]),
							new ListTag("Motion", [
								new DoubleTag("", 0.0),
								new DoubleTag("", 0.0),
								new DoubleTag("", 0.0)
							]),
								new ListTag("Rotation", [
								new FloatTag("", $sender->getYaw()),
								new FloatTag("", $sender->getPitch())
							])
						]);

						$child = Entity::createEntity("Villager", $sender->getLevel(), $nbt, $sender);
						$child->setNameTag("§7§l►§r §fРебенок §e".$sender->getName()." §fи §e".$two_half->getName()."\n§fИмя: §e{$args[0]}");
        				$child->setNameTagAlwaysVisible(true);
        				$child->getDataPropertyManager()->setFloat(Villager::DATA_SCALE, 0.6);
						$child->spawnToAll();

						$this->child[strtolower($sender->getName())] = 0;

						$sender->sendMessage("§7§l►§r §fВы с супругом завели ребенка");
						$two_half->sendMessage("§7§l►§r §fВы с супругом завели ребенка");
					} else {
						$sender->sendMessage("§7§l►§r §fТвоя вторая половинка не в сети.");
					}

					} else {
						$sender->getLevel()->addSound((new \pocketmine\level\sound\AnvilFallSound($sender)), [$sender]);
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

				} else {
				$player = $sender;
				$player->sendMessage("§7§l►§r §r§fУ вас §cнет прав §fна эту команду, эту команду может использовать §cАдмин §fили выше!");
    $player->getLevel()->addSound((new \pocketmine\level\sound\AnvilFallSound($player)), [$player]);
			}

			} elseif($arg1 === "removechild") {
			if($sender->hasPermission("death.addchild")){
				if(Loader::getInstance()->getMarryManager()->isMarried($sender)) {
					$two_half_nickname = Loader::getInstance()->getMarryManager()->getTwoHalf($sender);
					$two_half = Server::getInstance()->getPlayer($two_half_nickname);

					foreach(Server::getInstance()->getLevels() as $levels => $level) {
						foreach ($level->getEntities() as $entities => $entity) {
							if((explode(' ', $entity->getNameTag())[0])) {
								$explode = explode(' ', $entity->getNameTag());

								if($explode[0] === "§7§l►§r §fРебенок") {
									if($explode[1] === $sender->getName() or $explode[2] === $sender->getName()) {
										$entity->close("", "");
									}

								}

							}

						}

					}

					if($two_half !== null) {
						$sender->sendMessage("§7§l►§r §fВы убили своего ребенка...");
						$two_half->sendMessage("§7§l►§r §fВы убили своего ребенка...");
					} else {
						$sender->sendMessage("§7§l►§r §fВы убили своего ребенка...");
					}

				} else {
					$sender->sendMessage("§7§l►§r §fТы не состоишь в браке.");
				}

			} else {
				$player = $sender;
				$player->sendMessage("§7§l►§r §r§fУ вас §cнет прав §fна эту команду, эту команду может использовать §cАдмин §fили выше!");
    $player->getLevel()->addSound((new \pocketmine\level\sound\AnvilFallSound($player)), [$player]);
			}

			}

		} else {
			$sender->sendMessage("§7§l►§r §fИспользуй §a/marry help.");
		}
		return true;
	}

}