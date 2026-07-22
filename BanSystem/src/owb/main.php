<?php

namespace owb;

use owb\api\{database, utils, vk};
use owb\commands\{kickCmd, muteCmd, pardonCmd, unmuteCmd, banCmd,};
use owb\listener\elist;
use owb\task\comments;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\utils\Config;

class main extends PluginBase implements Listener
{
    protected static $instance;
    public static $tag_msg = ''; //Тег перед сообщением в чате
    
    /** @var \SQLite3|null */
    public $bd = null;
    /** @var Config|null */
    public $messages = null;
    /** @var mixed|null */
    public $db = null;
    /** @var mixed|null */
    public $vk = null;
    /** @var mixed|null */
    public $utils = null;

    public static function getInstance() : main
    {
        return self::$instance;
    }

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getPluginManager()->registerEvents(new elist($this), $this);
       $this->getScheduler()->scheduleRepeatingTask(new comments($this), 20 * 5); //каждые 5 секунд проверяет комментарии
        $this->getServer()->getCommandMap()->register('ban', new banCmd($this));
        $this->getServer()->getCommandMap()->register('pardon', new pardonCmd($this));
        $this->getServer()->getCommandMap()->register('kick', new kickCmd($this));
        $this->getServer()->getCommandMap()->register('kick', new muteCmd($this));
        $this->getServer()->getCommandMap()->register('kick', new unmuteCmd($this));
        $this->bd = new \SQLite3($this->getDataFolder() . 'owb.db');
        $this->messages = new Config($this->getDataFolder() . "messages.yml", Config::YAML);
        self::$instance = $this;
        $this->db = new database($this);
        $this->vk = new vk($this);
        $this->utils = new utils($this);
    }
}