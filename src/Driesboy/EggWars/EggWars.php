<?php

namespace Driesboy\EggWars;

use Driesboy\EggWars\Commands\HubCommand;
use Driesboy\EggWars\Commands\EggWarsCommand;
use Driesboy\EggWars\Task\GameTask;
use Driesboy\EggWars\Task\SignManager;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
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

use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\SetPlayerGameTypePacket;
use pocketmine\network\mcpe\protocol\AdventureSettingsPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

class EggWars extends PluginBase{

  private static $ins;

  public $ky = array();
  public $mk = [];
  public $sb = '§6EggWars> ';
  public $tyazi = '§8§l» §r§6Egg §fWars §l§8«';

  public $shop =
  [
    [
      "name" => "§eBlocks",
      "item" => Item::STONE,
      "items" =>
      [ // item:id:amount:paymentid:paymentamount
        Item::SANDSTONE.":0:4:265:1", Item::END_STONE.":0:4:265:4", Item::GLASS.":0:2:265:1", Item::GLOWSTONE.":0:2:265:3", Item::OBSIDIAN.":0:1:266:4"
      ]
    ],
    [
      "name" => "§eWeapons",
      "item" => Item::GOLDEN_SWORD,
      "items" =>
      [ // item:id:amount:paymentid:paymentamount
        Item::WOODEN_SWORD.":0:1:265:5", Item::STONE_SWORD.":0:1:265:32", Item::IRON_SWORD.":0:1:266:16", Item::IRON_SWORD.":0:1:266:16", Item::DIAMOND_SWORD.":0:1:264:12", Item::BOW.":0:1:264:15", Item::ARROW.":0:2:266:2"
      ]
    ],
    [
      "name" => "§eArmor",
      "item" => Item::IRON_CHESTPLATE,
      "items" =>
      [ // item:id:amount:paymentid:paymentamount
        Item::LEATHER_CAP.":0:1:265:1", Item::LEATHER_TUNIC.":0:1:265:1", Item::LEATHER_PANTS.":0:1:265:1", Item::LEATHER_BOOTS.":0:1:265:1", Item::CHAIN_HELMET.":0:1:266:10", Item::CHAIN_CHESTPLATE.":0:1:266:10", Item::CHAIN_LEGGINGS.":0:1:266:10", Item::CHAIN_BOOTS.":0:1:266:10", Item::IRON_HELMET.":0:1:264:5", Item::IRON_CHESTPLATE.":0:1:264:5", Item::IRON_LEGGINGS.":0:1:264:5", Item::IRON_BOOTS.":0:1:264:5"
      ]
    ],
    [
      "name" => "§eFood",
      "item" => Item::CARROT,
      "items" =>
      [ // item:id:amount:paymentid:paymentamount
        Item::CARROT.":0:5:265:1", Item::STEAK.":0:1:265:1", Item::CAKE.":0:1:265:4", Item::GOLDEN_APPLE.":0:5:265:1", Item::GOLDEN_CARROT.":0:1:266:1"
      ]
    ],
    [
      "name" => "§ePICKAXES",
      "item" => Item::DIAMOND_PICKAXE,
      "items" =>
      [ // item:id:amount:paymentid:paymentamount
        Item::WOODEN_PICKAXE.":0:1:265:1", Item::STONE_PICKAXE.":0:1:265:32", Item::IRON_PICKAXE.":0:1:266:5", Item::DIAMOND_PICKAXE.":0:1:264:12"
      ]
    ]
  ];

