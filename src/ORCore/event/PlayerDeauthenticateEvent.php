<?php
namespace ORCore\event;

use pocketmine\event\Cancellable;
use pocketmine\Player;
use ORCore\ORCore;

class PlayerDeauthenticateEvent extends ORCoreEvent implements Cancellable{
	public static $handlerList = null;


	/** @var Player */
	private $player;

	/**
	 * @param ORCore $plugin
	 * @param Player     $player
	 */
	public function __construct(ORCore $plugin, Player $player){
		$this->player = $player;
		parent::__construct($plugin);
	}

	/**
	 * @return Player
	 */
	public function getPlayer(){
		return $this->player;
	}
}