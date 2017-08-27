<?php

namespace Driesboy\EggWars\Commands;

use Driesboy\EggWars\EggWars;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\level\Level;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

class EggWarsCommand extends Command{

  public function __construct(){
    parent::__construct("ew", "EggWars by Driesboy");
  }

  public function execute(CommandSender $sender, string $label, array $args){
    $main = EggWars::getInstance();
    if($sender->hasPermission("eggwars.command") && $sender instanceof Player){
      if(!empty($args[0])){
        if($args[0] === "help"){
          $sender->sendMessage("§6----- §fEggwars Help Page §6-----");
          $sender->sendMessage("§8» §e/ew create".' <arena> <teams> <PlayersPerTeam> '."§6Create an Arena!");
          $sender->sendMessage("§8» §e/ew set".' <arena> <team> '."§6Set the TeamSpawn!");
          $sender->sendMessage("§8» §e/ew lobby".' <arena> '."§6Set the WaitingLobby!");
          $sender->sendMessage("§8» §e/ew save".' <arena> '."§6Save the map!");
          $sender->sendMessage("§8» §e/ew shop "."§6Spawn a Villager");
        }elseif ($args[0] === "create"){
          if(!empty($args[1])){
            if(!empty($args[2]) && is_numeric($args[2])){
              if(!empty($args[3]) && is_numeric($args[3])){
                $main->ArenaCreate($args[1], $args[2], $args[3], $sender);
              }else{
                $sender->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
              }
            }else{
              $sender->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
            }
          }else{
            $sender->sendMessage("§8» §c/ew create ".'<arena> <team> <PlayersPerTeam>');
          }
        }elseif ($args[0] === "set"){
          if(!empty($args[1])){
            if(!empty($args[2])){
              $main->ArenaSet($args[1], $args[2], $sender);
            }else{
              $sender->sendMessage("§8» §c/ew set ".'<arena> <team>');
            }
          }else{
            $sender->sendMessage("§8» §c/ew set ".'<arena> <team>');
          }
        }elseif ($args[0] === "lobby"){
          if(!empty($args[1])){
            if($main->ArenaControl($args[1])){
              $ac = new Config($main->getDataFolder()."Arenas/$args[1].yml", Config::YAML);
              $ac->setNested("Lobby.X", $sender->getFloorX());
              $ac->setNested("Lobby.Y", $sender->getFloorY());
              $ac->setNested("Lobby.Z", $sender->getFloorZ());
              $ac->setNested("Lobby.Yaw", $sender->getYaw());
              $ac->setNested("Lobby.Pitch", $sender->getPitch());
              $ac->setNested("Lobby.World", $sender->getLevel()->getFolderName());
              $ac->save();
              $sender->sendMessage("§8» §a$args[1] 's WaitingLobby has been created succesfull");
            }else{
              $sender->sendMessage("§8» §c$args[1] is not an arena");
            }
          }else{
            $sender->sendMessage("§8» §c/ew Lobby ".'<arena>');
          }
        }elseif($args[0] === "save"){
          if(!empty($args[1])){
            if($main->ArenaControl($args[1])) {
              if ($sender->getLevel() != Server::getInstance()->getDefaultLevel()) {
                $ac = new Config($main->getDataFolder()."Arenas/$args[1].yml", Config::YAML);
                $ac->set("World", $sender->getLevel()->getFolderName());
                $ac->save();
                $main->copy(Server::getInstance()->getDataPath()."worlds/".$sender->getLevel()->getFolderName(), $main->getDataFolder()."Back-Up/".$sender->getLevel()->getFolderName());
                $sender->sendMessage("§8» §a$args[1] has been saved");
              } else {
                $sender->sendMessage("§8» §cYour map cannot be the ServerSpawn");
              }
            }else{
              $sender->sendMessage("§8» §c$args[1] is not an arena");
            }
          }else{
            $sender->sendMessage("§8» §c/ew save ".'<arena>');
          }
        }elseif($args[0] === "shop"){
          $this->CreateShop($sender->x, $sender->y, $sender->z, $sender->yaw, $sender->pitch, $sender->getLevel(), 1);
        }elseif($args[0] === "start"){
          if($main->IsInArena($sender->getName())){
            $arena = $main->IsInArena($sender->getName());
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            if($ac->get("Status") === "Lobby"){
              $ac->set("StartTime", 6);
              $ac->save();
              $sender->sendMessage("§bStarting the game ...");
            }
          }else{
            $sender->sendMessage("§cYou are not in a game!");
          }
        }
      }else{
        $sender->sendMessage("§8» §c/ew Help §7EggWars Help Commando's");
      }
    }else{
      $sender->sendMessage("§8» §6EggWars Plugin By §eDriesboy!");
    }
  }


  public function CreateShop($x, $y, $z, $yaw, $pitch, Level $World, $pro){
    $nbt = new CompoundTag("", [
      "Pos" => new ListTag("Pos", [
        new DoubleTag("", $x),
        new DoubleTag("", $y),
        new DoubleTag("", $z)
      ]),
      "Motion" => new ListTag("Motion", [
        new DoubleTag("", 0),
        new DoubleTag("", 0),
        new DoubleTag("", 0)
      ]),
      "Rotation" => new ListTag("Rotation", [
        new FloatTag("", $yaw),
        new FloatTag("", $pitch)
      ]),
    ]);
    $nbt->Health = new ShortTag("Health", 10);
    $nbt->CustomName = new StringTag("CustomName", "§6EggWars Shop");
    $World->loadChunk($x >> 4, $z >> 4);
    $koylu = Entity::createEntity("Villager", $World, $nbt);
    $koylu->spawnToAll();
  }
}
