<?php
namespace ORCore\task;

use pocketmine\Player;
use pocketmine\scheduler\PluginTask;
use ORCore\ORCore;

class ShowMessageTask extends PluginTask{

	/** @var Player[] */
	private $playerList = [];

	public function __construct(ORCore $plugin){
		parent::__construct($plugin);
	}

	/**
	 * @return ORCore
	 */
	public function getPlugin(){
		return $this->owner;
	}

	public function addPlayer(Player $player){
		$this->playerList[$player->getClientSecret()] = $player;
	}

	public function removePlayer(Player $player){
		unset($this->playerList[$player->getClientSecret()]);
	}

	public function onRun($currentTick){
		$plugin = $this->getPlugin();
		if($plugin->isDisabled()){
			return;
		}
	}
}
