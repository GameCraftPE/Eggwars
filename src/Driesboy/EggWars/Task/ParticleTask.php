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

  private $p;

  private $degrees;

  public function __construct($p){
    $this->p = $p;
    parent::__construct($p);
  }

  public function onRun(int $tick){
    $main = $this->p;
    foreach($main->getServer()->getOnlinePlayers() as $p) {
      if($p->hasPermission("rank.lapis")) {
        $x = (cos(deg2rad($this->degrees)) * 0.6) + $p->x;
        $z = (sin(deg2rad($this->degrees)) * 0.6) + $p->z;
        $p->getLevel()->addParticle(new FlameParticle(new Vector3($x, $p->y + 2.2, $z)));
        if($this->degrees === 360) {
          $this->degrees = 0;
        } else {
          $this->degrees += 6;
        }
      }
    }
  }
}
