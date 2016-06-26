<?php
namespace ORCore\task;

use pocketmine\scheduler\PluginTask;
use ORCore\ORCore;

class MySQLPingTask extends PluginTask{

	/** @var \mysqli */
	private $database;

	public function __construct(ORCore $owner, \mysqli $database){
		parent::__construct($owner);
		$this->database = $database;
	}

	public function onRun($currentTick){
		$this->database->ping();
	}
}