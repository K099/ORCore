<?php
namespace ORCore\event;

use pocketmine\event\plugin\PluginEvent;
use ORCore\ORCore;

abstract class ORCoreEvent extends PluginEvent{

	/**
	 * @param ORCore $plugin
	 */
	public function __construct(ORCore $plugin){
		parent::__construct($plugin);
	}
}