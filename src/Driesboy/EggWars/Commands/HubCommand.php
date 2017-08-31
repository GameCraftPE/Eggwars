<?php

namespace Driesboy\EggWars\Commands;

use Driesboy\EggWars\EggWars;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

class HubCommand extends Command{

  public function __construct(){
    parent::__construct("hub", "Hub Command");
    $this->setAliases(array("lobby", "spawn", "leave"));
  }

  public function execute(CommandSender $sender, string $label, array $args){
    $main = EggWars::getInstance();
    if($arena = $main->IsInArena($sender)){
      $main->RemoveArenaPlayer($arena, $sender);
      $sender->sendMessage("§8» §aYou are teleported to the Lobby");
    }
  }
}
