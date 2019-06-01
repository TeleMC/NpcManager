<?php

namespace leeapp\npcmanager\command;

use leeapp\npcmanager\entity\CustomNPC;
use leeapp\npcmanager\NPCManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Skin;
use pocketmine\Player;
use pocketmine\utils\Config;

class NPCCommand extends Command {
    public function __construct(NPCManager $plugin, string $name) {
        $this->plugin = $plugin;
        $description = "NPC 명령어";
        $usage = "{$this->plugin->pre}§e/npc create <msg|cmd|quest|shop> <name> | NPC를 생성합니다.";
        $usage .= "\n{$this->plugin->pre}§e/npc remove <number> | NPC를 제거합니다.";
        $usage .= "\n{$this->plugin->pre}§e/npc list | NPC 목록을 확인합니다.";
        $aliases = array(
                "npc",
                "n"
        );
        parent::__construct($name, $description, $usage, $aliases);
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        $pre = $this->plugin->pre;
        if (!$sender->isOp()) return false;
        if (!isset($args[0])) {
            $sender->sendMessage($this->getUsage());
            return false;
        }
        if ($args [0] == "create" || $args [0] == "c") {
            if ($sender instanceof Player) {
                if (!isset($args[1]) || !isset($args[2])) {
                    $sender->sendMessage($this->getUsage());
                    return false;
                }
                if ($args[1] == "msg")
                    $code = 1;
                elseif ($args[1] == "cmd")
                    $code = 2;
                elseif ($args[1] == "quest")
                    $code = 3;
                elseif ($args[1] == "shop")
                    $code = 4;
                elseif ($args[1] == "warp")
                    $code = 5;
                elseif ($args[1] == "subquest")
                    $code = 6;
                elseif ($args[1] == "particle")
                    $code = 7;
                else {
                    $sender->sendMessage($this->getUsage());
                    return false;
                }
                unset($args[0]);
                unset($args[1]);
                $name = implode(" ", $args);
                /*$temp = isset($args[1]) ? $args[1] : "Unknown";
                $name = is_numeric($temp) ? "test" : $args[1];		// 추후에 quest 추가시 code를 통해 npc명 가져옴
                $code = is_numeric($temp) ? $args[1] : -1;*/
                $skin = $sender->getSkin();
                $skinData = new Skin($skin->getSkinId(), $skin->getSkinData(), $skin->getCapeData(), $skin->getGeometryName(), $skin->getGeometryData());
                $npc = new CustomNPC ($this->plugin, $sender->getLocation(), $name, $skinData, $sender->getInventory()->getItemInHand(), $code);

                if ($this->plugin->addNpc($npc)) {
                    $sender->sendMessage($pre . "NPC를 성공적으로 설치하였습니다.");
                    return true;
                } else {
                    $sender->sendMessage($pre . "NPC설치에 실패하였습니다.");
                    return false;
                }
            } else {
                NPCManager::log($pre . "콘솔에서는 사용이 불가능 합니다!");
                return false;
            }
        }
        if ($args [0] == "remove" || $args [0] == "rm" || $args [0] == "r") {
            if (!isset ($args [1]) || !is_numeric($args [1])) {
                $sender->sendMessage($this->getUsage());
                return false;
            }
            if ($this->plugin->removeNpc($args [1])) {
                $sender->sendMessage($pre . "NPC를 성공적으로 삭제하였습니다.");
                return true;
            } else {
                $sender->sendMessage($pre . "NPC삭제에 실패하였습니다.");
                return false;
            }
        }
        if ($args[0] == "set" || $args[0] == "s") {
            if (isset($this->plugin->settingData[$sender->getName()]) || isset($this->plugin->settingScale[$sender->getName()]) || isset($this->plugin->settingSneak[$sender->getName()])) {
                $sender->sendMessage($pre . "이미 다른작업을 진행중입니다.");
                return true;
            }
            unset($args[0]);
            $data = implode(" ", $args);
            $sender->sendMessage($pre . "NPC를 클릭하면 데이터가 설정됩니다.");
            $this->plugin->settingData[$sender->getName()] = $data;
            return true;
        }
        if ($args[0] == "setscale" || $args[0] == "ss") {
            if (isset($this->plugin->settingData[$sender->getName()]) || isset($this->plugin->settingScale[$sender->getName()]) || isset($this->plugin->settingSneak[$sender->getName()])) {
                $sender->sendMessage($pre . "이미 다른작업을 진행중입니다.");
                return true;
            }
            if (!isset($args[1]) || !is_numeric($args[1])) {
                $sender->sendMessage($pre . "크기는 실수 형태여야합니다.");
                return true;
            }
            $sender->sendMessage($pre . "NPC를 클릭하면 크기가 설정됩니다.");
            $this->plugin->settingScale[$sender->getName()] = $args[1];
            return true;
        }
        if ($args[0] == "cancel" || $args[0] == "cc") {
            $sender->sendMessage($pre . "모든작업을 취소했습니다.");
            unset($this->plugin->settingData[$sender->getName()]);
            unset($this->plugin->settingScale[$sender->getName()]);
            unset($this->plugin->settingSneak[$sender->getName()]);
            return true;
        }
        if ($args [0] == "list" || $args [0] == "ls" || $args [0] == "l") {
            if (count($this->plugin->npc_list) <= 0) {
                $sender->sendMessage("--- NPC 목록 1 / 1 ---");
                $sender->sendMessage($pre . "NPC가 없습니다.");
                return true;
            }
            $list = $this->plugin->getNpcList();
            $maxpage = ceil(count($list) / 5);
            if (!isset($args[1]) || !is_numeric($args[1]) || $args[1] <= 0) {
                $page = 1;
            } elseif ($args[1] > $maxpage) {
                $page = $maxpage;
            } else {
                $page = $args[1];
            }
            $npc = "";
            $count = 0;
            foreach ($list as $key => $value) {
                if ($page * 5 - 5 <= $count and $count < $page * 5) {
                    $npc .= $list[$key] . "\n";
                    $count++;
                } else {
                    $count++;
                    continue;
                }
            }
            $sender->sendMessage("--- NPC 목록 {$page} / {$maxpage} ---");
            $sender->sendMessage($npc);
            return true;
        }
        /*if($args[0] == "7208"){
            $this->mial = (new Config($this->plugin->getDataFolder()."Npc.yml", Config::YAML))->getAll();
            $this->a = new Config($this->plugin->getDataFolder()."config.yml", Config::YAML);
            $this->b = $this->a->getAll();
            foreach($this->mial as $key => $value){
                if(isset($this->mial[$key]["shop"])){
                    $name = $key;
                  foreach($this->b as $key1 => $value1){
                      if($this->b[$key1]["name"] == $name){
                            $this->b[$key1]["shop"] = [];
                            $this->b[$key1]["shop"] = $this->mial[$key]["shop"];
                            $sender->sendMessage("성공");
                            $this->a->setAll($this->b);
                            $this->a->save();
                            continue;
                        }
                  }
                }
            }
            $this->a->setAll($this->b);
            $this->a->save();
        }*/
        return false;
    }
}
