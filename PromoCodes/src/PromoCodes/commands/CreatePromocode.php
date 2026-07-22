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

class CreatePromocode extends Command {
    
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
        
        if(count($args) != 2) {
            
            $sender->sendMessage($this->messages["create_usage"]);
            
            return false;
            
        }
        
        if(!preg_match("/^[a-zA-Z0-9\-_]+$/", $args[0])) {
            
            $sender->sendMessage($this->messages["create_promocode_wrong"]);
            
            return false;
            
        }
        
        if(!is_numeric($args[1])) {
            
            $sender->sendMessage($this->messages["create_limit_wrong"]);
            
            return false;
            
        }
        
        if($this->utils->promocodeExists($args[0])) {
            
            $sender->sendMessage($this->messages["create_promocode_exists"]);
            
            return false;
            
        }
        
        $this->utils->createNewPromocode($args[0], $args[1]);
        
        $sender->sendMessage($this->messages["create_successful"]);
        
        return true;
        
    }
    
}