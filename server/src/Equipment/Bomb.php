<?php

namespace cs\Equipment;

use cs\Core\Player;
use cs\Core\Point;
use cs\Core\Util;
use cs\Enum\InventorySlot;
use cs\Enum\ItemType;

class Bomb extends BaseEquipment
{

    const MAX_BLAST_DISTANCE = 1000;

    private Point $position;
    private int $plantTickCount = 0;
    private int $plantTickCountMax;
    private int $defuseTickCount = 0;
    private int $defuseTickCountMax;

    public function __construct(int $plantTimeMs, int $defuseTimeMs)
    {
        parent::__construct();
        $this->plantTickCountMax = Util::millisecondsToFrames($plantTimeMs);
        $this->defuseTickCountMax = Util::millisecondsToFrames($defuseTimeMs);
    }

    public function getType(): ItemType
    {
        return ItemType::TYPE_BOMB;
    }

    public function getSlot(): InventorySlot
    {
        return InventorySlot::SLOT_BOMB;
    }

    public function reset(): void
    {
        $this->plantTickCount = 0;
        $this->defuseTickCount = 0;
    }

    public function unEquip(): void
    {
        parent::unEquip();
        $this->reset();
    }

    public function plant(): bool
    {
        $this->plantTickCount++;
        return ($this->plantTickCount >= $this->plantTickCountMax);
    }

    public function defuse(bool $hasKit): bool
    {
        $this->defuseTickCount += $hasKit ? 2 : 1;
        return ($this->defuseTickCount >= $this->defuseTickCountMax);
    }

    public function setPosition(Point $position): void
    {
        $this->position = $position;
    }

    public function getPosition(): Point
    {
        return $this->position;
    }

    public function explodeDamageToPlayer(Player $player): void
    {
        $distanceSquared = Util::distanceSquared($player->getPositionImmutable(), $this->position);
        $maxDistance = self::MAX_BLAST_DISTANCE * self::MAX_BLAST_DISTANCE;
        if ($distanceSquared > $maxDistance) {
            return;
        }

        if ($distanceSquared > ($maxDistance * .9)) {
            $player->lowerHealth(4);
        } elseif ($distanceSquared > ($maxDistance * .8)) {
            $player->lowerHealth(7);
        } elseif ($distanceSquared > ($maxDistance * .7)) {
            $player->lowerHealth(12);
        } elseif ($distanceSquared > ($maxDistance * .6)) {
            $player->lowerHealth(26);
        } elseif ($distanceSquared > ($maxDistance * .5)) {
            $player->lowerHealth(49);
        } elseif ($distanceSquared > ($maxDistance * .4)) {
            $player->lowerHealth(61);
        } elseif ($distanceSquared > ($maxDistance * .3)) {
            $player->lowerHealth(74);
        } elseif ($distanceSquared > ($maxDistance * .2)) {
            $player->lowerHealth(84);
        } elseif ($distanceSquared > ($maxDistance * .1)) {
            $player->lowerHealth(92);
        } else {
            $player->lowerHealth(500);
        }
    }

}
