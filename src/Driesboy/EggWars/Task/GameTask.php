<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\TextFormat as TF;

class GameTask extends PluginTask{

  private $p;

  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun(int $tick){
    $main = $this->p;
    foreach($main->getServer()->getOnlinePlayers() as $p){
      if($p->getLevel()->getFolderName() === "ELobby"){
        if(!$p->getInventory()->getItemInHand()->hasEnchantments()){
          $p->sendPopup(TF::GRAY."You are playing on ".TF::BOLD.TF::BLUE."GameCraft PE EggWars".TF::RESET."\n".TF::DARK_GRAY."[".TF::LIGHT_PURPLE.count($main->getServer()->getOnlinePlayers()).TF::DARK_GRAY."/".TF::LIGHT_PURPLE.$main->getServer()->getMaxPlayers().TF::DARK_GRAY."] | ".TF::YELLOW."$".$main->getServer()->getPluginManager()->getPlugin("EconomyAPI")->myMoney($p).TF::DARK_GRAY." | ".TF::BOLD.TF::AQUA."Vote: ".TF::RESET.TF::GREEN."vote.gamecraftpe.tk");
        }
      }
    }
    foreach($main->arenas as $arena){
      if($main->ArenaReady($arena)){
        $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
        $status = $main->status[$arena];
        if($status === "Lobby"){
          $time = (int) $main->StartTime[$arena];
          if($time > 0 || $time <= 0){
            if(count($main->players[$arena]) >= $main->teamscount[$arena]){
              $time--;
              $main->StartTime[$arena] = $time;
              switch ($time){
                case 120:
                $main->ArenaMessage($arena, "§9EggWars starting in 2 minutes");
                break;
                case 90:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute and 30 seconds");
                break;
                case 60:
                $main->ArenaMessage($arena, "§9EggWars starting in 1 minute");
                break;
                case 30:
                case 15:
                case 5:
                case 4:
                case 3:
                case 2:
                case 1:
                $main->ArenaMessage($arena, "§9EggWars starting in $time seconds");
                break;
                default:
                if($time <= 0) {
                  foreach ($main->players[$arena] as $p) {
                    $player = $main->getServer()->getPlayer($p);
                    if ($player instanceof Player) {
                      if (!$main->PlayerTeamColor($player)) {
                        $team = $main->AvailableRastTeam($arena);
                        $player->setNameTag($team . $player->getName());
                      }
                      $team = $main->PlayerTeamColor($player);
                      $player->teleport(new Position($ac->getNested($team . ".X"), $ac->getNested($team . ".Y"), $ac->getNested($team . ".Z"), $main->getServer()->getLevelByName($ac->get("World"))));
                      $player->getInventory()->clearAll();
                      $player->getInventory()->sendContents($player);
                      $player->setFood(20);
                      $player->sendMessage("§1Go!");
                    }
                  }
                  $main->status[$arena] = "In-Game";
                }
                break;
              }
              foreach($main->players[$arena] as $p){
                $player = $main->getServer()->getPlayer($p);
                if($player instanceof Player){
                  $player->setXpLevel($time);
                  $player->getInventory()->sendContents($player);
                }
              }
            }
          }
        }elseif($status === "In-Game"){
          $level = Server::getInstance()->getLevelByName($ac->get("World"));
          $tile = $level->getTiles();
          foreach ($tile as $sign){
            if($sign instanceof Sign){
              $y = $sign->getText();
              if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
                $evet = false;
                foreach($level->getNearbyEntities(new AxisAlignedBB($sign->x - 10, $sign->y - 10, $sign->z - 10, $sign->x + 10, $sign->y + 10, $sign->z + 10)) as $ent){
                  if($ent instanceof Player){
                    $evet = true;
                  }
                }
                if($evet === true){
                  $im = explode(" ", $y[2]);
                  $second = str_ireplace("§b", "", $im[0]);
                  $tur = $y[0];
                  if($second != "Broken"){
                    $item = $this->turDonusItem($tur);
                    if(time() % $second === 0){
                      $level->dropItem(new Vector3($sign->x, $sign->y, $sign->z), $item);
                    }
                  }
                }
              }
            }
          }
          foreach($main->players[$arena] as $Is){
            $p = Server::getInstance()->getPlayer($Is);
            $i = null;
            foreach($main->Status($arena) as $status){
              $i = $status;
            }
            $p->sendPopup($i);
          }
          if($main->OneTeamRemained($arena)){
            $main->status[$arena] = "Done";
            $main->ArenaMessage($arena, "§aCongratulations, you win!");
            foreach ($main->players[$arena] as $Is) {
              $p = Server::getInstance()->getPlayer($Is);
              if(!($p instanceof Player)){
                return true;
              }
              $team = $main->PlayerTeamColor($p);
            }
            Server::getInstance()->broadcastMessage("$team §9won the game on §b$arena!");
          }
        }elseif($status === "Done"){
          $bitis = (int) $main->EndTime[$arena];
          if($bitis > 0 || $bitis <= 0){
            $bitis--;
            $main->EndTime[$arena] = $bitis;
            foreach($main->players[$arena] as $players){
              $p = Server::getInstance()->getPlayer($players);
              if($bitis <= 1){
                $main->RemoveArenaPlayer($arena, $p->getName());
              }
            }
            if($bitis <= 0){
              $main->ArenaRefresh($arena);
              return;
            }
          }
        }else{
          $main->status[$arena] = "Done";
        }
      }
    }
  }

  public function turDonusItem($tur){
    $item = null;
    switch($tur){
      case "§6Gold":
      $item = ItemFactory::get(266);
      break;
      case "§bDiamond":
      $item = ItemFactory::get(264);
      break;
      default:
      $item = ItemFactory::get(265);
      break;
    }
    return $item;
  }
}
