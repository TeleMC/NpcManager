<?php

namespace leeapp\npcmanager;

use Equipments\Equipments;
use EtcItem\EtcItem;
use GuiLibrary\GuiLibrary;
use HotbarSystemManager\HotbarSystemManager;
use leeapp\npcmanager\command\NPCCommand;
use leeapp\npcmanager\entity\CustomNPC;
use leeapp\npcmanager\provider\DataProvider;
use leeapp\npcmanager\provider\ListenerProvider;
use leeapp\npcmanager\task\ParticleTask;
use Monster\Monster;
use ParticleManager\ParticleManager;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Location;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use QuestManager\QuestManager;
use SubQuestManager\SubQuestManager;
use TeleMoney\TeleMoney;
use TutorialManager\TutorialManager;
use UiLibrary\UiLibrary;
use WarpManager\WarpManager;

class NPCManager extends PluginBase {
    /** @var NPCManager */
    private static $instance;
    //public $pre = "§l§e[ §f시스템 §e] §r§e";
    public $pre = "§e• ";
    /** @var CustomNPC[] */
    public $npc_list = array();
    public $npc_pos = array();
    public $npc_particle = array();
    /** @var DataProvider */
    public $data = null;
    public $maxDistance = 25;
    public $near = [];
    /** @var ListenerProvider */
    private $listener = null;
    /** @var Config */
    private $config;

    public static function getInstance() {
        return self::$instance;
    }

    public function onLoad() {
        self::$instance = $this;
    }

    public function onEnable() {
        $this->configSetting();
        if ($this->data == null) $this->data = new DataProvider ($this, $this->config);
        if ($this->listener == null) $this->listener = new ListenerProvider ($this);
        $this->getServer()->getPluginManager()->registerEvents($this->listener, $this);
        $this->getServer()->getCommandMap()->register("npc", new NPCCommand ($this, "npc"));
        $this->loadNPC();
        $this->money = TeleMoney::getInstance();
        $this->hotbar = HotbarSystemManager::getInstance();
        $this->etcitem = EtcItem::getInstance();
        $this->quest = QuestManager::getInstance();
        $this->subquest = SubQuestManager::getInstance();
        $this->equipments = Equipments::getInstance();
        $this->ui = UiLibrary::getInstance();
        $this->monster = Monster::getInstance();
        $this->tutorial = TutorialManager::getInstance();
        $this->gui = GuiLibrary::getInstance();
        $this->warp = WarpManager::getInstance();
        $this->particle = ParticleManager::getInstance();
        $this->getScheduler()->scheduleRepeatingTask(new ParticleTask($this), 2 * 20);
        self::log("NpcManager onEnable...");
    }

