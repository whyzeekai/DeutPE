<?php namespace PromoCodes\commands;

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

use pocketmine\command\Command;

use pocketmine\command\CommandSender;

use pocketmine\Player;

class ActivatePromocode extends Command {
    
    private $loader;
    private $utils;
    private $messages;
    
    public function __construct($plugin, $command, $permission, $description) {
        
        $this->loader = $plugin;
        
        parent::__construct($command, $description);
        
        $this->setPermission($permission);
        
        $this->utils = $this->loader->getUtils();
        
        $this->messages = $this->utils->getMessages();
        
    }
    
    public function execute(CommandSender $sender, $label, array $args) : bool {
        
        if(!$this->testPermission($sender)) {
            
            return false;
            
        }
        
        
        if(!$sender instanceof Player) {
            
            $sender->sendMessage($this->messages["console_not_allowed"]);
            
            return false;
            
        }
        
        if(count($args) != 1) {
            
            $this->sendAnswer($sender, "usage");
            
            return false;
            
        }
        
        if(($type = $this->utils->checkPromocode($sender, $args[0])) != "successful") {
            
            $this->sendAnswer($sender, $type);
            
            return false;
            
        }
        
        $this->utils->activatePromocode($sender, $args[0]);
        
        $this->sendAnswer($sender, "successful");
        
        return true;
        
    }
    
    private function sendAnswer(Player $player, string $type) : void {
        
        $messages = $this->messages[$type];
        
        $player->sendTitle($messages["title"], $messages["subtitle"]);
        
        $player->sendPopup($messages["popup"]);
        
        $player->sendTip($messages["tip"]);
        
        $player->sendMessage($messages["message"]);
        
    }
    
}