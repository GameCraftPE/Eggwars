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

  /** @var int */
  private $seconds = 0;

  private $plugin;

  public function __construct($plugin){
    parent::__construct($plugin);
    $this->plugin = $plugin;
  }

  public function onRun(int $tick){
    $main = $this->plugin;
    foreach($main->getServer()->getOnlinePlayers() as $player){
      if($player->getLevel()->getFolderName() === "ELobby"){
        if(!$player->getInventory()->getItemInHand()->hasEnchantments()){
          $player->sendPopup(TF::GRAY."You are playing on ".TF::BOLD.TF::BLUE."GameCraft PE EggWars".TF::RESET."\n".TF::DARK_GRAY."[".TF::LIGHT_PURPLE.count($main->getServer()->getOnlinePlayers()).TF::DARK_GRAY."/".TF::LIGHT_PURPLE.$main->getServer()->getMaxPlayers().TF::DARK_GRAY."] | ".TF::YELLOW."$".$main->getServer()->getPluginManager()->getPlugin("EconomyAPI")->myMoney($player).TF::DARK_GRAY." | ".TF::BOLD.TF::AQUA."Vote: ".TF::RESET.TF::GREEN."vote.gamecraftpe.tk");
        }
      }
    }
    $main->tick();
  }
}
