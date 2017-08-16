<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\tile\Sign;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;

class SignManager extends PluginTask{

  private $p;

  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun(int $currentTick){
    $main = $this->p;
    $level = Server::getInstance()->getDefaultLevel();
    $tiles = $level->getTiles();
    foreach ($tiles as $tile){
      if($tile instanceof Sign){
        $text = $tile->getText();
        if($text[0] === $main->tyazi){
          $arena = str_ireplace("§e", "", $text[2]);
          $status = $main->status[$arena];
          $players = count($main->players[$arena]);
          $fullPlayer = $main->teamscount[$arena] * $main->perteamcount[$arena];
          $newstatus = null;
          $re = null;
          $block = $tile->getBlock();
          if($status === "Lobby"){
            if($players >= $fullPlayer){
              $newstatus = "§c§lFull";
              $re = 14;
            }else{
              $newstatus = "§a§lTap to join";
              $re = 5;
            }
          }elseif ($status === "In-Game"){
            $newstatus = "§d§lIn-Game";
            $re = 1;
          }elseif($status === "Done"){
            $newstatus = "§9§lRestarting";
            $re = 4;
          }
          $ab = $block->getSide(Vector3::SIDE_SOUTH, 1);
          $ba = $block->getSide(Vector3::SIDE_NORTH, 1);
          $ca = $block->getSide(Vector3::SIDE_EAST, 1);
          $ac = $block->getSide(Vector3::SIDE_WEST, 1);
          $tile->setText($text[0], "§f$players/$fullPlayer", $text[2], $newstatus);
          if($ac->getId() === 35){
            $ac->setDamage($re);
            $block->getLevel()->setBlock($ac, $ac);
          }elseif($ca->getId() === 35){
            $ca->setDamage($re);
            $block->getLevel()->setBlock($ca, $ca);
          }elseif($ab->getId() === 35){
            $ab->setDamage($re);
            $block->getLevel()->setBlock($ab, $ab);
          }elseif($ba->getId() === 35){
            $ba->setDamage($re);
            $block->getLevel()->setBlock($ba, $ba);
          }
        }
      }
    }
  }
}
