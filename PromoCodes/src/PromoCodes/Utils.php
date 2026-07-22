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

use pocketmine\command\ConsoleCommandSender;

use pocketmine\Player;

class Utils {
    
    public $promocodes, $activates, $messages;
    
    private $loader;
    
    public function __construct($plugin, array $promocodes, array $messages, array $activates) {
        
        $this->loader = $plugin;
        
        $this->promocodes = $promocodes;
        
        $this->messages = $messages;
        
        $this->activates = $activates;
        
    }
    
    public function saveActivates() : void {
        
        $this->loader->activates_file->setAll($this->activates);
        
        $this->loader->activates_file->save();
        
    }
    
    public function getMessages() : array {
        
        return $this->messages;
        
    }
    
    public function getActivates() : array {
        
        return $this->activates;
        
    }
    
    public function getPromocodes() : array {
        
        return $this->promocodes;
        
    }
    
    public function promocodeExists(string $promocode) : bool {
        
        $promocode = strtolower($promocode);
        
        return isset($this->promocodes[$promocode]);
        
    }
    
    public function createNewPromocode(string $promocode, int $limit) : void {
        
        $promocode = strtolower($promocode);
        
        $date = date("Y-m-d H:i:s", strtotime("+24 hours", time()));
        
        $data = ["limit" => $limit, "date" => $date, "commands" => ["gamemode 1 @player"]];
        
        $this->loader->promocodes_file->set($promocode, $data);
        
        $this->loader->promocodes_file->save();
        
        $this->promocodes[$promocode] = $data;
        
    }
    
    public function deletePromocode(string $promocode) : void {
        
        $promocode = strtolower($promocode);
        
        $this->loader->promocodes_file->remove($promocode);
        
        $this->loader->promocodes_file->save();
        
        unset($this->promocodes[$promocode]);
        
    }
    
    public function isExpired(string $promocode) : bool {
        
        $promocode = strtolower($promocode);
        
        return time() > strtotime($this->promocodes[$promocode]["date"]);
        
    }
    
    public function checkPromocode(Player $player, string $promocode) : string {
        
        $promocode = strtolower($promocode);
        
        if(!$this->promocodeExists($promocode)) {
            
            return "not_exists";
            
        }
        
        if(!isset($this->activates[$promocode])) {
            
            $this->activates[$promocode] = [];
            
        }
        
        $name = strtolower($player->getName());
        
        if(in_array($name, $this->activates[$promocode])) {
            
            return "used";
            
        }
        
        if($this->isExpired($promocode)) {
            
            return "expired";
            
        }
        
        if(count($this->activates[$promocode]) >= $this->promocodes[$promocode]["limit"]) {
            
            return "limit";
            
        }
        
        return "successful";
        
    }
    
    public function activatePromocode(Player $player, string $promocode) : void {
        
        $promocode = strtolower($promocode);
        
        foreach($this->promocodes[$promocode]["commands"] as $command) {
            
            $this->loader->getServer()->dispatchCommand(new ConsoleCommandSender(), str_replace(["@player", "@ip"], [$player->getName(), $player->getAddress()], $command));
            
        }
        
        $this->activates[$promocode][] = strtolower($player->getName());
        
    }
    
}