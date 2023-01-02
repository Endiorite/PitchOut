<?php

namespace PitchOut\Items;

use PitchOut\Main;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Tool;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\player\Player;
use pocketmine\world\sound\ThrowSound;

class FishingRod extends Tool {

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, 'Fishing Rod');
    }

    public function getMaxDurability() : int {
        return 65;
    }

    public function getFuelTime() : int {
        return 300;
    }

    public function onAttackEntity(Entity $victim) : bool {
        return $this->applyDamage(1);
    }

    public function onClickAir(Player $player, Vector3 $directionVector) : ItemUseResult {

        $location = $player->getLocation();
        $world = $player->getWorld();

        if(Main::getFishingHook($player) === null) {
            $hook = new HookEntity(Location::fromObject(
                $player->getEyePos(),
                $world,
                ($location->yaw > 180 ? 360 : 0) - $location->yaw,
                -$location->pitch
            ), $player);

            $ev = new ProjectileLaunchEvent($hook);
            if($ev->isCancelled()) {
                $hook->flagForDespawn();
                return ItemUseResult::FAIL();
            }

            $hook->spawnToAll();
        } else {
            $hook = Main::getFishingHook($player);
            $hook->handleHookRetraction();
        }

        $world->broadcastPacketToViewers($location, AnimatePacket::create($player->getId(), AnimatePacket::ACTION_SWING_ARM));
        $world->addSound($player->getPosition(), new ThrowSound());
        return ItemUseResult::SUCCESS();
    }

}