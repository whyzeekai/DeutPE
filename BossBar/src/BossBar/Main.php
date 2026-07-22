<?php

namespace BossBar;

use Pvp\Main as PvP;
use Coord\Main as Coordinates;
use wmpe\wAuth;
use Air\Loader as Mystic;
use \timurkaundefined\gametimer\GameTimer as GameTimer;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\level\sound\ClickSound;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;
use pocketmine\command\{Command, CommandSender};
use pocketmine\event\inventory\{InventoryPickupItemEvent};
use pocketmine\event\player\{PlayerQuitEvent, PlayerJoinEvent, PlayerChatEvent, PlayerMoveEvent};
use pocketmine\entity\Entity;
use pocketmine\entity\EntityIds;
use pocketmine\network\mcpe\protocol\{AddActorPacket, BossEventPacket, RemoveActorPacket, SetActorDataPacket, UpdateAttributesPacket};
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;

class Main extends PluginBase implements Listener {

    public $entityRuntimeId = null;
    public $time = 0;
    public $bossbar = [];
    public static $instance;
    
    /** @var mixed|null */
    public $mystic = null;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->mystic = $this->getServer()->getPluginManager()->getPlugin("AirDrop");
        $this->entityRuntimeId = Entity::$entityCount++;
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(array($this, "SendTask")), 20);
    }

    public function onLoad(){
        self::$instance = $this;
    }

    public static function getInstance(){
        return self::$instance;
    }

    public function onJoin(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        $this->sendBossBarToPlayer($player, $this->entityRuntimeId);
        $this->setProgress($player, $this->entityRuntimeId, 0);
    }

    public function SendTask(){
        if ($this->entityRuntimeId === null) return;
        foreach($this->getServer()->getOnlinePlayers() as $player){
            if($this->time >= 11){
                $this->time = 0;
            }
            if($this->time == 0){
                $this->setProgress($player, $this->entityRuntimeId, 0);
                $this->setTitle($player, $this->entityRuntimeId);
            }
            if($this->time == 1){
                $this->setProgress($player, $this->entityRuntimeId, 0.1);
            }
            if($this->time == 2){
                $this->setProgress($player, $this->entityRuntimeId, 0.2);
            }
            if($this->time == 3){
                $this->setProgress($player, $this->entityRuntimeId, 0.3);
            }
            if($this->time == 4){
                $this->setProgress($player, $this->entityRuntimeId, 0.4);
            }
            if($this->time == 5){
                $this->setProgress($player, $this->entityRuntimeId, 0.5);
            }
            if($this->time == 6){
                $this->setProgress($player, $this->entityRuntimeId, 0.6);
            }
            if($this->time == 7){
                $this->setProgress($player, $this->entityRuntimeId, 0.7);
            }
            if($this->time == 8){
                $this->setProgress($player, $this->entityRuntimeId, 0.8);
            }
            if($this->time == 9){
                $this->setProgress($player, $this->entityRuntimeId, 0.9);
            }
            if($this->time == 10){
                $this->setProgress($player, $this->entityRuntimeId, 1);
            }

            if (isset($this->mystic->checkPlayer[strtolower($player->getName())])) {
                $this->setTitle($player, $this->entityRuntimeId);
            }
        }
        $this->time++;
    }

    public function setTitle($player, $id){
        $text = $this->getText($player);

        if (isset($this->mystic->checkPlayer[strtolower($player->getName())])) {
            $x = floor($player->getX());
            $y = floor($player->getY());
            $z = floor($player->getZ());
            $text = "§eВаши координаты§8:\n\n  §l§a{$x}§8, §a{$y}§8, §a{$z}§r";
        }

        $packet = new SetActorDataPacket();
        $packet->entityRuntimeId = $id;
        $packet->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text]];
        $packet->syncedProperties = new PropertySyncData([], []);
        $player->dataPacket($packet);
        $bpk = new BossEventPacket();
        $bpk->bossEid = $id;
        $bpk->eventType = BossEventPacket::TYPE_TITLE;
        $bpk->title = $text;
        $player->dataPacket($bpk);
    }

    public function convertTimeOptimized(int $diff) : string{
        $minutes = (int)($diff / 60);
        $remainingSeconds = (int)($diff % 60);
        if ($minutes >= 1) {
            $result = "§6" . $minutes . ' м. §6' . $remainingSeconds . ' с. ';
        } else {
            $result = "§6" . $remainingSeconds . ' с. ';
        }
        return $result !== "" ? substr($result, 0, -1) : "n/a";
    }

    public function getText($player){
        $playTime = GameTimer::getGameTime($player);
        $timePlayed = GameTimer::convertTimeOptimized($playTime);
        $items = array(
            "§r§fчастые конкурсы в нашем телеграм§7:§b ｔ.ｍｅ/ｃｒａｆｔｐｗ",
            "§r§fнаша группа телеграм§7:§a ｔ.ｍｅ/ｃｒａｆｔｐｗ",
            "§r§fнаша группа вконтакте§7:§b ｖｋ.ｃｏｍ/ｃｒａｆｔｎｗ",
            "§r§fкупить крутую привелегию§7:§a ｔ.ｍｅ/ｃｒａｆｔｓｐ",
        );
        return $items[array_rand($items)];
    }

    public function sendBossBarToPlayer($player, $id){
        $text = $this->getText($player);

        if (isset($this->mystic->checkPlayer[strtolower($player->getName())])) {
            $x = floor($player->getX());
            $y = floor($player->getY());
            $z = floor($player->getZ());
            $text = "§fВаши координаты§8:\n\n  §l§a{$x}§8, §a{$y}§8, §a{$z}§r";
        }

        $packet = new AddActorPacket();
        $packet->entityUniqueId = $id;
        $packet->entityRuntimeId = $id;
        $packet->type = EntityIds::SLIME;
        $packet->position = $player->getPosition()->asVector3()->subtract(0, 28, 0);
        $packet->motion = new Vector3(0, 0, 0);
        $packet->pitch = 0.0;
        $packet->yaw = 0.0;
        $packet->headYaw = 0.0;
        $packet->bodyYaw = 0.0;
        $packet->attributes = [];
        $packet->metadata = [Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $text], Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 0 ^ 1 << Entity::DATA_FLAG_SILENT ^ 1 << Entity::DATA_FLAG_INVISIBLE ^ 1 << Entity::DATA_FLAG_NO_AI]];
        $packet->syncedProperties = new PropertySyncData([], []);
        $packet->links = [];
        $player->dataPacket($packet);
        $bpk = new BossEventPacket();
        $bpk->bossEid = $id;
        $bpk->eventType = BossEventPacket::TYPE_SHOW;
        $bpk->title = $text;
        $bpk->healthPercent = 1.0;
        $bpk->darkenScreen = false;
        $bpk->color = 0;
        $bpk->overlay = 0;
        $player->dataPacket($bpk);
    }

    public function setProgress(Player $player, int $id, $healthPercent){
        if (isset($this->mystic->checkPlayer[strtolower($player->getName())])) {
            $healthPercent = 1;
        }

        $upk = new UpdateAttributesPacket();
        $upk->entries[] = new BossBarValues(1, 600, max(1, min([$healthPercent * 100, 100])) / 100 * 600, 'minecraft:health');
        $upk->entityRuntimeId = $id;
        $player->dataPacket($upk);
        $bpk = new BossEventPacket();
        $bpk->bossEid = $id;
        $bpk->eventType = BossEventPacket::TYPE_HEALTH_PERCENT;
        $bpk->healthPercent = $healthPercent;
        $player->dataPacket($bpk);
    }

    public function removeBossBar($player, $id){
        $text = $this->getText($player);
        $username = strtolower($player->getName());
        $pk = new RemoveActorPacket();
        $pk->entityUniqueId = $id;
        $player->dataPacket($pk);
        unset($this->bossbar[$username]);
    }
}