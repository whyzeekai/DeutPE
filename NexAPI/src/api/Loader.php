<?php

declare(strict_types=1);

namespace api;

use pocketmine\plugin\PluginBase;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\block\Block;
use pocketmine\block\BlockIds;

use pocketmine\item\Item;

use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\math\Vector3;

use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\nbt\tag\{CompoundTag, DoubleTag, FloatTag, ListTag};

use pocketmine\command\{Command, CommandSender};

use pocketmine\utils\Utils;
use pocketmine\utils\Config;

use pocketmine\scheduler\CallbackTask;

use pocketmine\entity\Entity;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;

use pocketmine\network\mcpe\protocol\AddItemEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;

use api\utils\Leaderboard;
//use api\utils\particle\FloatingTextParticle;

use \timurkaundefined\gametimer\GameTimer as GameTimer;

use api\event\{Event, Auth, Cases, Arena, PvpSystem, SphereEvent, VanillaBow, BonusCases};
use api\purechat\pchat;
use api\task\HotBar;
use api\task\UpdateMineTask;
use api\task\{ClearTask, JackNPCTask, ParticleUpdateTask, CleanupTask, GoraTask};
use api\npc\Npc;
use api\npc\Sizenpc;
use api\npc\{Killer, Miner, Skup, Reward, Pass, Alhimic, Shop, Cosmetic};
use api\vkapi\VKAPI;
use api\vkapi\VKHELPER;
use api\anticheat\Anticheat;

use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;
use Duels\Arena as DuelsArena;
use DateTime;

class Loader extends PluginBase {
    //

	public static $instance;
    public static $questsminer;
    public static $questsdonatepass;
    public static $questsalhimic;
    public static $questsbuyer;
    private static $bossEntity;

    public $duelarena = [];
    public $pg;

    public $checks = [];
    public $console = [];
    public $lastTPATimes = [];
    public $auto_totem = [];

    public $nextUpdate;
    public $nextUpdateSkup;
    public $nextRarity;
    public $nextSkup;
    private $animationStep = 0;
    public $time = 90 * 60;
    public $test = 1500;

    public static $clans;
    public static $invites = [];

    public $pvp = [];
    public $opponents = [];
    public $hotbar = [];
    private $banList = [];

    // FloatingTextParticle properties
    public $info1;
    public $info2;
    public $info3;
    public $info4;
    public $info5;
    public $info6;
    public $info7;
    public $grief1;
    public $grief2;
    public $grief3;
    public $arenanews;
    public $arena;
    public $arena1;
    public $arena2;
    public $duels;
    public $duels1;
    public $duels2;
    public $casetext;
    public $casetext1;
    public $flytextcase1;
    public $flytextcase2;
    public $flytextcase3;
    public $flytextcase4;
    public $areashop1;
    public $areashop2;
    public $areashop3;
    public $areashop4;
    public $areashop5;
    
    // Config properties
    public $ranks;
    public $sub;
    public $device;
    public $timeranks;
    public $titul;
    public $auth;
    public $positions;
    public $killer;
    public $rep;
    public $lang;
    public $rewards;
    public $helpers;
    public $reportsConfig;
    public $kits;
    public $cosmetic;
    public $blacklist;
    public $goratime;
    public $bonuspatrik;
    public $grant;
    public $topreward;
    public $homes;
    public $warps;
    
    // Database properties
    public $db;
    public $dbc;
    
    // Permissions
    public $perms = [];
    
    // Plugin references
    public $marry;
    public $rg;
    
    // Additional FloatingTextParticle properties
    public $promotext1;
    public $promotext2;
    public $promotext3;
    public $passtext1;
    public $passtext2;
    public $passtext3;
    public $automine;
    public $automine1;
    public $automine2;
    public $automine3;
    public $grief;

    const AVAILABLE_OS = ["Unknown", "§r", "§r⩚", "macOS", "FireOS", "GearVR", "HoloLens", "§r⩓", "§r⩓", "Dedicated", "Orbis", "NX"];

    const NOPERMS = "§6» §fУ вас нету §cправ§r на эту команду, купите донат §bｃｒａｆｔ-ｐｅ.ｒｕ§r";
    const TEST = "§6» §rНедоступно, данная функция находится в §aтестирование§r!";
    const RECORDONLINE = "３５";

    const WEBSITE = "§bｃｒａｆｔ-ｐｅ.ｒｕ§r";
    const VK = "§r§6@ｃｒａｆｔｎｗ§r";
    const TG = "§r§6@ｃｒａｆｔｐｗ§r";

    const SERVERNAME = "§l§eＣｒａｆｔ§6ＰＥ§r";
    const SERVERNAME_FORMAT = "§l§eＣｒａｆｔ§6ＰＥ";

	public static function getInstance(): self {
		return self::$instance;
	}

