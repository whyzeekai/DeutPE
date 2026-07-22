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

class PromocodesList extends Command {
    
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
        
        $promocodes = "";
        
        $activates = $this->utils->getActivates();
        
        foreach($this->utils->getPromocodes() as $promocode => $data) {
            
            $status = $this->utils->isExpired($promocode) ? "Просрочен" : "Актуален";
            
            $promocodes .= str_replace([
                "@promocode",
                "@status",
                "@activates",
                "@limit"
            ],
            [
                $promocode,
                $status,
                isset($activates[$promocode]) ? count($activates[$promocode]) : 0,
                $data["limit"]
            ],
            $this->messages["list_text_line"]) . "\n";
            
        }
        
        $sender->sendMessage($this->messages["list_title"]);
        
        $sender->sendMessage($promocodes);
        
        return true;
        
    }
    
}