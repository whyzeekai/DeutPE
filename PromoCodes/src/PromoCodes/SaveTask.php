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

use pocketmine\scheduler\PluginTask;

class SaveTask extends PluginTask {
    
    /** @var mixed */
    private $utils;
    
    public function __construct($plugin, $utils) {
        
        parent::__construct($plugin);
        
        $this->utils = $utils;
        
    }
    
    public function onRun($tick) {
        
        $this->utils->saveActivates();
        
    }
    
}