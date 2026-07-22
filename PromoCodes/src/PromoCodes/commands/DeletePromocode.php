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

class DeletePromocode extends Command {
    
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
    
    public function execute(CommandSender $sender, $label, array $args) {
        
        if(!$this->testPermission($sender)) {
            
            return false;
            
        }
        
        if(count($args) != 1) {
            
            $sender->sendMessage($this->messages["delete_usage"]);
            
            return false;
            
        }
        
        if(!$this->utils->promocodeExists($args[0])) {
            
            $sender->sendMessage($this->messages["delete_not_exists"]);
            
            return false;
            
        }
        
        $this->utils->deletePromocode($args[0]);
        
        $sender->sendMessage($this->messages["delete_successful"]);
        
        return true;
        
    }
    
}