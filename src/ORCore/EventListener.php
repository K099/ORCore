<?php
namespace ORCore;

use onebone\economyapi\EconomyAPI;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player;
use pocketmine\utils\Config;

class EventListener implements Listener{
	/** @var ORCore */
	private $plugin;

	private $worldnopvp;

	private $blocknoupdate;

	private $canclebp;

	private $loginbyip;

	private $loginfirst;

	private $vips;

	public function __construct(ORCore $plugin){
		$this->plugin = $plugin;
		$this->worldnopvp = $this->plugin->getConfig()->get("world-NoPvP");
		$this->blocknoupdate = $this->plugin->getConfig()->get("block-NoUpdate");
		$this->canclebp = $this->plugin->getConfig()->get("block-CancleBP");
		$this->loginbyip = $this->plugin->getConfig()->get("authenticateByLastUniqueId");
		$this->loginfirst = $this->plugin->getConfig()->get("forceSingleSession");
		$this->vips = new Config($this->plugin->getDataFolder() . "vip_players.txt", Config::ENUM, array(
		));
	}

	/**
	 * @param PlayerJoinEvent $event
	 *
	 * @priority LOWEST
	 */
	public function onPlayerJoin(PlayerJoinEvent $event){
		if($this->loginbyip === true and $event->getPlayer()->hasPermission("orcore.lastid")){
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
		if($this->loginfirst !== true){
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

	/**
	 * @param BlockBreakEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockBreak(BlockBreakEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp) && !$event->getPlayer()->isOp()){
            $event->setCancelled(true);
        }
		if($event->getPlayer() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$event->setCancelled(true);
		}
	}

	/**
	 * @param BlockPlaceEvent $event
	 *
	 * @priority MONITOR
	 */
	public function onBlockPlace(BlockPlaceEvent $event){
        if(in_array($event->getPlayer()->getLevel()->getFolderName(), $this->canclebp) && !$event->getPlayer()->isOp()){
            $event->setCancelled(true);
        }
		if($event->getPlayer() instanceof Player and !$this->plugin->isPlayerAuthenticated($event->getPlayer())){
			$event->setCancelled(true);
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		$this->plugin->closePlayer($event->getPlayer());
	}

    /*****************
    *  BlockFreezer  *
    *****************/
    public function onBlockUpdate(BlockUpdateEvent $event){
        if(in_array($event->getBlock()->getId(), $this->blocknoupdate)){
            $event->setCancelled(true);
        }
    }

    /***********
    * PvPWorlds*
    ************/
    public function onPvP(EntityDamageEvent $event){
    	if($event instanceof EntityDamageByEntityEvent){
    		if($event->getEntity() instanceof Player && $event->getDamager() instanceof Player){
    			if(in_array($event->getEntity()->getLevel()->getFolderName(), $this->worldnopvp)){
    				$event->setCancelled(true);
    			}
    		}
    	}
    }

    /******************
     *  KillForMoney  *
     ******************/

    /*Entity*/
    /*public function onEntityDeath(EntityDeathEvent $event){
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player){
                EconomyAPI::getInstance()->addMoney($killer->getName(),100);
                $killer->sendTip('§eYou earn §a100 §bCoins§e.');
            }
        }
    }*/
    /*Player*/
    public function onPlayerDeath(PlayerDeathEvent $event){
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player){
                EconomyAPI::getInstance()->addMoney($killer->getName(),100);
                $killer->sendMessage('§b- §eYou earn §a100 §bCoins§e.');
                $killer->setHealth(20);
            }
        }
    }

    /************
    *  VIPSlots *
    ************/
    public function onPlayerKick(PlayerKickEvent $event){
        if($this->vips->exists(strtolower($event->getPlayer()->getName())) and $event->getReason() === "disconnectionScreen.serverFull"){
            $event->setCancelled(true);
        }
    }
}
