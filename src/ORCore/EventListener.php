<?php
namespace ORCore;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;

class EventListener implements Listener{
	/** @var ORCore */
	private $plugin;

	public function __construct(ORCore $plugin){
		$this->plugin = $plugin;
	}

	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority LOWEST
	 */
	public function onPlayerJoin(PlayerJoinEvent $event){
		if($this->plugin->getConfig()->get("authenticateByLastUniqueId") === true and $event->getPlayer()->hasPermission("orcore.lastid")){
			$config = $this->plugin->getDataProvider()->getPlayer($event->getPlayer());
			if($config !== null and $config["lastip"] === $event->getPlayer()->getClientSecret()){
				$this->plugin->authenticatePlayer($event->getPlayer());
				return;
			}
		}
		$this->plugin->deauthenticatePlayer($event->getPlayer());
	}

	/**
	 * @param PlayerPreLoginEvent $event
	 *
	 * @priority HIGHEST
	 */
	public function onPlayerPreLogin(PlayerPreLoginEvent $event){
		if($this->plugin->getConfig()->get("forceSingleSession") !== true){
			return;
		}
		$player = $event->getPlayer();
		foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
			if($p !== $player and strtolower($player->getName()) === strtolower($p->getName())){
				if($this->plugin->isPlayerAuthenticated($p)){
					$event->setCancelled(true);
					$player->kick("already logged in");
					return;
				}
			}
		}
	}
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$message = $event->getMessage();
			if($message{0} === "/"){ //Command
				$event->setCancelled(true);
				$command = substr($message, 1);
				$args = explode(" ", $command);
				if($args[0] === "register" or $args[0] === "login" or $args[0] === "help"){
					$this->plugin->getServer()->dispatchCommand($event->getPlayer(), $command);
				}else{
					$this->plugin->sendAuthenticateMessage($event->getPlayer());
				}
			}elseif(!$event->getPlayer()->hasPermission("orcore.chat")){
				$event->setCancelled(true);
			}
		}
	}
	public function onPlayerMove(PlayerMoveEvent $event){
		if(!$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			if(!$event->getPlayer()->hasPermission("orcore.move")){
				$event->setCancelled(true);
			}
		}
	}
	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->plugin->closePlayer($event->getPlayer());
	}
}
