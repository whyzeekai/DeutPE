<?php

declare(strict_types=1);

namespace API\task;

use API\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\math\Vector3;

use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\scheduler\Task;

use \timurkaundefined\gametimer\GameTimer as GameTimer;

class GoraTask extends task {

    private Loader $loader;

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    }

    public function onRun($currentTick) {
        foreach (Server::getInstance()->getOnlinePlayers() as $p) {
            $x = $p->getFloorX();
            $y = $p->getFloorY();
            $z = $p->getFloorZ();

            $position = $p->getPosition();
            $target = new Vector3(16, 72, -288);

            if ($position->distanceSquared($target) <= 28 && 
                $position->getY() >= $target->getY() - 4 && 
                $position->getY() <= $target->getY() + 4) {

                if ($p->isCreative() or $p->getAllowFlight(true)) {
                    $p->sendMessage("§b§lАРЕНА §7§l►§r §rОтключите флай/креатив");
                    $p->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
                    return true;
                }

                if(count(Server::getInstance()->getOnlinePlayers()) < 1){
                    return $p->sendPopup('§7§l►§r §r§fНедостаточно игроков на сервере, нужно §a5§r§f игроков! §l§7◄');
                }

                if (Loader::getInstance()->time <= 30) {
                    $p->sendPopup("§l§bЦарь§7-§bГоры §8:: §rПопробуйте после перезагрузки!");
                    return true;
                }

                $playTime = GameTimer::getGameTime($p);
                $requiredPlayTime = 600;

                if($playTime < $requiredPlayTime){
                    $p->sendPopup("§r§r§fНужно наиграть§7: §a". Loader::getInstance()->parseTime($requiredPlayTime));
                    return;
                }

                $money = mt_rand(2, 25);
                $exp = mt_rand(0, 5);
                Loader::getInstance()->addMoney($p, $money);
                Loader::getInstance()->addExp($p, $exp);
                $nickname = strtolower($p->getName());
                $timegora = Loader::getInstance()->goratime->get($nickname);
                $time = Loader::getInstance()->parseTime($timegora);

                if (!Loader::getInstance()->goratime->exists($nickname)) {
                    Loader::getInstance()->goratime->set($nickname, 1);
                    Loader::getInstance()->goratime->save();
                } else {
                    $sec = Loader::getInstance()->goratime->get($nickname);
                    Loader::getInstance()->goratime->set($nickname, $sec + 1);
                    Loader::getInstance()->goratime->save();
                }
                $p->sendMessage("§l§bЦарь§7-§bГоры §8:: §rВам выдано§7: §a{$money}§2$ §rи §d{$exp}§5exp");
                $p->sendPopup("§l§bЦарь§7-§bГоры §8:: §f§rВы простояли уже§7: §a{$time}");
            }else{
                $nickname = strtolower($p->getName());
                Loader::getInstance()->goratime->remove($nickname);
            }
        }
    }
}