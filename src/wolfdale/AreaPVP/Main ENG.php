<?php

namespace wolfdale\AreaPVP;

use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener {
	
	public $temp = array();

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this,$this);
		$this->loadYml();
    }

	public function onCommand(CommandSender $p,Command $cmd,$label,array $args) {
		if ($cmd->getName() === "areapvp"){
			if (!isset($args[0])) return false;
			if(!($p instanceof Player) && $args[0] !== "list" && $args[0] !== "del"){
				$p->sendMessage(TextFormat::RED . "This Command can only be used in-game.");
				return true;
			}
			$n = $p->getName();
			switch($args[0]){
				case "p1":
					$x = floor($p->x);
					$y = floor($p->y);
					$z = floor($p->z);
					$this->temp[$n]["p1"] = array($x,$y,$z,$p->getLevel()->getName());
					$p->sendMessage("p1 set as $x $y $z");
				break;
				case "p2":
					$x = floor($p->x);
					$y = floor($p->y);
					$z = floor($p->z);
					$this->temp[$n]["p2"] = array($x,$y,$z,$p->getLevel()->getName());
					$p->sendMessage("p2 set as $x $y $z");
				break;
				case "create":
					if (!isset($args[1])){
						$p->sendMessage(TextFormat::RED."Usage: /areapvp create <name>");
						return true;
					}
					if (!isset($this->temp[$n])){
						$p->sendMessage(TextFormat::RED."Please set a point first");
					}
					if (!isset($this->temp[$n]["p1"])){
						$p->sendMessage(TextFormat::RED."Please set p1");
						return true;
					}
					if(!isset($this->temp[$n]["p2"])){
						$p->sendMessage(TextFormat::RED."Please set p2");
						return true;
					}
					if ($this->temp[$n]["p1"][3] !== $this->temp[$n]["p2"][3]){
						$p->sendMessage(TextFormat::RED."p1 and p2 must be in the same world");
						return true;
					}
					$areaname = strtolower($args[1]);
					if (isset($this->areas[$areaname])){
						$p->sendMessage(TextFormat::RED."$areaname already exsits");
						return true;
					}
					$this->areas[$areaname] = array("name" => $areaname,
													"x1" => $this->temp[$n]["p1"][0],
													"y1" => $this->temp[$n]["p1"][1],
													"z1" => $this->temp[$n]["p1"][2],
													"x2" => $this->temp[$n]["p2"][0],
													"y2" => $this->temp[$n]["p2"][1],
													"z2" => $this->temp[$n]["p2"][2],
													"level" => $this->temp[$n]["p2"][3]
													);
					$p->sendMessage(TextFormat::GREEN."area $areaname created successfully!");
					$this->saveYml();
					unset($this->temp[$n]);
				break;
				case "del":
					if (!isset($args[1])){
						$p->sendMessage(TextFormat::RED."Usage: /areapvp del <name>");
						return true;
					}
					$areaname = strtolower($args[1]);
					if (isset($this->areas[$areaname])){
						$p->sendMessage(TextFormat::GREEN."area $areaname deleted successfully");
						unset($this->areas[$args[1]]);
						$this->saveYml();
					}else $p->sendMessage(TextFormat::RED."area $areaname does not exist");
					return true;
				break;
				case "t":
					foreach($this->areas as $area) {
						if ($area === false){
							$p->sendMessage("cannot PVP (PVP is off)");
							return true;
						}elseif($area === true) continue;
						$lv1 = $p->getLevel()->getName();
						$lv2 = $area["level"];
						$p1 = new Vector3($area["x1"],$area["y1"],$area["z1"]);
						$p2 = new Vector3($area["x2"],$area["y2"],$area["z2"]);
						if ($this->canPVP($p,$p1,$p2,$lv1,$lv2)){
							$p->sendMessage("Can PVP");
							return true;
						}
					}
					$p->sendMessage("cannot PVP (not in area)");
				break;
				case "list":
					$output = TextFormat::AQUA."Areas：".TextFormat::GREEN;
					foreach($this->areas as $areaname => $areadata){
						if($areaname !== "PVPisOn") $output .= " $areaname,";
					}
					$p->sendMessage(substr($output, 0, -1));
					return true;
				break;
				case "pvp":
					$this->areas["PVPisOn"] = !$this->areas["PVPisOn"];
					$p->sendMessage("PVP is ".($this->areas["PVPisOn"] ? "enabled" : "disabled"));
					$this->saveYml();
					return true;
				break;
			
			}
			return true;
		}
	}
	
	public function onHurt(EntityDamageEvent $event) {
		$p = $event->getEntity();
		if($p instanceof Player && $event instanceof EntityDamageByEntityEvent) {
			$dmg = $event->getDamager();
			if ($dmg instanceof Player){
				if ($dmg->hasPermission("areapvp.bypass.nopvp")) return;
				foreach($this->areas as $area) {
					if (!isset($area["x1"])){
						if ($area === false){
							$dmg->sendTip(TextFormat::RED."PVP is not enabled");
							$event->setCancelled();
							return;
						}
						continue;
					}
					$lv1 = $p->getLevel()->getName();
					$lv2 = $area["level"];
					$p1 = new Vector3($area["x1"],$area["y1"],$area["z1"]);
					$p2 = new Vector3($area["x2"],$area["y2"],$area["z2"]);
					if($this->canPVP($dmg,$p1,$p2,$lv1,$lv2)){
						if ($this->canPVP($p,$p1,$p2,$lv1,$lv2)){
							return;
						}else $dmg->sendTip(TextFormat::AQUA."Your opponent is outside of the PVP area");
					}else $dmg->sendTip(TextFormat::AQUA."You are outside of the PVP area");
				}
				$event->setCancelled();
			}
		}
	}
	
	public function canPVP(Vector3 $pp, Vector3 $p1, Vector3 $p2, $lv1,$lv2){
		return ((min($p1->getX(),$p2->getX()) <= $pp->getX()) && 
				(max($p1->getX(),$p2->getX()) >= $pp->getX()) && 
				(min($p1->getY(),$p2->getY()) <= $pp->getY()) && 
				(max($p1->getY(),$p2->getY()) >= $pp->getY()) && 
				(min($p1->getZ(),$p2->getZ()) <= $pp->getZ()) && 
				(max($p1->getZ(),$p2->getZ()) >= $pp->getZ()) && 
				$lv1 === $lv2
				);
	}
  
	public function loadYml(){
		@mkdir($this->getDataFolder());
	    $this->config = new Config($this->getDataFolder()."Areas.yml", Config::YAML, array(
			"PVPisOn" => false
		));
		$this->areas = $this->config->getAll();
	}

	public function saveYml(){
		$this->config->setAll($this->areas);
		$this->config->save();
		$this->loadYml();
    }

}
?>