  public function onEnable(){
    @mkdir($this->getDataFolder());
    @mkdir($this->getDataFolder()."Arenas/");
    @mkdir($this->getDataFolder()."Back-Up/");

    self::$ins = $this;

    $cfg = new Config($this->getDataFolder()."config.yml", Config::YAML, [
      "StartTime" => 61,
      "EndTime" => 16,
      "BuildBlocks" => [24, 20, 89, 49, 54, 92, 121]
    ]);
    $cfg->save();

    Server::getInstance()->getPluginManager()->registerEvents(new EventListener(), $this);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new SignManager($this), 20);
    Server::getInstance()->getScheduler()->scheduleRepeatingTask(new GameTask($this), 20);
    Server::getInstance()->getCommandMap()->register("ew", new EggwarsCommand());
    Server::getInstance()->getCommandMap()->register("lobby", new HubCommand());
  }

  public static function getInstance(){
    return self::$ins;
  }

  public function prepareArenas(){
    foreach($this->Arenas() as $arena){
      if($this->ArenaReady($arena)){
        $this->ArenaRefresh($arena);
      }
    }
  }

  public function ArenaPlayer($arena){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $players = $ac->get("Players");
    $p = array();
    foreach ($players as $player) {
      $go = Server::getInstance()->getPlayer($player);
      if($go instanceof Player){
        $p[] = $player;
      }else{
        $this->RemoveArenaPlayer($arena, $player, 1);
      }
    }
    return $p;
  }

  public function RemoveArenaPlayer($arena, $player, $oa = 0){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $players = $ac->get("Players");
    $status = $ac->get("Status");
    if($status === "Lobby"){
      $this->ArenaMessage($arena, "§6$player left the game ". count($this->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
    }
    if(@in_array($player, $players)){
      $p = Server::getInstance()->getPlayer($player);
      if($p instanceof Player && $oa != 1){
        $p->setNameTag($this->getServer()->getPluginManager()->getPlugin("PureChat")->getNametag($p));
        $p->getInventory()->clearAll();
        $p->setHealth(20);
        $p->setFood(20);
        $p->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
        if ($p->hasPermission("rank.diamond")){
          $p->setGamemode("1");
          $pk = new ContainerSetContentPacket();
          $pk->windowid = ContainerIds::CREATIVE;
          $pk->targetEid = $p->getId();
          $p->dataPacket($pk);
        }
      }
      $key = array_search($player, $players);
      unset($players[$key]);
      $ac->set("Players", $players);
      $ac->save();
    }
  }

  public function AddArenaPlayer($arena, $player){
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $players = $ac->get("Players");
    if(!in_array($player, $players)){
      $p = Server::getInstance()->getPlayer($player);
      if($p instanceof Player){
        $p->setNameTag($p->getName());
        $p->setGamemode(0);
        $p->getInventory()->clearAll();
        $p->setHealth(20);
        $p->setFood(20);
        $p->removeAllEffects();
      }
      $players[] = $player;
      $ac->set("Players", $players);
      $ac->save();
    }
  }

  public function Arenas(){
    $Arenas = array();
    $d = opendir($this->getDataFolder()."Arenas");
    while($file = readdir($d)){
      if($file != "." && $file != ".."){
        $arena = str_replace(".yml", "", $file);
        if($this->ArenaReady($arena)){
          $Arenas[] = $arena;
        }
      }
    }
    return $Arenas;
  }

  public function Teams(){
    $teams = array(
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
    );
    return $teams;
  }

  public function TeamSearcher(){
    $tyc = array(
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
    return $tyc;
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

  public function IsInArena($player){
    $Arenas = $this->Arenas();
    $a = null;
    foreach ($Arenas as $arena){
      $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
      $players = $ac->get("Players");
      if(in_array($player, $players)){
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
    $ac = new Config($this->getDataFolder()."Arenas/$arena.yml", Config::YAML);
    $status = $ac->get("Status");
    return $status;
  }

  public function ArenaCreate($arena, $team, $tbo, Player $p){
    if(!$this->ArenaControl($arena)){
      if($team <= 8) {
        if($tbo <= 8) {
          $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
          $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
          $ac->set("Status", "Lobby");
          $ac->set("StartTime", $cfg->get("StartTime"));
          $ac->set("EndTime", $cfg->get("EndTime"));
          $ac->set("Team", (int) $team);
          $ac->set("PlayersPerTeam", (int) $tbo);
          $ac->set("Players", array());
          $ac->save();
          $p->sendMessage($this->sb."§a$arena was successfully built!");
        }else{
          $p->sendMessage("§8» §cThe number of players per team should be 8 or less.");
        }
      }else{
        $p->sendMessage("§8» §cTeam number should be 8 or less.");
      }
    }else{
      $p->sendMessage("§8» §c$arena already exists!");
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

  public function ArenaSet($arena, $team, Player $p){
    if($this->ArenaControl($arena)){
      $ac = new Config($this->getDataFolder() . "Arenas/$arena.yml", Config::YAML);
      if(!empty($this->Teams()[$team])){
        if(count($this->ArenaTeams($arena)) === $ac->get("Team")){
          if($ac->getNested("$team.X")){
            $ac->setNested("$team.X", $p->getFloorX());
            $ac->setNested("$team.Y", $p->getFloorY());
            $ac->setNested("$team.Z", $p->getFloorZ());
            $ac->save();
            $p->sendMessage("§8» §a$team has been successfully updated!");
          }else{
            $p->sendMessage("§8» §cAll the teams are settled, you can only change the teams!");
          }
        }else{
          $ac->setNested("$team.X", (int) $p->getFloorX());
          $ac->setNested("$team.Y", (int) $p->getFloorY());
          $ac->setNested("$team.Z", (int) $p->getFloorZ());
          $ac->save();
          $p->sendMessage($this->Teams()[$team]."$team 's spawn successfully placed!");
        }
      }else{
        $team = null;
        foreach ($this->Teams() as $team => $color){
          $team .= $color.$team." ";
        }
        $p->sendMessage("§8» §fTeams you can use: \n$team");
      }
    }else{
      $p->sendMessage("§8» §cThere is no such arena.");
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

  public function ItemId(Player $p, $id){
    $items = 0;
    for($i=0; $i<36; $i++){
      $item = $p->getInventory()->getItem($i);
      if($item->getId() === $id){
        $items += $item->getCount();
      }
    }
    return $items;
  }

  public function Status($arena){
    $status = array();
    $plus = "§8[§a+§8]";
    $minus = "§8[§c-§8]";
    foreach($this->ArenaTeams($arena) as $at){
      if(!@in_array($at, $this->ky[$arena])){
        $status[] = $this->Teams()[$at].$at.$plus." ";
      }else{
        $status[] = $this->Teams()[$at].$at." ".$minus." ";
      }
    }
    return $status;
  }

  public function ArenaMessage($arena, $message){
    $players = $this->ArenaPlayer($arena);
    foreach($players as $player){
      $p = $this->getServer()->getPlayer($player);
      if($p instanceof Player){
        $p->sendMessage($message);
      }
    }
  }

  public function PlayerTeamColor(Player $p){
    $teamColor = substr($p->getNameTag(), 0, 3);
    if(strstr($teamColor, "§")){
      $Key = array_search($teamColor, $this->Teams());
      return $Key;
    }else{
      return false;
    }
  }

  public function AvailableTeams($arena){
    $players = $this->ArenaPlayer($arena);
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

  public function TeamSellector($arena, Player $p){
    foreach($this->ArenaTeams($arena) as $at){
      $meta = $this->TeamSearcher()[$at];
      $color = $this->Teams()[$at];
      $item = Item::get(35);
      $item->setDamage($meta);
      $item->setCustomName("§r§8» ".$color.$at."§8 «");
      $p->getInventory()->addItem($item);
    }
    $p->getInventory()->sendContents($p);
  }

  public function EggSkin($arena, $team){
    if(empty($this->ky[$arena])){
      return false;
    }else{
      if(@in_array($team, $this->ky[$arena])){
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
    $ac->set("Status", "Lobby");
    $ac->set("StartTime", (int) $cfg->get("StartTime"));
    $ac->set("EndTime", (int) $cfg->get("EndTime"));
    $ac->set("Players", array());
    $ac->save();
    unset($this->ky[$arena]);
    $this->MapReset($arena);
  }

  public function OneTeamRemained($arena){
    $players = $this->ArenaPlayer($arena);
    $teams = array();
    foreach ($players as $pl){
      $p = Server::getInstance()->getPlayer($pl);
      if($p instanceof Player){
        $team = $this->PlayerTeamColor($p);
        if(!in_array($team, $teams)){
          $teams[] = $team;
        }
      }
    }
    if(count($teams) === 1){
      return true;
    }else{
      return false;
    }
  }

  public function EmptyShop(Player $player){
    $player->getLevel()->setBlock(new Vector3($player->getFloorX(), $player->getFloorY() - 4, $player->getFloorZ()), Block::get(Block::CHEST));
    $nbt = new CompoundTag("", [
      new ListTag("Items", []),
      new StringTag("id", Tile::CHEST),
      new IntTag("x", $player->getFloorX()),
      new IntTag("y", $player->getFloorY() - 4),
      new IntTag("z", $player->getFloorZ()),
      new StringTag("CustomName", "§6EggWars §fShop")
    ]);
    $nbt->Items->setTagType(NBT::TAG_Compound);
    $tile = Tile::createTile("Chest", $player->getLevel(), $nbt);
    if($tile instanceof Chest) {
      $inv = $tile->getInventory();
      $inv->clearAll();
      $shopitems = $this->shop;
      foreach($shopitems as $slot => $shopitem){
        $mitem = Item::fromString($shopitem["item"])->setCustomName("§r".$shopitem["name"]);
        $inv->setItem($slot, $mitem);
      }
      $this->mk[$player->getName()] = 1;
    }
    $player->addWindow($tile->getInventory());
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
}
