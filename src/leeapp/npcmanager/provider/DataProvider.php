<?php

namespace leeapp\npcmanager\provider;

use leeapp\npcmanager\NPCManager;
use pocketmine\Player;
use pocketmine\utils\Config;

/*
x~y~z~yaw~pitch~level:
  code:
  skincode:
  name:
  itemid:
  itemmeta:
*/

class DataProvider {
    /** @var Config */
    private $config;
    /** @var array */
    private $data;
    /** @var NPCManager */
    private $plugin;

    public function __construct(NPCManager $plugin, Config $config) {
        $this->plugin = $plugin;
        $this->config = $config;
        $this->data = $config->getAll();
    }

    public function isExists($pos) {
        $list = $this->getDatas();
        return in_array($pos, $list);
    }

    public function getDatas() {
        return $this->config->getAll(true);
    }

    public function unset($pos) {
        unset($this->data [$pos]);
    }

    public function setCode($pos, $code) {
        $this->data [$pos] ['code'] = $code;
    }

    public function getCode($pos) {
        return $this->data [$pos] ['code'];
    }

    public function setSkincode($pos, $skincode) {
        $this->data [$pos] ['skincode'] = $skincode;
    }

    public function getSkincode($pos) {
        return $this->data [$pos] ['skincode'];
    }

    public function setName($pos, $name) {
        $this->data [$pos] ['name'] = $name;
    }

    public function getName($pos) {
        return $this->data [$pos] ['name'];
    }

    public function setItemid($pos, $id) {
        $this->data [$pos] ['itemid'] = $id;
    }

    public function getItemid($pos) {
        return $this->data [$pos] ['itemid'];
    }

    public function setItemmeta($pos, $meta) {
        $this->data [$pos] ['itemmeta'] = $meta;
    }

    public function getItemmeta($pos) {
        return $this->data [$pos] ['itemmeta'];
    }

    public function getData($pos) {
        return $this->data [$pos] ['data'];
    }

    public function setData($pos, $meta) {
        $this->data [$pos] ['data'] = $meta;
    }

    public function setScale($pos, $meta) {
        $this->data [$pos] ['scale'] = (float) $meta;
    }

    public function getScale($pos) {
        return $this->data [$pos] ['scale'];
    }

    public function setShop($pos, $key, $meta) {
        if (!isset($this->data [$pos] ['shop'] [$key]))
            $this->Shop($pos);
        $this->data [$pos] ['shop'] [$key] = $meta;
    }

    public function Shop($pos) {
        $this->data [$pos] ['shop'] = [];
        for ($i = 0; $i < 54; $i++) {
            if (27 <= $i && $i < 36) $this->data [$pos] ['shop'] [$i] = "383:38";
            elseif ($i == 47) $this->data [$pos] ['shop'] [$i] = "351:12";
            elseif ($i == 48) $this->data [$pos] ['shop'] [$i] = "351:14";
            elseif ($i == 50) $this->data [$pos] ['shop'] [$i] = "351:13";
            elseif ($i == 51) $this->data [$pos] ['shop'] [$i] = "351:10";
            elseif ($i == 53) $this->data [$pos] ['shop'] [$i] = "351:5";
            else $this->data [$pos] ['shop'] [$i] = "0:0";
        }
    }

    public function getShop($pos, $key) {
        if (!isset($this->data [$pos] ['shop'] [$key]))
            $this->Shop($pos);
        return $this->data [$pos] ['shop'] [$key];
    }

    public function save() {
        $this->config->setAll($this->data);
        $this->config->save();
    }
}

?>
