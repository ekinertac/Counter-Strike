<?php

namespace cs\Core;

use cs\Enum\ArmorType;
use cs\Enum\BuyMenuItem;
use cs\Enum\InventorySlot;
use cs\Equipment\Flashbang;
use cs\Equipment\Kevlar;
use cs\Event\EquipEvent;
use cs\Weapon\Knife;
use cs\Weapon\PistolGlock;
use cs\Weapon\PistolUsp;

class Inventory
{

    /** @var Item[] [slotId => Item] */
    private array $items = [];
    private int $dollars = 0;
    private int $equippedSlot;
    private int $lastEquippedSlotId;
    private ArmorType $armorType;
    private BuyMenu $store;

    public function __construct(bool $isAttackerSide)
    {
        $this->reset($isAttackerSide, true);
    }

    public function reset(bool $isAttackerSide, bool $respawn): void
    {
        if ($respawn) {
            $this->items = [
                InventorySlot::SLOT_KNIFE->value     => new Knife(),
                InventorySlot::SLOT_SECONDARY->value => ($isAttackerSide ? new PistolGlock(true) : new PistolUsp(true)),
            ];
            $this->equippedSlot = InventorySlot::SLOT_SECONDARY->value;
            $this->lastEquippedSlotId = InventorySlot::SLOT_KNIFE->value;
            $this->armorType = ArmorType::NONE;
        } else {
            foreach ($this->items as $item) {
                $item->reset();
            }
            if (!isset($this->items[InventorySlot::SLOT_SECONDARY->value])) {
                $this->items[InventorySlot::SLOT_SECONDARY->value] = ($isAttackerSide ? new PistolGlock(true) : new PistolUsp(true));
            }
        }

        unset($this->items[InventorySlot::SLOT_BOMB->value]);
        $this->store = new BuyMenu($isAttackerSide, $this->items);
    }

    private function updateEquippedSlot(): int
    {
        if (isset($this->items[$this->lastEquippedSlotId])) {
            $this->equippedSlot = $this->lastEquippedSlotId;
        } else {
            $this->equippedSlot = InventorySlot::SLOT_KNIFE->value;
        }
        return $this->equippedSlot;
    }

    public function removeBomb(): InventorySlot
    {
        unset($this->items[InventorySlot::SLOT_BOMB->value]);
        return InventorySlot::from($this->updateEquippedSlot());
    }

    public function getEquipped(): Item
    {
        return $this->items[$this->equippedSlot];
    }

    public function removeEquipped(): ?Item
    {
        if (!$this->getEquipped()->isUserDroppable()) {
            return null;
        }

        $item = $this->items[$this->equippedSlot];
        unset($this->items[$this->equippedSlot]);
        $this->updateEquippedSlot();

        $item->unEquip();
        return $item;
    }

    public function canBuy(Item $item): bool
    {
        if ($item->getPrice() > $this->dollars) {
            return false;
        }

        $alreadyHave = $this->items[$item->getSlot()->value] ?? false;
        if ($alreadyHave) {
            /** @var Item $item */
            $item = $alreadyHave;
            if ($item->canPurchaseMultipleTime()) {
                return true;
            }
            return false;
        }

        return true;
    }

    public function purchase(Player $player, BuyMenuItem $buyCandidate): ?EquipEvent
    {
        $item = $this->store->get($buyCandidate);
        if (!$item || !$this->canBuy($item)) {
            return null;
        }

        $this->dollars -= $item->getPrice();
        $alreadyHave = $this->items[$item->getSlot()->value] ?? false;
        if ($alreadyHave) {
            if ($alreadyHave instanceof Flashbang) {
                $alreadyHave->incrementQuantity();
                $item = $alreadyHave;
            } else {
                $this->equip($item->getSlot());
                $player->dropEquippedItem(false);
            }
        }

        $this->store->buy($item);
        if ($item instanceof Kevlar) {
            $this->armorType = $item->getArmorType();
            return null;
        }
        $this->items[$item->getSlot()->value] = $item;
        return $this->equip($item->getSlot());
    }

    public function equip(InventorySlot $slot): ?EquipEvent
    {
        $item = $this->items[$slot->value] ?? false;
        if (!$item) {
            return null;
        }

        $this->items[$this->equippedSlot]->unEquip();
        $this->lastEquippedSlotId = $this->equippedSlot;
        $this->equippedSlot = $slot->value;
        return $item->equip();
    }

    public function pickup(Item $item): bool
    {
        $haveIt = $this->items[$item->getSlot()->value] ?? false;
        if ($haveIt && $haveIt->getQuantity() === $haveIt->getMaxQuantity()) {
            return false;
        }

        if ($haveIt) {
            $haveIt->incrementQuantity();
            return true;
        }
        $this->items[$item->getSlot()->value] = $item;
        return true;
    }

    public function getDollars(): int
    {
        return $this->dollars;
    }

    public function earnMoney(int $amount): void
    {
        $this->dollars += $amount;
        if ($this->dollars < 0) {
            $this->dollars = 0;
        }
        if ($this->dollars > 16000) {
            $this->dollars = 16000;
        }
    }

    /**
     * @return Item[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<int,int> [slotId => itemCount]
     */
    public function getFilledSlots(): array
    {
        $slots = [];
        foreach ($this->items as $slotId => $item) {
            $slots[$slotId] = $item->getQuantity();
        }

        return $slots;
    }

    public function getArmor(): ArmorType
    {
        return $this->armorType;
    }

    public function has(int $slotId): bool
    {
        return (isset($this->items[$slotId]));
    }

}
