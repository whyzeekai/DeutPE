<?php

declare(strict_types=1);


namespace API\task;

use API\Loader;

use pocketmine\scheduler\PluginTask;

use pocketmine\plugin\PluginBase;

use pocketmine\math\Vector3;

use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\level\particle\HugeExplodeSeedParticle;


class TaskLevelUpSeller extends PluginTask
{

    private $loader;

    public function __construct(Loader $loader, $player){
    	$this->loader = $loader;
        $this->player = $player;
        $this->time = 10;
        parent::__construct($loader);
    }

    public function onRun($tick){
    	$player = $this->player;
        $this->time--;
        switch ($this->time) {
            case 9:
            $player->addTitle("§l§bＬ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 8:
            $player->addTitle("§l§bＬＥ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 7:
            $player->addTitle("§l§bＬＥＶ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 6:
            $player->addTitle("§l§bＬＥＶＥ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 5:
            $player->addTitle("§l§bＬＥＶＥＬ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 4:
            $player->addTitle("§l§bＬＥＶＥＬ Ｕ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 3:
            $player->addTitle("§l§bＬＥＶＥＬ ＵＰ");
            $player->getLevel()->addSound((new \pocketmine\level\sound\ButtonClickSound($player)), [$player]);
                break;

            case 2:
            $lvl = Loader::getInstance()->getLvls($player);
            $lvlup = $lvl + 1;
            $player->addTitle("§l§bＬＥＶＥＬ ＵＰ", "§l§7[ §r§b{$lvl} §7-> §b{$lvlup} §l§7]");
            Loader::getInstance()->getServer()->getDefaultLevel()->addParticle(new HugeExplodeSeedParticle(new Vector3($player->getX(), $player->getY(), $player->getZ())));
                break;

            
            case 1:
            $lvl = Loader::getInstance()->getLvlbuyer($player);
            $lvlup = $lvl + 1;
            $money = mt_rand(1000, 10000);

            $player->addTitle("§l§bＬＥＶＥＬ ＵＰ", "§l§7[ §r§b{$lvl} §7-> §b{$lvlup} §l§7]");
            $player->sendMessage("\n\n§7§r §r§rВы повысили уровень §l§bскупщика§r, с §8[§b" . Loader::getInstance()->getLvlbuyer($player) . "ур.§8] §7-> §8[§b" . $lvlup . "ур.§8]");
            $player->sendMessage("§7§r §r§rЗа повышение уровня §l§bскупщика§r, вы получили §a". number_format($money) ."§2$\n\n");

            Loader::getInstance()->addMoney($player, $money);
            Loader::getInstance()->addLvlbuyer($player, 1);
            Loader::getInstance()->getServer()->getDefaultLevel()->addParticle(new HugeExplodeSeedParticle(new Vector3($player->getX(), $player->getY(), $player->getZ())));
                break;
            
        }
    }

}
