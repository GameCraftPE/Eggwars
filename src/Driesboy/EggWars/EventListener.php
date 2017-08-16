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
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
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

  public function OnJoin(PlayerJoinEvent $event){
    $player = $event->getPlayer();
    if ($player->hasPermission("rank.diamond")){
      $player->setGamemode("1");
      $pk = new ContainerSetContentPacket();
      $pk->windowid = ContainerIds::CREATIVE;
      $pk->targetEid = $player->getId();
      $player->dataPacket($pk);
    }
  }

  public function OnQuit(PlayerQuitEvent $event){
    $main = EggWars::getInstance();
    $player = $event->getPlayer();
    if($main->IsInArena($player)){
      $arena = $main->IsInArena($player);
      $main->RemoveArenaPlayer($arena, $player);
      $player->teleport(Server::getInstance()->getDefaultLevel()->getSafeSpawn());
      $message = $player->getNameTag()." §eleft the game!";
      $main->ArenaMessage($arena, $message);
    }
  }

  /**
  * Priority is the MONITOR so it can pass PureChat plugin priority.
  *
  * @param PlayerChatEvent $e
  * @priority MONITOR
  */
  public function Chat(PlayerChatEvent $event){
    $player = $event->getPlayer();
    $message = $event->getMessage();
    $main = EggWars::getInstance();
    if($main->IsInArena($player)){
      $color = "";
      $is = substr($message, 0, 1);
      $team = $main->PlayerTeamColor($player);
      $arena = $main->IsInArena($player);
      $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
      $players = $main->players[$arena];
      if($main->status[$arena] === "Lobby"){
        foreach($players as $p){
          $to = $main->getServer()->getPlayer($p);
          if($to instanceof Player){
            $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($player, $message);
            $to->sendMessage($chatFormat);
            $event->setCancelled();
          }
        }
      }
      if(!empty($main->Teams()[$team])){
        $color = $main->Teams()[$team];
      }
      if ($main->status[$arena] != "Lobby"){
        if($is === "!"){
          foreach($players as $p){
            $to = $main->getServer()->getPlayer($p);
            if($to instanceof Player){
              $msil = substr($m, 1);
              $chatFormat = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($player, $msil);
              $to->sendMessage($chatFormat);
              $event->setCancelled();
            }
          }
        }else{
          foreach($players as $p){
            $to = $main->getServer()->getPlayer($p);
            if($to instanceof Player){
              $toTeam = $main->PlayerTeamColor($to);
              if($team === $toTeam){
                $format = $main->getServer()->getPluginManager()->getPlugin("PureChat")->getChatFormat($player, $message);
                $message = "§8[".$color."team§8] ". $format;
                $to->sendMessage($message);
                $event->setCancelled();
              }
            }
          }
        }
      }
    }
  }

  public function OnInteract(PlayerInteractEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $tile = $player->getLevel()->getTile($block);
    $main = EggWars::getInstance();
    if($tile instanceof Sign){
      $text = $tile->getText();
      if($text[0] === '§8§l» §r§6Egg §fWars §l§8«'){
        $arena = str_ireplace("§e", "", $text[2]);
        $status = $main->ArenaStatus($arena);
        if($status === "Lobby"){
          if(!$main->IsInArena($player)){
            $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
            $players = count($main->players[$arena]);
            $fullPlayer = $main->teamscount[$arena] * $main->perteamcount[$arena];
            if($players >= $fullPlayer){
              $player->sendPopup("§8» §cThis game is full! §8«");
              return;
            }
            $main->AddArenaPlayer($arena, $player);
            $player->teleport(new Position($ac->getNested("Lobby.X"), $ac->getNested("Lobby.Y"), $ac->getNested("Lobby.Z"), $main->getServer()->getLevelByName($ac->getNested("Lobby.World"))));
            $main->TeamSellector($arena, $player);
            $main->ArenaMessage($arena, "§5".$player->getName()." §5joined the game. ". count($main->players[$arena]) . "/" .$main->teamscount[$arena] * $main->perteamcount[$arena]);
          }else{
            $player->sendPopup("§cYou're already in a game!");
          }
        }elseif ($status === "In-Game"){
          $player->sendPopup("§8» §dThe game is still going on!");
        }elseif ($status === "Done"){
          $player->sendPopup("§8» §eResetting the Arena ...");
        }
        $event->setCancelled();
      }
    }
  }

  public function UpgradeGenerator(PlayerInteractEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $sign = $player->getLevel()->getTile($block);
    $main = EggWars::getInstance();
    if($sign instanceof Sign){
      $text = $sign->getText();
      if($text[0] === "§fIron" || $text[0] === "§6Gold" || $text[0] === "§bDiamond"){
        $tip = $text[0];
        $level = str_ireplace("§eLevel ", "", $text[1]);
        switch($level){
          case 0:
          switch ($tip){
            case "§6Gold":
            if($main->ItemId($player, Item::GOLD_INGOT) >= 5){
              $player->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,5));
              $sign->setText($text[0], "§eLevel 1", "§b5 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §65 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($player, Item::DIAMOND) >= 5){
              $player->getInventory()->removeItem(Item::get(Item::DIAMOND,0,5));
              $sign->setText($text[0], "§eLevel 1", "§b10 seconds", $text[3]);
              $player->sendMessage("§8» §aDiamond generator Activated!");
            }else{
              $player->sendMessage("§8» §b5 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 1:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($player, Item::IRON_INGOT) >= 20){
              $player->getInventory()->removeItem(Item::get(Item::IRON_INGOT,0,20));
              $sign->setText($text[0], "§eLevel 2", "§b3 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §f20 Iron needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($player, Item::GOLD_INGOT) >= 10){
              $player->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,10));
              $sign->setText($text[0], "§eLevel 2", "§b4 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §610 Gold needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($player, Item::DIAMOND) >= 10){
              $player->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($text[0], "§eLevel 2", "§b5 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 2:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($player, Item::GOLD_INGOT) >= 20){
              $player->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,20));
              $sign->setText($text[0], "§eLevel 3", "§b2 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §620 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($player, Item::DIAMOND) >= 10){
              $player->getInventory()->removeItem(Item::get(Item::DIAMOND,0,10));
              $sign->setText($text[0], "§eLevel 3", "§b2 seconds", $text[3]);
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §b10 Diamonds needed to upgrade!");
            }
            break;
            case "§bDiamond":
            if($main->ItemId($player, Item::DIAMOND) >= 25){
              $player->getInventory()->removeItem(Item::get(Item::DIAMOND,0,25));
              $sign->setText($text[0], "§eLevel 3", "§b3 seconds", "§c§lMAXIMUM");
              $player->sendMessage("§aUpgraded generator!");;
            }else{
              $player->sendMessage("§8» §b25 Diamonds needed to upgrade!");
            }
            break;
          }
          break;
          case 3:
          switch ($tip){
            case "§fIron":
            if($main->ItemId($player, Item::GOLD_INGOT) >= 50){
              $player->getInventory()->removeItem(Item::get(Item::GOLD_INGOT,0,50));
              $sign->setText($text[0], "§eLevel 4", "§b1 seconds", "§c§lMAXIMUM");
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §650 Gold needed to upgrade!");
            }
            break;
            case "§6Gold":
            if($main->ItemId($player, Item::DIAMOND) >= 25){
              $player->getInventory()->removeItem(Item::get(Item::DIAMOND,0,25));
              $sign->setText($text[0], "§eLevel 4", "§b1 seconds", "§c§lMAXIMUM");
              $player->sendMessage("§aUpgraded generator!");
            }else{
              $player->sendMessage("§8» §b25 Diamonds needed to upgrade!");
            }
            break;

          }
          break;
          default:
          $player->sendMessage("§8» §cThis generator is already on the Maximum level!");
          break;
        }
      }
    }
  }

  public function DestroyEgg(PlayerInteractEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $main = EggWars::getInstance();
    if($main->IsInArena($player)){
      if($block->getId() === 122){
        $wool = $block->getLevel()->getBlock(new Vector3($block->x, $block->y - 1, $block->z));
        if($wool->getId() === 35){
          $color = $wool->getDamage();
          $team = array_search($color, $main->TeamSearcher());
          $oht = $main->PlayerTeamColor($player);
          if($oht === $team){
            $player->sendPopup("§8»§c You can not break your own egg!");
            $event->setCancelled();
          }else{
            $block->getLevel()->setBlock(new Vector3($block->x, $block->y, $block->z), Block::get(0));
            $main->lightning($block->x, $block->y, $block->z, $player->getLevel());
            $arena = $main->IsInArena($player);
            $main->egg[$arena][] = $team;
            $main->ArenaMessage($main->IsInArena($player), "§eTeam " .$main->Teams()[$team]."$team's".$main->Teams()[$pht]." §eegg has been destroyed by " .$player->getNameTag());
          }
        }
      }
    }
  }

  public function CreateSign(SignChangeEvent $event){
    $player = $event->getPlayer();
    $main = EggWars::getInstance();
    if($player->isOp()){
      if($event->getLine(0) === "eggwars"){
        if(!empty($event->getLine(1))){
          if($main->ArenaControl($event->getLine(1))){
            if($main->ArenaReady($event->getLine(1))){
              $arena = $event->getLine(1);
              $event->setLine(0, $main->tyazi);
              $event->setLine(1, "§f0/0");
              $event->setLine(2, "§e$arena");
              $event->setLine(3, "§l§bTap to Join");
              for($i = 0; $i <= 3; $i++){
                $p->sendMessage("§8» §a$i".$event->getLine($i));
              }
            }else{
              $event->setLine(0, "§cERROR");
              $event->setLine(1, "§7".$event->getLine(1));
              $event->setLine(2, "§7Arena");
              $event->setLine(3, "§7not exactly!");
            }
          }else{
            $event->setLine(0, "§cERROR");
            $event->setLine(1, "§7".$event->getLine(1));
            $event->setLine(2, "§7Arena");
            $event->setLine(3, "§7Not found");
          }
        }else{
          $event->setLine(0, "§cERROR");
          $event->setLine(1, "§7Arena");
          $event->setLine(2, "§7Section");
          $event->setLine(3, "§7null!");
        }
      }elseif ($event->getLine(0) === "generator"){
        if(!empty($event->getLine(1))){
          switch ($event->getLine(1)){
            case "Iron":
            $event->setLine(0, "§fIron");
            $event->setLine(1, "§eLevel 1");
            $event->setLine(2, "§b4 seconds");
            $event->setLine(3, "§a§lUpgrade");
            break;
            case "Gold":
            if($event->getLine(2) != "Broken") {
              $event->setLine(0, "§6Gold");
              $event->setLine(1, "§eLevel 1");
              $event->setLine(2, "§b5 seconds");
              $event->setLine(3, "§a§lUpgrade");
            }else{
              $event->setLine(0, "§6Gold");
              $event->setLine(1, "§eLevel 0");
              $event->setLine(2, "§bBroken");
              $event->setLine(3, "§a§l-------");
            }
            break;
            case "Diamond":
            if($event->getLine(2) != "Broken") {
              $event->setLine(0, "§bDiamond");
              $event->setLine(1, "§eLevel 1");
              $event->setLine(2, "§b10 seconds");
              $event->setLine(3, "§a§lUpgrade");
            }else{
              $event->setLine(0, "§bDiamond");
              $event->setLine(1, "§eLevel 0");
              $event->setLine(2, "§bBroken");
              $event->setLine(3, "§a§l-------");
            }
            break;
          }
        }else{
          $event->setLine(0, "§cERROR");
          $event->setLine(1, "§7generator");
          $event->setLine(2, "§7Type");
          $event->setLine(3, "§7unspecified!");
        }
      }
    }
  }

  public function onDeath(PlayerDeathEvent $event){
    $player = $event->getPlayer();
    $main = EggWars::getInstance();
    if($main->IsInArena($player)){
      $event->setDeathMessage("");
      $lastdamage = $player->getLastDamageCause();
      if($lastdamage instanceof EntityDamageByEntityEvent){
        $event->setDrops(array());
        $lastdamageplayer = $lastdamage->getDamager();
        if($lastdamageplayer instanceof Player){
          $main->ArenaMessage($main->IsInArena($player), $player->getNameTag()." §ewas killed by ".$lastdamageplayer->getNameTag());
        }
      }else{
        $event->setDrops(array());
        if(!empty($this->sd[$p->getName()])){
          $lastdamageplayer = $main->getServer()->getPlayer($this->sd[$p->getName()]);
          if($lastdamageplayer instanceof Player){
            $main->ArenaMessage($main->IsInArena($player), $player->getNameTag()." §ewas killed by ".$lastdamageplayer->getNameTag());
          }
        }else{
          $main->ArenaMessage($main->IsInArena($player), $player->getNameTag()." §edied!");
        }
      }
    }
  }

  public function Damage(EntityDamageEvent $event){
    $player = $event->getEntity();
    $main = EggWars::getInstance();
    if($player->getLevel()->getFolderName() === "ELobby"){
      $event->setCancelled();
    }
    if($event instanceof EntityDamageByEntityEvent){
      $damager = $event->getDamager();
      if($player instanceof Villager && $damager instanceof Player){
        if($player->getNameTag() === "§6EggWars Shop"){
          $event->setCancelled();
          $main->m[$damager->getName()] = "ok";
          $main->EmptyShop($damager);
        }
      }
      if($player instanceof Player && $damager instanceof Player){
        if($main->IsInArena($player)){
          $arena = $main->IsInArena($player);
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          $team = $main->PlayerTeamColor($player);
          if($main->status[$arena] === "Lobby"){
            $event->setCancelled();
          }else{
            $td = substr($damager->getNameTag(), 0, 3);
            $to = substr($player->getNameTag(), 0, 3);
            if($td === $to){
              $event->setCancelled();
            }else{
              $this->sd[$player->getName()] = $damager->getName();
            }
          }
          if($event->getDamage() >= $event->getEntity()->getHealth()){
            $event->setCancelled();
            $player->setHealth(20);
            $player->setFood(20);
            if($main->EggSkin($arena, $team)){
              $main->RemoveArenaPlayer($arena, $player);
            }else{
              $player->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $player->getNameTag()." §ewas killed by ".$damager->getNameTag());
            }
            $player->getInventory()->clearAll();
            $player->getInventory()->sendContents($player);
          }
        }else{
          $event->setCancelled();
        }
      }
    }else{
      if($player instanceof Player){
        if($main->IsInArena($player)){
          $arena = $main->IsInArena($player);
          $ac = new Config($main->getDataFolder()."Arenas/$arena.yml", Config::YAML);
          if($main->status[$arena] === "Lobby"){
            $event->setCancelled();
          }
          $team = $main->PlayerTeamColor($player);
          $message = null;
          if(!empty($this->sd[$player->getName()])){
            $sd = $main->getServer()->getPlayer($this->sd[$player->getName()]);
            if($sd instanceof Player){
              unset($this->sd[$player->getName()]);
              $message = $player->getNameTag()." §ewas killed by ".$sd->getNameTag();
            }else{
              $message = $player->getNameTag()." §edied!";
            }
          }else{
            $message = $player->getNameTag()." §edied!";
          }
          if($event->getDamage() >= $event->getEntity()->getHealth()){
            $event->setCancelled();
            $player->setHealth(20);
            $player->setFood(20);
            if($main->EggSkin($arena, $team)){
              $playername = $player->getName();
              $main->RemoveArenaPlayer($arena, $player);
              $main->ArenaMessage($arena, $message);
              $main->ArenaMessage($arena, "§c$playername has been eliminated from the game.");

            }else{
              $player->teleport(new Position($ac->getNested("$team.X"), $ac->getNested("$team.Y"), $ac->getNested("$team.Z"), $main->getServer()->getLevelByName($ac->get("World"))));
              $main->ArenaMessage($arena, $message);
            }
            $player->getInventory()->clearAll();
            $player->getInventory()->sendContents($player);
          }
        }
      }
    }
  }

  public function onMove(PlayerMoveEvent $event){
    $player = $event->getPlayer();
    $main = EggWars::getInstance();
    if ($player->getLevel()->getFolderName() === "ELobby"){
      if($event->getTo()->getFloorY() < 3){
        $player->teleport($player->getLevel()->getSafeSpawn());
      }
    }
    if ($player->getLevel()->getFolderName() === "EWaiting"){
      if($event->getTo()->getFloorY() < 3){
        $player->teleport($player->getLevel()->getSafeSpawn());;
      }
    }
  }

  public function envKapat(InventoryCloseEvent $e){
    $player = $e->getPlayer();
    $env = $e->getInventory();
    $main = EggWars::getInstance();
    if($env instanceof ChestInventory){
      if(!empty($main->m[$player->getName()])){
        $player->getLevel()->setBlock(new Vector3($player->getFloorX(), $player->getFloorY() - 4, $player->getFloorZ()), Block::get(Block::AIR));
        unset($main->m[$player->getName()]);
      }
    }
  }

  public function StoreEvent(InventoryTransactionEvent $e){
    $envanter = $e->getTransaction()->getInventories();
    $trans = $e->getTransaction()->getTransactions();
    $main = EggWars::getInstance();
    $player = null;
    $sb = null;
    $transfer = null;
    foreach($envanter as $env){
      $Held = $env->getHolder();
      if($Held instanceof Chest){
        $sb = $Held->getBlock();
      }
      if($Held instanceof Player){
        $player = $Held;
      }
    }

    foreach($trans as $t){
      if($t->getInventory() instanceof PlayerInventory){
        $transfer = $t;
      }
    }

    if($player != null and $sb != null and $transfer != null){

      $shopc = new Config($main->getDataFolder()."shop.yml", Config::YAML);
      $shop = $shopc->get("shop");
      $sandik = $player->getLevel()->getTile($sb);
      if($sandik instanceof Chest){
        $item = $transfer->getTargetItem();
        $si = $sandik->getInventory();

        if(empty($main->m[$player->getName()])){
          $itemler = 0;
          for($i=0; $i<count($shop); $i += 2){
            $slot = $i / 2;
            if($item->getId() === $shop[$i]){
              $itemler++;
            }
          }
          if($itemler === count($shop)){
            $main->m[$player->getName()] = 1;
          }
        }else{
          $e->setCancelled();
          if($item->getId() === 35 && $item->getDamage() === 14){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            $sandik->getInventory()->clearAll();
            for($i=0; $i<count($shop); $i += 2){
              $slot = $i / 2;
              $sandik->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
            }
          }
          $transSlot = 0;
          for($i=0; $i<$si->getSize(); $i++){
            if($si->getItem($i)->getId() === $item->getId()){
              $transSlot = $i;
              break;
            }
          }
          $is = $si->getItem(1)->getId();
          if($transSlot % 2 != 0 && ($is === 264 or $is === 265 or $is === 266)){
            $e->setCancelled();
          }
          if($item->getId() === 264 or $item->getId() === 265 or $item->getId() === 266){
            $e->setCancelled();
          }
          if($transSlot % 2 === 0 && ($is === 264 or $is === 265 or $is === 266)){
            $ucret = $si->getItem($transSlot + 1)->getCount();
            $para = $main->ItemId($player, $si->getItem($transSlot + 1)->getId());
            if($para >= $ucret){
              $player->getInventory()->removeItem(Item::get($si->getItem($transSlot + 1)->getId(), 0, $ucret));
              $aitemd = $si->getItem($transSlot);
              $aitem = Item::get($aitemd->getId(), $aitemd->getDamage(), $aitemd->getCount());
              $player->getInventory()->addItem($aitem);
            }
            $e->setCancelled();
          }
          if($is != 264 or $is != 265 or $is != 266){
            $e->setCancelled();
            $shopc->reload();
            $shop = $shopc->get("shop");
            for($i=0; $i<count($shop); $i+=2){
              if($item->getId() === $shop[$i]){
                $sandik->getInventory()->clearAll();
                $gyer = $shop[$i+1];
                $slot = 0;
                for($e=0; $e<count($gyer); $e++){
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][0], 0, $gyer[$e][1]));
                  $slot++;
                  $sandik->getInventory()->setItem($slot, Item::get($gyer[$e][2], 0, $gyer[$e][3]));
                  $slot++;
                }
                break;
              }
            }
            $sandik->getInventory()->setItem($sandik->getInventory()->getSize() - 1, Item::get(Item::WOOL, 14, 1));
          }
        }
      }
    }

  }

  public function BlockBreakEvent(BlockBreakEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $main = EggWars::getInstance();
    if($player->getLevel()->getName() === "ELobby"){
      if (!$player->isOP()){
        $event->setCancelled();
      }
    }
    if($main->IsInArena($player)){
      $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
      $ad = $main->ArenaStatus($main->IsInArena($player));
      if($ad === "Lobby"){
        $event->setCancelled(true);
        return;
      }
      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($block->getId() != $blok){
          $event->setCancelled();
        }else{
          $event->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$player->isOp()){
        $event->setCancelled(true);
      }
    }
  }

  public function BlockPlaceEvent(BlockPlaceEvent $event){
    $player = $event->getPlayer();
    $block = $event->getBlock();
    $main = EggWars::getInstance();
    if($player->getLevel()->getName() === "ELobby" ||  $player->getLevel()->getName() === "EWaiting"){
      if (!$player->isOP()){
        $event->setCancelled();
      }
    }
    $cfg = new Config($main->getDataFolder()."config.yml", Config::YAML);
    if($main->IsInArena($player)){
      if($main->ArenaStatus($main->IsInArena($player)) === "Lobby"){
        if($block->getId() === 35){
          $tyun = array_search($block->getDamage() ,$main->TeamSearcher());
          $marena = $main->AvailableTeams($main->IsInArena($player));
          if(in_array($tyun, $marena)){
            $color = $main->Teams()[$tyun];
            $player->setNameTag($color.$player->getName());
            $player->sendPopup("§8» Team $color"."$tyun Selected!");
          }else{
            $player->sendPopup("§8» §cTeams must be equal!");
          }
          $event->setCancelled();
        }
        return;
      }

      $bloklar = $cfg->get("BuildBlocks");
      foreach($bloklar as $blok){
        if($block->getId() != $blok){
          $event->setCancelled();
        }else{
          $event->setCancelled(false);
          break;
        }
      }
    }else{
      if(!$player->isOp()){
        $event->setCancelled(true);
      }
    }
  }

}
