<?php namespace Tynaev\GmInventory;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerGameModeChangeEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\utils\TextFormat as F;
use pocketmine\Server;
use pocketmine\item\Item;
use pocketmine\inventory\OffhandInventory;

class Main extends PluginBase implements Listener{
    
    public $inv = [];

    public function onEnable(): void {
        Server::getInstance()->getPluginManager()->registerEvents($this, $this);
        if(file_exists($this->getDataFolder() . "inventories.txt")){
            $data = file_get_contents($this->getDataFolder() . "inventories.txt");
            $this->inv = unserialize($data);
        }
        $this->getScheduler()->scheduleRepeatingTask(new CallbackTask(array($this, "saveInventories")), 20 * 60);
    }

    public function saveInventories(){
        $data = serialize($this->inv);
        file_put_contents($this->getDataFolder() . "inventories.txt", $data);
    }

    public function loadInventory($player, $gm){
        $name = strtolower($player->getName());
        
        if(isset($this->inv[$name]["armor_{$gm}"])) $player->getArmorInventory()->setContents($this->inv[$name]["armor_{$gm}"]);
        if(isset($this->inv[$name]["items_{$gm}"])) $player->getInventory()->setContents($this->inv[$name]["items_{$gm}"]);
        if(isset($this->inv[$name]["offhand_{$gm}"])) $player->getOffhandInventory()->setItemInOffhand($this->inv[$name]["offhand_{$gm}"]);
    }

    public function onGamemodeChange(PlayerGameModeChangeEvent $event){
    $player = $event->getPlayer();
    $name = strtolower($player->getName());
    $gm = $player->getGamemode();

    $this->inv[$name]["armor_{$gm}"] = $player->getArmorInventory()->getContents();
    $this->inv[$name]["items_{$gm}"] = $player->getInventory()->getContents();
    $this->inv[$name]["offhand_{$gm}"] = $player->getOffhandInventory()->getItemInOffhand();

    $player->getInventory()->clearAll(); 
    $player->getOffhandInventory()->clearAll();    

    $this->getScheduler()->scheduleDelayedTask(
        new CallbackTask(array($this, "loadInventory"), array($player, $event->getNewGamemode())),
        1
    );
}



    public function onDisable(): void {
        $this->saveInventories();
    }
}