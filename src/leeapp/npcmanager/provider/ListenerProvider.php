<?php

namespace leeapp\npcmanager\provider;

use leeapp\npcmanager\NPCManager;
use muqsit\invmenu\inventories\DoubleChestInventory as DoubleInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\inventory\DoubleChestInventory;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\level\Location;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class ListenerProvider implements Listener {
    /** @var NPCManager */
    private $plugin;

    public function __construct(NPCManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        if (!isset($this->time[$player->getName()]))
            $this->time[$player->getName()] = time();
        if (time() - $this->time[$player->getName()] < 1)
            return;
        $this->time[$player->getName()] = time();
        $npcs = $this->plugin->getNearbyNPC($player);
        foreach ($npcs as $npc) {
            if (!isset($this->plugin->near[$player->getId()][$npc->getId()])) {
                $npc->spawnTo($player);
                $this->plugin->near[$player->getId()][$npc->getId()] = $npc;
            }
        }
        if (!isset($this->plugin->near[$player->getId()]))
            return;
        foreach ($this->plugin->near[$player->getId()] as $key => $npc) {
            if ($npc->distance($player) > $this->plugin->maxDistance) {
                $npc->removeFrom($player);
                unset($this->plugin->near[$player->getId()][$key]);
            }
        }
    }

    public function onPacketReceived(DataPacketReceiveEvent $ev) {
        $pk = $ev->getPacket();
        if ($pk instanceof InventoryTransactionPacket and $pk->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
            if (isset($this->plugin->npc_list[$pk->trData->entityRuntimeId])) {
                $npc = $this->plugin->npc_list[$pk->trData->entityRuntimeId];
                $pos = $npc->x . '~' . $npc->y . '~' . $npc->z . '~' . $npc->yaw . '~' . $npc->pitch . '~' . $npc->getLevel()->getFolderName();
                if (isset($this->plugin->settingData[$ev->getPlayer()->getName()])) {
                    if ($this->plugin->data->getCode($pos) == 7) {
                        if (!is_numeric($this->plugin->settingData[$ev->getPlayer()->getName()])) {
                            $ev->getPlayer()->sendMessage($this->plugin->pre . "파티클 코드는 숫자여야합니다.");
                            return false;
                        }
                    }
                    $this->plugin->data->setData($pos, $this->plugin->settingData[$ev->getPlayer()->getName()]);
                    $ev->getPlayer()->sendMessage($this->plugin->pre . "데이터 설정을 완료했습니다.");
                    $this->plugin->data->save();
                    unset($this->plugin->settingData[$ev->getPlayer()->getName()]);
                } elseif (isset($this->plugin->settingScale[$ev->getPlayer()->getName()])) {
                    $this->plugin->data->setScale($pos, $this->plugin->settingScale[$ev->getPlayer()->getName()]);
                    $npc->setScale($this->plugin->settingScale[$ev->getPlayer()->getName()]);
                    $ev->getPlayer()->sendMessage($this->plugin->pre . "크기 설정을 완료했습니다.");
                    unset($this->plugin->settingScale[$ev->getPlayer()->getName()]);
                } else {
                    $npc->onInteract($ev->getPlayer());
                    return;
                }
            }
        }
    }

    public function onShop(InventoryTransactionEvent $ev) {
        foreach ($ev->getTransaction()->getActions() as $action) {
            if ($ev->getTransaction()->getSource() instanceof Player) {
                if ($action instanceof CreativeInventoryAction) return;
                if ($action instanceof DropItemAction) return;
                $player = $ev->getTransaction()->getSource();
                $name = $player->getName();
                if ($action->getInventory() instanceof DoubleInventory && isset($this->plugin->shopinv[$name])) {//일반 상점
                    $pre = $this->plugin->pre;
                    $Sitem = "{$action->getSourceItem()->getId()}:{$action->getSourceItem()->getDamage()}";
                    $Titem = "{$action->getTargetItem()->getId()}:{$action->getTargetItem()->getDamage()}";
                    $ev->setCancelled(true);
                    if ($action->getSlot() == 47) {
                        if ($action->getInventory()->getItem(49)->getId() == 0) {
                            return;
                        } else {
                            if ($action->getInventory()->getItem(49)->getCount() <= 10) {
                                $item = $action->getInventory()->getItem(49);
                                $item->setCount(1);
                                $action->getInventory()->setItem(49, $item);
                                return;
                            } else {
                                $item = $action->getInventory()->getItem(49);
                                $count = $item->getCount();
                                $item->setCount($count -= 10);
                                $action->getInventory()->setItem(49, $item);
                                return;
                            }
                        }
                    } elseif ($action->getSlot() == 48) {
                        if ($action->getInventory()->getItem(49)->getId() == 0) {
                            return;
                        } else {
                            if ($action->getInventory()->getItem(49)->getCount() == 1) return;
                            $item = $action->getInventory()->getItem(49);
                            $count = $item->getCount();
                            $item->setCount($count -= 1);
                            $action->getInventory()->setItem(49, $item);
                            return;
                        }
                    } elseif ($action->getSlot() == 49) {
                        $action->getInventory()->setItem(49, $action->getTargetItem());
                        return;
                    } elseif ($action->getSlot() == 50) {
                        if ($action->getInventory()->getItem(49)->getId() == 0) {
                            return;
                        } else {
                            if ($action->getInventory()->getItem(49)->getCount() == 64) return;
                            $item = $action->getInventory()->getItem(49);
                            $count = $item->getCount();
                            $item->setCount($count += 1);
                            $action->getInventory()->setItem(49, $item);
                            return;
                        }
                    } elseif ($action->getSlot() == 51) {
                        if ($action->getInventory()->getItem(49)->getId() == 0) {
                            return;
                        } else {
                            if ($action->getInventory()->getItem(49)->getCount() >= 54) {
                                $item = $action->getInventory()->getItem(49);
                                $item->setCount(64);
                                $action->getInventory()->setItem(49, $item);
                                return;
                            } else {
                                $item = $action->getInventory()->getItem(49);
                                $count = $item->getCount();
                                $item->setCount($count += 10);
                                $action->getInventory()->setItem(49, $item);
                                return;
                            }
                        }
                    } elseif ($action->getSlot() == 53) {
                        if ($action->getInventory()->getItem(49)->getId() == 0) {
                            return;
                        } else {
                            if ($this->plugin->etcitem->isEtcItem($action->getInventory()->getItem(49)->getCustomName())) {
                                if ($this->plugin->etcitem->getPrize($this->plugin->etcitem->getEtcItemName($action->getInventory()->getItem(49))) == "null") {
                                    $action->getInventory()->close($player);
                                    $player->sendMessage($this->plugin->pre . "해당 아이템은 구매가 불가능한 아이템입니다.");
                                    return;
                                }
                                $item = $action->getInventory()->getItem(49);
                                $itemName = $this->plugin->etcitem->getEtcItemName($item);
                                $count = $item->getCount();
                                $prize = $this->plugin->etcitem->getPrize($itemName) * $count;
                                if ($this->plugin->money->getMoney($player->getName()) < $prize) {
                                    $action->getInventory()->close($player);
                                    $player->sendMessage($this->plugin->pre . "해당 아이템을 구매할 테나가 부족합니다.");
                                    return;
                                } elseif ($this->plugin->money->getMoney($player->getName()) >= $prize) {
                                    if (!$player->getInventory()->canAddItem($this->plugin->etcitem->getEtcItem_1($itemName, $count))) {
                                        $action->getInventory()->close($player);
                                        $player->sendMessage($this->plugin->pre . "인벤토리의 공간이 부족합니다.");
                                        return;
                                    } else {
                                        $action->getInventory()->close($player);
                                        $this->plugin->money->reduceMoney($player->getName(), $prize);
                                        $this->plugin->etcitem->getEtcItem($player, $itemName, $count);
                                        $player->sendMessage($this->plugin->pre . "{$prize}테나로 {$itemName} {$count}개를 구매하였습니다!");
                                        $this->plugin->tutorial->check($player, 3);
                                        return;
                                    }
                                }
                            } elseif ($this->plugin->equipments->isEquipment($action->getInventory()->getItem(49)->getCustomName())) {
                                if ($this->plugin->equipments->getPrize($this->plugin->equipments->ConvertName($action->getInventory()->getItem(49)->getCustomName())) == "null") {
                                    $action->getInventory()->close($player);
                                    $player->sendMessage($this->plugin->pre . "해당 아이템은 구매가 불가능한 아이템입니다.");
                                    return;
                                }
                                $item = $action->getInventory()->getItem(49);
                                $itemName = $this->plugin->equipments->ConvertName($item->getCustomName());
                                $count = $item->getCount();
                                $prize = $this->plugin->equipments->getPrize($itemName) * $count;
                                if ($this->plugin->money->getMoney($player->getName()) < $prize) {
                                    $action->getInventory()->close($player);
                                    $player->sendMessage($this->plugin->pre . "해당 아이템을 구매할 테나가 부족합니다.");
                                    return;
                                } elseif ($this->plugin->money->getMoney($player->getName()) >= $prize) {
                                    if (!$player->getInventory()->canAddItem(($this->plugin->equipments->getEquipment($itemName, $count))[0])) {
                                        $action->getInventory()->close($player);
                                        $player->sendMessage($this->plugin->pre . "인벤토리의 공간이 부족합니다.");
                                        return;
                                    } else {
                                        $action->getInventory()->close($player);
                                        $this->plugin->money->reduceMoney($player->getName(), $prize);
                                        $eq = $this->plugin->equipments->getEquipment($itemName, $count);
                                        foreach ($eq as $key => $value) {
                                            $player->getInventory()->addItem($value);
                                        }
                                        //$this->plugin->equipments->getEquipment($player, $itemName, $count);
                                        $player->sendMessage($this->plugin->pre . "{$prize}테나로 {$itemName} {$count}개를 구매하였습니다!");
                                        $this->plugin->tutorial->check($player, 3);
                                        return;
                                    }
                                }
                            }
                        }
                    } else {
                        if ($action->getSourceItem()->getId() == 383 and $action->getSourceItem()->getDamage() == 38) return;
                        if ($action->getSourceItem()->getId() == 0 and $action->getSourceItem()->getDamage() == 0) return;
                        $action->getInventory()->setItem(49, $action->getSourceItem());
                    }
                } elseif ($action->getInventory() instanceof DoubleInventory && isset($this->plugin->settinginv[$name])) {//상점 설정
                    $item = "{$action->getTargetItem()->getId()}:{$action->getTargetItem()->getDamage()}";
                    $pos = $this->plugin->settinginv[$name];
                    if (45 <= $action->getSlot() && $action->getSlot() < 54) {
                        $ev->setCancelled(true);
                        return;
                    } elseif ($this->plugin->etcitem->isEtcItem($action->getTargetItem()->getName())) {
                        $this->plugin->data->setShop($pos, $action->getSlot(), $this->plugin->etcitem->getEtcItemName($action->getTargetItem()));
                        $this->plugin->data->save();
                        return;
                    } elseif ($this->plugin->equipments->isEquipment($action->getTargetItem()->getName())) {
                        $this->plugin->data->setShop($pos, $action->getSlot(), $this->plugin->equipments->ConvertName($action->getTargetItem()->getCustomName()));
                        $this->plugin->data->save();
                        return;
                    } elseif ($item == "0:0") {
                        $this->plugin->data->setShop($pos, $action->getSlot(), $item);
                        $this->plugin->data->save();
                        return;
                    } elseif ($item == "383:38") {
                        $this->plugin->data->setShop($pos, $action->getSlot(), $item);
                        $this->plugin->data->save();
                        return;
                    } else {
                        $ev->setCancelled(true);
                        return;
                    }
                }
            }
        }
    }

    public function onClose(InventoryCloseEvent $ev) {
        if ($ev->getPlayer() instanceof Player) {
            if (isset($this->plugin->shopinv[$ev->getPlayer()->getName()])) {
                unset($this->plugin->shopinv[$ev->getPlayer()->getName()]);
            }
            if (isset($this->plugin->settinginv[$ev->getPlayer()->getName()])) {
                unset($this->plugin->settinginv[$ev->getPlayer()->getName()]);
            }
        }
    }

    public function onQuit(PlayerQuitEvent $ev) {
        unset($this->plugin->near[$ev->getPlayer()->getId()]);
        if (isset($this->plugin->shopinv[$ev->getPlayer()->getName()])) {
            unset($this->plugin->shopinv[$ev->getPlayer()->getName()]);
        }
        if (isset($this->plugin->settinginv[$ev->getPlayer()->getName()])) {
            unset($this->plugin->settinginv[$ev->getPlayer()->getName()]);
        }
    }
}
