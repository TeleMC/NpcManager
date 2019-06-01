<?php
declare (strict_types=1)
;

namespace leeapp\npcmanager\entity;

use leeapp\npcmanager\NPCManager;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\Item;
use pocketmine\level\Location;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\UUID;

class CustomNPC extends Location {
    public $temporalVector;
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
    private $scale;

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
    public function __construct(NPCManager $plugin, Location $loc, string $name, Skin $skin, Item $item, int $code = -1, float $scale = 1) {
        parent::__construct($loc->x, $loc->y, $loc->z, $loc->yaw, $loc->pitch, $loc->level);
        $this->plugin = $plugin;
        $this->skin = clone $skin;
        $this->name = $name;
        $this->item = $item;
        $this->eid = Entity::$entityCount++;
        $this->uuid = UUID::fromRandom();
        $this->code = $code;
        $this->scale = $scale;
        $this->loc = $loc;
        $this->temporalVector = new Vector3();
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
     * @param float $scale
     * @return void
     */
    public function setScale(float $scale) {
        $this->scale = $scale;
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

    public function getItem() {
        return $this->item;
    }

    /**
     *
     * @return int
     */
    public function getId() {
        return $this->eid;
    }

    public function isSpawned() {
        $pos = $this->loc->x . '~' . $this->loc->y . '~' . $this->loc->z . '~' . $this->loc->yaw . '~' . $this->loc->pitch . '~' . $this->loc->getLevel()->getFolderName();
        return $this->plugin->data->isExists($pos);
    }

    public function getPos() {
        return $this->loc->x . '~' . $this->loc->y . '~' . $this->loc->z . '~' . $this->loc->yaw . '~' . $this->loc->pitch . '~' . $this->loc->getLevel()->getFolderName();
    }

    public function onInteract(Player $player) {
        $pos = $this->loc->x . '~' . $this->loc->y . '~' . $this->loc->z . '~' . $this->loc->yaw . '~' . $this->loc->pitch . '~' . $this->loc->getLevel()->getFolderName();
        if ($this->plugin->data->getCode($pos) == 1) {
            $player->sendMessage($this->plugin->data->getData($pos));
        } elseif ($this->plugin->data->getCode($pos) == 2) {
            $this->plugin->getServer()->dispatchCommand($player, $this->plugin->data->getData($pos));
        } elseif ($this->plugin->data->getCode($pos) == 3) {
            $this->plugin->quest->Quest($player, $this->plugin->data->getData($pos));
        } elseif ($this->plugin->data->getCode($pos) == 4) {
            if (isset($this->plugin->quest->udata[$player->getName()]["퀘스트 듣는중..."]))
                return;
            if ($player->isSneaking() and $player->isOp()) {
                $this->plugin->settingShop[$player->getName()] = $pos;
                $this->plugin->openShop($player, $this);
            } elseif ($player->isSneaking() and !$player->isOp()) {
                $this->plugin->openSell($player, $this);
            } else {
                $this->plugin->openShop($player, $this);
            }
        } elseif ($this->plugin->data->getCode($pos) == 5) {
            $this->plugin->warp->WarpUI($player);
        } elseif ($this->plugin->data->getCode($pos) == 6) {
            $this->plugin->subquest->Quest($player, $this->plugin->data->getData($pos));
        } elseif ($this->plugin->data->getCode($pos) == 7) {
            $this->plugin->particle->checkShop($player, (int) $this->plugin->data->getData($pos));
        }
    }

    public function spawnAll() {
        if ($this->level->getPlayers() != null) {
            foreach ($this->level->getPlayers() as $player) {
                $this->spawnTo($player);
            }
        }
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
        /*$pk->x = $this->x;
        $pk->y = $this->y;
        $pk->z = $this->z;*/
        $pk->yaw = $this->yaw;
        $pk->pitch = $this->pitch;
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
        $meta [Entity::DATA_SCALE] = array(
                Entity::DATA_TYPE_FLOAT,
                $this->scale
        );
        $pk->metadata = $meta;
        $target->dataPacket($pk);
        // send PlayerListPacket to target
        $pk = new PlayerListPacket ();
        $pk->type = PlayerListPacket::TYPE_ADD;
        $pk->entries = array(PlayerListEntry::createAdditionEntry($this->uuid, $this->eid, "NPC: " . $this->name, $this->skin));
        $target->dataPacket($pk);
        $pk = new PlayerListPacket ();
        $pk->type = PlayerListPacket::TYPE_REMOVE;
        $pk->entries = array(PlayerListEntry::createRemovalEntry($this->uuid));
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
        $pk->entries = array(PlayerListEntry::createAdditionEntry($this->uuid, $this->eid, "NPC: " . $this->name, $this->skin));
        $player->dataPacket($pk);
    }
}