	public function onEnable(): void {
		self::$instance = $this;
		Server::getInstance()->getPluginManager()->registerEvents(new Event($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Cases($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Arena($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new PvpSystem($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new SphereEvent($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Npc($this), $this);        
        Server::getInstance()->getPluginManager()->registerEvents(new Sizenpc($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Auth($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Miner($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Killer($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Skup($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Reward($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Cosmetic($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Pass($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Alhimic($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Shop($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new VanillaBow($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new Anticheat($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new BonusCases($this), $this);
        Server::getInstance()->getPluginManager()->registerEvents(new pchat($this), $this);

        // Create necessary directories
        @mkdir(Loader::getInstance()->getDataFolder() . "db", 0755, true);
        @mkdir(Loader::getInstance()->getDataFolder() . "vaip", 0755, true);

        Loader::getInstance()->ranks = new Config(Loader::getInstance()->getDataFolder() . "db/ranks.yml", Config::YAML);
        Loader::getInstance()->sub = new Config(Loader::getInstance()->getDataFolder() . "db/subscriptions.yml", Config::YAML);
        Loader::getInstance()->device = new Config(Loader::getInstance()->getDataFolder() . "db/device.yml", Config::YAML);
        Loader::getInstance()->timeranks = new Config(Loader::getInstance()->getDataFolder() . "db/timeranks.yml", Config::YAML);
        Loader::getInstance()->titul = new Config(Loader::getInstance()->getDataFolder() . "db/function.yml", Config::YAML);
        Loader::getInstance()->auth = new Config(Loader::getInstance()->getDataFolder() . "db/accounts.yml", Config::YAML);
        Loader::getInstance()->positions = new Config(Loader::getInstance()->getDataFolder() . "db/positions.yml", Config::YAML);
        Loader::getInstance()->killer = new Config(Loader::getInstance()->getDataFolder() . "vaip/killer.yml", Config::YAML);
        Loader::getInstance()->rep = new Config(Loader::getInstance()->getDataFolder() . "vaip/rep.yml", Config::YAML);
        Loader::getInstance()->lang = new Config(Loader::getInstance()->getDataFolder() . "db/language.yml", Config::YAML);
        Loader::getInstance()->rewards = new Config(Loader::getInstance()->getDataFolder() . "vaip/rewards.yml", Config::YAML);
        Loader::getInstance()->helpers = new Config(Loader::getInstance()->getDataFolder() . "db/helpers.yml", Config::YAML);
        Loader::getInstance()->reportsConfig = new Config(Loader::getInstance()->getDataFolder() . "vaip/reports.yml", Config::YAML);
        Loader::getInstance()->kits = new Config(Loader::getInstance()->getDataFolder() . "vaip/kits.yml", Config::YAML);
        Loader::getInstance()->cosmetic = new Config(Loader::getInstance()->getDataFolder() . "vaip/cosmetic.yml", Config::YAML);
        Loader::getInstance()->blacklist = new Config(Loader::getInstance()->getDataFolder() . "db/blacklist.yml", Config::YAML);
        Loader::getInstance()->goratime = new Config(Loader::getInstance()->getDataFolder() . "vaip/goratime.yml", Config::YAML);
        Loader::getInstance()->bonuspatrik = new Config(Loader::getInstance()->getDataFolder() . "vaip/bonuspatrik.yml", Config::YAML);
        Loader::getInstance()->grant = new Config(Loader::getInstance()->getDataFolder() . "vaip/grant.yml", Config::YAML);
        Loader::getInstance()->db = new \SQLite3(Loader::getInstance()->getDataFolder() . "datebase.db");
        Loader::getInstance()->db->query("CREATE TABLE IF NOT EXISTS datebase(name TEXT NOT NULL, money INTEGER NOT NULL, lvl INTEGER NOT NULL, exp INTEGER NOT NULL, kills INTEGER NOT NULL, death INTEGER NOT NULL, rub INTEGER NOT NULL, key INTEGER NOT NULL, lvlminer INTEGER NOT NULL, expminer INTEGER NOT NULL, lvls INTEGER NOT NULL, topdonater INTEGER NOT NULL, pass INTEGER NOT NULL, win INTEGER NOT NULL)"
        );

        Loader::getInstance()->dbc = new \SQLite3(Loader::getInstance()->getDataFolder() . "cases.db");
        Loader::getInstance()->dbc->query("CREATE TABLE IF NOT EXISTS cases(name TEXT NOT NULL, donatecase INTEGER NOT NULL, prefixcase INTEGER NOT NULL, newyearcase INTEGER NOT NULL, moneycase INTEGER NOT NULL)");
        
        self::$questsminer = new Config($this->getDataFolder().'vaip/questsminer.yml', Config::YAML);
        self::$questsdonatepass = new Config($this->getDataFolder().'vaip/questsdonatepass.yml', Config::YAML);
        self::$clans = new Config($this->getDataFolder() . "vaip/clans.yml", Config::YAML);
        self::$questsalhimic = new Config($this->getDataFolder(). "vaip/questsalhimic.yml", Config::YAML);
        self::$questsbuyer = new Config($this->getDataFolder(). "vaip/questsbuyer.yml", Config::YAML);

        $this->topreward = new Config($this->getDataFolder() . "vaip/topreward.yml", Config::YAML);
        $this->homes = new Config($this->getDataFolder() . "vaip/homes.yml", Config::YAML);
        $this->warps = new Config($this->getDataFolder() . "vaip/warps.yml", Config::YAML);

        Server::getInstance()->getCommandMap()->registerAll("clan", [
            new \api\event\ClanCommand
        ]);

        Loader::getInstance()->marry = Server::getInstance()->getPluginManager()->getPlugin("Marry");
        Loader::getInstance()->rg = Server::getInstance()->getPluginManager()->getPlugin("REGION");

        $this->getScheduler()->scheduleRepeatingTask(new HotBar($this), 20);

        Loader::getInstance()->promotext1 = new FloatingTextParticle(new Vector3(26.5019, 36.6, -30.4965), "");
        Loader::getInstance()->promotext2 = new FloatingTextParticle(new Vector3(26.5019, 36.3, -30.4965), "", "");
        Loader::getInstance()->promotext3 = new FloatingTextParticle(new Vector3(26.5019, 36, -30.4965), "", "");

        Loader::getInstance()->passtext1 = new FloatingTextParticle(new Vector3(42.4937, 38.5, 0.4962), "");
        Loader::getInstance()->passtext2 = new FloatingTextParticle(new Vector3(42.4937, 38.2, 0.4962), "", "");
        Loader::getInstance()->passtext3 = new FloatingTextParticle(new Vector3(42.4937, 37.9, 0.4962), "", "");

        Loader::getInstance()->automine = new FloatingTextParticle(new Vector3(18.472, 36.6, 6.5077), "");
        Loader::getInstance()->automine1 = new FloatingTextParticle(new Vector3(18.472, 36.3, 6.5077), "", "");
        Loader::getInstance()->automine2 = new FloatingTextParticle(new Vector3(18.472, 36, 6.5077), "", "");
        Loader::getInstance()->automine3 = new FloatingTextParticle(new Vector3(18.472, 35.7, 6.5077), "", "");

        Loader::getInstance()->grief = new FloatingTextParticle(new Vector3(20.5296, 36.9, -12.5939), "");
        Loader::getInstance()->grief1 = new FloatingTextParticle(new Vector3(20.5296, 36.6, -12.5939), "", "");
        Loader::getInstance()->grief2 = new FloatingTextParticle(new Vector3(20.5296, 36.3, -12.5939), "", "");
        Loader::getInstance()->grief3 = new FloatingTextParticle(new Vector3(20.5296, 36, -12.5939), "", "");

        Loader::getInstance()->arenanews = new FloatingTextParticle(new Vector3(15.5189, 38.9, -33.5258), "");
        Loader::getInstance()->arena = new FloatingTextParticle(new Vector3(15.5189, 38.6, -33.5258), "");
        Loader::getInstance()->arena1 = new FloatingTextParticle(new Vector3(15.5189, 38.3, -33.5258), "", "");
        Loader::getInstance()->arena2 = new FloatingTextParticle(new Vector3(15.5189, 38, -33.5258), "", "");

        Loader::getInstance()->duels = new FloatingTextParticle(new Vector3(9.53, 38.9, -33.5623), "");
        Loader::getInstance()->duels1 = new FloatingTextParticle(new Vector3(9.53, 38.6, -33.5623), "", "");
        Loader::getInstance()->duels2 = new FloatingTextParticle(new Vector3(9.53, 38.3, -33.5623), "", "");

        Loader::getInstance()->casetext = new FloatingTextParticle(new Vector3(49.486, 37.7, -12.4944), "");
        Loader::getInstance()->casetext1 = new FloatingTextParticle(new Vector3(49.486, 37.4, -12.4944), "", "");

        Loader::getInstance()->info1 = new FloatingTextParticle(new Vector3(-0.6014, 40.6, -12.5312), "");
        Loader::getInstance()->info2 = new FloatingTextParticle(new Vector3(-0.6014, 40.3, -12.5312), "", "");
        Loader::getInstance()->info3 = new FloatingTextParticle(new Vector3(-0.6014, 40, -12.5312), "", "");
        Loader::getInstance()->info4 = new FloatingTextParticle(new Vector3(-0.6014, 39.7, -12.5312), "", "");
        Loader::getInstance()->info5 = new FloatingTextParticle(new Vector3(-0.6014, 39.3, -12.5312), "", "");
        Loader::getInstance()->info6 = new FloatingTextParticle(new Vector3(-0.6014, 39, -12.5312), "", "");
        Loader::getInstance()->info7 = new FloatingTextParticle(new Vector3(-0.6014, 38.5, -12.5312), "", "");

        Loader::getInstance()->flytextcase1 = new FloatingTextParticle(new Vector3(-5.5044, 34.5, -29.5195), "");
        Loader::getInstance()->flytextcase2 = new FloatingTextParticle(new Vector3(-11.5455, 34.5, -29.5807), "");
        Loader::getInstance()->flytextcase3 = new FloatingTextParticle(new Vector3(-8.4724, 34.5, -32.543), "");
        Loader::getInstance()->flytextcase4 = new FloatingTextParticle(new Vector3(-8.5549, 34.5, -26.4795), "");

        Loader::getInstance()->areashop1 = new FloatingTextParticle(new Vector3(39.5098, 36.7, -16.5114), "");
        Loader::getInstance()->areashop2 = new FloatingTextParticle(new Vector3(39.5098, 36.4, -16.5114), "");
        Loader::getInstance()->areashop3 = new FloatingTextParticle(new Vector3(39.5098, 36.1, -16.5114), "");
        Loader::getInstance()->areashop4 = new FloatingTextParticle(new Vector3(39.5098, 35.8, -16.5114), "");
        Loader::getInstance()->areashop5 = new FloatingTextParticle(new Vector3(39.5098, 35.5, -16.5114), "");


        $this->getScheduler()->scheduleRepeatingTask(new ClearTask(180), 20);
        $this->getScheduler()->scheduleRepeatingTask(new GoraTask($this), 20 * 2);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"updateMineText"]), 20 * 60);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"updateText"]), 20 * 180);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"restart"]),20);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"Talisman"]), 20 * 7);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"Sphere"]), 20 * 7);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"SkullEffect"]), 20 * 4);
        $this->getScheduler()->scheduleRepeatingTask(new UpdateMineTask($this), 20 * 900);
        $this->getScheduler()->scheduleRepeatingTask(new JackNPCTask($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new CleanupTask($this), 20 * 60 * 60 * 24);
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"infoServer"]), 20 * 120);

        Server::getInstance()->getNetwork()->setName("". self::SERVERNAME_FORMAT . "§r");

        Server::getInstance()->loadLevel("world");
        Server::getInstance()->loadLevel("spawn");
        Server::getInstance()->loadLevel("arena");

        $center = new Position(49.486, 35.5, -12.4944, Server::getInstance()->getDefaultLevel());
        $radius = 5;
        $task = new ParticleUpdateTask($center, $radius);
        $this->getScheduler()->scheduleRepeatingTask($task, 5);

	}

    public function infoServer() {
        $website = self::WEBSITE;
        $timeLeft = $this->nextUpdate - time();
        $minetime = Loader::getInstance()->parseTime($timeLeft);
            $messages = [
            "§f樧 §f§lХочешь быть§6 крутым§f на сервере?\n§f樧 §f§lПокупай Донат§f §l§bлегенда§f§l навсегда всеголишь за§a 730руб\n§f樧 §f§lКупить донат можно в нашем тг§7:§b @ｃｒａｆｔｐｗ",
            "§f樧 §f§lХочешь себе красивый§a Донат\n§f§f樧 §f§lПокупай 5 §a§l§f по низкой цене§7 -§d 120руб\n§f§f樧 §f§lКупить можно в нашем тг§a§l @ｃｒａｆｔｓｐe",
            "§f橓 §f§lМы ищем активных §c§lЮтуберов§f§l на наш сервер\n§f橓 §f§lВся Информацыя в нашей группе ВК§7:§a @ｃｒａｆｔｎｗ",
            "§f樑 §f§lМы ищем активных §a§lХелперов§f§l на наш сервер\n§f樑 §f§lВся Информацыя в нашей группе ВК§7:§a @ｃｒａｆｔｎｗ",
            "§f樧 §f§lХочешь быть§5 Повелителем§f на сервере?\n§f樧 §f§lПокупай Донат§f §l§сВладыка§f§l навсегда всеголишь за§a 2000руб\n§f樧 §f§lКупить донат можно на нашем тг§7:§a @ｃｒａｆｔｐｗ",
        ];
        Server::getInstance()->broadcastMessage($messages[array_rand($messages)]);
    }

    public function SkullEffect() {
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            $helmet = $player->getArmorInventory()->getHelmet();
            if($helmet !== null && $helmet->getCustomName() == "§r§l§dГОЛОВА ДРАКОНА") { 
                $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 0)); 
                $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 1));
                $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 1));
            }

            if($helmet !== null && $helmet->getCustomName() == "§r§l§bГОЛОВА СТИВА") {
                $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 0)); 
                $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 2));
                $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 1));
                $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
            }
        }
    }


    public function Sphere(){
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            switch ($player->getOffhandInventory()->getItemInOffHand()->getCustomName()) {

                case '§r§l§bШАР ЗЕВСА': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(8), 20 * 8, 1));
                break;

                case '§r§l§dШАР ДРАКУЛЫ': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
                break;

                case '§r§l§eШАР АИДА': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(8), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
                break;

                case '§r§l§cШАР МОРОЗА': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
                break;

                case '§r§l§aШАР ПАТРИКА': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(3), 20 * 8, 0));
                break;
            }
        }
    }

    public function Talisman(){
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            switch ($player->getOffhandInventory()->getItemInOffHand()->getCustomName()) {

                case '§r§l§7§aТАЛИСМАН ЧЕРЕПАХИ §7[§e✩§7]': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(11), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 1));
                break;

                case '§r§l§7§6ТАЛИСМАН ГЕПАРДА §7[§e✩✩§7]': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 0)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 2));
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(19), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
                break;

                case '§r§l§7§cТАЛИСМАН БЕРСЕРКА §7[§e✩✩✩§7]': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 2)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(12), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(20), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                break;

                case '§r§l§7§eТАЛИСМАН ГРИФОНА §7[§e✩✩✩✩§7]': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(12), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(17), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(20), 20 * 8, 0));
                break;

                case '§r§l§7§dТАЛИСМАН ДРАКОНА §7[§e✩✩✩✩✩§7]': 
                    $player->addEffect(new EffectInstance(Effect::getEffect(5), 20 * 8, 1)); 
                    $player->addEffect(new EffectInstance(Effect::getEffect(1), 20 * 8, 1));
                    $player->addEffect(new EffectInstance(Effect::getEffect(10), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(12), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(20), 20 * 8, 0));
                    $player->addEffect(new EffectInstance(Effect::getEffect(2), 20 * 8, 0));
                break;
            }
        }
    }

    public function updateMine() {
        $level = Server::getInstance()->getDefaultLevel();
        $rarity = isset($this->nextRarity) ? $this->nextRarity : mt_rand(1, 100);
        $blocks = Loader::getInstance()->getBlocksByRarity($rarity);
        $rarityName = Loader::getInstance()->getRarityName($rarity);

        if(count(Server::getInstance()->getOnlinePlayers()) < 1){
            Loader::getInstance()->nextUpdate = time() + 900;
            $this->nextRarity = mt_rand(1, 100);
            Server::getInstance()->broadcastMessage("\n\n§7§r§l§bАвто§7-§bШахта§r, не обновилась!\n§rДля обновления §l§bАвто§7-§bШахты§r, нужен онлайн §a5 игроков§r!\n\n");
            return;
        }

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->getPosition()->x >= 9 && $player->getPosition()->x <= 28 &&
                $player->getPosition()->y >= 23 && $player->getPosition()->y <= 34 &&
                $player->getPosition()->z >= 9 && $player->getPosition()->z <= 28) {
                $level = Server::getInstance()->getLevelByName("spawn");
                Loader::getInstance()->teleportPlayer($player, new Position(18, 35, 4, $level));
            }
        }

        for ($x = 9; $x <= 28; $x++) {
            for ($y = 23; $y <= 34; $y++) {
                for ($z = 9; $z <= 28; $z++) {
                    $level->setBlock(new \pocketmine\math\Vector3($x, $y, $z), Block::get(0), false, false);
                }
            }
        }

        Loader::getInstance()->automine1->setTitle("§rПодождите авто-шахта, обновляется!");
        Loader::getInstance()->automine2->setTitle("§r§8[§rОсталось:§a пару секунд.§r§8]");
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->automine1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->automine2);       


        $this->startAnimation($level, $blocks, $rarityName);
    }

    private function startAnimation($level, $blocks, $rarityName) {
        if ($this->animationStep < 12) { 
            for ($x = 9; $x <= 28; $x++) {
                for ($z = 9; $z <= 28; $z++) {
                    $y = 23 + $this->animationStep;
                    $block = $blocks[array_rand($blocks)];
                    $level->setBlock(new \pocketmine\math\Vector3($x, $y, $z), $block, false, false);
                }
            }

            $this->animationStep++; 

            $this->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\CallbackTask(function () use ($level, $blocks, $rarityName) {
                $this->startAnimation($level, $blocks, $rarityName);
            }), 6);

            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                if ($player->getPosition()->x >= 9 && $player->getPosition()->x <= 28 &&
                    $player->getPosition()->y >= 23 && $player->getPosition()->y <= 34 &&
                    $player->getPosition()->z >= 9 && $player->getPosition()->z <= 28) {
                    $level = Server::getInstance()->getLevelByName("spawn");
                    Loader::getInstance()->teleportPlayer($player, new Position(18, 35, 4, $level));
                    $player->sendMessage("§l§6➛ §rПодождите §l§bАвто§7-§bШахта§r, обновляется!");
                }
            }
        } else {
            Server::getInstance()->broadcastMessage("\n\n§l§e          §r§7 §l§eАВТО ШАХТА §r§7         \n§r       §7• §rШахта была обновлена! §7•        \n§r      §7• §rРедкость шахты: §a{$rarityName} §7•     \n §7• §rБыстрая телепортация к шахте: §e/mine §7• §r\n\n");
            Loader::getInstance()->nextUpdate = time() + 900;
            Loader::getInstance()->updateMineText();
            $this->nextRarity = mt_rand(1, 100);
            $this->animationStep = 0;
        }
    }

    public function getRarityName($rarity) {
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            $language = Loader::getInstance()->getLang($player);

            if ($rarity <= 50) {
                return $language === "rus" ? "§l§aОбычная§r" : "§l§aCommon§r";
            } elseif ($rarity <= 80) {
                return $language === "rus" ? "§l§bРедкая§r" : "§l§bRare§r";
            } elseif ($rarity <= 95) {
                return $language === "rus" ? "§l§dЭпическая§r" : "§l§dEpic§r";
            } else {
                return $language === "rus" ? "§l§6Легендарная§r" : "§l§6Legendary§r";
            }
        }
    }

    public function getBlocksByRarity($rarity) {
        if ($rarity <= 50) {
            return[
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(16),
                Block::get(16),
                Block::get(16),
                Block::get(15),
                Block::get(15),
                Block::get(15),
                Block::get(14),
                Block::get(14),
                Block::get(73),
                Block::get(73),
                Block::get(21),
                Block::get(21)
            ];
        } elseif ($rarity <= 80) {
            return[
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(16),
                Block::get(16),
                Block::get(16),
                Block::get(15),
                Block::get(15),
                Block::get(15),
                Block::get(14),
                Block::get(14),
                Block::get(73),
                Block::get(73),
                Block::get(21),
                Block::get(21)
            ];
        } elseif ($rarity <= 95) {
            return[
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(16),
                Block::get(16),
                Block::get(16),
                Block::get(15),
                Block::get(15),
                Block::get(15),
                Block::get(14),
                Block::get(14),
                Block::get(73),
                Block::get(73),
                Block::get(21),
                Block::get(21),
                Block::get(56),
                Block::get(129),
                Block::get(173)
            ];
        } else {
            return[
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(1),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(4),
                Block::get(16),
                Block::get(16),
                Block::get(16),
                Block::get(15),
                Block::get(15),
                Block::get(15),
                Block::get(14),
                Block::get(14),
                Block::get(73),
                Block::get(73),
                Block::get(21),
                Block::get(21),
                Block::get(56),
                Block::get(129),
                Block::get(173),
                Block::get(129),
                Block::get(133),
                Block::get(42),
                Block::get(57),
            ];
        }
    }

    public function restart(){
        if(Loader::getInstance()->time == 5400){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a1 час 30 минут, §rперезагрузка сервера!");
        }

        if(Loader::getInstance()->time == 4800){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a1 час 20 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 4200){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a1 час 10 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 3600){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a1 час §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 3000){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a50 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 2400){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a40 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 1800){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a30 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 1200){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a20 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 600){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a10 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 300){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a5 минут, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 180){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a3 минуты, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 120){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a2 минуты, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 60){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a1 минуту, §rперезагрузка сервера!");
        }
        if(Loader::getInstance()->time == 30){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a30 секунд, §rперезагрузка сервера!");
        }

        if(Loader::getInstance()->time == 20){
            Server::getInstance()->broadcastMessage("§r§f §rЧерез §a20 секунд, §rперезагрузка сервера!");
        }

        if(Loader::getInstance()->time > 0){
            if(Loader::getInstance()->time < 10){
                Server::getInstance()->broadcastTitle("§l§bРЕСТАРТ", "§r§f §rБудет через §a".Loader::getInstance()->time."§r секунд! §r§f §r");            
            }

            if(Loader::getInstance()->time < 5){
                foreach(Server::getInstance()->getOnlinePlayers() as $player){
                    if ($player->isCreative()) {
                        $player->setGamemode(0);
                    }
                }     
            }
            Loader::getInstance()->time--;
        }else{
            Loader::getInstance()->transfer();
            Server::getInstance()->getLogger()->info("§f§lПерезагрузка сервера§r §7(NexVILL GRIEF)");
            Server::getInstance()->shutdown();
        }
    }

    public function transfer(){
        foreach(Server::getInstance()->getOnlinePlayers() as $p){
            $p->transfer("mwix-pe.ru", 19132);
        }
    }

    public function getLang($player) {
        $lang = Loader::getInstance()->lang->get($player->getName());
        return $lang ?? "rus";
    }

    public function transformText($text) {
        $result = '';
        for ($i = 0; $i < mb_strlen($text); $i++) {
            $char = mb_substr($text, $i, 1);
            $result .= $this->translitMap[$char] ?? $char; // Заменяем или оставляем как есть
        }
        return $result;
    }

    public function onCommand(CommandSender $player, Command $command, $label, array $args){
        $name = mb_strtolower($player->getName());
        $timeday = date("d.m.y");
        $language = Loader::getInstance()->getLang($player);
        $auth = Loader::getInstance()->auth->get($name);

        switch ($command->getName()) {

            case "back":
                if ($player->hasPermission("api.cmd.back")) {
                    global $xcordd, $ycordd, $zcordd, $worldd;
                    if (isset($xcordd[$player->getName()])) {
                        $player->sendMessage("§l§aBACK §8:: §rТелепортация...");

                        $world = $worldd[$player->getName()];

                        if ($world->getName() === "proxyworld" || $world->getName() === "arena") {
                            $player->sendMessage("§l§aBACK §8:: §rНа арену нельзя §aтелепортироваться§r!");
                            return; 
                        }

                        $pos = new Position(
                            $xcordd[$player->getName()] + rand(0, 55) / 100,
                            $ycordd[$player->getName()] + 0.50,
                            $zcordd[$player->getName()] + rand(0, 55) / 100,
                            $world 
                        );

                        for ($i = 0; $i < 2; $i++) {  
                            $pcale = rand(0, 3);
                            $particlePos = new Position(
                                $xcordd[$player->getName()] + rand(0, 55) / 100,
                                $ycordd[$player->getName()] + (2.50 - $i),
                                $zcordd[$player->getName()] + rand(0, 55) / 100,
                                $world 
                            );
                        }
                        $player->teleport($pos);
                        unset($xcordd[$player->getName()], $ycordd[$player->getName()], $zcordd[$player->getName()], $worldd[$player->getName()]);
                    } else {
                        $player->sendMessage("§l§aBACK §8:: §rТочка возврата не найдена!");
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'vanish':
                if ($player->hasPermission("api.cmd.vanish")) {
                    global $vanishlist;
                    $playerName = $player->getName();

                    if (!isset($vanishlist[$playerName])) {
                        $player->addEffect(new EffectInstance(Effect::getEffect(14), 20 * 999999, 3, false));
                        $vanishlist[$playerName] = true;
                        $player->sendMessage("§l§dVANISH §8:: §rВы включили §l§aНевидимость§r");
                        $player->addTitle("§r§l§aНевидимость§r", "§rВы включили невидимость!");
                    } else {
                        $player->removeEffect(14);
                        unset($vanishlist[$playerName]);
                        $player->sendMessage("§l§dVANISH §8:: §rВы выключили §l§aНевидимость§r");
                        $player->addTitle("§r§l§aНевидимость§r", "§rВы выключили невидимость!");
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'device':
                $playerName = strtolower($player->getName());
                $deviceModel = $player->getDeviceModel();

                if (count($args) !== 1) {
                    return $player->sendMessage("§l§7► §rИспользование §a/device on/off");
                }

                $isDeviceEnabled = $this->device->exists($playerName);
                $action = $args[0];

                if ($action === "on") {
                    if (!$isDeviceEnabled) {
                        $this->device->set($playerName, $deviceModel);
                        $this->device->save();
                        $player->sendMessage("§l§aDEVICE §8:: §rЗащита по устройству §aвключена");
                    } else {
                        $player->sendMessage("§l§aDEVICE §8:: §rЗащита по устройству уже §aвключена");
                    }
                } elseif ($action === "off") {
                    if ($isDeviceEnabled) {
                        $this->device->remove($playerName);
                        $this->device->save();
                        $player->sendMessage("§l§aDEVICE §8:: §rЗащита по устройству §cвыключена");
                    } else {
                        $player->sendMessage("§l§aDEVICE §8:: §rЗащита по устройству уже §cвыключена");
                    }
                } else {
                    return $player->sendMessage("§l§aDEVICE §8:: §rНедопустимый аргумент. Используйте §a/device on/off");
                }
            break;

            case 'repairall':
                if ($player->hasPermission("api.cmd.repairall")) {
                    $playerName = $player->getName();
                    $cooldown = 600;
                    $lastUsage = Loader::getInstance()->getCooldown($playerName);
                    if ($lastUsage !== null && (time() - $lastUsage) < $cooldown) {
                        $remainingTime = $cooldown - (time() - $lastUsage);
                        $player->sendMessage("§l§cКД §8:: §rВы уже использовали §aкоманду§r, нужно подождать §a". Loader::getInstance()->parseTime($remainingTime));
                        return true;
                    }

                    $inventory = $player->getInventory();
                    $contents = $inventory->getContents();
                    foreach($contents as $slot => $item){
                        if($item instanceof Item){
                            $item->setDamage(0);
                            $inventory->setItem($slot, $item);
                            $player->sendMessage("§l§7► §rПредметы и инветаре были §aпочинены!");
                            Loader::getInstance()->setCooldown($playerName, time());
                        }
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'say':
                $playerName = $player->getName();
                $cooldown = 60;
                $lastUsage = Loader::getInstance()->getCooldown($playerName);

                if ($lastUsage !== null && (time() - $lastUsage) < $cooldown) {
                    $remainingTime = $cooldown - (time() - $lastUsage);
                    $player->sendMessage("§l§cКД §8:: §rДля отправки нового §aсообщения§r, нужно подождать §a0м. {$remainingTime}c.");
                    return true;
                }

                if (count($args) == 0) {
                    $player->sendMessage("§l§7► §rИспользование §a/say (Сообщение) - §7(Цена 10,000$ за отправку сообщение)");
                    return true;
                }

                $message = implode(" ", $args);

                if (!$player->hasPermission("api.english.allow") && preg_match('/[A-Za-z]/', $message)) {
                    $player->sendMessage("§l§cОШИБКА §8:: §rСообщение не должно содержать §aанглийские буквы§r!");
                    return true;
                }

                if (preg_match('/[§&][0-9a-fk-or]/i', $message)) {
                    $player->sendMessage("§l§cОШИБКА §8:: §rНельзя отправить сообщение с §aцветом§r!");
                    return true;
                }

                if (Loader::getInstance()->getMoney($player) >= 10000) {
                    Loader::getInstance()->remMoney($player, 10000);
                    Server::getInstance()->broadcastMessage("\n§l§6| §rОбъявление от игрока §a{$playerName}§r!\n§l§6| §r§f" . $message);
                    Loader::getInstance()->setCooldown($playerName, time());
                } else {
                    $player->sendMessage("§l§cОШИБКА §8:: §rДля отправки §aобъявление§r нужно §a10,000§2$");
                }
            break;

            case "cp":
                if (count($args) < 2) {
                    $player->sendMessage("§6»§rИспользование §a/cp (старый пароль) (новый пароль)");
                    return true;
                }

                $oldPassword = $args[0];
                $newPassword = $args[1];
                $name = mb_strtolower($player->getName());

                if ($auth["password"] === $oldPassword) {
                    $m = [
                        "password" => $newPassword,
                        "ip" => $player->getAddress(),
                        "cid" => $player->getClientId()
                    ];

                    Loader::getInstance()->auth->set($name, $m);
                    Loader::getInstance()->auth->save();
                    $player->sendMessage("§6»§rВы §aуспешно §rсменили свой пароль, ваш новый пароль §a{$newPassword}§r");
                } else {
                    $player->sendMessage("§c§l► §rВы ввели §cневерный §rстарый пароль!");
                }
                return true;
            break;

            case 's':
                if ($player->hasPermission("api.cmd.s")) {
                    if (count($args) == 0) {
                        $player->sendMessage("§l§7► §rИспользование §a/s (Сообщение)");
                        return true;
                    }
                    Server::getInstance()->broadcastMessage("§fСЕРВЕР§7: §a" . implode(" ", $args));
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'sub':
                if (!Loader::getInstance()->sub->exists($player->getName())) {
                    $player->sendMessage("§l§aSUB §8:: §rУ вас нету §aподписки§r, купить можно на " . self::WEBSITE. "!");
                    return true;
                }

                if($player->getGamemode() === Player::CREATIVE) {
                    $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                    return true;
                }

                Loader::getInstance()->openMenuSUB($player);
            break;

            case 'kits':
                /*$playTime = GameTimer::getGameTime($player);
                $requiredPlayTime = 900;

                if($playTime < $requiredPlayTime){
                    $player->sendMessage("§6»§rЧтобы открыть меню §aнаборов§r, нужно наиграть §a". Loader::getInstance()->parseTime($requiredPlayTime) ."§r, §rвы наиграли§7:§a ". Loader::getInstance()->parseTime($playTime) . "§r");
                    $player->getLevel()->addSound((new \pocketmine\level\sound\FizzSound($player)), [$player]);
                    return;
                }
                */

                if($player->getGamemode() === Player::CREATIVE) {
                    $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                    return true;
                }

                $chest = ChestAPI::getInstance()->openChest($player, [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    21 => Item::get(218, 4, 1)->setCustomName("§rНабор §e§lИГРОКА§r (#9001)")->setLore(["\n§rПрава§7:§r §l§aДОСТУПНО\n\n§rРедоксть§7: §l§aCAMMON\n\n§rКД: §l§a3ч. 0м."]),
                    22 => Item::get(218, 2, 1)->setCustomName("§rНабор §d§lКРОНОСА§r (#9002")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.kronos") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§aCAMMON\n\n§rКД: §l§a3ч. 0м."]),
                    23 => Item::get(218, 1, 1)->setCustomName("§rНабор §6§lАРИСТОКРАТА§r (#9003)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.arist") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§9RARY\n\n§rКД: §l§a6ч. 0м."]),
                    29 => Item::get(218, 14, 1)->setCustomName("§rНабор §l§cЛЮЦИФЕРА (#9004)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.luc") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§9RARY\n\n§rКД: §l§a6ч. 0м."]),
                    30 => Item::get(218, 2, 1)->setCustomName("§rНабор §l§dКОРОЛЯ§r (#9005)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.korol") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§dEPIC\n\n§rКД: §l§a12ч. 0м."]),
                    31 => Item::get(218, 3, 1)->setCustomName("§rНабор §l§bЦАРЯ§r (#9006)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.king") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§6LEGENDARY\n\n§rКД: §l§a12ч. 0м."]),
                    32 => Item::get(218, 14, 1)->setCustomName("§rНабор §l§cКНЯЗЯ§r (#9007)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.knayz") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§6LEGENDARY\n\n§rКД: §l§a1д. 0ч. 0м."]),
                    33 => Item::get(218, 5, 1)->setCustomName("§rНабор §l§aПАТРИКА§r (#9008)")->setLore(["\n§rПрава§7:§r " . ($player->hasPermission("api.kit.patrik") ? "§l§aДОСТУПНО" : "§l§cНЕДОСТУПНО"). "\n\n§rРедоксть§7: §l§dMYSTIC\n\n§rКД: §l§a2д. 0ч. 0м."]),
                ], "§a§lНАБОРЫ §8:: §rВыберите §aнабор§r и выживайте!", ChestAPI::DOUBLE_CHEST);
            break;

            case 'rtp':
                $chest = ChestAPI::getInstance()->openChest($player, [
                    "0-53" => Item::get(102),
                    "10-43" => Item::get(0),
                    20 => Item::get(2, 0, 1)->setCustomName("§r§l§aРАНДОМНАЯ ТЕЛЕПОТРАЦИЯ\n\n§rСтатус§7: §aДоступен\n\n§rРадиус§7: §a1500блоков.\n\n§7Нажмите дважды, для телепортации!"),
                    22 => Item::get(2, 0, 1)->setCustomName("§r§l§aДАЛЕКАЯ РАНДОМНАЯ ТЕЛЕПОТРАЦИЯ\n\n§rСтатус§7: " . ($player->hasPermission("api.rtp") ? "§aДоступен" : "§cНедоступен\n§rДоступно с привилегии §l§dКронос§r") . "\n\n§rРадиус§7: §a3000блоков.\n\n§7Нажмите дважды, для телепортации!"),
                    24 => Item::get(2, 0, 1)->setCustomName("§r§l§aТЕЛЕПОРТАЦИЯ РЯДОМ С ИГРОКОМ\n\n§rСтатус§7: " . ($player->hasPermission("api.rtp.near") ? "§aДоступен" : "§cНедоступен\n§rДоступно с привилегии §l§cЛюцифер§r"). "\n\n§rРадиус§7: §a50-80блоков.\n\n§7Нажмите дважды, для телепортации!"),
                ], "§a§lRTP §8:: §rРандомная телепортация!", ChestAPI::DOUBLE_CHEST);
            break;

            case 'patrik':
                if ($player->hasPermission("api.kit.patrik")) {
                    if($player->getGamemode() === Player::CREATIVE) {
                        $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                        return true;
                    }
                    if (!Loader::getInstance()->bonuspatrik->exists($name) or Loader::getInstance()->bonuspatrik->get($name) < time()) {
                        Loader::getInstance()->addPrefixKey($player, 1);
                        Loader::getInstance()->addRub($player, 100);
                        $player->sendMessage("§l§aБОНУС §8:: §rВы получили бонус патрика§7: §r§l§bПрефикс§7-§bКейсы§r и §a100§rРУБ.");
                        Loader::getInstance()->bonuspatrik->set($name, time() + 2592000);
                        Loader::getInstance()->bonuspatrik->save();
                    } else {
                        $sec = Loader::getInstance()->bonuspatrik->get($name);
                        $secs = $sec - time();
                        $time = $this->parseTime($secs);
                        $player->sendMessage("§l§aБОНУС §8:: §rВы уже брали бонус. Вам осталось подождать §a{$time}");
                        return true;
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case "grant":
                if ($player->isOp()) {
                    if (count($args) < 2) {
                        $player->sendMessage("§6»§r§fИспользование: §a/grant (ник) (§aприз) - (префикс, монеткейс, аристократ [14д]");
                        return true;
                    }

                    $username = mb_strtolower($player->getName());
                    $nickname = $args[0];
                    $giftType = mb_strtolower($args[1]);

                    if (Loader::getInstance()->timeranks->get($username)) {
                        $player->sendMessage("§l§aGRANT §8:: §rИспользование §aгрант§r недоступно. У вас привилегия на время.");
                        return true;
                    }

                    $validGiftTypes = ["префикс", "монеткейс", "аристократ"];
                    if (!in_array($giftType, $validGiftTypes)) {
                        $player->sendMessage("§l§aGRANT §8:: §rСписок что можно выдать§7:§a " . implode(", ", $validGiftTypes));
                        return true;
                    }

                    $p2 = Server::getInstance()->getPlayer($nickname);
                    if ($p2 === null) {
                        $player->sendMessage("§l§6➛ §rТакого игрока нет на сервере или он не в сети!");
                        return true;
                    }

                    $name = $player->getName();

                    if (!Loader::getInstance()->grant->exists($name) or Loader::getInstance()->grant->get($name) < time()) {
                        if ($giftType === "префикс") {
                            $duration = "30";
                            Server::getInstance()->broadcastMessage("\n\n§l§aGRANT §8:: §rИгрок §a{$player->getName()}§r подарил игроку §a{$p2->getName()}§r §r§l§bПрефикс§7-§bКейс§r\n\n");
                            Loader::getInstance()->addPrefixKeyy($p2->getName(), 1);

                        } elseif ($giftType === "монеткейс") {
                            Server::getInstance()->broadcastMessage("\n\n§l§aGRANT §8:: §rИгрок §a{$player->getName()}§r подарил игроку §a{$p2->getName()}§r §r§l§eДенежный§7-§eКейс§r\n\n");
                            Loader::getInstance()->addMoneyKeyy($p2->getName(), 1);
                        } elseif ($giftType === "аристократ") {
                            $grant_group = "Aristocrat"; 
                            $data = "14";
                            if (Loader::getInstance()->getGroup($p2->getName()) != "User") {
                                $player->sendMessage("§l§aGRANT §8:: §rУ игрока уже есть привилегия!");
                                return true;
                            }
                            Loader::getInstance()->setGroup($p2->getName(), $grant_group, $data);
                            Server::getInstance()->broadcastMessage("\n\n§l§aGRANT §8:: §rИгрок §a{$player->getName()}§r подарил игроку §a{$p2->getName()}§r привилегию §l§6Аристократ§r §7[14д.]\n\n");
                        }

                        Loader::getInstance()->grant->set($name, time() + 2592000); 
                        Loader::getInstance()->grant->save();
                    } else {
                        $sec = Loader::getInstance()->grant->get($name);
                        $secs = $sec - time();
                        $time = $this->parseTime($secs);
                        $player->sendMessage("§l§aGRANT §8:: §rВы уже дарили подарок. Вам осталось подождать §a{$time}");
                        return true;
                    }
                } else {
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'invsee':
                if ($player->hasPermission("api.cmd.invsee")) {
                    if (count($args) == 0) {
                        $player->sendMessage("§l§7► §rИспользование §a/invsee (никнейм)");
                        return true;
                    }

                    $targetName = $args[0];
                    $target = Server::getInstance()->getPlayer($targetName);

                    if (!$target) {
                        $player->sendMessage("§l§6➛ §rИгрок не найден.");
                        return true;
                    }

                    if($player->getGamemode() === Player::CREATIVE) {
                        $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                        return true;
                    }

                    $this->openPlayerChest($player, $target);
                    return true;
                } else {
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'chunk':
                if (count($args) !== 1) {
                    $player->sendMessage("§l§6➛ §rИспользование §a/chunk (min/max)");
                    return true;
                }

                $chunkCommand = strtolower($args[0]);

                switch ($chunkCommand) {
                    case 'min':
                        $viewDistance = 4;
                        $message = "§rВы поставили §amin§r!";
                        break;

                    case 'max':
                        $viewDistance = 10;
                        $message = "§rВы поставили §amax§r!";
                        break;
                    default:
                        $player->sendMessage("§l§6➛ §rИспользование §a/chunk (min/max)");
                    return true;
                }

                $player->setViewDistance($viewDistance);
                $player->sendTitle("§a§lЧАНКИ", $message);
            break;

            case "console":
                if ($player->hasPermission("api.cmd.console")) {
                    if (!isset(Loader::getInstance()->console[$name])) {
                        $player->sendMessage("§l§7► §rВы успешно §l§aвключили §rпросмотор логов!");
                        Loader::getInstance()->console[$name] = 0;
                    } else {
                        $player->sendMessage("§l§7► §rВы успешно §l§cвыключили §rпросмотор логов!");
                        unset(Loader::getInstance()->console[$name]);
                    }
                } else {
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'tpa':
                    if (count($args) < 1) {
                        $player->sendMessage("§l§6➛ §rИспользование §a/tpa (никнейм)\n §7- §a/tpa unban/ban (никнейм) §r разрешить/запретить, телепортацию к вам!");
                        return false;
                    }

                    switch (strtolower($args[0])) {
                        case "yes":
                            Loader::getInstance()->acceptTPARequest($player);
                            break;

                       case "no":
                            Loader::getInstance()->denyTPARequest($player);
                            break;

                        case "ban":
                            if (isset($args[1])) {
                                $target = Server::getInstance()->getPlayer($args[1]);
                                if ($target !== null && $target->isOnline()) {
                                    Loader::getInstance()->banTPA($player, $target);
                                } else {
                                    $player->sendMessage("§l§6➛ §rИгрока с ником §a{$args[0]}§r нету на сервере!");
                                }
                            } else {
                                $player->sendMessage("§l§6➛ §rИспользование §a/tpa ban (никнейм)");
                            }
                            break;

                        case "unban":
                            if (isset($args[1])) {
                                $target = Server::getInstance()->getPlayer($args[1]);
                                if ($target !== null && $target->isOnline()) {
                                    Loader::getInstance()->unbanTPA($player, $target);
                                } else {
                                    $player->sendMessage("§l§6➛ §rИгрока с ником §a{$args[0]}§r нету на сервере!");
                                }
                            } else {
                                $player->sendMessage("§l§6➛ §rИспользование §a/tpa ubban (никнейм)");
                            }
                            break;

                        default:
                            $target = Server::getInstance()->getPlayer($args[0]);
                            if ($target !== null && $target->isOnline()) {
                                Loader::getInstance()->sendTPARequest($player, $target);
                            } else {
                                $player->sendMessage("§l§6➛ §rИгрока с ником §a{$args[0]}§r нету на сервере!");
                            }
                            break;
                        }
                break;

                case 'tp':
                    if ($player->hasPermission("api.cmd.tp")) {
                        if (isset($args[0])) {
                            $target = Server::getInstance()->getPlayer($args[0]);
                            if ($target !== null) {
                                $targetName = $target->getName();
                                if ($player->getName() === $target->getName()) {
                                    $player->sendMessage("§l§6➛ §rНельзя §aтелепортироваться§r к самому себе!");
                                    return;
                                }

                                if (isset(Loader::getInstance()->pvp[$target->getLowerCaseName()])) {
                                    $player->sendMessage("§l§cᴘᴠᴘ §l§6➛ §r§fИгрок находится в §cпвп режиме§r!");
                                    return true;
                                }

                                if (in_array($targetName, Loader::getInstance()->banList)) {
                                    $player->sendMessage("§l§6➛ §rИгрок §a{$targetName}§r запретил §aтелепортацию§r к себе§r!");
                                    return true;
                                }

                                $worldName = $target->getLevel()->getName();
                                if ($worldName !== "proxyworld" && $worldName !== "kp3") {
                                    $player->teleport($target->getPosition());
                                    $player->sendMessage("§l§6➛ §rВы телепортировались к игроку §a{$target->getName()}§r!");
                                } else {
                                    $worldNameRus = $worldName === "proxyworld" ? "§l§bАРЕНЕ§r" : "§l§eДУЭЛЯХ§r";
                                    $player->sendMessage("§l§6➛ §rК этом игроку нельзя §aтелепортироваться§r, он находится на {$worldNameRus}");
                                }
                            } else {
                                $player->sendMessage("§l§6➛ §rИгрока с ником §a{$args[0]}§r нету на сервере!");
                            }
                        } else {
                            $player->sendMessage("§l§6➛ §rИспользование §a/tp (никнейм)");
                        }
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
            break;

                case 'tpban':
                    $playerName = $player->getName();
                    if (in_array($playerName, Loader::getInstance()->banList)) {
                        unset(Loader::getInstance()->banList[array_search($playerName, Loader::getInstance()->banList)]);
                        $player->sendMessage("§l§6➛ §rТелепортация к вам §aразрешена§r!");
                    } else {
                        Loader::getInstance()->banList[] = $playerName;
                        $player->sendMessage("§l§6➛ §rТелепортация к вам §cзапрещена§r!");
                    }
            break;

            case 'size':
                    if ($player->hasPermission("api.cmd.size")) {
                        if (count($args) == 0) {
                            $player->sendMessage("§l§6➛ §rИспользование §a/size (supermin, min, norm, big, superbig)");
                            return true;
                        }

                        $sizeMap = [
                            "supermin" => 0.5,
                            "min" => 0.7,
                            "norm" => 1.0,
                            "big" => 1.7,
                            "superbig" => 2.4
                        ];

                        $size = $args[0];
                        if (isset($sizeMap[$size])) {
                            $player->getDataPropertyManager()->setFloat(Player::DATA_SCALE, $sizeMap[$size]);
                            $player->sendMessage("§l§6➛ §rВы стали§a " . ($size === "norm" ? "обычным" : ($size === "supermin" ? "супер маленьким" : ($size === "min" ? "маленьким" : ($size === "big" ? "большим" : "супер большим")))) . "!");
                        } else {
                            $player->sendMessage("§l§6➛ §cНеверный размер§r, использование §a/size (supermin, min, norm, big, superbig)");
                        }
                    } else {
                        $player->sendMessage(self::NOPERMS);
                    }
            break;

            case 'autototem':
                if ($player->hasPermission("api.cmd.autototem")) {
                    if(isset(Loader::getInstance()->autototem[$player->getLowerCaseName()])){
                        $player->sendTitle(" §r§l§eАвто§7-§eТотем", "§r§7♨ §rБыл §cуспешно§r выключен! §7♨", 20, 50, 20);
                        unset(Loader::getInstance()->autototem[$player->getLowerCaseName()]);
                    }else{
                        $player->sendTitle(" §r§l§eАвто§7-§eТотем", "§r§7♨ §rБыл §aуспешно§r включен! §7♨", 20, 50, 20);
                        Loader::getInstance()->autototem[$player->getLowerCaseName()] = true;
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'fly':
                    if ($player->hasPermission("api.cmd.fly")) {
                        if ($player->getGamemode() !== 0) {
                            $player->sendMessage("§l§6➛ §rВыключите §aкреатив §rчтобы включить флай");
                            return false;
                        }

                        $allowFlight = $player->getAllowFlight();
                        $title = "§bРежим полета";
                        $subtitle = $allowFlight ? "§cОтключен" : "§aВключен";
                        $player->setAllowFlight(!$allowFlight);
                        $player->setFlying(!$allowFlight);
                        $player->addTitle($title, $subtitle);
                    } else {
                        $player->sendMessage(self::NOPERMS);
                    }
                break;

                case 'clear':
                    if ($player->hasPermission("api.cmd.clear")) {
                        $player->getInventory()->clearAll();
                        $player->sendMessage("§l§6➛ §rВы очистили §aинвентарь§r!");
                        $player->addTitle("§l§aОчистка", "§rВаш инвентарь был успешно §aочищен§r!");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
                break;

                case 'feed':
                case 'food':
                    if ($player->hasPermission("api.cmd.feed")) {
                        $player->setFood(20);
                        $player->sendMessage("§l§6➛ §rВы пополнили шкалу §aеды§r!");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
                break;
                break;

                case 'heal':
                    if ($player->hasPermission("api.cmd.heal")) {
                        $player->setHealth(20);
                        $player->sendMessage("§l§6➛ §rВы восстановили свое §aздоровье§r!");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
                break;

                case 'day':
                    if ($player->hasPermission("api.cmd.day")) {
                        $player->getLevel()->setTime(1400);
                        Server::getInstance()->broadcastMessage("\n§l§bTIME §8:: §rИгрок §a{$player->getName()}§r поставил §aдень§r на сервере!");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
                break;

                case 'night':
                    if ($player->hasPermission("api.cmd.night")) {
                        $player->getLevel()->setTime(14000);
                        Server::getInstance()->broadcastMessage("\n§l§bTIME §8:: §rИгрок §a{$player->getName()}§r поставил §aночь§r на сервере!");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
                break;

            case 'server':
                $maxPlayers = Server::getInstance()->getMaxPlayers();
                $onlinePlayers = count(Server::getInstance()->getOnlinePlayers());
                if ($onlinePlayers === 0) {
                    $player->sendMessage("§l§aINFO §8:: §l§fИНФОРМАЦИЯ СЕРВЕРА§7:");
                    $player->sendMessage("§7 * §cНа сервере нет игроков.");
                } else {
                    $online = "";
                    foreach (Server::getInstance()->getOnlinePlayers() as $player2) {
                            $online .= $player2->getDisplayName() . "§7,§a ";
                    }

                    $player->sendMessage("\n§l§aINFO §8:: §l§fИНФОРМАЦИЯ СЕРВЕРА§7:");
                    $player->sendMessage("§7 * §rРекорд онлайна§7:§a 15 §rЧел.");
                    $player->sendMessage("§7 * §rВерсия сервера§7:§a ". Server::getInstance()->getVersion() ." §r");
                    $player->sendMessage("§7 * §rОнлайн игроков§7:§e $onlinePlayers §7/§6 $maxPlayers §r");
                    $player->sendMessage("§7 * §a{$online}");
                }

                $player->sendMessage("§l§aINFO §8:: §l§fСОЦ-СЕТИ СЕРВЕРА§7:");
                $player->sendMessage("§7 * §rНаш VK§7: " . self::VK);
                $player->sendMessage("§7 * §rНаш TG§7: " . self::TG);
                $player->sendMessage("§7 * §rНаш Сайт§7: " . self::WEBSITE);
            break;

            case 'helper':
                if ($player->hasPermission("helper.system")) {
                    if ($player->isCreative()) {
                        $player->sendMessage("§l§6➛ §rВыключите §aкреатив§r пожалуйста!");
                        return;
                    }

                    $chest = ChestAPI::getInstance()->openChest($player, [
                        "0-53" => Item::get(102),
                        "10-43" => Item::get(0),

                        20 => Item::get(340)->setCustomName("§r§l§aВЫДАТЬ ХЕЛПЕРА\n\n§rСтатус§7: " . ($player->hasPermission("api.helper.setting") ? "§aДоступен" : "§cНедоступен"). "\n\n§7Нажмите дважды, чтобы перейти!"),
                        32 => Item::get(340)->setCustomName("§r§l§aЧСП ИГРОКИ\n\n§rСтатус§7: §aДоступен\n\n§7Нажмите дважды, чтобы перейти!"),
                        30 => Item::get(340)->setCustomName("§r§l§aСПИСОК ХЕЛПЕРОВ\n\n§rСтатус§7: §aДоступен\n\n§7Нажмите дважды, чтобы перейти!"),
                        24 => Item::get(340)->setCustomName("§r§l§aСПИСОК ЖАЛОБ\n\n§rСтатус§7: §aДоступен\n\n§7Нажмите дважды, чтобы перейти!"),
                    ], "§d§lHELPER §8:: §rВыберите §aдействия§r!", ChestAPI::DOUBLE_CHEST);
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'report':
                if (count($args) < 2) {
                    $player->sendMessage("§r §r§rИспользование §a/report (Ник) (Жалоба)");
                    return false;
                }

                $target = array_shift($args);
                $reason = implode(" ", $args);

                if (Loader::getInstance()->hasCurrentReport($target)) {
                    $player->sendMessage("§l§6➛ §rНа этого §aигрока§r уже подана жалоба. Пожалуйста, дождитесь обработки текущей §aжалобы§r!");
                } else {
                    Loader::getInstance()->addReport($player->getName(), $target, $reason);
                    $player->sendMessage("§l§6➛ §rЖалоба на игрока §a{$target}§r, отправлена на §aпроверку§r!");
                    VKHELPER::getLogger("⚔ ＮｅｘＶｉｌｌ ＧＲＩＥＦ ⚔\n📣 Игрок {$player->getName()} жалуется на {$target}, причина: {$reason}");

                    if ($player->hasPermission("helper.system")) {
                        $date = date("d.m.y");
                        $player->sendMessage("§7╔ §fИгрок §a{$player->getName()} §rжалуется на §c{$target}\n§7╠ §f§rПричина жалобы§7: §c{$reason}\n§7╚ §f§rДата подачи жалобы§7:§a {$date}\n");
                    }
                }
            break;

            case 'arena':
                $level = Server::getInstance()->getLevelByName("arena");
                if ($level === null) {
                    $player->sendMessage("§l§cОШИБКА", "§l§6➛ §rУровень арены не найден §l§7◄");
                    return false;
                }

                if ($player->getGamemode() !== 0) {
                    $player->sendTitle("§l§cОШИБКА", "§r§r §rВыключите §aкреатив §f§r");
                    return false;
                }

                if ($player->getAllowFlight()) {
                    $player->sendTitle("§l§cОШИБКА", "§r§r §rВыключите §aфлай§r! §f§r");
                    return false;
                }

                $onlinePlayers = count($level->getPlayers());
                $x = rand(-11, 46);
                $z = rand(-328, -250); 
                $y = $level->getHighestBlockAt($x, $z); 
                $position = new Position($x, $y - 20, $z, $level); 
                $player->teleport($position); 
                $world1 = count(Server::getInstance()->getLevelByName("arena")->getPlayers());
                Loader::getInstance()->arena1->setTitle("§r§f §rОнлайн в мире§7: §a{$world1} §r§f");
                Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arena1);
            break;

            case 'gm':
            case 'gamemode': 
                if ($player->hasPermission("helper.system")) {
                    if (isset($args[0])) {
                        if ($args[0] == "3") {
                            $player->setGamemode(3);
                            $player->sendMessage("§r §rТвой игровой режим изменен на §bнаблюдение");
                            return;
                        }

                        if ($args[0] == "2") {
                            $player->setGamemode(1);
                            $player->sendMessage("§r §rТвой игровой режим изменен на креатив");
                            return;
                        }

                        if ($args[0] == "1") {
                            $player->setGamemode(0);
                            $player->sendMessage("§r §rТвой игровой режим изменен на выживание");
                            return;
                        }
                    } else {
                        $player->sendMessage("§l§cADMIN §8:: §rУ вас есть права §ahelper.system§r!\n §7- §rПоэтому вы можете, поставить себе любой режим.\n§7 - §a/gm (режим) §7(1, 2, 3)");
                        return;
                    }
                }

                if ($player->hasPermission("api.cmd.gm")) {
                    $currentGamemode = $player->getGamemode();
                    $newGamemode = $currentGamemode == 1 ? 0 : 1;
                    $gamemodeName = $newGamemode == 1 ? "Креатив" : "Выживание";
                    $color = $newGamemode == 1 ? "§b" : "§a";

                    $player->setGamemode($newGamemode);
                    $player->sendMessage("§r §rТвой игровой режим изменен на {$color}{$gamemodeName}");
                    $player->addTitle("§l{$color}{$gamemodeName}", "§rтвой игровой режим был изменен");
                } else {
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'lang':
                if (count($args) === 0) {
                    $player->sendMessage("§r §rИспользование §a/lang (язык) - rus/eng");
                    return true;
                }
                $lang = strtolower($args[0]); 

                switch ($lang) {
                    case 'rus':
                        Loader::getInstance()->lang->set($player->getName(), 'rus');
                        Loader::getInstance()->lang->save();
                        $player->sendMessage("⨀ §rЯзык установлен на §aРусский§r.");
                        break;

                    case 'eng':
                        Loader::getInstance()->lang->set($player->getName(), 'eng');
                        Loader::getInstance()->lang->save();
                        $player->sendMessage("⨇ §rЯзык установлен на §aEnglish§r.");
                        break;

                    default;
                        $player->sendMessage("§r⩕ §rНедопустимый язык. Пожалуйста, выберите 'rus' или 'eng'.");
                        break;
                    }
                break;

            case 'restart':
                $timerestart = $this->time;
                $time = Loader::getInstance()->parseTime($timerestart);
                $player->sendMessage("§r§f §rПерезагрузка сервера через §a{$time}\n§r§f §rТекущей время на сервере §a{$timeday}");
            break;

            case "setgroup":
                if ($player->isOp()) {
                    if (count($args) < 3) {
                            $player->sendMessage("§l§6➛ §rИспользование: §a/setgroup (никнейм) (роль) (дни)\n §7- §rРоли§7: §aKronos, Aristocrat, Lucefer, Gresh, Knayz, Admin, Helper, User, Patrik, Santa, Moderator\n§7 - §rДни§7: §a7, 14, 30 §rили §a'навсегда'§r");
                            return true;
                        }

                    $targetPlayerName = $args[0];
                    $role = $args[1];
                    $duration = $args[2] ?? "навсегда";
                    $validRoles = [
                        "User" => ["права" => "§сИгрока, дожили, можно купить игрока)", "цена" => 100],
                        "Kronos" => ["права" => "§l§dКроноса§r", "цена" => 50],
                        "Aristocrat" => ["права" => "§l§6Аристократа§r", "цена" => 100],
                        "Lucefer" => ["права" => "§l§cЛюцифера§r", "цена" => 200],
                        "korol" => ["права" => "§l§dКороль§r", "цена" => 250],
                        "Gresh" => ["права" => "§l§bЦарь§r", "цена" => 350],
                        "Knayz" => ["права" => "§l§4Князя§r", "цена" => 500],
                        "Santa" => ["права" => "§l§cSANTA§r", "цена" => 699],
                        "Patrik" => ["права" => "§l§aПАТРИК§r", "цена" => 699],
                        "Rabbit" => ["права" => "§l§9RABBIT§r", "цена" => 899],
                        "Admin" => ["права" => "§l§cВладелеца§r", "цена" => 5000],
                        "Helper" => ["права" => "§l§aHELPER§r", "цена" => 5000],
                        "Yt" => ["права" => "§l§cYou§fTube§r", "цена" => 50000],
                        "Support" => ["права" => "§l§2SUPPORT§r", "цена" => 0],
                        "Kyrator" => ["права" => "§b§lKYRATOR§r", "цена" => 9999],
                        "Moderator" => ["права" => "§l§aМодератор§r", "цена" => 2500]
                    ];

                    if (array_key_exists($role, $validRoles) && (in_array($duration, ["7", "14", "30"]) || $duration === "навсегда")) {
                            $targetPlayer = Server::getInstance()->getPlayer($targetPlayerName);
                            Loader::getInstance()->setGroup($targetPlayerName, $role, $duration);
                
                            Loader::getInstance()->updatePermissions($player, Loader::getInstance()->getGroup($player->getName()));

                            $roleName = $validRoles[$role];
                            $roleName = $validRoles[$role]["права"];
                            $price = $validRoles[$role]["цена"];
                            Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$args[0]} §rкупил§a {$roleName} §7[{$duration}д.] (§a". number_format($price) ."рублей.§7)§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                            Loader::getInstance()->addRubSite($args[0], $price);
                        } else {
                        $player->sendMessage("§l§6➛ §rНеверная роль. Доступные роли: §aKronos, Aristocrat, Lucefer, Gresh, Knayz, Admin, Helper, User");
                    }
                }
            break;

            case "giverub":
                if ($player->isOp()) {
                    if($player instanceof Player){
                        $player->sendMessage(self::NOPERMS);
                        return true;
                    }
                    if (count($args) < 2) {
                        $player->sendMessage("§fИспользование: /giverub (игрок) (кол-во)§f!");
                        return true;
                    }

                    $pl = Loader::getInstance()->getServer()->getPlayer($args[0]);
                    if ($pl !== null) {
                        Loader::getInstance()->addRub($pl, $args[1]);
                        Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$args[0]} §rкупил§a $args[1] §rрублей§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE ."\n\n");
                    } else {
                        Loader::getInstance()->addRubb($args[0], $args[1]);
                        Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$args[0]} §rкупил§a $args[1] §rрублей§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE ."\n\n");
                    }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'givepass':
                if ($player->isOp()) {
                    if (count($args) < 1) {
                        $player->sendMessage("§l§6➛ §rИспользование: §a/givepass (никнейм)");
                        return true;
                    }

                    $targetPlayerName = $args[0];

                    $targetPlayer = Server::getInstance()->getPlayer($targetPlayerName);
                    if (Loader::getInstance()->getPass($targetPlayer) >= 1) {
                        Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$targetPlayerName} §rкупил§a §r§l§eВЕСЕННИЙ ПАСС§r\n§r §rУ игрока уже есть §aпасс§r, ему выдано §a200§r §rруб.\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                        Loader::getInstance()->addRub($targetPlayer, 200);
                        return;
                    }

                    Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$targetPlayerName} §rкупил§a §r§l§eВЕСЕННИЙ ПАСС§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                    Loader::getInstance()->givePass($targetPlayerName, 2);
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'givesub':
                if ($player->isOp()) {
                    if (count($args) < 2) {
                        $player->sendMessage("§fИспользование: /givesub (игрок) (время) §f[например: 1м, 7д., 14д.]!");
                        return true;
                    }

                    $targetPlayerName = $args[0];
                    $duration = strtolower($args[1]);
                    $validDurations = ['1', '7.', '14', '30', '45', '60'];

                    if (!in_array($duration, $validDurations)) {
                        $player->sendMessage("§cНекорректный срок подписки. Доступные: " . implode(', ', $validDurations));
                        return true;
                    }

                    $targetPlayer = Loader::getInstance()->getServer()->getPlayer($targetPlayerName);
                    Loader::getInstance()->giveSubscription($targetPlayer, $duration);
                    Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$targetPlayerName} §rкупил подписку§a §r§l§aMWIX+§r §7[{$duration}д.]\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                } else {
                     $player->sendMessage(self::NOPERMS);
                }
            break;

            case "givecase":
                if ($player->isOp()) {
                if (count($args) === 3) {
                    $nickname = mb_strtolower($args[0]);
                    $caseName = strtolower($args[1]);
                    $quantity = (int)$args[2];

                    if ($quantity < 1) {
                        $player->sendMessage("§r§l§6➛ §r§fНельзя выдать 0 кейсов.");
                        return false;
                    }
                    

                    $caseDisplayNames = [
                        'donate' => "§r§l§aДонат§7-§aКейс",
                        'vesen' => "§r§l§eВесенний§7-§eКейс",
                        'money' => "§r§l§eДенежный§7-§eКейс",
                        'prefix' => "§r§l§bПрефикс§7-§bКейс"
                    ];

                    if (array_key_exists($caseName, $caseDisplayNames)) {
                        $playerToGive = Server::getInstance()->getPlayer($nickname);
                        if ($playerToGive !== null) {
                            if ($caseName === 'donate') {
                                Loader::getInstance()->addDonateKey($playerToGive, $quantity);
                            } else if ($caseName === 'prefix') {
                                Loader::getInstance()->addPrefixKey($playerToGive, $quantity);
                            } else if ($caseName === 'vesen') {
                                Loader::getInstance()->addNewyearKey($playerToGive, $quantity);
                            } else if ($caseName === 'money') {
                                Loader::getInstance()->addMoneyKeyy($nickname, $quantity);
                            }

                            Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$nickname} §rкупил §a{$caseDisplayNames[$caseName]} §7x{$quantity}§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                        } else {
                            if ($caseName === 'donate') {
                                Loader::getInstance()->addDonateKeyy($nickname, $quantity);
                            } else if ($caseName === 'prefix') {
                                Loader::getInstance()->addPrefixKeyy($nickname, $quantity);
                            } else if ($caseName === 'vesen') {
                                Loader::getInstance()->addNewyearKeyy($nickname, $quantity);
                            } else if ($caseName === 'money') {
                                Loader::getInstance()->addMoneyKeyy($nickname, $quantity);
                            }

                            Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$nickname} §rкупил §a{$caseDisplayNames[$caseName]} §7x{$quantity}§r\n§r §rУспей купить на нашем сайте: " . self::WEBSITE . "\n\n");
                        }
                    } else {
                        $player->sendMessage("§cdonate/prefix/vesen - USE плиз.");
                    }
                } else {
                    $player->sendMessage("§l§6➛ §rИспользование: §a/givecase (никнейм) (название кейса - donate/prefix/vesen) (кол-во)");
                }
                }else{
                    $player->sendMessage(self::NOPERMS);
                }
            break;

            case 'stats':
                if (empty($args)) {
                    $player->sendMessage("§l§6➛ §rИспользование §a/stats (никнейм)");
                    return true;
                }

                $player2 = Loader::getInstance()->getServer()->getPlayer($args[0]);
                if (!$player2) {
                    $player->sendMessage("§l§6➛ §fИгрок §a{$player2} §rнет в онлайне§a!");
                    return true;
                }

                Loader::getInstance()->infoPlayer($player, $player2);
            break;

            case "pos":
                    $player->sendMessage("§l§6➛ §rTвои координаты:");
                    $player->sendMessage("§7 - §cX: §a". $player->getX());
                    $player->sendMessage("§7 - §cY: §a". $player->getY());
                    $player->sendMessage("§7 - §cZ: §a". $player->getZ());
            break;

            case "pay":
                if (count($args) < 2) {
                    $player->sendMessage("§r §rИспользование §a/pay (ник) (сумма)");
                    return true;
                }

                $amount = $args[1];
                if (!is_numeric($amount)) {
                    $player->sendMessage("§r⩐§r Сумма может быть только числом");
                    return true;
                }

                $amount = (float)$amount;
                if ($amount <= 0) {
                    return true;
                }

                $targetPlayer = Loader::getInstance()->getServer()->getPlayer($args[0]);
                if ($targetPlayer === null) {
                    $player->sendMessage("§r⩕ такого игрока нет на сервере, или он не в сети!");
                    return true;
                }

                if ($targetPlayer->getName() === $player->getName()) {
                    $player->sendMessage("§r⩕ Нельзя §aпереводить§r деньги самому себе!");
                    return true;
                }

                $playerMoney = Loader::getInstance()->getMoney($player);
                if ($playerMoney < $amount) {
                    $player->sendMessage("§r⩕ §rУ вас §cнету §rстолько денег§a ". number_format($amount) ."§r§f⩐ §rдля отправки!");
                    return true;
                }

                $player->sendMessage("§r §rВы успешно отправили игроку §a{$targetPlayer->getName()} §rсумму§a " . number_format($amount) . "§r§f⩐");
                $targetPlayer->sendMessage("§r §rИгрок §a{$player->getName()} §rотправил вам §2". number_format($amount) ."§r§f⩐");

                Loader::getInstance()->addMoney($targetPlayer, round($amount));
                Loader::getInstance()->remMoney($player, round($amount));
            break;

            case 'rep':
                if (count($args) !== 2) {
                    $player->sendMessage("§r §rИспользование §a/rep (никнейм) (+/-)");
                    return false;
                }

                $targetName = $args[0];
                $reaction = $args[1];
                $playerName = $player->getName();

                $targetPlayer = Server::getInstance()->getPlayer($targetName);

                if ($targetPlayer instanceof Player) {
                    if (!Loader::getInstance()->rep->exists($targetName)) {
                        Loader::getInstance()->rep->set($targetName, [
                            "likes" => 0,
                            "dislikes" => 0,
                            "lastReaction" => [],
                        ]);
                        Loader::getInstance()->rep->save();
                    }

                    $data = Loader::getInstance()->rep->get($targetName);

                    if (in_array($playerName, $data["lastReaction"])) {
                        $player->sendMessage("§r⨞ §rВы уже поставили репутацию этому игроку.");
                        return false;
                    }

                    if ($reaction === '+') {
                        $data["likes"] += 1; 
                        $player->sendMessage("§r⨞ §rИгроку §a{$targetName}§r добавлен §a+1§rREP.");
                    } elseif ($reaction === '-') {
                        $data["dislikes"] += 1;
                        $player->sendMessage("§r⨞ §rИгроку §a{$targetName}§r добавлен §a-1§rREP.");
                    } else {
                        $player->sendMessage("§r⨞ §rНекорректная реакция. Используйте + или -.");
                        return false;
                    }
                    $data["lastReaction"][] = $playerName;
                    Loader::getInstance()->rep->set($targetName, $data);
                    Loader::getInstance()->rep->save();
                }else{
                    $player->sendMessage("§r⩕ §r§fИгрок не в сети!");
                }
            break;

            case 'world':
                $i = $player->getInventory()->getItemInHand();
                $player->getInventory()->setItemInHand($i);
                $player->sendMessage("§l§6➛ §rАйди предмета§7:§a {$i}"); 
               /* if (count($args) !== 1) {
                    $player->sendMessage("§r §rИспользование §a/world (сумма)");
                    return false;
                }

                $amount = (int)$args[0];

                if (!is_numeric($amount)) {
                    $player->sendMessage("§r⩕ §r§fСумма может быть только числом!");
                    return false;
                }

                if ($amount <= 0) {
                    $player->sendMessage("§r⩕ §r§fВведите сумму больше §a1§r§f⩐");
                    return false;
                }

                $playerName = $player->getName();
                if (!Loader::getInstance()->barrier->exists($playerName)) {
                    Loader::getInstance()->barrier->set($playerName, [
                        "money" => 0,
                        "barrierSize" => 1500 
                    ]);
                    Loader::getInstance()->barrier->save();
                }

                $data = Loader::getInstance()->barrier->get($playerName);

                if(Loader::getInstance()->getMoney($player) >= $amount) {
                    Loader::getInstance()->remMoney($player, $amount);

                    $data["money"] += $amount;
                    Loader::getInstance()->barrier->set($playerName, $data);
                    Loader::getInstance()->barrier->save();

                    if ($data["money"] >= 10000000) {
                        $data["barrierSize"] += 500;
                        $data["money"] = 0;
                        Loader::getInstance()->barrier->set($playerName, $data);
                        Loader::getInstance()->barrier->save();
                    }
                    $player->sendMessage("§r§f §rВы пожертвовали §a". number_format($amount) ."§r§f⩐\n§r§f §rОбщая сумма: §a". $data["money"] ."/10,000,000§r§f⩐");
                }else{
                    $player->sendMessage("§r⩕ §r§fУ вас недостаточно средств!");
                }
                */
            break;

            case 'killer':
                if (count($args) !== 2) {
                    $player->sendMessage("§r §rИспользование §a/killer (ник) (сумма)");
                    return false;
                }

                $targetName = $args[0]; 
                $amount = (int)$args[1];
                $senderName = $player->getName();

                if (isset(Loader::getInstance()->killer->getAll()[$targetName])) {
                    $player->sendMessage("§r⩕ §r§fНа этого игрока уже есть активный заказ!");
                    return false;
                }

                if ($amount < 100000) {
                    $player->sendMessage("§r⩕ §r§fМинимальная сумма заказа составляет §a100,000§r§f⩐");
                    return false;
                }

                if (Loader::getInstance()->getMoney($player) >= $amount) {
                    Loader::getInstance()->remMoney($player, $amount);

                    $this->killer->set($targetName, [
                        'executor' => $senderName,
                        'amount' => $amount,
                        'time' => time() + 86400 * 2
                    ]);

                    $this->killer->save(); 
                    Server::getInstance()->broadcastMessage("\n\n§r §rИгрок §a{$player->getName()}§r, заказал убийство на §c{$targetName}§r, §rсумма §a". number_format($amount) ."§r§f⩐\n\n");
                } else {
                    $player->sendMessage("§r⩕ §r§fУ вас недостаточно средств!");
                }
            break;

            case 'spawn':
                    $player->teleport(Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
                    $world2 = count(Server::getInstance()->getLevelByName("world")->getPlayers());
                    Loader::getInstance()->grief1->setTitle("§r §rОнлайн в мире§7: §a{$world2} §r");
                    Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->grief1);
            break;

            case 'mine':
                $level = Server::getInstance()->getLevelByName("spawn");
                Loader::getInstance()->teleportPlayer($player, new Position(18, 35, 3, $level));
            break;

            case 'prefix':
                if (count($args) !== 1) {
                    $player->sendMessage("§r §rИспользование §a/prefix on/off/my\n §7- §a(my - мой префикс), (on/off - включить/выключить).");
                    return false;
                }

                $name = mb_strtolower($player->getName());
                $currentStatus = Loader::getInstance()->titul->get($name)["titulstatus"];
                $currentTitul = Loader::getInstance()->titul->get($name)["titul"];

                if (empty($currentTitul) || $currentTitul === "none") {
                    $player->sendMessage("§l§dПРЕФИКС §8:: §r§cУ вас нету префикса!");
                    return false;
                }

                if ($args[0] == "my") {
                    $player->sendMessage("§l§dПРЕФИКС §8:: §rУ вас есть префикс: {$currentTitul}");
                }

                if($args[0] == "on") {
                    Loader::getInstance()->giveTitle($player, $currentTitul);
                    $player->sendMessage("§l§dПРЕФИКС §8:: §rВы установили префикс {$currentTitul}");
                 } elseif ($args[0] == "off") {
                    Loader::getInstance()->titul->set($name, [
                        "titulstatus" => "off",
                        "titul" => $currentTitul,
                    ]);
                    $player->sendMessage("§l§dПРЕФИКС §8:: §rПрефикс отключён!");
                }
                Loader::getInstance()->titul->save(); 
            break;

            case "sethome":
                    if (count($args) < 1) {
                        $player->sendMessage("§6»§rИспользование§a /sethome (название)");
                        return true;
                    }

                    $homeName = $args[0];

                    if ($this->homes->exists($player->getName() . "." . $homeName)) {
                        $player->sendMessage("§l§eHOME §8:: §rДом с таким именем уже §aсуществует§r!");
                        return true;
                    }

                    $playerGroup = $this->getGroup($player->getName());

                    $maxHomes = 0;

                    switch ($playerGroup) {
                        case 'User':
                            $maxHomes = 10;
                            break;

                        case 'Kronos':
                            $maxHomes = 13;
                            break;

                        case 'Aristocrat':
                            $maxHomes = 16;
                            break;

                        case 'Lucefer':
                            $maxHomes = 20;
                            break;

                        case 'Gresh':
                            $maxHomes = 25;
                            break;

                        case 'Knayz':
                            $maxHomes = 30;
                            break;

                        case 'Helper':
                            $maxHomes = 40;
                            break;

                        case 'Moderator':
                            $maxHomes = 60;
                            break;

                        case 'Patrik':
                            $maxHomes = 35;
                            break;

                        case 'Santa':
                            $maxHomes = 35;
                            break;

                        case 'Moderator':
                            $maxHomes = 60;
                            break;

                        case 'Partner':
                            $maxHomes = 60;
                            break;

                        case 'Texadmin':
                            $maxHomes = 999;
                            break;

                        case 'Owner':
                            $maxHomes = 999;
                            break;

                        default:
                            $maxHomes = 10;
                            break;
                        }

                    $playerhome = 0;
                    foreach ($this->homes->getAll() as $name => $data) {
                        if ($data["owner"] === $player->getName()) {
                            $playerhome++;
                        }
                    }

                    if ($playerhome >= $maxHomes) {
                        $player->sendMessage("§l§eHOME §8:: §rВы не можете создать больше §a" . $maxHomes . " точек домов§r!");
                        return true;
                    }

                    $this->homes->set($player->getName() . "." . $homeName, [
                        "x" => $player->getX(),
                        "y" => $player->getY(),
                        "z" => $player->getZ(),
                        "level" => $player->getLevel()->getName(),
                        "owner" => $player->getName()
                    ]);

                    $this->homes->save();
                    $player->sendMessage("§l§eHOME §8:: §rДом §a" . $homeName . "§r создан!");
            break;

            case "delhome":
                    if (count($args) < 1) {
                        $player->sendMessage("§6»§rИспользование §a/delhome (название)");
                        return true;
                    }

                    $homeName = $args[0];

                    if (!$this->homes->exists($player->getName() . "." . $homeName)) {
                        $player->sendMessage("§l§eHOME §8:: §rДом с таким именем не §aсуществует§r!");
                        return true;
                    }

                    $this->homes->remove($player->getName() . "." . $homeName);
                    $this->homes->save();
                    $player->sendMessage("§l§eHOME §8:: §rДом §a" . $homeName . "§r удален.");
            break;

            case "home":
                if (count($args) < 1) {
                    $player->sendMessage("§l§eHOME §8:: §rВаши точки домов§7:");
                    $homesList = array_keys($this->homes->getAll()); 

                    $playerHomes = array_filter($homesList, function($key) use ($player) {
                        return strpos($key, $player->getName() . ".") === 0;
                    });
                    if (empty($playerHomes)) {
                        $player->sendMessage("§l§eHOME §8:: §cУ вас нет домов.");
                    } else {
                        foreach ($playerHomes as $homeKey) {
                            $homeName = substr($homeKey, strlen($player->getName() . "."));
                            $player->sendMessage("§l§a- §f" . $homeName);
                        }
                    }
                    return true;
                }

                $homeName = $args[0];
                if (!$this->homes->exists($player->getName() . "." . $homeName)) {
                    $player->sendMessage("§l§eHOME §8:: §rДом с таким именем не §aсуществует§r!");
                    return true;
                }
                $homeData = $this->homes->get($player->getName() . "." . $homeName);
                $level = $this->getServer()->getLevelByName($homeData["level"]);
                if ($level === null) {
                    $player->sendMessage("§l§eHOME §8:: §rУровень дома не найден.");
                    return true;
                }
                $player->teleport(new Position($homeData["x"], $homeData["y"], $homeData["z"], $level));
                $player->sendMessage("§l§eHOME §8:: §rВы телепортированы к дому §a" . $homeName . "§r.");
            break;

            case "warp":
                    if (count($args) < 1) {
                        $warps = $this->warps->getAll();
                        if (empty($warps)) {
                            $player->sendMessage("§l§eWARP §8:: §rНа сервере нет §aварпов§r!");
                        } else {
                            $warpList = " §rИспользование§a /warp (название)\n§l§6➛ §rДоступные варпы§7: ";
                        foreach ($warps as $name => $data) {
                            $warpList .= "§a" . $name . "§r, ";
                        }
                        $player->sendMessage(rtrim($warpList, ", "));
                    }
                    return true;
                }

                $warpName = $args[0];
                if (!$this->warps->exists($warpName)) {
                    $player->sendMessage("§l§eWARP §8:: §rВарп не §aсуществует§r!");
                    return true;
                }
                $warpData = $this->warps->get($warpName);
                $level = $this->getServer()->getLevelByName($warpData["level"]);
                if (!$level) {
                    $player->sendMessage("§l§eWARP §8:: §rУровень для варпа не существует!");
                    return true;
                }
                $player->teleport(new Position($warpData["x"], $warpData["y"], $warpData["z"], $level));
                $player->sendMessage("§l§eWARP §8:: §rВы телепортированы к варпу §a" . $warpName);

            break;

            case "delwarp":
                    if (count($args) < 1) {
                        $player->sendMessage("§l§7► §rИспользование §a/delwarp (название)");
                        return true;
                    }

                    $warpName = $args[0];
                    if (!$this->warps->exists($warpName)) {
                        $player->sendMessage("§l§eWARP §8:: §rВарп не §aсуществует§r!");
                        return true;
                    }

                    $warpData = $this->warps->get($warpName);
                    if ($warpData["owner"] !== $player->getName() && !$player->hasPermission("warpsystem.admin")) {
                        $player->sendMessage("§l§eWARP §8:: §rУ вас нет §aразрешения§r на удаление этого §aварпа§r!");
                        return true;
                    }

                    $this->warps->remove($warpName);
                    $this->warps->save();
                    $player->sendMessage("§l§eWARP §8:: §rВарп с названием §a{$warpName}§r удален!");
            break;

            case "setwarp":
                if (count($args) < 1) {
                    $player->sendMessage("§6»§rИспользование§a /setwarp (название)");
                    return true;
                }

                    if ($player->hasPermission("api.cmd.setwarp")) {

                        $playerName = $player->getName();
                        $warpName = $args[0];

                        if (strlen($warpName) > 10) {
                            $player->sendMessage("§l§eWARP §8:: §rНазвание варпа не может содержать больше §a10 символов§r!");
                            return true;
                        }

                        $warpName = $args[0];
                        if ($this->warps->exists($warpName)) {
                            $player->sendMessage("§l§eWARP §8:: §rВарп с таким именем уже §aсуществует§r!");
                            return true;
                        }

                        $playerWarps = 0;
                        foreach ($this->warps->getAll() as $name => $data) {
                            if ($data["owner"] === $player->getName()) {
                                $playerWarps++;
                            }
                        }

                        if ($playerWarps >= 10) {
                            $player->sendMessage("§l§eWARP §8:: §rВы не можете создать больше §a10 варпов§r!");
                            return true;
                        }

                        if (count($this->warps->getAll()) >= 200) {
                            $player->sendMessage("§l§eWARP §8:: §rНа §aсервере§r не может быть больше §a200 варпов§r!");
                            return true;
                        }

                        $this->warps->set($warpName, [
                            "x" => $player->getX(),
                            "y" => $player->getY(),
                            "z" => $player->getZ(),
                            "level" => $player->getLevel()->getName(),
                            "owner" => $player->getName()
                        ]);
                        $this->warps->save();
                        $player->sendMessage("§l§eWARP §8:: §rВарп §a" . $warpName . "§r создан!");
                        Server::getInstance()->broadcastMessage("\n\n§l§6| §r§a{$player->getDisplayName()}§r создал варп §a" . $warpName . "§r!\n§l§6| §rТелепортация к варпу §a/warp {$warpName}\n\n");
                    }else{
                        $player->sendMessage(self::NOPERMS);
                    }
            break;

            case 'adminka':
                if (!$player->hasPermission("api.cmd.rewd")) {
                    $player->sendMessage(self::NOPERMS);
                    break;
                }

                if (!isset($args[0])) {
                    $player->sendMessage("§cИспользование: /adminka <updatemine|shop|eventworld|...>");
                    break;
                }

                if ($args[0] == "updatemine") {
                    Loader::getInstance()->updateMine();
                }

                if ($args[0] == "shop") {
                    Shop::openMenu($player);
                }

                if ($args[0] == "eventworld"){
                     $level = Server::getInstance()->getLevelByName("duels");
                     $position = new Position(3000, 81, 3000, $level); 
                     $player->teleport($position); 
                }

                if ($args[0] == "setgroup") {
                    Loader::getInstance()->setGroup($args[1], $args[2], $args[3]);
                }

                if ($args[0] == "givemoney") {
                    Loader::getInstance()->addMoneyy($args[1], $args[2]);
                }

                if ($args[0] == "givecase") {
                    Loader::getInstance()->addPrefixKeyy($args[1], $args[2]);
                }

                if ($args[0] == "rabbit") {
                    Loader::getInstance()->spawnRabbit($player, $args[1]);
                }

                if ($args[0] == "giverub") {
                    Loader::getInstance()->addRubb($args[1], $args[2]);
                }

                if ($args[0] == "promo1") {
                    Loader::getInstance()->promotext3->setTitle("§aСегодня будут промокоды, ожидайте!");
                    Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext3);
                }

                if ($args[0] == "promo2") {
                    Loader::getInstance()->promotext3->setTitle("§aЧерез пару секунд будет промокод!");
                    Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext3);
                }

                if ($args[0] == "promo3") {
                    Loader::getInstance()->promotext3->setTitle("§d/promo {$args[1]}");
                    Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext3);
                }
            break;

            case 'black':
             if (!$player->hasPermission("api.cmd.rewd")) {
                    $player->sendMessage(self::NOPERMS);
                    break;
                }
                if (count($args) < 1) {
                    $player->sendMessage("§cИспользуйте: /black <НИК> <ПРИЧИНА>");
                    return true;
                }
                $nick = $args[0]; // Получаем ник игрока (первый аргумент)
                $reason = count($args) > 1 ? implode(" ", array_slice($args, 1)) : "Не указана"; // Объединяем все аргументы, начиная с 1 до конца, для причины

                $this->addToBlacklist($nick, $reason); // Добавляем в черный список
                $player->sendMessage("§aИгрок §b{$nick} §aдобавлен в черный список с причиной: §e{$reason}.");
            break;

            case 'proverka': 
                if (!$player->hasPermission("api.cmd.proverka")) {
                    $player->sendMessage(self::NOPERMS);
                    break;
                }

                if (count($args) == 0) {
                    $player->sendMessage("§6»§rИспользование §a/proverka (никнейм)");
                    break;
                }

                $player2 = Loader::getInstance()->getServer()->getPlayer($args[0]);
                if (!$player2) {
                    $player->sendMessage("§7§l►§r §r§fТакого игрока нет на сервере или он не в сети!");
                    break;
                }

                if ($player2->getName() === $player->getName()) {
                    $player->sendMessage("§7§l►§r Нельзя §aвызвать§r себя самого на проверку!");
                    return true;
                }

                if (isset($args[1]) && $args[1] === 'off') {
                    Loader::getInstance()->releasePlayerFromCheck($player2);
                    $player->sendMessage("§l§7► §rВы отпустили игрока §a{$player2->getName()}§r с §aпроверки§r!");
                    break;
                }

                Loader::getInstance()->performPlayerCheck($player, $player2);
            break;

            default:
                $player->sendMessage("§cНеизвестная команда, /help.");
                break;
        }
    }

    public function openMenuSUB(Player $player) {
        $subscriptionInfo = $this->checkSubscription($player);
        $chest = ChestAPI::getInstance()->openChest($player, [
            "0-53" => Item::get(102),
            22 => Item::get(397, 3, 1)->setCustomName("§r{$player->getDisplayName()}\n{$subscriptionInfo}"),
            20 => Item::get(278, 0, 1)->setCustomName("§r§l§dУстановить редкость шахты\n\n§7Нажмите дважды, чтобы перейти!"),
        ], "§a§lSUB §8:: §rМеню управления!", ChestAPI::DOUBLE_CHEST);
    }

    public function getSub(Player $player) {
        $subData = Loader::getInstance()->sub->get($player->getName());

        $subColor = isset($subData["color"]) ? $subData["color"] : "§f";

        if ($subData) {
            return " §l§f" . $subColor . "ϟ";
        } else {
            return "§f";
        }
    }

    public function giveSubscription(Player $player, $duration) {
        $name = $player->getLowerCaseName();
        $durationInDays = (int)$duration; 
        $expiryTime = time() + ($durationInDays * 86400); 

        Loader::getInstance()->sub->set($name, [
            "date" => date("Y.m.d H:i:s"),
            "duration" => $duration,
            "expires" => $expiryTime,
            "color" => "§f"
        ]);
        Loader::getInstance()->sub->save();
    }

    public function checkSubscription(Player $player) {
        $username = $player->getName();
        $subData = Loader::getInstance()->sub->get($username);
        $subscriptionInfo = "";

        if ($subData) {
            $expirationTime = $subData["expires"];
            $currentTime = time();
            $remainingTime = $expirationTime - $currentTime;

            $subscriptionInfo .= "\n§l§aSUB §8:: §l§fИНФОРМАЦИЯ ПОДПИСКИ§7:\n";
            $subscriptionInfo .= "§7 - §rДата выдачи подписки§7:§a §8[§a" . (isset($subData["date"]) ? $subData["date"] : "§7Нету данных§r") . "§8]\n";
            $subscriptionInfo .= "§7 - §rСрок подписки§7:§a " . (isset($subData["duration"]) ? $subData["duration"] : "§7Нету данных§r") . "д.\n";
            $subscriptionInfo .= "§7 - §rЦвет подписки§7:§l§f " . $subData["color"] . "ϟ\n";

            if ($remainingTime > 0) {
                $daysRemaining = floor($remainingTime / (60 * 60 * 24));
                $subscriptionInfo .= "§7 - §rОкончание подписки через§7: §7[" . $daysRemaining . "§7д.]\n";
            } else {
                $subscriptionInfo .= "§cВаша подписка истекла.\n";
                Loader::getInstance()->sub->remove($username);
                Loader::getInstance()->sub->save();
            }
        } else {
            $subscriptionInfo .= "§l§aSUB §8:: §rУ вас нету §aподписки§r, купить можно на " . self::WEBSITE . "!\n";
        }
        return $subscriptionInfo; 
    }

    public static function spawnRabbit(Player $player, $name) {
        $level = $player->getLevel();
        $nbt = new CompoundTag("", [ "Pos" => new ListTag( "Pos", [new DoubleTag("", $player->x), new DoubleTag("", $player->y), new DoubleTag("", $player->z)]), "Motion" => new ListTag("Motion", [ new DoubleTag("", 0), new DoubleTag("", 0), new DoubleTag("", 0)]), "Rotation" => new ListTag("Rotation", [new FloatTag("", 0), new FloatTag("", 0)])]);
        self::$bossEntity = Entity::createEntity(Rabbit::NETWORK_ID, $level, $nbt);
        self::$bossEntity->setNameTag($name);
        self::$bossEntity->getDataPropertyManager()->setFloat(Entity::DATA_SCALE, 1.5);
        self::$bossEntity->setNameTagAlwaysVisible(false);
        self::$bossEntity->spawnToAll();
        self::$bossEntity->setMaxHealth(5);
        self::$bossEntity->setHealth(5);
    }

    public function getCooldown($playerName) {
        return $this->cooldowns[$playerName] ?? null;
    }

    public function setCooldown($playerName, $timestamp) {
        $this->cooldowns[$playerName] = $timestamp;
    }

    private function performPlayerCheck($player, $player2) {
        $level = Server::getInstance()->getLevelByName("spawn");
        $date = date("d.m.y");

        Loader::getInstance()->teleportPlayer($player, new Position(51, 90, -12, $level));
        $player->sendMessage("§l§aPROVERKA §8:: §rЧтобы отпустить игрока пропишите, §a/proverka {$player2->getName()} off");

        Loader::getInstance()->teleportPlayer($player2, new Position(49, 90, -10, $level));
        $player2->setImmobile(true);
        $player2->sendMessage("\n§l§a        ПРОВЕРКА НА ЧИТЫ");
        $player2->sendMessage("§r§f §rПишите ваш дискорд (ТЕЛЕГРАММ, ВК), у вас §a5 минут§r!\n§7╠ §rЕсли не скинете в течение §a5 минут§r, ваш аккаунт §aзаблокируют§r!\n§7╠ §rУклон, выход из игры = §aблокировка аккаунта§r!\n§7╚             §7{$date}\n\n");
        $player2->sendTitle('§l§aПРОВЕРКА', '§rПРОВЕРЬТЕ СООБЩЕНИЕ В ЧАТЕ!', 20, 300, 20);
        Loader::getInstance()->checks[strtolower($player2->getName())] = true;
    }

    private function releasePlayerFromCheck($player2) {
        $player2->setImmobile(false);
        Loader::getInstance()->teleportPlayer($player2, Server::getInstance()->getLevelByName("spawn")->getSafeSpawn());
        $player2->sendMessage("§l§aPROVERKA §8:: §rВас отпустили с §aпроверки§r!");
        unset(Loader::getInstance()->checks[strtolower($player2->getName())]);
    }

    private function addToBlacklist(string $nick, string $reason): void {
        $date = (new DateTime())->format('Y-m-d H:i:s');
        
        $this->blacklist->set($nick, [
            "reason" => $reason,
            "date" => $date,
        ]);
        $this->blacklist->save();
    }

    private function openPlayerChest(Player $player, Player $target) {
        $inventory = $target->getInventory();
        $chestItems = [];

        $glassPane = Item::get(Item::GLASS_PANE, 0, 1);

        for ($slot = 0; $slot < 54; ++$slot) {
            $item = $inventory->getItem($slot);
            if ($item->getId() === Item::AIR) {
                $chestItems[$slot] = $glassPane;
            } else {
                $chestItems[$slot] = $item;
            }
        }
        $chest = ChestAPI::getInstance()->openChest($player, $chestItems, "§a§lINVENTORY §8:: §rИгрока§a " . $target->getName(), ChestAPI::DOUBLE_CHEST);
    }

    public function infoPlayer(Player $player, $target): void {
        $lvl = Loader::getInstance()->getLvl($target);
        $expNeeded = number_format(100 * pow(1.2, $lvl));
        $exp = number_format(Loader::getInstance()->getExp($target));

        $chest = ChestAPI::getInstance()->openChest($player, [
            "0-53" => Item::get(102),
            "10-43" => Item::get(0),
            13 => Item::get(397, 3, 1)->setCustomName("§r§l§fСТАТУС ПРИВИЛЕГИЕ/ПРЕФИКСА/КЛАНА§7:\n\n§rДонат§7:§r ". Loader::getInstance()->getPrefix($target) ."\n§rПрефикс§7: ". Loader::getInstance()->getPrefixTitul($target) ."§r\n§rКлан§7: §8[§b". Loader::getInstance()->getPrefixClans($target) ."§r§8]§r"),
            19 => Item::get(267, 0, 1)->setCustomName("§r§l§fСТАТУС УБИЙСТВ/СМЕРТЕЙ§7:\n\n§rУбийств§7:§c ". Loader::getInstance()->getKills($target) ."§r\n§rСмертей§7:§c ". Loader::getInstance()->getDeath($target) ."§r"),
            25 => Item::get(175, 0, 1)->setCustomName("§r§l§fСТАТУС МОНЕТ/РУБЛЕЙ§7:\n\n§rМонет§7:§a ". number_format(Loader::getInstance()->getMoney($target)) ."§r⩐\n§rРублей§7:§a ". number_format(Loader::getInstance()->getRub($target)) ."§r"),

            29 => Item::get(406, 0, 1)->setCustomName("§r§l§fСТАТУС УРОВЕНЬ/ОПЫТ§7:\n\n§rУровень§7:§r §e". Loader::getInstance()->getLvl($target) ."§l§e§r\n§rОпыт§7: §e§8[§e§d{$exp}§7/§5{$expNeeded}§8]§r"),

            33 => Item::get(130, 0, 1)->setCustomName("§r§l§fСТАТУС КЕЙСОВ§7:\n\n§r§l§aДонат§7-§aКейсы§r §rимеет §a" . Loader::getInstance()->getDonateKey($target) . "§r кейсов!\n§r§l§bПрефикс§7-§bКейсы§r §rимеет §a" . Loader::getInstance()->getPrefixKey($target) . "§r кейсов!\n§r§l§eВесенний§7-§eКейс§r §rимеет §a" . Loader::getInstance()->getNewyearKey($target) . "§r кейсов!"),

            40 => Item::get(218, 0, 1)->setCustomName("§r§l§fПРОЧЕЕ§7:\n\n§rЗадонатил за вайп§7:§r §a". number_format(Loader::getInstance()->getRubSite($target->getName())) ."§rрублей.\n§7Будет добавляться..."),
        ], "§a§lСТАТИСТИКА §8:: §rИгрока §a{$target->getName()}§r!", ChestAPI::DOUBLE_CHEST);
    }

    public function acceptTPARequest(Player $player) {
        if (isset($this->tpaRequests[$player->getName()])) {
            $senderName = $this->tpaRequests[$player->getName()];
            $sender = $this->getServer()->getPlayer($senderName);
            if ($sender !== null && $sender->isOnline()) {
                if (in_array($sender->getLevel()->getName(), ["proxyworld", "duels"])) {
                    $player->sendMessage("§l§6➛ §rВы не можете §aпринять§r запрос, игрок находится на §l§bАРЕНЕ§r");
                    $sender->sendMessage("§l§6➛ §rЗапрос §aтелепортации§r не принят, вы находитесь на §l§bАРЕНЕ§r");
                    return;
                }

                if (isset(Loader::getInstance()->pvp[$sender->getLowerCaseName()])) {
                    $player->sendMessage("§l§cᴘᴠᴘ §l§6➛ §r§fИгрок находится в §cпвп режиме§r!");
                    return true;
                }
                $sender->teleport($player->getPosition());
                $sender->sendMessage("§l§6➛ §rВаш запрос на телепортацию к §a{$player->getName()} §rбыл §aпринят!");
                $player->sendMessage("§l§6➛ §rВы приняли запрос на телепортацию от §a{$sender->getName()}");
            } else {
                $player->sendMessage("§l§6➛ §rИгрок, отправивший запрос, больше не в сети!");
            }
            unset($this->tpaRequests[$player->getName()]);
        } else {
            $player->sendMessage("§l§6➛ §rУ вас нет активных запросов на §aтелепортацию§r!");
        }
    }

    public function denyTPARequest(Player $player) {
        if (isset($this->tpaRequests[$player->getName()])) {
            $senderName = $this->tpaRequests[$player->getName()];
            $sender = $this->getServer()->getPlayer($senderName);
            if ($sender !== null && $sender->isOnline()) {
                $sender->sendMessage("§l§6➛ §rВаш запрос на телепортацию к §a{$player->getName()} §rбыл §cотклонен§r!");
                $player->sendMessage("§l§6➛ §rВы отклонили запрос на телепортацию от §a{$sender->getName()}");
            } else {
                $player->sendMessage("§l§6➛ §rИгрок, отправивший запрос, больше не в сети!");
            }
            unset($this->tpaRequests[$player->getName()]);
        } else {
            $player->sendMessage("§l§6➛ §rУ вас нет активных запросов на §aтелепортацию§r!");
        }
    }

    public function sendTPARequest(Player $sender, Player $target) {
        if ($sender->getName() === $target->getName()) {
            $sender->sendMessage("§l§6➛ §rНельзя отправить §aтелепортацию§r самому себе!");
            return;
        }

        if (isset($this->tpaBanned[$sender->getName()])) {
            $sender->sendMessage("§l§6➛ §rДанный игрок запретил вам §aтелепортироваться§r к нему!");
            return;
        }

        $this->tpaRequests[$target->getName()] = $sender->getName();
        $target->sendMessage("§l§6➛ §rИгрок §a{$sender->getName()} §rхочет §aтелепортироваться§r к вам!\n §7- §a/tpa yes §7-§r принять §7| §a/tpa no §7- §rотклонить");
        $sender->sendMessage("§l§6➛ §rЗапрос на телепортацию отправлен игроку §a{$target->getName()}");
    }

    public function banTPA(Player $player, Player $target) {
        $this->tpaBanned[$target->getName()] = true;
        $target->sendMessage("§l§6➛ §rИгрок §a{$player->getName()} §rзапретил вам отправлять запросы на §aтелепортацию§r!");
        $player->sendMessage("§l§6➛ §rВы запретили игроку §a{$target->getName()} §rотправлять вам запрос на §aтелепортацию§r!");
    }

    public function unbanTPA(Player $player, Player $target) {
        unset($this->tpaBanned[$target->getName()]);
        $target->sendMessage("§l§6➛ §rИгрок §a{$player->getName()} §rразрешил вам отправлять запросы на §aтелепортацию§r!");
        $player->sendMessage("§l§6➛ §rВы разрешили игроку §a{$target->getName()} §rотправлять вам запрос на §aтелепортацию§r!");
    }

    private function hasCurrentReport(string $target): bool {
        $reports = Loader::getInstance()->reportsConfig->get("reports", []);
        foreach ($reports as $report) {
            if ($report["target"] === $target) {
                return true;
            }
        }
        return false;
    }

    private function addReport(string $reporter, string $target, string $reason): void {
        $reports = Loader::getInstance()->reportsConfig->get("reports", []);
        $id = count($reports) + 1;
        $reports[] = [
            "id" => $id,
            "reporter" => $reporter,
            "target" => $target,
            "reason" => $reason,
            "date" => time()
        ];
        Loader::getInstance()->reportsConfig->set("reports", $reports);
        Loader::getInstance()->reportsConfig->save();
    }

    public function cleanupReports(): void {
        $timeLimit = time() - (24 * 60 * 60);
        $reports = Loader::getInstance()->reportsConfig->get("reports", []);
        $filteredReports = array_filter($reports, function ($report) use ($timeLimit) {
            return $report['date'] > $timeLimit;
        });
        Loader::getInstance()->reportsConfig->set("reports", $filteredReports);
        Loader::getInstance()->reportsConfig->save();
    }

    public function giveTitle(Player $target, $titleIndex) {
        $name = mb_strtolower($target->getName());
        $title = "§l{$titleIndex}§r";

        Loader::getInstance()->titul->set($name, [
            "titulstatus" => "on",
            "titul" => $title,
        ]);

        Loader::getInstance()->titul->save();
    }

    public function teleportPlayer($player, $position) {
        $player->teleport($position);
    }

    public function updateTextPromocode() {
        Loader::getInstance()->promotext1->setTitle("§r§f §l§aСЛИВ ПРОМОКОДОВ §r§f");
        Loader::getInstance()->promotext2->setTitle("§r§7- §rОжидайте скоро ниже появится промокод! §r§7-");
        Loader::getInstance()->promotext3->setTitle("§cПромокодов нету, ожидайте информации!");
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->promotext3);
    }

    public function updateText(){
        $world1 = count(Server::getInstance()->getLevelByName("arena")->getPlayers());

        $level = Server::getInstance()->getLevelByName("world");
        $world2 = ($level !== null) ? count($level->getPlayers()) : 0;

        Loader::getInstance()->passtext1->setTitle("§l§eＰＡＳＳ");
        Loader::getInstance()->passtext2->setTitle("§7- §rТекущей сезон§7: §aВесенний §7-");
        Loader::getInstance()->passtext3->setTitle("§rНажмите на NPC для просмотра!");

        Loader::getInstance()->duels->setTitle("§l§7[§c⚔§7] §l§eＤＵＥＬＳ §7[§c⚔§7]");
        Loader::getInstance()->duels1->setTitle("§r §cнеизвестно §rпротив §cнеизвестно §f§r");
        Loader::getInstance()->duels2->setTitle("§r§f⨞ §rНажмите чтобы играть! §r§f⨟");

        Loader::getInstance()->grief->setTitle("§r§f §l§cГРИФЕРСКИЙ МИР§r§f ");
        Loader::getInstance()->grief1->setTitle("§r§f §rОнлайн в мире§7: §a{$world2} §r§f");
        Loader::getInstance()->grief2->setTitle("§r§f §rРазмер мира§7: §a4000x4000 §r§f");
        Loader::getInstance()->grief3->setTitle("§r§f⨞ §rПрыгните в портал для входа! §r§f⨟");

        Loader::getInstance()->arenanews->setTitle("§r§dДОБАВИЛИ ЦАРЬ ГОРЫ, NEW MAP!");
        Loader::getInstance()->arena->setTitle("§r§f §l§bАРЕНА§r§f ");
        Loader::getInstance()->arena1->setTitle("§r§f §rОнлайн в мире§7: §a{$world1} §r§f");
        Loader::getInstance()->arena2->setTitle("§r§f⨞ §rНажмите на NPC, для входа! §r§f⨟");

     //   Loader::getInstance()->infotext1->setTitle("§7- §rЦена §aпочинке§r предмета §a1,000§2$ §7-");
     //   Loader::getInstance()->infotext2->setTitle("§7- §rЦена §aзачарования§r предмета §a3,000§2$ §7-");

        Loader::getInstance()->casetext->setTitle("§l§8.:: §aКЕЙСЫ §8::.");
        Loader::getInstance()->casetext1->setTitle("§r§fНажмите, чтобы открыть меню!");

        Loader::getInstance()->info1->setTitle("§r§f ". Loader::SERVERNAME_FORMAT ."§r§f ");
        Loader::getInstance()->info2->setTitle("§r§f⨧ §rНаша группа VK: §b". self::VK ."§r§f ⨧");
        Loader::getInstance()->info3->setTitle("§r§f⨧ §rНаш телеграмм: §d". self::TG ."§r§f ⨧");
        Loader::getInstance()->info4->setTitle("§r§f⨧ §rНаш сайт сервера: §a". self::WEBSITE ."§r§f ⨧\n\n");
        Loader::getInstance()->info5->setTitle("§r§f §rВзять набор §a/kit start§r§f ");
        Loader::getInstance()->info6->setTitle("§r§f §rНачать выживать §a/rtp§r§f \n\n");

        $randomTexts = [
            '§r§f §rСпасибо за игру на сервере!§r§f ',
            '§r§f §rМы вас ЛЮБИМ, спасибо за игру!§r§f ',
            '§r§f §rВ нашем ТГК, часто идут конкурсы!§r§f ',
            '§r§f §rНа нашем сервере самые лучшие!§r§f '
        ];
        Loader::getInstance()->info7->setTitle("{$randomTexts[array_rand($randomTexts)]}");

        Loader::getInstance()->areashop1->setTitle("§r §l§aПЛОЩАДЬ ТОРГОВЛИ §r ");
        Loader::getInstance()->areashop2->setTitle("§7- §rЗдесь вы cможете: §7-");
        Loader::getInstance()->areashop3->setTitle("§7- §rПродовать, покупать, обменивать §7-");
        Loader::getInstance()->areashop4->setTitle("§7- §rВ §aмагазине§r есть §aуникальные§r ресурсы! §7-");
        Loader::getInstance()->areashop5->setTitle("§7- §rМножество крутых §aсистем§r вы найдете здесь. §7-");
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->areashop1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->areashop2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->areashop3);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->areashop4);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->areashop5);

        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->passtext1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->passtext2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->passtext3);

        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->duels2);

        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info3);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info4);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info5);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info6);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->info7);

        //Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->infotext1);
        //Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->infotext2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->casetext1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->grief);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->grief1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->grief2);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->grief3);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arenanews);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arena);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arena1);
        Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->arena2);
    }

    public function updateMineText(){
        $timeLeft = Loader::getInstance()->nextUpdate - time();
        $minetime = floor($timeLeft / 60);

        if (!isset($this->nextRarity)) {
                $this->nextRarity = mt_rand(1, 100);
            }
            $rarityName = $this->getRarityName($this->nextRarity);

            Loader::getInstance()->automine->setTitle("§r §l§e §r ");
            Loader::getInstance()->automine1->setTitle("§r⨞ §rОбновления авто-шахты через§7: §e{$minetime}м. §r⨟");
            Loader::getInstance()->automine2->setTitle("§r⨞ §rРедкость следующей авто-шахты§7: §e{$rarityName} §r⨟");

            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->automine);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->automine1);
            Server::getInstance()->getDefaultLevel()->addParticle(Loader::getInstance()->automine2);
    }

    public function getPrefix(Player $player) {
        $group = Loader::getInstance()->getGroup($player->getName());

        if ($group == "Kronos") {
            return "§l§dКронос§r";
        }elseif ($group == "Aristocrat") {
            return "§l§6Аристократ§r";
        }elseif ($group == "Lucefer") {
            return "§l§cЛюцифер§r";
        }elseif ($group == "Korol") {
            return "§l§dКороль§r";
        }elseif ($group == "Gresh") {
            return "§l§bЦарь§r";
        }elseif ($group == "Knayz") {
            return "§l§4Князь§r";
        }elseif ($group == "Admin") {
            return "§l§cВЛАДЕЛЕЦ";
        }elseif ($group == "Helper") {
            return "§l§bХелпер§r";
        }elseif ($group == "User") {
            return "§l§eИгрок§r";
        }elseif ($group == "Moderator") {
            return "§l§aMODERATOR§r";
        }elseif ($group == "Santa") {
            return "§l§cSANTA§r";
        }elseif ($group == "Patrik") {
            return "§l§aПАТРИК§r";
        }elseif ($group == "Yt") {
            return "§l§cYou§fTube§r";
        }elseif ($group == "Support") {
            return "§l§2SUPPORT§r";
        }elseif ($group == "Rabbit") {
            return "§l§9RABBIT§r";
        }
    }

    public function getDonateName($group) {
        if ($group == "Kronos") {
            return "§l§dКронос§r";
        }elseif ($group == "Aristocrat") {
            return "§l§6Аристократ§r";
        }elseif ($group == "Lucefer") {
            return "§l§cЛюцифер§r";
        }elseif ($group == "Korol") {
            return "§l§dКороль§r";
        }elseif ($group == "Gresh") {
            return "§l§bЦарь§r";
        }elseif ($group == "Knayz") {
            return "§l§4Князь§r";
        }elseif ($group == "Admin") {
            return "§l§cВЛАДЕЛЕЦ";
        }elseif ($group == "Helper") {
            return "§l§bХелпер§r";
        }elseif ($group == "User") {
            return "§l§eИгрок§r";
        }elseif ($group == "Moderator") {
            return "§l§aMODERATOR§r";
        }elseif ($group == "Santa") {
            return "§l§cSANTA§r";
        }elseif ($group == "Patrik") {
            return "§l§aПАТРИК§r";
        }elseif ($group == "Yt") {
            return "§l§cYou§fTube§r";
        }elseif ($group == "Support") {
            return "§l§2SUPPORT§r";
        }elseif ($group == "Kyrator") {
            return "§l§bKYRATOR§r";
        }elseif ($group == "Rabbit") {
            return "§l§9RABBIT§r";
        }
    }

    public function getWorld(Player $player): string {
        switch ($player->getlevel()->getFolderName()) {
            case 'spawn':
                return '§l§aЛобби';
            case 'world':
                return '§l§cГриферский';
            case 'arena':
                return '§l§bАрена';
            default:
                return "§cнеизвестно";
            }
    }

    public function getOpponents($playerName) {
        return isset($this->opponents[$playerName]) ? array_unique($this->opponents[$playerName]) : [];
    }

    public function addOpponent($playerName, $opponentName) {
        if (!isset($this->opponents[$playerName])) {
            $this->opponents[$playerName] = [];
        }

        if (!in_array($opponentName, $this->opponents[$playerName], true)) {
            $this->opponents[$playerName][] = $opponentName;
        }
    }

    public function removeOpponent($playerName, $opponentName) {
        if (isset($this->opponents[$playerName])) {
            foreach ($this->opponents[$playerName] as $key => $opponent) {
                if ($opponent === $opponentName) {
                    unset($this->opponents[$playerName][$key]);
                    break;
                }
            }
            $this->opponents[$playerName] = array_values($this->opponents[$playerName]);
        }
    }

    public function formatRemainingTime($seconds) {
        $days = floor($seconds / 86400);
        $formatted = "";

        if ($days > 0) {
            $formatted .= "§7[§7{$days}д.§7]§r";
        }
        return trim($formatted);
    }

    public function getRemainingTime($player) {
        $username = mb_strtolower($player);
        $expiryTime = Loader::getInstance()->timeranks->get($username);

        if ($expiryTime) {
            $remainingTime = $expiryTime - time();
            if ($remainingTime > 0) {
                return $this->formatRemainingTime($remainingTime);
            } else {
                Loader::getInstance()->ranks->remove($username);
                Loader::getInstance()->ranks->save();
                Loader::getInstance()->timeranks->remove($username);
                Loader::getInstance()->timeranks->save();
                return "User";
            }
        }
        return "";
    }

    public function setGroup($player, $group, $duration) {
        $username = mb_strtolower($player);

        if ($duration === "навсегда") {
            Loader::getInstance()->ranks->set($username, $group);
            Loader::getInstance()->timeranks->remove($username);
            Loader::getInstance()->timeranks->save();
        } else {
            $durationInDays = (int)$duration; 
            $expiryTime = time() + ($durationInDays * 86400); 
            Loader::getInstance()->ranks->set($username, $group);
            Loader::getInstance()->timeranks->set($username, $expiryTime);
        }
        Loader::getInstance()->ranks->save();
        Loader::getInstance()->timeranks->save();
    }


    public function getGroup($player) {
        $username = mb_strtolower($player);
        $playerData = Loader::getInstance()->ranks->get($username);

        if (!Loader::getInstance()->ranks->get($username)) {
            return "User";
        } else {
            return Loader::getInstance()->ranks->get($username);
        }

        $expiryTime = Loader::getInstance()->timeranks->get($username);
        if ($expiryTime && time() > $expiryTime) {
            Loader::getInstance()->ranks->remove($username);
            Loader::getInstance()->ranks->save();
            Loader::getInstance()->timeranks->remove($username);
            Loader::getInstance()->timeranks->save();
            $player->sendMessage("§l§8:: §cВаша временная привилегия закончилась, права удалены.");
            return "User";
        }
    }

    public function getPrefixClans(Player $player): string{
        $name = strtolower($player->getName());
        if(Loader::getInstance()->isInClan($player->getName())){
        $clan = Loader::getInstance()->getPlayerClan($player);
        if(is_array($clan)){
            $clanName = $clan['name'];
            return "§b" . $clanName;
        }else{
            return "§b" . $clan;
        }
      }
        return "§7...";
    }

    public static function createClan(Player $leader, string $clanname){
        self::$clans->set(strtolower($clanname), [
            "leader" => strtolower($leader->getName()),
            "name" => $clanname,
            "kills" => 0,
            "tag" => false,
            "date" => date("j.n.Y"),
            "home" => false,
            "banclan" => false,
            "members" => [],
            "officers" => [],
            "max-members" => 7,
            "coins" => 0,
            "level" => 1,
            "xp" => 0,
            "level-up" => [
                "xp" => 1500,
                "coins" => 10,
                "kills" => 5
            ]
        ]);
        self::$clans->save();
    }

    public static function isInClan(string $nick) : bool{
        $clans = self::$clans->getAll();
        $nick = strtolower($nick);
        foreach($clans as $clan){
            if($clan["leader"] === $nick) return true;
            foreach($clan["members"] as $name => $data){
                if($nick === $name) return true;
            }
            foreach($clan["officers"] as $name => $data){
                if($nick === $name) return true;
            }
        }
        return false;
    }

    public static function isClan(string $clanname) : bool {
        $existingClans = self::$clans->getAll();
        $clannameLower = strtolower($clanname);

        foreach ($existingClans as $existingClan) {
            if (strtolower($existingClan['name']) === $clannameLower || strpos(strtolower($existingClan['name']), $clannameLower) !== false) {
                return true;
            }
        }
        return false;
    }

    public static function isClanByTag(string $tag) : bool{
        $clans = self::$clans->getAll();
        foreach($clans as $clan){
            if(strtolower($clan["tag"]) === strtolower($tag)) return true;
        }
        return false;
    }

    public static function isClanHome(string $clanname) : bool{
        if(is_string(self::$clans->get(strtolower($clanname))["home"])) return true;
        return false;
    }

    public static function isClanBan(string $clanname) : bool {
        $clanData = self::$clans->get(strtolower($clanname));
        if ($clanData !== null && isset($clanData["banclan"])) {
            return (bool) $clanData["banclan"]; 
        }
        return false; 
    }


    public static function getPlayerClan($p){
        $clans = self::$clans->getAll();
        $nick = is_string($p) ? strtolower($p) : strtolower($p->getName());
        foreach($clans as $clan){
            if($clan["leader"] === $nick) return $clan;
            foreach($clan["members"] as $name => $data){
                if($nick === $name) return $clan;
            }
            foreach($clan["officers"] as $name => $data){
                if($nick === $name) return $clan;
            }
        }
    }

    public static function getClan(string $clanname) : array{
        return self::$clans->get(strtolower($clanname));
    }

    public static function getClanByTag(string $tag){
        $clans = self::$clans->getAll();
        foreach($clans as $clan){
            if(strtolower($clan["tag"]) === strtolower($tag)) return $clan;
        }
    }

    public static function getClanOfficers(string $clanname) : array{
        return self::$clans->get(strtolower($clanname))["officers"];
    }

    public static function getClanMembers(string $clanname) : array{
        return self::$clans->get(strtolower($clanname))["members"];
    }

    public static function getClanLeader(string $clanname) : string{
        return self::$clans->get(strtolower($clanname))["leader"];
    }

    public static function removeClan(string $clanname){
        self::$clans->remove(strtolower($clanname));
        self::$clans->save();
    }

    public static function isLeader($p) : bool{
        $nick = is_string($p) ? strtolower($p) : strtolower($p->getName());
        return Loader::getPlayerClan($nick)["leader"] === $nick;
    }
    public static function isOfficer($p) : bool{
        $nick = is_string($p) ? strtolower($p) : strtolower($p->getName());
        $array = Loader::getPlayerClan($nick)["officers"];
        foreach($array as $name => $data){
            if($name === $nick) return true;
        }
        return false;
    }

    public static function isMember($p) : bool{
        $nick = is_string($p) ? strtolower($p) : strtolower($p->getName());
        $array = Loader::getPlayerClan($nick)["members"];
        foreach($array as $name => $data){
            if($name === $nick) return true;
        }
        return false;
    }


    public static function addMember(string $nick, string $clanname){
        $clan = self::$clans->get(strtolower($clanname));
        $clan["members"][strtolower($nick)] = [
            "nick" => $nick,
            "date" => date("j.n.Y"),
            "kills" => "0",
        ];
        self::$clans->set(strtolower($clanname), $clan);
        self::$clans->save();
    }
    public static function addOfficer(string $nick, string $clanname){
        $clan = self::$clans->get(strtolower($clanname));
        $clan["officers"][strtolower($nick)] = [
            "nick" => $nick,
            "date" => date("j.n.Y"),
            "kills" => "0",
        ];
        self::$clans->set(strtolower($clanname), $clan);
        self::$clans->save();
    }

    public static function removeMember(string $nick, string $clanname, $upgrade = false){
        $clan = self::$clans->get(strtolower($clanname));
        $nick = strtolower($nick);
        if($upgrade){
            $clan["officers"][$nick] = [
                "nick" => $nick,
                "date" => $clan["members"][$nick]["date"],
            ];
        } else {
            //$clan['kills'] = $clan['kills'] - Stats::getInstance()->arr["player"][strtolower($nick)]; 
        }
        unset($clan["members"][$nick]);
        self::$clans->set(strtolower($clanname), $clan);
        self::$clans->save();
    }
    public static function removeOfficer(string $nick, string $clanname, $downgrade = false){
        $clan = self::$clans->get(strtolower($clanname));
        $nick = strtolower($nick);
        if($downgrade){
            $clan["members"][$nick] = [
                "nick" => $nick,
                "date" => $clan["officers"][$nick]["date"],
                "kills" => $clan["officers"][$nick]["kills"]
            ];
        }
        unset($clan["officers"][$nick]);
        self::$clans->set(strtolower($clanname), $clan);
        self::$clans->save();
    }

    public function getPrefixTitul(Player $player): string {
        if (Loader::getInstance()->getTitul($player, "titulstatus") == "on"){
            return "{$this->getTitul($player, "titul")}";
        }else{
            return "§cнету§r";
        }
    }

    public function getWin($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT win FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["win"];
    }

    public function addWin($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `win` = `win` +{$count} WHERE `name` = '$name'");
    }

    public function givePass($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `pass` = `pass` +{$count} WHERE `name` = '$name'");
    }

    public function getPass($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT pass FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["pass"];
    }

    public function getTitul($p, $cfg) {
        $name = $p->getLowerCaseName();
        if (!Loader::getInstance()->titul->get($name)) {
            return "off";
        }
        return Loader::getInstance()->titul->get($name)[$cfg];
    }

    public function addMoneyKey($p, $count) {
        $name = $p->getLowerCaseName();
        $p->sendMessage("§l§eКейсы §8:: §rНа ваш аккаунт зачислен §r§l§eДенежный§7-§eКейс §7x{$count}");
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `moneycase` = `moneycase` +{$count} WHERE `name` = '$name'");
    }

    public function addMoneyKeyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `moneycase` = `moneycase` +{$count} WHERE `name` = '$name'");
    }

    public function getMoneyKey($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->dbc->query("SELECT moneycase FROM cases WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["moneycase"];
    }

    public function remMoneyKey($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `moneycase` = `moneycase` -{$count} WHERE `name` = '$name'");
    }

    public function addNewyearKey($p, $count) {
        $name = $p->getLowerCaseName();
        $p->sendMessage("§l§eКейсы §8:: §rНа ваш аккаунт зачислен §r§l§cНовогодний§7-§cКейс §7x{$count}");
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `newyearcase` = `newyearcase` +{$count} WHERE `name` = '$name'");
    }

    public function addNewyearKeyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `newyearcase` = `newyearcase` +{$count} WHERE `name` = '$name'");
    }

    public function getNewyearKey($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->dbc->query("SELECT newyearcase FROM cases WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["newyearcase"];
    }

    public function remNewyearKey($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `newyearcase` = `newyearcase` -{$count} WHERE `name` = '$name'");
    }

    public function addPrefixKey($p, $count) {
        $name = $p->getLowerCaseName();
        $p->sendMessage("§l§eКейсы §8:: §rНа ваш аккаунт зачислен §r§l§bПрефикс§7-§bКейс §7x{$count}");
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `prefixcase` = `prefixcase` +{$count} WHERE `name` = '$name'");
    }

    public function addPrefixKeyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `prefixcase` = `prefixcase` +{$count} WHERE `name` = '$name'");
    }

    public function getPrefixKey($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->dbc->query("SELECT prefixcase FROM cases WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["prefixcase"];
    }

    public function remPrefixKey($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `prefixcase` = `prefixcase` -{$count} WHERE `name` = '$name'");
    }

    public function addDonateKey($p, $count) {
        $name = $p->getLowerCaseName();
        $p->sendMessage("§l§eКейсы §8:: §rНа ваш аккаунт зачислен §r§l§aДонат§7-§aКейс §7x{$count}");
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `donatecase` = `donatecase` +{$count} WHERE `name` = '$name'");
    }

    public function addDonateKeyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `donatecase` = `donatecase` +{$count} WHERE `name` = '$name'");
    }

    public function getDonateKey($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->dbc->query("SELECT donatecase FROM cases WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["donatecase"];
    }

    public function remDonateKey($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->dbc->query("UPDATE `cases` SET `donatecase` = `donatecase` -{$count} WHERE `name` = '$name'");
    }

    public function addRubSite($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `topdonater` = `topdonater` +{$count} WHERE `name` = '$name'");
    }

    public function getRubSite($p) {
        $name = mb_strtolower($p);
        $result = Loader::getInstance()->db->query("SELECT topdonater FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["topdonater"];
    }

    public function addLvlbuyer($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `key` = `key` +{$count} WHERE `name` = '$name'");
    }

    public function getLvlbuyer($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT key FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["key"];
    }

    public function remLvlbuyer($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvlminer` = `lvlminer` -{$count} WHERE `name` = '$name'");
    }

    public function addExpbuyer($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `expminer` = `expminer` +{$count} WHERE `name` = '$name'");
    }

    public function getExpbuyer($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT expminer FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["expminer"];
    }

    public function remExpbuyer($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `expminer` = `expminer` -{$count} WHERE `name` = '$name'");
    }

    public function addExps($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `exps` = `exps` +{$count} WHERE `name` = '$name'");
    }

    public function getExps($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT exps FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["exps"];
    }

    public function remExps($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `exps` = `exps` -{$count} WHERE `name` = '$name'");
    }

    public function addLvls($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvls` = `lvls` +{$count} WHERE `name` = '$name'");
    }

    public function getLvls($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT lvls FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["lvls"];
    }

    public function remLvls($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvls` = `lvls` -{$count} WHERE `name` = '$name'");
    }

    public function addLvl($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvl` = `lvl` +{$count} WHERE `name` = '$name'");
    }

    public function getLvl($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT lvl FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["lvl"];
    }

    public function remLvl($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvl` = `lvl` -{$count} WHERE `name` = '$name'");
    }

    public function remLvll($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `lvl` = `lvl` -{$count} WHERE `name` = '$name'");
    }

    public function addExp($p, $count) {
        $name = $p->getLowerCaseName();
        //$p->sendMessage("§l§dБаланс §l§6➛ §r §rНа ваш баланс зачислено §d" . number_format($count) . "§5exp");
        Loader::getInstance()->db->query("UPDATE `datebase` SET `exp` = `exp` +{$count} WHERE `name` = '$name'");
    }

    public function getExp($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT exp FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["exp"];
    }

    public function remExp($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `exp` = `exp` -{$count} WHERE `name` = '$name'");
    }

    public function addKills($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `kills` = `kills` +{$count} WHERE `name` = '$name'");
    }

    public function getKills($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT kills FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["kills"];
    }

    public function remKills($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `kills` = `kills` -{$count} WHERE `name` = '$name'");
    }

    public function addDeath($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `death` = `death` +{$count} WHERE `name` = '$name'");
    }

    public function getDeath($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT death FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["death"];
    }

    public function remDeath($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `death` = `death` -{$count} WHERE `name` = '$name'");
    }

    public function addMoney($p, $count) {
        $name = $p->getLowerCaseName();
        //$p->sendMessage("§l§aБаланс §l§6➛ §r §rНа ваш баланс зачислено §a" . number_format($count) . "§2$");
        Loader::getInstance()->db->query("UPDATE `datebase` SET `money` = `money` +{$count} WHERE `name` = '$name'");
    }

    public function getMoneyy($p) {
        $name = mb_strtolower($p);
        $result = Loader::getInstance()->db->query("SELECT money FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["money"];
    }

    public function getMoney($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT money FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        $rubles = $result["money"];
        return $rubles;
    }
  
    public function remMoney($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `money` = `money` -{$count} WHERE `name` = '$name'");
    }

    public function addMoneyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `money` = `money` +{$count} WHERE `name` = '$name'");
    }

    public function remMoneyy($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `money` = `money` -{$count} WHERE `name` = '$name'");
    }

    public function addRub($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `rub` = `rub` +{$count} WHERE `name` = '$name'");
    }

    public function getRubb($p) {
        $name = mb_strtolower($p);
        $result = Loader::getInstance()->db->query("SELECT rub FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        return $result["rub"];
    }

    public function getRub($p) {
        $name = $p->getLowerCaseName();
        $result = Loader::getInstance()->db->query("SELECT rub FROM datebase WHERE name = '$name'")->fetchArray(SQLITE3_ASSOC);
        $rubles = $result["rub"];
        return $rubles;
    }

    public function remRub($p, $count) {
        $name = $p->getLowerCaseName();
        Loader::getInstance()->db->query("UPDATE `datebase` SET `rub` = `rub` -{$count} WHERE `name` = '$name'");
    }

    public function addRubb($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `rub` = `rub` +{$count} WHERE `name` = '$name'");
    }

    public function remRubb($p, $count) {
        $name = mb_strtolower($p);
        Loader::getInstance()->db->query("UPDATE `datebase` SET `rub` = `rub` -{$count} WHERE `name` = '$name'");
    }

     public function updatePermissions($p, $group) {
        $this->perms = [];
        if ($p instanceof Player) {
            $a = $p->addAttachment($this);
            $a->clearPermissions();
            switch ($group) {
                case 'User':
                    $this->perms = [
                        "api.kit.user" => true,
                    ];
                break;

                case "Kronos":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.rtp" => true,
                    ];
                    break;

                case "Aristocrat":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.arist" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.rtp" => true,
                        "api.cmd.size" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "Lucefer":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "korol":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.korol" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "Gresh":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.king" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "Knayz":
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "Admin":
                    $this->perms = [
                        "api.join.use" => true,
                        "list.helper" => true,
                        "api.cmd.fly" => true,
                        "helper.system" => true,
                        "api.helper.setting" => true,
                        "api.kit.user" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                    break;

                case "Moderator":
                    $this->perms = [
                        "api.join.use" => true,
                        "list.helper" => true,
                        "api.cmd.fly" => true,
                        "api.helper.setting" => true,
                        "helper.system" => true,
                        "api.kit.user" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.proverka" => true,
                    ];
                break;

                case "Support":
                    $this->perms = [
                        "api.join.use" => true,
                        "list.helper" => true,
                        "api.cmd.fly" => true,
                        "helper.system" => true,
                        "api.kit.user" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                        "api.cmd.proverka" => true,
                    ];
                break;

                case "Helper":
                    $this->perms = [
                        "api.join.use" => true,
                        "list.helper" => true,
                        "api.cmd.fly" => true,
                        "helper.system" => true,
                        "api.kit.user" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                        "api.cmd.proverka" => true,
                    ];
                break;

                case 'Patrik':
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];
                break;

                case 'Santa':
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                    ];
                break;

                case 'Yt':
                    $this->perms = [
                        "api.join.use" => true,
                        "api.cmd.fly" => true,
                        "api.kit.user" => true,
                        "api.kit.korol" => true,
                        "api.kit.arist" => true,
                        "api.kit.luc" => true,
                        "api.kit.kronos" => true,
                        "api.kit.king" => true,
                        "api.kit.knayz" => true,
                        "api.kit.patrik" => true,
                        "api.cmd.gm" => true,
                        "api.cmd.feed" => true,
                        "api.cmd.heal" => true,
                        "api.cmd.clear" => true,
                        "api.cmd.day" => true,
                        "api.cmd.night" => true,
                        "api.rtp" => true,
                        "api.rtp.near" => true,
                        "api.cmd.size" => true,
                        "api.cmd.tp" => true,
                        "api.cmd.kick" => true,
                        "api.cmd.unmute" => true,
                        "api.cmd.mute" => true,
                        "api.cmd.pardon" => true,
                        "api.cmd.ban" => true,
                        "api.cmd.invsee" => true,
                        "api.cmd.autototem" => true,
                        "api.cmd.console" => true,
                        "api.english.allow" => true,
                        "api.cmd.repairall" => true,
                        "api.cmd.vanish" => true,
                        "api.cmd.back" => true,
                    ];  
                break;

                case "Kyrator":
                   $this->perms = [
                       "api.join.use" => true,
                       "list.helper" => true,
                       "api.cmd.fly" => true,
                       "helper.system" => true,
                       "api.helper.setting" => true,
                       "api.kit.user" => true,
                       "api.cmd.fly" => true,
                       "api.kit.user" => true,
                       "api.kit.korol" => true,
                       "api.kit.arist" => true,
                       "api.kit.luc" => true,
                       "api.kit.kronos" => true,
                       "api.kit.king" => true,
                       "api.kit.knayz" => true,
                       "api.kit.patrik" => true,
                       "api.cmd.gm" => true,
                       "api.cmd.feed" => true,
                       "api.cmd.heal" => true,
                       "api.cmd.clear" => true,
                       "api.cmd.day" => true,
                       "api.cmd.night" => true,
                       "api.rtp" => true,
                       "api.rtp.near" => true,
                       "api.cmd.size" => true,
                       "api.cmd.tp" => true,
                       "api.cmd.kick" => true,
                       "api.cmd.unmute" => true,
                       "api.cmd.mute" => true,
                       "api.cmd.pardon" => true,
                       "api.cmd.ban" => true,
                       "api.cmd.invsee" => true,
                       "api.cmd.autototem" => true,
                       "api.cmd.console" => true,
                       "api.english.allow" => true,
                       "api.cmd.repairall" => true,
                       "api.cmd.vanish" => true,
                       "api.cmd.back" => true,
                   ];
               break;
                
            }
            $a->setPermissions($this->perms);
        }
    }


    public function fakeOpen(Player $player, Vector3 $position, bool $value = true)
    {
        $viewers = [];
        $pk = new BlockEventPacket();
        $pk->x = $position->getX();
        $pk->y = $position->getY();
        $pk->z = $position->getZ();
        $pk->case1 = 1;
        if ($value) {
            $pk->case2 = 2;
        } else {
            $pk->case2 = 0;
        }
        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if ($players->distance($position) >= 35) {
                continue;
            }
            $viewers[] = $players;
            $players->dataPacket($pk);
        }

        if ($value == true) {
            $this->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "fakeOpen"), array($player, $position, false)), 20 * 5);
        }
    }

    public function addFloatingText(Vector3 $position, $floatingText){
        $viewers = [];
        $floatEid = Entity::$entityCount++;
        $packet = new AddPlayerPacket();
        $packet->uuid = UUID::fromRandom();
        $packet->username = $floatingText;
        $packet->eid = $floatEid;
        $packet->x = $position->getX() + 0.5;
        $packet->y = $position->getY() + 1.2;
        $packet->z = $position->getZ() + 0.5;
        $packet->item = Item::get(Item::AIR);
        $flags = (
            (1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_IMMOBILE)
        );
        $packet->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $floatingText],
            Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0],
        ];

        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if ($players->distance($position) >= 35) {
                continue;
            }
            $viewers[] = $players;
            $players->dataPacket($packet);
        }
        $this->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "clearEntityPacket"), array($viewers, $floatEid)), 20 * 5);
    }

    public function clearEntityPacket(array $players, $eid){
        foreach ($players as $player) {
            $packet = new \pocketmine\network\mcpe\protocol\RemoveEntityPacket();
            $packet->eid = $eid;
            $player->dataPacket($packet);
        }
    }

    public function parseTime($time) {
        //$time = $time - time();
        switch ($time) {
            default:
                $left    = $time;
                $seconds = $left % 60;
                $minutes = (int) ($left / 60);
                if ($minutes >= 60) {
                    $hours   = (int) ($minutes / 60);
                    $minutes = $minutes % 60;
                }
                if (@$hours >= 24) {
                    $days  = (int) ($hours / 24);
                    $hours = $hours % 24;
                }
                $timeLeft = $seconds . "с.";
                $timeLeft = $minutes . "м. " . $timeLeft;
                if (isset($hours))
                    $timeLeft = $hours . "ч. " . $timeLeft;
                if (isset($days))
                    $timeLeft = $days . "д. " . $timeLeft;
                return $timeLeft;
                break;
        }
    }
}
?>