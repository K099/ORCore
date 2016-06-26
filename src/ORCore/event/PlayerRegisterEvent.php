<?php

namespace ORCore\event;

use pocketmine\event\Cancellable;
use pocketmine\IPlayer;
use ORCore\ORCore;

class PlayerRegisterEvent extends ORCoreEvent implements Cancellable{
	public static $handlerList = null;

	/** @var IPlayer */
	private $player;

	/**
	 * @param ORCore $plugin
	 * @param IPlayer    $player
	 */
	public function __construct(ORCore $plugin, IPlayer $player){
		$this->player = $player;
		parent::__construct($plugin);
	}

	/**
	 * @return IPlayer
	 */
	public function getPlayer(){
		return $this->player;
	}
}