    private function configSetting() {
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "skins/");
        $this->config = new Config ($this->getDataFolder() . "config.yml", Config::YAML);
    }

    public function loadNPC() {
        $list = $this->data->getDatas();

        if (count($list) == 0) return;
        foreach ($list as $key => $value) {
            $pos = explode('~', $value);    // [x, y, z, yaw, pitch, level]
            //$this->getServer()->loadLevel($pos[5]);
            $level = $this->getServer()->getLevelByName($pos[5]);
            $loc = new Location($pos[0], $pos[1], $pos[2], $pos[3], $pos[4], $level);
            $name = $this->data->getName($value);
            $skinFile = $this->getDataFolder() . "skins/" . $value . ".skin";
            $skindata = file_get_contents($skinFile);
            $skindata = explode("(cut)", $skindata);
            $skin = new Skin($this->data->getSkincode($value), $skindata[0], $skindata[1], $skindata[2], $skindata[3]);
            $item = Item::get($this->data->getItemid($value), $this->data->getItemmeta($value));
            $code = $this->data->getCode($value);
            $scale = $this->data->getScale($value);
            $npc = new CustomNPC($this, $loc, $name, $skin, $item, $code, $scale);
            $this->EnableNpc($npc);
        }
    }

    public function EnableNpc(CustomNPC $npc) {
        $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
        //$npc->spawnAll();
        $this->npc_list [$npc->getId()] = $npc;
        $this->npc_pos [(int) floor($npc->x) . ":" . (int) floor($npc->z) . ":" . $npc->getLevel()->getName()] = $npc;
        if ($this->data->getCode($pos) == 7)
            $this->npc_particle[$npc->getId()] = $npc;
        //$this->getScheduler()->scheduleRepeatingTask(new ParticleTask($this, $npc), 2*20);
        $this->data->save();
    }

    public static function log($message) {
        self::logger()->notice("[ NpcManager ] " . $message, "LEEAPP");
    }

    private static function logger() {
        return Server::getInstance()->getLogger();
    }

    public function onDisable() {
        $this->data->save();
    }

    public function getNearbyNPC($pos) {
        $minX = ((int) floor($pos->x - $this->maxDistance));
        $maxX = ((int) floor($pos->x + $this->maxDistance));
        $minZ = ((int) floor($pos->z - $this->maxDistance));
        $maxZ = ((int) floor($pos->z + $this->maxDistance));
        $list = [];
        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                $key = $x . ":" . $z . ":" . $pos->getLevel()->getName();
                if (isset($this->npc_pos[$key]))
                    array_push($list, $this->npc_pos[$key]);
            }
        }
        return $list;
    }

    public function save() {
        $this->data->save();
    }

    public function addNpc(CustomNPC $npc) {
        if (isset ($this->npc_list [$npc->getId()])) {
            return false;
        }
        $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
        if (!$this->data->isExists($pos)) {
            $npc->remove();
            $this->npc_list [$npc->getId()] = $npc;
            $this->npc_pos [(int) floor($npc->x) . ":" . (int) floor($npc->z) . ":" . $npc->getLevel()->getName()] = $npc;
            $this->data->setCode($pos, $npc->getCode());
            $this->data->setskincode($pos, $npc->getSkin()->getSkinId());
            $this->data->setName($pos, $npc->getName());
            $this->data->setItemid($pos, $npc->getItem()->getId());
            $this->data->setItemmeta($pos, $npc->getItem()->getDamage());
            $this->data->setData($pos, "NPC 세부사항을 설정해주세요.");
            $this->data->setScale($pos, 1);
            if ($npc->getCode() == 4) $this->data->Shop($pos);
            $skin = $npc->getSkin();
            file_put_contents($this->getDataFolder() . "skins/" . $pos . ".skin", "{$skin->getSkinData()}(cut){$skin->getCapeData()}(cut){$skin->getGeometryName()}(cut){$skin->getGeometryData()}");
            if ($this->data->getCode($pos) == 7)
                $this->npc_particle[$npc->getId()] = $npc;
            $this->data->save();
            return true;
        } else {
            return false;
        }
    }

    public function removeNpc(int $eid) {
        if (isset($this->npc_list[$eid])) {
            $npc = $this->npc_list[$eid];
            $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
            $npc->remove();
            unset ($this->npc_list [$npc->getId()]);
            unset ($this->npc_pos [(int) floor($npc->x) . ":" . (int) floor($npc->z) . ":" . $npc->getLevel()->getName()]);
            unset ($this->npc_particle [$npc->getId()]);
            $this->data->unset($pos);
            $this->data->save();
            return true;
        }
        return false;
    }

    public function getNpcList() {
        $list_ = array();
        foreach ($this->npc_list as $npc) {
            $str = "§l§e[§f{$npc->getId ()}§e] §r§e[ §r{$npc->getName ()} §r§e] | code: {$npc->getCode ()} | " . (int) $npc->getX() . ":" . (int) $npc->getY() . ":" . (int) $npc->getZ() . ":" . $npc->getLevel()->getName();
            array_push($list_, $str);
        }
        return $list_;
    }

    public function npcList() {
        return $this->npc_list;
    }

    public function openShop(Player $player, CustomNPC $npc) {
        $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
        if (isset($this->settingShop[$player->getName()])) {
            $shopname = "{$this->data->getData($pos)} - 상점 변경모드";
        } elseif (!isset($this->settingShop[$player->getName()])) {
            $shopname = "{$this->data->getData($pos)} - 보유테나 : {$this->money->getMoney($player->getName())}테나";
        }
        $tile = $this->gui->addWindow($player, $shopname, 1);
        for ($i = 0; $i < 54; $i++) {
            if (isset($this->etcitem->edata[$this->data->getShop($pos, $i)])) {
                $item = $this->etcitem->getEtcItem_1($this->data->getShop($pos, $i), 1);
                $itemName = $this->data->getShop($pos, $i);
                $prize = $this->etcitem->getPrize($itemName);
                $lore = $item->getLore();
                if ($prize == "null") {
                    array_push($lore, "\n§r§f가격 : §c구매 불가");
                } else {
                    array_push($lore, "\n§r§f가격 : §a{$prize}§f테나");
                }
                $item->setLore($lore);
                $tile[0]->getInventory()->setItem($i, $item, 1);
            } elseif ($this->equipments->isEquipment($this->data->getShop($pos, $i))) {
                $item = ($this->equipments->getEquipment($this->data->getShop($pos, $i), 1))[0];
                $itemName = $this->data->getShop($pos, $i);
                $prize = $this->equipments->getPrize($itemName);
                $lore = $item->getLore();
                if ($prize == "null") {
                    array_push($lore, "\n§r§f가격 : §c구매 불가");
                } else {
                    array_push($lore, "\n§r§f가격 : §a{$prize}§f테나");
                }
                $item->setLore($lore);
                $tile[0]->getInventory()->setItem($i, $item, 1);
            } elseif (!isset($this->etcitem->edata[$this->data->getShop($pos, $i)])) {
                $item = explode(":", $this->data->getShop($pos, $i));
                if ($i == 47) {
                    //$item = new Item((int)$item[0], (int)$item[1]);
                    $item = new Item(351, 10);
                    $item->setCustomName("§r§a10§f개 감소");
                } elseif ($i == 48) {
                    //$item = new Item((int)$item[0], (int)$item[1]);
                    $item = new Item(351, 9);
                    $item->setCustomName("§r§a1§f개 감소");
                } elseif ($i == 50) {
                    //$item = new Item((int)$item[0], (int)$item[1]);
                    $item = new Item(351, 14);
                    $item->setCustomName("§r§a1§f개 추가");
                } elseif ($i == 51) {
                    //$item = new Item((int)$item[0], (int)$item[1]);
                    $item = new Item(351, 12);
                    $item->setCustomName("§r§a10§f개 추가");
                } elseif ($i == 53) {
                    //$item = new Item((int)$item[0], (int)$item[1]);
                    $item = new Item(351, 5);
                    $item->setCustomName("§r§a구매하기");
                } else {
                    if ($item[0] == 383 and $item[1] == 38) {
                        $item = new Item((int) $item[0], (int) $item[1]);
                        $item->setCustomName("§r§c잠긴칸");
                    } else {
                        $item = new Item((int) $item[0], (int) $item[1]);
                    }
                }
                $tile[0]->getInventory()->setItem($i, $item);
            }
        }
        if (!isset($this->settingShop[$player->getName()])) {
            $this->shopinv[$player->getName()] = true;
        } elseif (isset($this->settingShop[$player->getName()])) {
            $this->settinginv[$player->getName()] = $this->settingShop[$player->getName()];
            unset($this->settingShop[$player->getName()]);
        }
        $tile[0]->send($player);
    }

    public function openSell(Player $player, CustomNPC $npc) {
        $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
        $form = $this->ui->SimpleForm(function (Player $player, array $data) {
            $i = 0;
            foreach ($this->monster->idata[$player->getName()] as $item => $info) {
                if ($this->monster->idata[$player->getName()][$item] == 0) continue;
                $this->sell[$player->getName()][$i] = $item;
                $i++;
            }
            if (!is_numeric($data[0])) return;
            $this->sell_1[$player->getName()] = $this->sell[$player->getName()][$data[0]];
            $form = $this->ui->CustomForm(function (Player $player, array $data) {
                if (!isset($data[0])) return;
                if ($data[0] > $this->monster->idata[$player->getName()][$this->sell_1[$player->getName()]]) {
                    $player->sendMessage($this->pre . "판매할 아이템이 부족합니다!");
                    return;
                }
                if ($this->monster->getPrize($this->sell_1[$player->getName()]) == false) {
                    $player->sendMessage($this->pre . "해당 아이템은 판매가 불가능합니다.");
                    return;
                }
                $prize = $this->monster->getPrize($this->sell_1[$player->getName()]) * $data[0];
                $this->money->addMoney($player->getName(), $prize);
                $this->monster->idata[$player->getName()][$this->sell_1[$player->getName()]] -= $data[0];
                $player->sendMessage($this->pre . "{$this->sell_1[$player->getName()]}(을)를 {$data[0]}개를 판매하여 {$prize}테나를 받았습니다!");
            });
            $arr = [];
            if ($this->monster->idata[$player->getName()][$this->sell[$player->getName()][$data[0]]] >= 64)
                $max = 64;
            else
                $max = $this->monster->idata[$player->getName()][$this->sell[$player->getName()][$data[0]]];
            for ($i = 0; $i <= $max; $i++) {
                array_push($arr, "{$i}");
            }
            $form->setTitle("{$this->sell[$player->getName()][$data[0]]} 판매");
            $form->addStepSlider("판매할 갯수", $arr, 0);
            $form->addLabel("소지한 {$this->sell[$player->getName()][$data[0]]} 갯수 : {$this->monster->idata[$player->getName()][$this->sell_1[$player->getName()]]}");
            $form->sendToPlayer($player);
        });
        $form->setTitle("{$this->data->getData($pos)} - 소지품 판매");
        foreach ($this->monster->idata[$player->getName()] as $item => $info) {
            if ($this->monster->idata[$player->getName()][$item] == 0) continue;
            $form->addButton("{$item} - {$this->monster->idata[$player->getName()][$item]}개");
        }
        $form->sendToPlayer($player);
    }

    public function returnBlock(Player $player, Vector3 $pos, Vector3 $pos_1) {
        $block = $player->getLevel()->getBlock($pos);
        $block_1 = $player->getLevel()->getBlock($pos_1);
        $player->getLevel()->sendBlocks([$player], [$block, $block_1]);
    }
}
