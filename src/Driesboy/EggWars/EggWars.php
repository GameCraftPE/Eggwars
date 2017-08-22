<?php

namespace Driesboy\EggWars;

use Driesboy\EggWars\Commands\HubCommand;
use Driesboy\EggWars\Commands\EggWarsCommand;
use Driesboy\EggWars\Task\Game;
use Driesboy\EggWars\Task\ParticleTask;
use Driesboy\EggWars\Task\SignManager;
use Driesboy\EggWars\Task\StackTask;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\AddEntityPacket;

use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

class EggWars extends PluginBase{

  public $players = array();

  public $arenas = array();

  public $status = array();

  public $StartTime = array();

  public $EndTime = array();

  public $teamscount = array();

  public $perteamcount = array();

  public $egg = array();

  public $m = array();

  public $mo = array();

  private static $ins;

  public function onEnable(){
    @mkdir($this->getDataFolder());
    @mkdir($this->getDataFolder()."Arenas/");
    @mkdir($this->getDataFolder()."Back-Up/");
    self::$ins = $this;
    $this->saveDefaultConfig();
    $this->saveResource("shop.yml");
    $this->AnotherPrepare();
    $this->PrepareArenas();
  }

  public static function getInstance(){
    return self::$ins;
  }

  public function AnotherPrepare(){
    Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), $this);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new Game($this), 20);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new ParticleTask($this), 0.001);
    Server::getInstance()->getCommandMap()->register("ew", new EggwarsCommand());
    Server::getInstance()->getCommandMap()->register("lobby", new HubCommand());
  }

  public function PrepareArenas(){
    $this->loadArenas();
    foreach($this->arenas as $arena){
      if($this->ArenaReady($arena)){
        $this->ArenaRefresh($arena);
      }
    }
  }

  public function loadArenas(){
    $d = opendir($this->getDataFolder()."Arenas");
    while($file = readdir($d)){
      if($file != "." && $file != ".."){
        $arena = str_replace(".yml", "", $file);
        if($this->ArenaReady($arena)){
          $this->arenas[] = $arena;
        }
      }
    }
  }

  public function resetPlayer(Player $player){
    $player->setNameTag($player->getName());
    $player->getInventory()->clearAll();
    $player->setHealth(20);
    $player->setFood(20);
    $player->removeAllEffects();
  }

  public function RemoveArenaPlayer($arena, Player $player){
    $name = $player->getName();
    if($this->status[$arena] === "Lobby"){
      $this->ArenaMessage($arena, "§6$name left the game ". count($this->players[$arena]) . "/" .$this->teamscount[$arena] * $this->perteamcount[$arena]);
    }
    $this->resetPlayer($player);
    $player->setGamemode($this->getServer()->getDefaultGamemode());
    $player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
    if ($player->hasPermission("rank.diamond")){
      $player->setGamemode("1");
      $pk = new ContainerSetContentPacket();
      $pk->windowid = ContainerIds::CREATIVE;
      $pk->targetEid = $player->getId();
      $player->dataPacket($pk);
    }
    unset($this->players[$arena][array_search($player->getName(), $this->players[$arena])]);
    $this->updateSigns();
  }

  public function AddArenaPlayer($arena, Player $player){
    if(!$this->IsInArena($player)){
      $this->resetPlayer($player);
      $player->setGamemode("0");
      array_push($this->players[$arena], $player->getName());
      $this->updateSigns();
    }
  }

  public function Teams(){
    return $teams = array(
      "WHITE" => "§f",
      "ORANGE" => "§6",
      "LIGHT-BLUE" => "§b",
      "YELLOW" => "§e",
      "LIME" => "§a",
      "PINK" => "§d",
      "GRAY" => "§8",
      "LIGHT-GRAY" => "§7",
      "CYAN" => "§3",
      "PURPLE" => "§5",
      "BLUE" => "§9",
      "BROWN" => "§6",
      "GREEN" => "§2",
      "RED" => "§c",
      "BLACK" => "§0"
    );;
  }

  public function TeamSearcher(){
    return array(
      "WHITE" => 0,
      "ORANGE" => 1,
      "LIGHT-BLUE" => 3,
      "YELLOW" => 4,
      "LIME" => 5,
      "PINK" => 6,
      "GRAY" => 7,
      "LIGHT-GRAY" => 8,
      "CYAN" => 9,
      "PURPLE" => 10,
      "BLUE" => 11,
      "BROWN" => 12,
      "GREEN" => 13,
      "RED" => 14,
      "BLACK" => 15
    );
  }


  public function ArenaControl($arena){
    if(file_exists($this->getDataFolder()."Arenas/$arena.yml")){
      return true;
    }else{
      return false;
    }
  }

  public function ArenaReady($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    if($ac->get("World")){
      if(file_exists($this->getDataFolder()."Back-Up/".$ac->get("World")."/")){
        return true;
      }else{
        return false;
      }
    }else{
      return false;
    }
  }

  public function IsInArena(Player $player){
    $a = null;
    foreach ($this->arenas as $arena){
      if(@in_array($player->getName(), $this->players[$arena])){
        $a = $arena;
        break;
      }
    }
    if($a != null){
      return $a;
    }else{
      return false;
    }
  }

  public function ArenaStatus($arena){
    return $this->status[$arena];
  }

  public function ArenaCreate($arena, $team, $tbo, Player $player){
    if(!$this->ArenaControl($arena)){
      if($team <= 8) {
        if($tbo <= 8) {
          $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
          $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
          $this->players[$arena] = array();
          $this->status[$arena] = "Lobby";
          $this->StartTime[$arena] = (int) $cfg->get("StartTime");
          $this->EndTime[$arena] = (int) $cfg->get("EndTime");
          $ac->set("Team", (int) $team);
          $ac->set("PlayersPerTeam", (int) $tbo);
          $ac->save();
          $this->updateSigns();
          $player->sendMessage("§6EggWars> §a$arena was successfully built!");
        }else{
          $player->sendMessage("§8» §cThe number of players per team should be 8 or less.");
        }
      }else{
        $player->sendMessage("§8» §cTeam number should be 8 or less.");
      }
    }else{
      $player->sendMessage("§8» §c$arena already exists!");
    }
  }

  public function ArenaTeams($arena){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      $teams = array();
      foreach ($this->Teams() as $team => $color){
        if(!empty($ac->getNested($team.".Y"))){
          $teams[] = $team;
        }
      }
      return $teams;
    }else{
      return false;
    }
  }

  public function ArenaSet($arena, $team, Player $player){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      if(!empty($this->Teams()[$team])){
        if(count($this->ArenaTeams($arena)) === $this->teamscount[$arena]){
          if($ac->getNested("$team.X")){
            $ac->setNested("$team.X", $player->getFloorX());
            $ac->setNested("$team.Y", $player->getFloorY());
            $ac->setNested("$team.Z", $player->getFloorZ());
            $ac->save();
            $player->sendMessage("§8» §a$team has been successfully updated!");
          }else{
            $player->sendMessage("§8» §cAll the teams are settled, you can only change the teams!");
          }
        }else{
          $ac->setNested("$team.X", (int) $player->getFloorX());
          $ac->setNested("$team.Y", (int) $player->getFloorY());
          $ac->setNested("$team.Z", (int) $player->getFloorZ());
          $ac->save();
          $player->sendMessage($this->Teams()[$team]."$team 's spawn successfully placed!");
        }
      }else{
        $team = null;
        foreach ($this->Teams() as $team => $color){
          $team .= $color.$team." ";
        }
        $player->sendMessage("§8» §fTeams you can use: \n$team");
      }
    }else{
      $player->sendMessage("§8» §cThere is no such arena.");
    }
  }

  public function copy($source, $target){
    $directory = opendir($source);
    @mkdir($target);
    while (false !== ($file = readdir($directory))){
      if ($file != "." && $file != "..") {
        if (is_dir($source.'/'.$file)) {
          $this->copy($source.'/'.$file, $target.'/'.$file);
        } else {
          copy($source.'/'.$file, $target.'/'.$file);
        }
      }
    }
    closedir($directory);
  }

  public function MapReset($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml");
    $World = $ac->get("World");
    $level = Server::getInstance()->getLevelByName($World);
    if($level instanceof Level){
      Server::getInstance()->unloadLevel($level);
    }
    $this->copy($this->getDataFolder()."Back-Up/".$World, $this->getServer()->getDataPath()."worlds/".$World);
    Server::getInstance()->loadLevel($World);
  }

  public function ItemId(Player $player, $id){
    $items = 0;
    for($i = 0; $i < 36; $i++){
      $item = $player->getInventory()->getItem($i);
      if($item->getId() === $id){
        $items += $item->getCount();
      }
    }
    return $items;
  }

  public function PopupStatus($arena){
    $status = array();
    $plus = "§8[§a+§8]";
    $minus = "§8[§c-§8]";
    foreach($this->ArenaTeams($arena) as $at){
      if(!@in_array($at, $this->egg[$arena])){
        $status[] = $this->Teams()[$at].$at.$plus." ";
      }else{
        $status[] = $this->Teams()[$at].$at." ".$minus." ";
      }
    }
    foreach($this->players[$arena] as $p){
      $player = $this->getServer()->getPlayer($p);
      if ($player instanceof Player) {
        $player->sendPopup($status);
      }
    }
  }

  public function ArenaMessage($arena, $message){
    foreach($this->players[$arena] as $p){
      $player = $this->getServer()->getPlayer($p);
      if($player instanceof Player){
        $player->sendMessage($message);
      }
    }
  }

  public function PlayerTeamColor(Player $player){
    $teamColor = substr($player->getNameTag(), 0, 3);
    if(strstr($teamColor, "§")){
      $Key = array_search($teamColor, $this->Teams());
      return $Key;
    }else{
      return false;
    }
  }

  public function AvailableTeams($arena){
    $players = $this->players[$arena];
    $teamNumber = 0;
    $cfg = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $musaitTeam = array();
    foreach($this->ArenaTeams($arena) as $team){
      foreach($players as $player){
        $p = $this->getServer()->getPlayer($player);
        if($p instanceof Player){
          if($this->PlayerTeamColor($p) === $team){
            $teamNumber++;
          }
        }
      }
      if($teamNumber < $cfg->get("PlayersPerTeam")){
        $musaitTeam[] = $team;
      }
      $teamNumber = 0;
    }
    return $musaitTeam;
  }

  public function AvailableRastTeam($arena){
    $mt = $this->AvailableTeams($arena);
    $mixed = array_rand($mt);
    return $this->Teams()[$mt[$mixed]];
  }

  public function TeamSellector($arena, Player $player){
    foreach($this->ArenaTeams($arena) as $at){
      $meta = $this->TeamSearcher()[$at];
      $color = $this->Teams()[$at];
      $item = Item::get(35);
      $item->setDamage($meta);
      $item->setCustomName("§r§8» ".$color.$at."§8 «");
      $player->getInventory()->addItem($item);
    }
    $player->getInventory()->sendContents($player);
  }

  public function EggSkin($arena, $team){
    if(empty($this->egg[$arena])){
      return false;
    }else{
      if(@in_array($team, $this->egg[$arena])){
        return true;
      }else{
        return false;
      }
    }
  }

  public function ArenaRefresh($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML);
    $Lobby = Server::getInstance()->getLevelByName($ac->getNested("Lobby.World"));
    if(!$Lobby instanceof Level){
      Server::getInstance()->loadLevel($ac->getNested("Lobby.World"));
    }
    $this->status[$arena] = "Lobby";
    $this->StartTime[$arena] = (int) $cfg->get("StartTime");
    $this->EndTime[$arena] = (int) $cfg->get("EndTime");
    $this->teamscount[$arena] = (int) $ac->get("Team");
    $this->perteamcount[$arena] = (int) $ac->get("PlayersPerTeam");
    $this->players[$arena] = array();
    unset($this->egg[$arena]);
    $this->MapReset($arena);
    $this->updateSigns();
  }

  public function CheckOneTeamRemained($arena){
    $teams = array();
    foreach ($this->players[$arena] as $p){
      $player = Server::getInstance()->getPlayer($p);
      if($player instanceof Player){
        $team = $this->PlayerTeamColor($player);
        if(!in_array($team, $teams)){
          $teams[] = $team;
        }
      }
    }
    if(count($teams) === 1){
      $this->status[$arena] = "Done";
      $this->ArenaMessage($arena, "§aCongratulations, you win!");
      foreach ($this->players[$arena] as $p) {
        $player = Server::getInstance()->getPlayer($p);
        if(!($player instanceof Player)){
          return true;
        }
        $team = $this->PlayerTeamColor($player);
      }
      Server::getInstance()->broadcastMessage("$team §9won the game on §b$arena!");
      $this->updateSigns();
    }
  }

  public function EmptyShop(Player $p){
    $p->getLevel()->setBlock(new Vector3($p->getFloorX(), $p->getFloorY() - 4, $p->getFloorZ()), Block::get(Block::CHEST));
    $nbt = new CompoundTag("", [
      new ListTag("Items", []),
      new StringTag("id", Tile::CHEST),
      new IntTag("x", $p->getFloorX()),
      new IntTag("y", $p->getFloorY() - 4),
      new IntTag("z", $p->getFloorZ()),
      new StringTag("CustomName", "§6EggWars Shop")
    ]);
    $nbt->Items->setTagType(NBT::TAG_Compound);
    $tile = Tile::createTile("Chest", $p->getLevel(), $nbt);
    if($tile instanceof Chest) {
      $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
      $shop = $config->get("shop");
      $tile->setName("§6EggWars Shop");
      $tile->getInventory()->clearAll();
      for ($i = 0; $i < count($shop); $i+=2) {
        $slot = $i / 2;
        $tile->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
      }
      $tile->getInventory()->setItem($tile->getInventory()->getSize()-1, Item::get(Item::WOOL, 14, 1));
      $p->addWindow($tile->getInventory());
    }
  }
  public function lightning($x, $y, $z, $level){
    $pk = new AddEntityPacket();
    $pk->type = 93;
    $pk->eid = Entity::$entityCount++;
    $pk->entityRuntimeId = Entity::$entityCount++;
    $pk->x = $x;
    $pk->y = $y;
    $pk->z = $z;
    $pk->speedX = 0;
    $pk->speedY = 0;
    $pk->speedZ = 0;
    $pk->metadata = array();
    foreach($level->getPlayers() as $pl){
      $pl->dataPacket($pk);
    }
  }

  public function updateSigns(){
    $level = Server::getInstance()->getDefaultLevel();
    $tiles = $level->getTiles();
    foreach ($tiles as $tile){
      if($tile instanceof Sign){
        $text = $tile->getText();
        if($text[0] === "§8§l» §r§6Egg §fWars §l§8«"){
          $arena = str_ireplace("§e", "", $text[2]);
          $players = count($this->players[$arena]);
          $fullPlayer = $this->teamscount[$arena] * $this->perteamcount[$arena];
          $newstatus = null;
          if($this->status[$arena] === "Lobby"){
            if($players >= $fullPlayer){
              $newstatus = "§c§lFull";
            }else{
              $newstatus = "§a§lTap to join";
            }
          }elseif ($this->status[$arena] === "In-Game"){
            $newstatus = "§d§lIn-Game";
          }elseif($this->status[$arena] === "Done"){
            $newstatus = "§9§lRestarting";
          }
          $tile->setText($text[0], "§f$players/$fullPlayer", $text[2], $newstatus);
        }
      }
    }
  }

  public function tick(){
    foreach($this->arenas as $arena){
      if($this->status[$arena] === "Lobby"){
        $time = (int)$this->StartTime[$arena];
        if($time > 0 || $time <= 0){
          if(count($this->players[$arena]) >= $this->teamscount[$arena]){
            $time--;
            $this->StartTime[$arena] = $time;
            // CountDown
            switch ($time){
              case 120:
              $this->ArenaMessage($arena, "§9EggWars starting in 2 minutes");
              break;
              case 90:
              $this->ArenaMessage($arena, "§9EggWars starting in 1 minute and 30 seconds");
              break;
              case 60:
              $this->ArenaMessage($arena, "§9EggWars starting in 1 minute");
              break;
              case 30:
              case 15:
              case 5:
              case 4:
              case 3:
              case 2:
              case 1:
              $this->ArenaMessage($arena, "§9EggWars starting in $time seconds");
              break;
              default:
              foreach($this->players[$arena] as $p){
                $player = $this->getServer()->getPlayer($p);
                if($player instanceof Player){
                  $player->setXpLevel($time);
                  $player->getInventory()->sendContents($player);
                }
              }
              if($time <= 0) {
                foreach ($this->players[$arena] as $p) {
                  $player = $this->getServer()->getPlayer($p);
                  if ($player instanceof Player) {
                    if (!$this->PlayerTeamColor($player)) {
                      $team = $this->AvailableRastTeam($arena);
                      $player->setNameTag($team . $player->getName());
                    }
                    $team = $this->PlayerTeamColor($player);
                    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
                    $player->teleport(new Position($ac->getNested($team . ".X"), $ac->getNested($team . ".Y"), $ac->getNested($team . ".Z"), $this->getServer()->getLevelByName($ac->get("World"))));
                    $player->getInventory()->clearAll();
                    $player->getInventory()->sendContents($player);
                    $player->setFood(20);
                    $player->sendMessage("§1Go!");
                  }
                }
                $this->status[$arena] = "In-Game";
                $this->updateSigns();
              }
              break;
            }
          }
        }
      }elseif($this->status[$arena] === "In-Game"){
        $level = Server::getInstance()->getLevelByName(new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML)->get("World"));
        $tiles = $level->getTiles();
        foreach ($tiles as $sign){
          if($sign instanceof Sign){
            $text = $sign->getText();
            if($text[0] === "§fIron"){
              foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 5, $sign->y - 5, $sign->z - 5, $sign->x + 5, $sign->y + 5, $sign->z + 5)) as $p){
                if($p instanceof Player){
                  if(time() % $second === 0){
                    $p->getInventory()->addItem(Item::get(265));
                    $p->getInventory()->sendContents($p);
                  }
                }
              }
            }
            if($text[0] === "§6Gold"){
              foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 5, $sign->y - 5, $sign->z - 5, $sign->x + 5, $sign->y + 5, $sign->z + 5)) as $p){
                if($p instanceof Player){
                  if(time() % $second === 0){
                    $p->getInventory()->addItem(Item::get(266));
                    $p->getInventory()->sendContents($p);
                  }
                }
              }
            }
            if($text[0] === "§bDiamond"){
              foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 5, $sign->y - 5, $sign->z - 5, $sign->x + 5, $sign->y + 5, $sign->z + 5)) as $p){
                if($p instanceof Player){
                  if(time() % $second === 0){
                    $p->getInventory()->addItem(Item::get(264));
                    $p->getInventory()->sendContents($p);
                  }
                }
              }
            }
          }
        }
        $this->PopupStatus($arena);
        $this->CheckOneTeamRemained($arena);
      }elseif($this->status[$arena] === "Done"){
        $bitis = (int) $this->EndTime[$arena];
        if($bitis > 0 || $bitis <= 0){
          $bitis--;
          $this->EndTime[$arena] = $bitis;
          foreach($this->players[$arena] as $p){
            $player = Server::getInstance()->getPlayer($p);
            if($bitis <= 1){
              $this->RemoveArenaPlayer($arena, $player));
            }
          }
          if($bitis <= 0){
            $this->ArenaRefresh($arena);
            return;
          }
        }
      }else{
        $this->status[$arena] = "Done";
        $this->updateSigns();
      }
    }
  }
}
