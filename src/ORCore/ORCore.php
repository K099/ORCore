<?php
namespace ORCore;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\IPlayer;
use pocketmine\utils\Config;
use pocketmine\permission\PermissionAttachment;
use pocketmine\permission\Permission;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use ORCore\event\PlayerAuthenticateEvent;
use ORCore\event\PlayerDeauthenticateEvent;
use ORCore\event\PlayerRegisterEvent;
use ORCore\event\PlayerUnregisterEvent;
use ORCore\provider\DataProvider;
use ORCore\provider\DummyDataProvider;
use ORCore\provider\MySQLDataProvider;
use ORCore\provider\SQLite3DataProvider;
use ORCore\provider\YAMLDataProvider;
use ORCore\task\ShowMessageTask;

class ORCore extends PluginBase{

	/** @var PermissionAttachment[] */
	protected $needAuth = [];

	/** @var EventListener */
	protected $listener;

	/** @var DataProvider */
	protected $provider;

	protected $blockPlayers = 6;
	protected $blockSessions = [];

	/** @var string[] */
	protected $messages = [];

	protected $messageTask = null;

	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool
	 */
	public function isPlayerAuthenticated(Player $player){
		return !isset($this->needAuth[spl_object_hash($player)]);
	}

	/**
	 * @api
	 *
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function isPlayerRegistered(IPlayer $player){
		return $this->provider->isPlayerRegistered($player);
	}

	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function authenticatePlayer(Player $player){
		if($this->isPlayerAuthenticated($player)){
			return true;
		}
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerAuthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}
		if(isset($this->needAuth[spl_object_hash($player)])){
			$attachment = $this->needAuth[spl_object_hash($player)];
			$player->removeAttachment($attachment);
			unset($this->needAuth[spl_object_hash($player)]);
		}
		$this->provider->updatePlayer($player, $player->getClientSecret(), time());
		$player->sendMessage('§aYou have been authenticated.');
		$this->getMessageTask()->removePlayer($player);
		unset($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]);
		return true;
	}
	/**
	 * @api
	 *
	 * @param Player $player
	 *
	 * @return bool True if call not blocked
	 */
	public function deauthenticatePlayer(Player $player){
		if(!$this->isPlayerAuthenticated($player)){
			return true;
		}
		$this->getServer()->getPluginManager()->callEvent($ev = new PlayerDeauthenticateEvent($this, $player));
		if($ev->isCancelled()){
			return false;
		}
		$attachment = $player->addAttachment($this);
		$this->removePermissions($attachment);
		$this->needAuth[spl_object_hash($player)] = $attachment;
		$this->sendAuthenticateMessage($player);
		$this->getMessageTask()->addPlayer($player);
		return true;
	}
	public function tryAuthenticatePlayer(Player $player){
		if($this->blockPlayers <= 0 and $this->isPlayerAuthenticated($player)){
			return;
		}
		if(count($this->blockSessions) > 2048){
			$this->blockSessions = [];
		}
		if(!isset($this->blockSessions[$player->getAddress()])){
			$this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] = 1;
		}else{
			$this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())]++;
		}

		if($this->blockSessions[$player->getAddress() . ":" . strtolower($player->getName())] > $this->blockPlayers){
			$player->kick('§cToo many tries!', true);
			$this->getServer()->getNetwork()->blockAddress($player->getAddress(), 600);
		}
	}
	/**
	 * @api
	 *
	 * @param IPlayer $player
	 * @param string  $password
	 *
	 * @return bool
	 */
	public function registerPlayer(IPlayer $player, $password){
		if(!$this->isPlayerRegistered($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new PlayerRegisterEvent($this, $player));
			if($ev->isCancelled()){
				return false;
			}
			$this->provider->registerPlayer($player, $this->hash(strtolower($player->getName()), $password));
			return true;
		}
		return false;
	}
	/**
	 * @api
	 *
	 * @param IPlayer $player
	 *
	 * @return bool
	 */
	public function unregisterPlayer(IPlayer $player){
		if($this->isPlayerRegistered($player)){
			$this->getServer()->getPluginManager()->callEvent($ev = new PlayerUnregisterEvent($this, $player));
			if($ev->isCancelled()){
				return false;
			}
			$this->provider->unregisterPlayer($player);
		}
		return true;
	}
	/**
	 * @api
	 *
	 * @param DataProvider $provider
	 */
	public function setDataProvider(DataProvider $provider){
		$this->provider = $provider;
	}

	/**
	 * @api
	 *
	 * @return DataProvider
	 */
	public function getDataProvider(){
		return $this->provider;
	}
	/* -------------------------- Non-API part -------------------------- */

	public function closePlayer(Player $player){
		unset($this->needAuth[spl_object_hash($player)]);
		$this->getMessageTask()->removePlayer($player);
	}
	public function sendAuthenticateMessage(Player $player){
		$config = $this->provider->getPlayer($player);
		$player->sendMessage('§3Welcome §bto §6Orange§aCraft§f!');
		if($config === null){
			$player->sendMessage('§aPlease register using: §b/register §f<password>');
		}else{
			$player->sendMessage('§aPlease log in using: §b/login §f<password>');
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "login":
				if($sender instanceof Player){
					if(!$this->isPlayerRegistered($sender) or ($data = $this->provider->getPlayer($sender)) === null){
						$sender->sendMessage('§cThis account is not registered.');
						return true;
					}
					if(count($args) !== 1){
						$sender->sendMessage('§aUsage:§b '.$command->getUsage());
						return true;
					}
					$password = implode(" ", $args);
					if(hash_equals($data["hash"], $this->hash(strtolower($sender->getName()), $password)) and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$this->tryAuthenticatePlayer($sender);
						$sender->sendMessage('§cIncorrect password!');
						return true;
					}
				}else{
					$sender->sendMessage('§cThis command only works in-game.');
					return true;
				}
				break;
			case "register":
				if($sender instanceof Player){
					if($this->isPlayerRegistered($sender)){
						$sender->sendMessage('§cThis account is already registered.');
						return true;
					}
					$password = implode(" ", $args);
					if(strlen($password) < 6){
						$sender->sendMessage('§cYour password is too short!');
						return true;
					}
					if($this->registerPlayer($sender, $password) and $this->authenticatePlayer($sender)){
						return true;
					}else{
						$sender->sendMessage('§cError during authentication.');
						return true;
					}
				}else{
					$sender->sendMessage("§cThis command only works in-game.");
					return true;
				}
				break;
		}
		return false;
	}
	private function parseMessages(array $messages){
		$result = [];
		foreach($messages as $key => $value){
			if(is_array($value)){
				foreach($this->parseMessages($value) as $k => $v){
					$result[$key . "." . $k] = $v;
				}
			}else{
				$result[$key] = $value;
			}
		}
		return $result;
	}

	public function getMessage($key){
		return isset($this->messages[$key]) ? $this->messages[$key] : $key;
	}

	public function onEnable(){
		$this->saveDefaultConfig();
		$this->reloadConfig();
		$this->saveResource("messages.yml", false);
		$messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();
		$this->messages = $this->parseMessages($messages);
		$registerCommand = $this->getCommand("register");
		$registerCommand->setUsage('/register <password>');
		$registerCommand->setDescription('Registers an account');
		$registerCommand->setPermissionMessage('§cYou do not have permission to use the register command!');
		$loginCommand = $this->getCommand("login");
		$loginCommand->setUsage('/login <password>');
		$loginCommand->setDescription('Logs into an account');
		$loginCommand->setPermissionMessage('§cYou do not have permission to use the login command!');
		$this->blockPlayers = (int) $this->getConfig()->get("blockAfterFail", 6);
		$provider = $this->getConfig()->get("dataProvider");
		unset($this->provider);
		switch(strtolower($provider)){
			case "yaml":
				$this->getLogger()->info("§eUsing YAML data provider");
				$provider = new YAMLDataProvider($this);
				break;
			case "sqlite3":
				$this->getLogger()->info("§eUsing SQLite3 data provider");
				$provider = new SQLite3DataProvider($this);
				break;
			case "mysql":
				$this->getLogger()->info("§eUsing MySQL data provider");
				$provider = new MySQLDataProvider($this);
				break;
			case "none":
			default:
				$provider = new DummyDataProvider($this);
				break;
		}
		if(!isset($this->provider) or !($this->provider instanceof DataProvider)){ //Fix for getting a Dummy provider
			$this->provider = $provider;
		}
		$this->listener = new EventListener($this);
		$this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$this->deauthenticatePlayer($player);
		}
		$this->getLogger()->info("§aEverything loaded!");
	}
	public function onDisable(){
		$this->getServer()->getPluginManager();
		$this->provider->close();
		$this->messageTask = null;
		$this->blockSessions = [];
	}
	public static function orderPermissionsCallback($perm1, $perm2){
		if(self::isChild($perm1, $perm2)){
			return -1;
		}elseif(self::isChild($perm2, $perm1)){
			return 1;
		}else{
			return 0;
		}
	}

	public static function isChild($perm, $name){
		$perm = explode(".", $perm);
		$name = explode(".", $name);
		foreach($perm as $k => $component){
			if(!isset($name[$k])){
				return false;
			}elseif($name[$k] !== $component){
				return false;
			}
		}
		return true;
	}
	protected function removePermissions(PermissionAttachment $attachment){
		$permissions = [];
		foreach($this->getServer()->getPluginManager()->getPermissions() as $permission){
			$permissions[$permission->getName()] = false;
		}
		$permissions["pocketmine.command.help"] = true;
		$permissions[Server::BROADCAST_CHANNEL_USERS] = true;
		$permissions[Server::BROADCAST_CHANNEL_ADMINISTRATIVE] = false;

		unset($permissions["orcore.chat"]);
		unset($permissions["orcore.move"]);
		unset($permissions["orcore.lastid"]);
		uksort($permissions, [ORCore::class, "orderPermissionsCallback"]); //Set them in the correct order
		$attachment->setPermissions($permissions);
	}

	/**
	 * Uses SHA-512 [http://en.wikipedia.org/wiki/SHA-2] and Whirlpool [http://en.wikipedia.org/wiki/Whirlpool_(cryptography)]
	 *
	 * Both of them have an output of 512 bits. Even if one of them is broken in the future, you have to break both of them
	 * at the same time due to being hashed separately and then XORed to mix their results equally.
	 *
	 * @param string $salt
	 * @param string $password
	 *
	 * @return string[128] hex 512-bit hash
	 */
	private function hash($salt, $password){
		return bin2hex(hash("sha512", $password . $salt, true) ^ hash("whirlpool", $salt . $password, true));
	}

	/**
	 * @return ShowMessageTask
	 */
	protected function getMessageTask(){
		if($this->messageTask === null){
			$this->messageTask = new ShowMessageTask($this);
			$this->getServer()->getScheduler()->scheduleRepeatingTask($this->messageTask, 10);
		}

		return $this->messageTask;
	}
}
