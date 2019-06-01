<?php

namespace leeapp\npcmanager\task;

use leeapp\npcmanager\NPCManager;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class ParticleTask extends Task {
    public function __construct(NPCManager $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun($currentTick) {
        foreach ($this->plugin->npc_particle as $id => $npc) {
            if ($npc->isSpawned()) {
                if (!is_numeric($this->plugin->data->getData($npc->getPos()))) {
                    continue;
                } elseif ($this->plugin->particle->getPrice($this->plugin->data->getData($npc->getPos())) == null) {
                    continue;
                } else {
                    if (($particleId = (int) $this->plugin->data->getData($npc->getPos())) !== 14)
                        $this->plugin->particle->Normal($npc, $particleId);
                    else
                        $this->plugin->particle->Jump($npc);
                    if (!isset($this->plugin->npc_particle[$npc->getId()]))
                        $this->plugin->npc_particle[$npc->getId()] = $npc;
                }
            } else {
                continue;
            }
        }
    }
}
