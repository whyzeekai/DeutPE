<?php namespace PromoCodes;

/*
 *  _____
 * |_   _|   _ _ __   __ _  _____   __
 *   | || | | | '_ \ / _` |/ _ \ \ / /
 *   | || |_| | | | | (_| |  __/\ V /
 *   |_| \__, |_| |_|\__,_|\___| \_/
 *       |___/
 * 
 *  VK -> vk.com/tynaev_vk
 *  TG -> t.me/tynaev_tg
 * 
 *  Специально для сообщества FortifieMix
*/

use PromoCodes\Utils;

use PromoCodes\SaveTask;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\Config;

use pocketmine\Player;

class Loader extends PluginBase {
    
    public $activates_file, $promocodes_file, $utils;
    
    private $commands, $promocodes, $activates, $messages;
    
    public function onEnable() : void {
        
        $this->loadResources();
        
        $this->loadPromocodes();
        
        $this->utils = new Utils($this, $this->promocodes, $this->messages, $this->activates);
        
        $this->registerCommands();
        
        $this->getScheduler()->scheduleRepeatingTask(new SaveTask($this, $this->getUtils()), 20 * 60);
        
    }
    
    public function onDisable() : void {
        
        $this->getUtils()->saveActivates();
        
    }
    
    private function loadResources() : void {
        
        @mkdir($this->getDataFolder());
        
        $this->saveResource("commands.yml");
        
        $this->saveResource("promocodes.yml");
        
        $this->saveResource("messages.yml");
        
        $this->commands = (new Config($this->getDataFolder() . "commands.yml", Config::YAML))->getAll();
        
        $this->activates_file = new Config($this->getDataFolder() . "activates.yml", Config::YAML);
        
        $this->messages = (new Config($this->getDataFolder() . "messages.yml", Config::YAML))->getAll();
        
    }
    
    private function registerCommands() : void {
        
        $map = $this->getServer()->getCommandMap();
        
        $commands = [
            $this->commands["activate"]["command"] => ["PromoCodes\\commands\\ActivatePromocode", $this->commands["activate"]["permission"], $this->commands["activate"]["description"]],
            $this->commands["create"]["command"] => ["PromoCodes\\commands\\CreatePromocode", $this->commands["create"]["permission"], $this->commands["create"]["description"]],
            $this->commands["delete"]["command"] => ["PromoCodes\\commands\\DeletePromocode", $this->commands["delete"]["permission"], $this->commands["delete"]["description"]],
            $this->commands["list"]["command"] => ["PromoCodes\\commands\\PromocodesList", $this->commands["list"]["permission"], $this->commands["list"]["description"]]
        ];
        
        foreach($commands as $command => $data) {
            
            $map->register("PromoCodes", new $data[0]($this, $command, $data[1], $data[2]));
            
        }
        
    }
    
    private function loadPromocodes() : void {
        
        $this->promocodes_file = new Config($this->getDataFolder() . "promocodes.yml", Config::YAML);
        
        $this->promocodes = [];
        
        foreach($this->promocodes_file->getAll() as $promocode => $data) {
            
            $this->promocodes[strtolower($promocode)] = $data;
            
        }
        
        $this->activates = $this->activates_file->getAll();
        
    }
    
    public function getUtils() : Utils {
        
        return $this->utils;
        
    }
    
}