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
				return true;
			}
			$n = $p->getName();
			switch($args[0]){
				case "p1":
					$x = floor($p->x);
					$y = floor($p->y);
					$z = floor($p->z);
					$this->temp[$n]["p1"] = array($x,$y,$z,$p->getLevel()->getName());
					$p->sendMessage("p1 设置为 $x $y $z");
				break;
				case "p2":
					$x = floor($p->x);
					$y = floor($p->y);
					$z = floor($p->z);
					$this->temp[$n]["p2"] = array($x,$y,$z,$p->getLevel()->getName());
					$p->sendMessage("p2 设置为 $x $y $z");
				break;
				case "create":
					if (!isset($args[1])){
						$p->sendMessage(TextFormat::RED."用法: /areapvp c <name>");
						return true;
					}
					if (!isset($this->temp[$n])){
						$p->sendMessage(TextFormat::RED."请先设置一个点");
					}
					if (!isset($this->temp[$n]["p1"])){
						$p->sendMessage(TextFormat::RED."请先设置p1");
						return true;
					}
					if(!isset($this->temp[$n]["p2"])){
						$p->sendMessage(TextFormat::RED."请先设置p2");
						return true;
					}
					if ($this->temp[$n]["p1"][3] !== $this->temp[$n]["p2"][3]){
						$p->sendMessage(TextFormat::RED."p1 和 p2 必须在同一个世界");
						return true;
					}
					$areaname = strtolower($args[1]);
					if (isset($this->areas[$areaname])){
						$p->sendMessage(TextFormat::RED."$areaname 已经存在");
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
					$p->sendMessage(TextFormat::GREEN."区域 $areaname 创造成功!");
					$this->saveYml();
					unset($this->temp[$n]);
				break;
				case "del":
					if (!isset($args[1])){
						$p->sendMessage(TextFormat::RED."用法: /areapvp d <name>");
						return true;
					}
					$areaname = strtolower($args[1]);
					if (isset($this->areas[$areaname])){
						$p->sendMessage(TextFormat::GREEN."地区 $areaname 成功删除");
						unset($this->areas[$args[1]]);
						$this->saveYml();
					}else $p->sendMessage(TextFormat::RED."地区 $areaname 不存在");
					return true;
				break;
				case "t":
					foreach($this->areas as $area) {
						if ($area === false){
							$p->sendMessage("无法 PVP (PVP 关闭状态)");
							return true;
						}elseif($area === true) continue;
						$lv1 = $p->getLevel()->getName();
						$lv2 = $area["level"];
						$p1 = new Vector3($area["x1"],$area["y1"],$area["z1"]);
						$p2 = new Vector3($area["x2"],$area["y2"],$area["z2"]);
						if ($this->canPVP($p,$p1,$p2,$lv1,$lv2)){
							$p->sendMessage("可 PVP");
							return true;
						}
					}
					$p->sendMessage("无法 PVP (不在范围内)");
				break;
				case "list":
					$output = TextFormat::AQUA."区域：".TextFormat::GREEN;
					foreach($this->areas as $areaname => $areadata){
						if($areaname !== "PVPisOn") $output .= " $areaname,";
					}
					$p->sendMessage(substr($output, 0, -1));
					return true;
				break;
				case "pvp":
					$this->areas["PVPisOn"] = !$this->areas["PVPisOn"];
					$p->sendMessage("PVP 已".($this->areas["PVPisOn"] ? "开启" : "关闭"));
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
							$dmg->sendTip(TextFormat::RED."PVP 并没有开启");
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
						}else $dmg->sendTip(TextFormat::AQUA."对手在 PVP 场地外面, 无法造成伤害");
					}else $dmg->sendTip(TextFormat::AQUA."你在 PVP 场地外面, 无法造成伤害");
				}
				$event->setCancelled();
			}
		}
	}
	
	public function canPVP(Vector3 $pp, Vector3 $p1, Vector3 $p2, $lv1,$lv2){
				/*var_dump(min($p1->getX(),$p2->getX()) <= $pp->getX());
				var_dump(max($p1->getX(),$p2->getX()) >= $pp->getX());
				var_dump(min($p1->getY(),$p2->getY()) <= $pp->getY());
				var_dump(max($p1->getY(),$p2->getY()) >= $pp->getY());//returns false
				var_dump(max($p1->getY(),$p2->getY()).">=".$pp->getY());
				var_dump(min($p1->getZ(),$p2->getZ()) <= $pp->getZ());
				var_dump(max($p1->getZ(),$p2->getZ()) >= $pp->getZ());
				var_dump($lv1 === $lv2);*/
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
