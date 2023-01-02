<?php

namespace PitchOut\Items;

use PitchOut\Games\GameListener;
use PitchOut\Main;
use PitchOut\Managers\GameManager;
use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Throwable;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\Random;

class HookEntity extends Throwable {
    public static function getNetworkTypeId() : string { return EntityIds::FISHING_HOOK; }

    protected $gravity = 0.1;

    private const MOTION_SEED = 0.007499999832361937;

    public function getResultDamage() : int {
        return 1;
    }

    protected function onHitBlock(Block $blockHit, RayTraceResult $hitResult) : void {
        Projectile::onHitBlock($blockHit, $hitResult);
    }

    public function __construct(Location $pos, ?Entity $owner, ?CompoundTag $nbt = null) {
        parent::__construct($pos, $owner, $nbt);

        if($owner instanceof Player) {
            $this->setPosition($this->location->add(0, $owner->getEyeHeight() - 0.1, 0));
            $this->setMotion($owner->getDirectionVector()->multiply(0.4));
            Main::setFishingHook($this, $owner);
            $this->handleHookCasting($this->motion->x, $this->motion->y, $this->motion->z, 1.5, 1.0);
        }
    }

    public function handleHookCasting(float $x, float $y, float $z, float $f1, float $f2) : void {
        $rand = new Random();
        $f = sqrt($x * $x + $y * $y + $z * $z);
        $x = $x / (float)$f;
        $y = $y / (float)$f;
        $z = $z / (float)$f;
        $x = $x + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
        $y = $y + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
        $z = $z + $rand->nextSignedFloat() * 0.007499999832361937 * (float)$f2;
        $x = $x * (float)$f1;
        $y = $y * (float)$f1;
        $z = $z * (float)$f1;
        $this->motion->x += $x;
        $this->motion->y += $y;
        $this->motion->z += $z;
    }

    public function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void {
        //Do nothing
    }

    public function entityBaseTick(int $tickDiff = 1) : bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $owner = $this->getOwningEntity();
        if($owner instanceof Player) {
            if(!$owner->getInventory()->getItemInHand() instanceof FishingRod or !$owner->isAlive() or $owner->isClosed()) {
                $this->flagForDespawn();
            }
        } else {
            $this->flagForDespawn();
        }

        return $hasUpdate;
    }

    public function onDispose() : void {
        parent::onDispose();

        $owner = $this->getOwningEntity();
        if($owner instanceof Player) {
            Main::setFishingHook(null, $owner);
        }
    }

    public function handleHookRetraction() : void {
        $owner = $this->getOwningEntity();
        if($owner instanceof Player){
            if(GameManager::inGame($owner)){
                $game = GameManager::getPlayerGame($owner);
                $player = $game->getPlayer($owner->getName());
                $player->addMalus($player->randomFloat(1.0, 15.0));
                if($this->canUseRod($owner->getName())){
                    $ownerPos = $owner->getPosition();
                    $dist = $this->location->distanceSquared($ownerPos);
                    $owner->setMotion($this->location->subtractVector($ownerPos)->multiply($this->getGrapplingSpeed($dist)));
                    $this->flagForDespawn();
                    $this->removeUse($owner->getName());
                }
            }else{
                $ownerPos = $owner->getPosition();
                $dist = $this->location->distanceSquared($ownerPos);
                $owner->setMotion($this->location->subtractVector($ownerPos)->multiply($this->getGrapplingSpeed($dist)));
                $this->flagForDespawn();
            }
        }
    }

    private function getGrapplingSpeed(float $dist) : float {
        if($dist > 600):
            $motion = 0.11;
        elseif($dist > 500):
            $motion = 0.11;
        elseif($dist > 300):
            $motion = 0.11;
        elseif($dist > 200):
            $motion = 0.11;
        elseif($dist > 100):
            $motion = 0.105;
        elseif($dist > 40):
            $motion = 0.10;
        else:
            $motion = 0.8;
        endif;

        return $motion;
    }

    public function onUpdate(int $currentTick) : bool {
        if($this->closed){
            return false;
        }

        if($this->isUnderwater()) {
            $this->motion->y += $this->gravity;
        }

        return parent::onUpdate($currentTick);
    }

    public function removeUse($pName){
        $use = 5;
        if(isset(GameListener::$fishingRod[$pName])) $use = GameListener::$fishingRod[$pName];
        GameListener::$fishingRod[$pName] = $use;

        if($use <= 0) return;
        GameListener::$fishingRod[$pName]--;
    }

    public function canUseRod($pName){
        $use = 5;
        if(isset(GameListener::$fishingRod[$pName])) $use = GameListener::$fishingRod[$pName];

        if($use <= 0) return false;
        return true;
    }
}