<?php

namespace Duels;

use pocketmine\scheduler\PluginTask;

class Task extends PluginTask
{
	public $pg;
	
	public function __construct(Main $pg){
		$this->pg = $pg;
		parent::__construct($pg);
	}
	
	public function onRun($ticks){
		foreach($this->pg->arenas as $name => $class){
			$class->tick();
		}
	}
}
