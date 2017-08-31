<?php

namespace Driesboy\EggWars\Task;

use pocketmine\scheduler\PluginTask;
use pocketmine\Server;
use pocketmine\level\particle\FlameParticle;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\math\AxisAlignedBB;
use pocketmine\utils\TextFormat as TF;

class ParticleTask extends PluginTask{

  /** @var float */
  private $degrees;

  private $plugin;

  public function __construct($plugin){
    parent::__construct($plugin);
    $this->plugin = $plugin;
  }

  public function onRun(int $currentTick){
    $main = $this->plugin;
    foreach($main->getServer()->getOnlinePlayers() as $player) {
      if($player->hasPermission("rank.lapis")) {
        $x = (cos(deg2rad($this->degrees)) * 0.6) + $player->x;
        $z = (sin(deg2rad($this->degrees)) * 0.6) + $player->z;
        $player->getLevel()->addParticle(new FlameParticle(new Vector3($x, $player->y + 2.2, $z)));
        if($this->degrees === 360) {
          $this->degrees = 0;
        } else {
          $this->degrees += 6;
        }
      }
    }
  }
}
