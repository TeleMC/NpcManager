<?php
declare (strict_types=1)
;

namespace leeapp\npcmanager\entity;

use leeapp\npcmanager\NPCManager;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Location;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\utils\UUID;

class HumanNPC extends Location {
    /** @var  NPCManager */
    private $plugin;
    /** @var int */
    private $eid, $code;
    /** @var UUID */
    private $uuid;
    /** @var Skin */
    private $skin;
    /** @var string */
    private $name;
    /** @var Item */
    private $item;

    /**
     *
     * @param NPCManager $plugin
     * @param Location $loc
     * @param string $name
     * @param Skin $skin
     * @param int $skinId
     * @param Item $item
     * @param int $code
     */
    public function __construct(NPCManager $plugin, Location $loc, string $name, Skin $skin, Item $item, int $code = -1) {
        parent::__construct($loc->x, $loc->y, $loc->z, $loc->yaw, $loc->pitch, $loc->level);
        $this->plugin = $plugin;
        $this->skin = clone $skin;
        $this->name = $name;
        $this->item = $item;
        $this->eid = Entity::$entityCount++;
        $this->uuid = UUID::fromRandom();
        $this->code = $code;
    }

    /**
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     * @param int $code
     * @return void
     */
    public function setCode(int $code) {
        $this->code = $code;
    }

    /**
     *
     * @return int
     */
    public function getCode() {
        return $this->code;
    }

    /**
     *
     * @return Skin
     */
    public function getSkin() {
        return $this->skin;
    }

    /**
     *
     * @param Item $item
     */
    public function setHoldingItem(Item $item) {
        $this->item = $item;
    }

    /**
     *
     * @return int
     */
    public function getId() {
        return $this->eid;
    }

    public function onInteract(Player $player) {
    }

    /**
     *
     * @param Player $target
     */
    public function spawnTo(Player $target) {
        // send AddPlayerPacket to target
        $pk = new AddPlayerPacket ();
        $pk->uuid = $this->uuid;
        $pk->username = $this->name;
        $pk->entityRuntimeId = $this->eid;
        $pk->position = $this->asVector3();
        $pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;
        $pk->item = $this->item;
        $meta [Entity::DATA_FLAGS] = array(
                Entity::DATA_TYPE_LONG,
                (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) + (1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG)
        );
        $meta [Entity::DATA_NAMETAG] = array(
                Entity::DATA_TYPE_STRING,
                $this->name
        );
        $meta [Entity::DATA_LEAD_HOLDER_EID] = array(
                Entity::DATA_TYPE_LONG,
                -1
        );
        $pk->metadata = $meta;
        $target->dataPacket($pk);
        // send PlayerListPacket to target
        $pk = new PlayerListPacket ();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries = PlayerListEntry::createAdditionEntry($this->uuid, $this->eid, "NPC: " . $this->name, "", 0, $this->skin);
        $target->dataPacket($pk);
    }

    public function remove() {
        foreach ($this->level->getPlayers() as $player) {
            $this->removeFrom($player);
        }
    }

    public function removeFrom(Player $player) {
        // send RemoveEntityPacket to target
        $pk = new RemoveEntityPacket ();
        $pk->entityUniqueId = $this->eid;
        $player->dataPacket($pk);
        // send PlayerListPacket to target
        $pk = new PlayerListPacket ();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = PlayerListEntry::createRemovalEntry($uuid);
        $player->dataPacket($pk);
    }
}
