<?php

namespace Driesboy\EggWars;

use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\block\Block;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\tile\Sign;
use pocketmine\tile\Chest;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\utils\Config;

use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

class EventListener implements Listener{

  public $sd = array();
  public function __construct(){
  }

  public function OnJoin(PlayerJoinEvent $e){
    if ($e->getPlayer()->hasPermission("rank.diamond")){
      $e->getPlayer()->setGamemode("1");
      $pk = new ContainerSetContentPacket();
      $pk->windowid = ContainerIds::CREATIVE;
      $pk->targetEid = $e->getPlayer()->getId();
      $e->getPlayer()->dataPacket($pk);
    }
  }

  public function OnQuit(PlayerQuitEvent $e){
    $main = EggWars::getInstance();
    $p = $e->getPlayer();
    if($main->IsInArena($p->getName())){
      $arena = $main->IsInArena($p->getName());
      $main->RemoveArenaPlayer($arena, $p->getName());
      $p->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
      $message = $p->getNameTag()." §eleft the game!";
      $main->ArenaMessage($arena, $message);
    }
  }

  /**
  * Priority is the MONITOR so it can pass PureChat plugin priority.
  *
  * @param PlayerChatEvent $e
  * @priority MONITOR
  */
  public function Chat(PlayerChatEvent $e){
    $p = $e->getPlayer();
    $m = $e->getMessage();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      $color = "";
      $is = substr($m, 0, 1);
      $team = $main->PlayerTeamColor($p);
      $arena = $main->IsInArena($p->getName());
      $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
      $players = $main->ArenaPlayer($arena);
      if($ac->get("Status") === "Lobby"){
        foreach($players as $p){
          $to = $main->getServer()->getPlayer($p);
          if($to instanceof Player){
            $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($e->getPlayer(), $m);
            $to->sendMessage($chatFormat);
            $e->setCancelled();
          }
        }
      }
      if(!empty($main->Teams()[$team])){
        $color = $main->Teams()[$team];
      }
      if ($ac->get("Status") != "Lobby"){
        if($is === "!"){
          foreach($players as $p){
            $to = $main->getServer()->getPlayer($p);
            if($to instanceof Player){
              $msil = substr($m, 1);
              $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($e->getPlayer(), $msil);
              $to->sendMessage($chatFormat);
              $e->setCancelled();
            }
          }
        }else{
          foreach($players as $p){
            $to = $main->getServer()->getPlayer($p);
            if($to instanceof Player){
              $toTeam = $main->PlayerTeamColor($to);
              if($team === $toTeam){
                $format = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($e->getPlayer(), $m);
                $message = "§8[".$color."team§8] ". $format;
                $to->sendMessage($message);
                $e->setCancelled();
              }
            }
          }
        }
      }
      return;
    }
  }

  public function OnInteract(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $t = $p->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($t instanceof Sign){
      $yazilar = $t->getText();
      if($yazilar[0] === $main->tyazi){
        $arena = str_ireplace("§e", "", $yazilar[2]);
        $status = $main->ArenaStatus($arena);
        if($status === "Lobby"){
          if(!$main->IsInArena($p->getName())){
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            $players = count($main->ArenaPlayer($arena));
            $fullPlayer = $ac->get("Team") * $ac->get("PlayersPerTeam");
            if($players >= $fullPlayer){
              $p->sendPopup("§8» §cThis game is full! §8«");
              return;
            }
            $main->AddArenaPlayer($arena, $p->getName());
            $p->teleport(new Position($ac->getNested("Lobby.X"), $ac->getNested("Lobby.Y"), $ac->getNested("Lobby.Z"), $main->getServer()->getLevelByName($ac->getNested("Lobby.World"))));
            $main->TeamSellector($arena, $p);
            $main->ArenaMessage($arena, "§5".$p->getName()." §5joined the game. ". count($main->ArenaPlayer($arena)) . "/" .$ac->get("Team") * $ac->get("PlayersPerTeam"));
          }else{
            $p->sendPopup("§cYou're already in a game!");
          }
        }elseif ($status === "In-Game"){
          $p->sendPopup("§8» §dThe game is still going on!");
        }elseif ($status === "Done"){
          $p->sendPopup("§8» §eResetting the Arena ...");
        }
        $e->setCancelled();
      }
    }
  }

  public function UpgradeGenerator(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $sign = $p->getLevel()->getTile($b);
    $main = EggWars::getInstance();
    if($sign instanceof Sign){
      $y = $sign->getText();
      if($y[0] === "§fIron" || $y[0] === "§6Gold" || $y[0] === "§bDiamond"){
        $tip = $y[0];
        $level = (int) explode(" ", $y[1])[1];
        switch($level){
          case 0:
          switch ($tip){
            case "§6Gold":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 5){
              $p->getInventory()->removeItem(ItemFactory::get(Item::GOLD_INGOT,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b5 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §65 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 5){
              $p->getInventory()->removeItem(ItemFactory::get(Item::DIAMOND,0,5));
              $sign->setText($y[0], "§eLevel 1", "§b10 seconds", $y[3]);
              $p->sendMessage("§8» §aDiamond generator Activated!");
            }else{
              $p->sendMessage("§8» §b5 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 1:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($p, Item::IRON_INGOT) >= 20){
              $p->getInventory()->removeItem(ItemFactory::get(Item::IRON_INGOT,0,20));
              $sign->setText($y[0], "§eLevel 2", "§b3 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §f20 Iron needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 10){
              $p->getInventory()->removeItem(ItemFactory::get(Item::GOLD_INGOT,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b4 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 10){
              $p->getInventory()->removeItem(ItemFactory::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 2", "§b5 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 2:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 20){
              $p->getInventory()->removeItem(ItemFactory::get(Item::GOLD_INGOT,0,20));
              $sign->setText($y[0], "§eLevel 3", "§b2 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §620 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($p, Item::DIAMOND) >= 10){
              $p->getInventory()->removeItem(ItemFactory::get(Item::DIAMOND,0,10));
              $sign->setText($y[0], "§eLevel 3", "§b2 seconds", $y[3]);
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($p, Item::DIAMOND) >= 25){
              $p->getInventory()->removeItem(ItemFactory::get(Item::DIAMOND,0,25));
              $sign->setText($y[0], "§eLevel 3", "§b3 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§aUpgraded generator!");;
            }else{
              $p->sendMessage("§8» §b25 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 3:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($p, Item::GOLD_INGOT) >= 50){
              $p->getInventory()->removeItem(ItemFactory::get(Item::GOLD_INGOT,0,50));
              $sign->setText($y[0], "§eLevel 4", "§b1 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §650 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($p, Item::DIAMOND) >= 25){
              $p->getInventory()->removeItem(ItemFactory::get(Item::DIAMOND,0,25));
              $sign->setText($y[0], "§eLevel 4", "§b1 seconds", "§c§lMAXIMUM");
              $p->sendMessage("§aUpgraded generator!");
            }else{
              $p->sendMessage("§8» §b25 Diamonds needed to upgrade!");
            }
            break;

          }
          break;
          default:
          $p->sendMessage("§8» §cThis generator is already on the Maximum level!");
          break;
        }
      }
    }
  }

  public function DestroyEgg(PlayerInteractEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      if($b->getId() === 122){
        $yun = $b->getLevel()->getBlock(new Vector3($b->x, $b->y - 1, $b->z));
        if($yun->getId() === 35){
          $color = $yun->getDamage();
          $team = array_search($color, $main->TeamSearcher());
          $oht = $main->PlayerTeamColor($p);
          if($oht === $team){
            $p->sendPopup("§8»§c You can not break your own egg!");
            $e->setCancelled();
          }else{
            $b->getLevel()->setBlock(new Vector3($b->x, $b->y, $b->z), Block::get(0));
            $main->lightning($b->x, $b->y, $b->z, $p->getLevel());
            $arena = $main->IsInArena($p->getName());
            $main->ky[$arena][] = $team;
            $main->ArenaMessage($main->IsInArena($p->getName()), "§eTeam " .$main->Teams()[$team]."$team's".$main->Teams()[$oht]." §eegg has been destroyed by " .$p->getNameTag());
          }
        }
      }
    }
  }

  public function CreateSign(SignChangeEvent $e){
    $p = $e->getPlayer();
    $main = EggWars::getInstance();
    if($p->isOp()){
      if($e->getLine(0) === "eggwars"){
        if(!empty($e->getLine(1))){
          if($main->ArenaControl($e->getLine(1))){
            if($main->ArenaReady($e->getLine(1))){
              $arena = $e->getLine(1);
              $e->setLine(0, $main->tyazi);
              $e->setLine(1, "§f0/0");
              $e->setLine(2, "§e$arena");
              $e->setLine(3, "§l§bTap to Join");
              for($i=0; $i<=3; $i++){
                $p->sendMessage("§8» §a$i".$e->getLine($i));
              }
            }else{
              $e->setLine(0, "§cERROR");
              $e->setLine(1, "§7".$e->getLine(1));
              $e->setLine(2, "§7Arena");
              $e->setLine(3, "§7not exactly!");
            }
          }else{
            $e->setLine(0, "§cERROR");
            $e->setLine(1, "§7".$e->getLine(1));
            $e->setLine(2, "§7Arena");
            $e->setLine(3, "§7Not found");
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7Arena");
          $e->setLine(2, "§7Section");
          $e->setLine(3, "§7null!");
        }
      }elseif ($e->getLine(0) === "generator"){
        if(!empty($e->getLine(1))){
          switch ($e->getLine(1)){
            case "Iron":
            $e->setLine(0, "§fIron");
            $e->setLine(1, "§eLevel 1");
            $e->setLine(2, "§b4 seconds");
            $e->setLine(3, "§a§lUpgrade");
            break;
            case "Gold":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b5 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§6Gold");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
            case "Diamond":
            if($e->getLine(2) != "Broken") {
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 1");
              $e->setLine(2, "§b10 seconds");
              $e->setLine(3, "§a§lUpgrade");
            }else{
              $e->setLine(0, "§bDiamond");
              $e->setLine(1, "§eLevel 0");
              $e->setLine(2, "§bBroken");
              $e->setLine(3, "§a§l-------");
            }
            break;
          }
        }else{
          $e->setLine(0, "§cERROR");
          $e->setLine(1, "§7generator");
          $e->setLine(2, "§7Type");
          $e->setLine(3, "§7unspecified!");
        }
      }
    }
  }

  public function onDeath(PlayerDeathEvent $e){
    $p = $e->getPlayer();
    $main = EggWars::getInstance();
    if($main->IsInArena($p->getName())){
      $e->setDeathMessage("");
      $sondarbe = $p->getLastDamageCause();
      if($sondarbe instanceof EntityDamageByEntityEvent){
        $e->setDrops(array());
        $plduren = $sondarbe->getDamager();
        if($plduren instanceof Player){
          $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §ewas killed by ".$plduren->getNameTag());
        }
      }else{
        $e->setDrops(array());
        if(!empty($this->sd[$p->getName()])){
          $plduren = $main->getServer()->getPlayer($this->sd[$p->getName()]);
          if($plduren instanceof Player){
            $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §ewas killed by ".$plduren->getNameTag());
          }
        }else{
          $main->ArenaMessage($main->IsInArena($p->getName()), $p->getNameTag()." §edied!");
        }
      }
    }
  }

  public function Damage(EntityDamageEvent $e){
    $p = $e->getEntity();
    $main = EggWars::getInstance();
    if($p->getLevel()->getName() === "ELobby"){
      $e->setCancelled();
    }
    if($e instanceof EntityDamageByEntityEvent){
      $d = $e->getDamager();
      if($p instanceof Villager && $d instanceof Player){
        if($p->getNameTag() === "§6EggWars Shop"){
          $e->setCancelled();
          $main->EmptyShop($d);
          return;
        }
      }
      if($p instanceof Player && $d instanceof Player){
        if($main->IsInArena($p->getName())){
          $arena = $main->IsInArena($p->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          $team = $main->PlayerTeamColor($p);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }else{
            $td = substr($d->getNameTag(), 0, 3);
            $to = substr($p->getNameTag(), 0, 3);
            if($td === $to){
              $e->setCancelled();
            }else{
              $this->sd[$p->getName()] = $d->getName();
            }
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $p->setHealth(20);
            $p->setFood(20);
            if($main->EggSkin($arena, $team)){
              $main->RemoveArenaPlayer($arena, $p->getName());
            }else{
              $p->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $p->getNameTag()." §ewas killed by ".$d->getNameTag());
            }
            $p->getInventory()->clearAll();
            $p->getInventory()->sendContents($p);
          }
        }else{
          $e->setCancelled();
        }
      }
    }else{
      if($p instanceof Player){
        if($main->IsInArena($p->getName())){
          $arena = $main->IsInArena($p->getName());
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          if($ac->get("Status") === "Lobby"){
            $e->setCancelled();
          }
          $team = $main->PlayerTeamColor($p);
          $message = null;
          if(!empty($this->sd[$p->getName()])){
            $sd = $main->getServer()->getPlayer($this->sd[$p->getName()]);
            if($sd instanceof Player){
              unset($this->sd[$p->getName()]);
              $message = $p->getNameTag()." §ewas killed by ".$sd->getNameTag();
            }else{
              $message = $p->getNameTag()." §edied!";
            }
          }else{
            $message = $p->getNameTag()." §edied!";
          }
          if($e->getDamage() >= $e->getEntity()->getHealth()){
            $e->setCancelled();
            $p->setHealth(20);
            $p->setFood(20);
            if($main->EggSkin($arena, $team)){
              $pname = $p->getName();
              $main->RemoveArenaPlayer($arena, $p->getName());
              $main->ArenaMessage($arena, $message);
              $main->ArenaMessage($arena, "§c$pname has been eliminated from the game.");

            }else{
              $p->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $message);
            }
            $p->getInventory()->clearAll();
            $p->getInventory()->sendContents($p);
          }
        }
      }
    }
  }

  public function onMove(PlayerMoveEvent $e){
    $p = $e->getPlayer();
    $main = EggWars::getInstance();
    if ($p->getLevel()->getFolderName() === "ELobby"){
      if($e->getTo()->getFloorY() < 3){
        $p->teleport($p->getLevel()->getSafeSpawn());
      }
    }
    if ($p->getLevel()->getFolderName() === "EWaiting"){
      if($e->getTo()->getFloorY() < 3){
        $p->teleport($p->getLevel()->getSafeSpawn());;
      }
    }
  }

  public function CloseInv(InventoryCloseEvent $event){
    $player = $event->getPlayer();
    $main = EggWars::getInstance();
    if($event->getInventory() instanceof ChestInventory){
      if(!empty($main->mk[$player->getName()])){
        $player->getLevel()->setBlockIdAt($player->getFloorX(), $player->getFloorY() - 4, $player->getFloorZ(), 0);
        unset($main->mk[$player->getName()]);
      }
    }
  }

  public function StoreEvent(InventoryTransactionEvent $e){
    $main = EggWars::getInstance();
    foreach ($e->getTransaction()->getTransactions() as $t) {
      $env = $t->getInventory();
      if ($env instanceof ChestInventory) {
        foreach ($env->getViewers() as $o) {
          if(empty($main->mk[$o->getName()])) return;
          $sandik = $env->getHolder(); // item:id:amount:paymentid:paymentamount
          if ($sandik instanceof Chest) {
            $shopitems = $main->shop;
            $item = $t->getSourceItem($t->getSlot());
            if(!($item instanceof Item)) return;
            if($env->getItem(26)->getId() == 0){ // Start menu
              foreach ($shopitems as $shopitem){
                $mitem = Item::fromString($shopitem["item"]);
                if($mitem->getId() == $item->getId()){
                  $env->clearAll();
                  foreach($shopitem["items"] as $slot => $gitem){
                    $parcala = explode(":", $gitem);
                    $env->setItem($slot * 2, Item::get($parcala[0], $parcala[1], $parcala[2]));
                    $env->setItem($slot * 2 + 1, Item::get($parcala[3], 0, $parcala[4]));
                  }
                  $env->setItem(26, Item::get(Item::WOOL, 14, 1)->setCustomName("§r§cBack"));
                }
                $e->setCancelled();
              }
            }else{
              $illegal = [264,265,266];
              if(in_array($item->getId(), $illegal)){
                $e->setCancelled();
              }else{
                $slot = $t->getSlot();
                if($slot == 26){
                  $env->clearAll();
                  foreach($shopitems as $slot => $shopitem){
                    $mitem = Item::fromString($shopitem["item"])->setCustomName("§r".$shopitem["name"]);
                    $env->setItem($slot, $mitem);
                  }
                  $e->setCancelled();
                }else{
                  if($o->getInventory()->contains($env->getItem($slot + 1))){
                    $o->getInventory()->removeItem($env->getItem($slot + 1));
                    $o->getInventory()->addItem($env->getItem($slot));
                    $o->getInventory()->sendContents($o);
                  }else{
                    $e->setCancelled();
                  }
                }
              }
            }
            $e->setCancelled();
          }
        }
      }
    }
  }

  public function BlockBreakEvent(BlockBreakEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($p->getLevel()->getName() === "ELobby"){
      if (!$p->isOP()){
        $e->setCancelled();
      }
    }
    if($main->IsInArena($p->getName())){
      $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
      $ad = $main->ArenaStatus($main->IsInArena($p->getName()));
      if($ad === "Lobby"){
        $e->setCancelled(true);
        return;
      }
      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$p->isOp()){
        $e->setCancelled(true);
      }
    }
  }

  public function BlockPlaceEvent(BlockPlaceEvent $e){
    $p = $e->getPlayer();
    $b = $e->getBlock();
    $main = EggWars::getInstance();
    if($p->getLevel()->getName() === "ELobby" ||  $p->getLevel()->getName() === "EWaiting"){
      if (!$p->isOP()){
        $e->setCancelled();
      }
    }
    $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
    if($main->IsInArena($p->getName())){
      $ad = $main->ArenaStatus($main->IsInArena($p->getName()));
      if($ad === "Lobby"){
        if($b->getId() === 35){
          $arena = $main->IsInArena($p->getName());
          $tyun = array_search($b->getDamage() ,$main->TeamSearcher());
          $marena = $main->AvailableTeams($arena);
          if(in_array($tyun, $marena)){
            $color = $main->Teams()[$tyun];
            $p->setNameTag($color.$p->getName());
            $p->sendPopup("§8» Team $color"."$tyun Selected!");
          }else{
            $p->sendPopup("§8» §cTeams must be equal!");
          }
          $e->setCancelled();
        }
        return;
      }

      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($b->getId() != $blok){
          $e->setCancelled();
        }else{
          $e->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$p->isOp()){
        $e->setCancelled(true);
      }
    }
  }

}
