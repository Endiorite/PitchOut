<?php

namespace PitchOut\Games;

use PitchOut\Constants\Messages;
use PitchOut\Constants\Prefix;
use PitchOut\Items\FishingRod;
use PitchOut\Managers\GameManager;
use pocketmine\block\Block;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\Event;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

class GameListener implements Listener
{

    public static array $fishingRod = [];
    public static array $snowball = [];

    public function onMove(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if(GameManager::inGame($player)){
            $game = GameManager::getPlayerGame($player);
            if($game->inVoidLimite($player)){
                $pdeathEvent = new PlayerDeathEvent($player, [], 0, "death");
                $pdeathEvent->call();
            }
        }
    }

    public function onDamage(EntityDamageEvent $event){
        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        if(GameManager::inGame($entity)){
            $game = GameManager::getPlayerGame($entity);
            if (is_null($game->startTime)){
                $event->cancel();
            }
            if($event->getCause() == EntityDamageEvent::CAUSE_FALL){
                $event->cancel();
            }
        }
    }

    /**
     * @param EntityDamageByEntityEvent $event
     * @return void
     * @priority MONITOR
     */
    public function onEntityDamage(EntityDamageByEntityEvent $event){
        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        if(GameManager::inGame($entity)){
            if(!is_null($e = $event->getDamager())){
                $game = GameManager::getPlayerGame($entity);
                if (is_null($game->startTime)){
                    return;
                }
                $pManager = $game->getPlayer($entity->getName());
                $event->setBaseDamage(0);
                $damager = $event->getDamager();
                if (!is_null($damager)){
                    if ($damager instanceof Player){
                        $itemInHand = $damager->getInventory()->getItemInHand();
                        if ($itemInHand instanceof FishingRod){
                            $event->setKnockBack( (3*0.5) + (0.012 * $pManager->getMalus()));
                            $pManager->addMalus($pManager->randomFloat(1.0, 4.0));
                        }
                    }
                }
                $event->setKnockBack((3*0.5) + (0.012 * $pManager->getMalus()));
            }

            $event->setAttackCooldown(9*20);
        }
    }

    public function onHit(ProjectileHitEntityEvent $event){
        $projectile = $event->getEntity();
        if ($projectile instanceof Snowball){
            $entityHited = $event->getEntityHit();
            if($entityHited instanceof Player){
                if (GameManager::inGame($entityHited)){
                    $game = GameManager::getPlayerGame($entityHited);
                    if ($game->isAlive($entityHited->getName())){
                        $pManager = $game->getPlayer($entityHited->getName());
                        $pManager->addMalus($pManager->randomFloat(1.0, 4.0));

                    }
                }
            }

            $owner = $projectile->getOwningEntity();
            if(!is_null($owner)){
                $sound = PlaySoundPacket::create("uhc.xporb", $owner->getPosition()->getX(), $owner->getPosition()->getY(), $owner->getPosition()->getZ(), 5, 1);
                $owner->getNetworkSession()->sendDataPacket($sound);
            }
        }
    }

    public function projectileLaunch(ProjectileLaunchEvent $event){
        $projectile = $event->getEntity();
        if ($projectile instanceof Snowball){
            $owner = $projectile->getOwningEntity();
            if(is_null($owner)) return;
            if (!$owner instanceof Player) return;

            if (GameManager::inGame($owner)){
                $game = GameManager::getPlayerGame($owner);

                if (is_null($game->startTime)){
                    return;
                }
                if(!isset(self::$snowball[$owner->getName()])){
                    self::$snowball[$owner->getName()] = 16;
                }

                if (self::$snowball[$owner->getName()] <= 0){
                    $event->cancel();
                    return;
                }

                self::$snowball[$owner->getName()]--;
            }
        }
    }

    public function regen(EntityRegainHealthEvent $event){
        $entity = $event->getEntity();
        if(!$entity instanceof Player) return;

        if(GameManager::inGame($entity)){
            $event->cancel();
        }
    }

    public function playerDropItem(PlayerDropItemEvent $event){
        $player = $event->getPlayer();
        if (GameManager::inGame($player)){
            $event->cancel();
        }
    }

    public function hunger(PlayerExhaustEvent $event){
        $player = $event->getPlayer();
        if (!$player instanceof Player) return;
        if (GameManager::inGame($player)){
            $event->cancel();
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        if (GameManager::inGame($player)){
            $game = GameManager::getPlayerGame($player);
            $pManager = $game->getPlayer($player->getName());
            $pManager->alive = false;
            if (isset($game->players[$player->getName()])){
                unset($game->players[$player->getName()]);
            }
            GameManager::unsetGame($player->getName());
            if (is_null($game->startTime)){
                $game->broadcastMessage(Prefix::prefix_pitchout . "§8[§c-§8]§8 " . $player->getName());
                return;
            }

            if($game->isAlive($player->getName())){
                $game->broadcastMessage(str_replace(["{player}", "{final_kill}"], [$player->getName(), "§3FINAL KILL"], Prefix::prefix_pitchout . Messages::PLAYER_DEATH));
            }
        }
    }

    public function onBreak(BlockBreakEvent $event){
        $player = $event->getPlayer();
        if(GameManager::inGame($player)){
            $event->cancel();
        }
    }

    public function onPlace(BlockPlaceEvent $event){
        $player = $event->getPlayer();
        if(GameManager::inGame($player)){
            $event->cancel();
        }
    }

    public function onDeath(PlayerDeathEvent $event){
        $player = $event->getPlayer();
        $entity = $event->getEntity();
        if(GameManager::inGame($player)){
            $event->setDrops([]);
            $event->setDeathMessage("");

            $game = GameManager::getPlayerGame($player);

            if (is_null($game->startTime)){
                $game->spawnPlayer($player);
                return;
            }

            $pManager = $game->getPlayer($player->getName());

            $game->kill($player->getName());

            $finalKill = "";
            if($game->getHealth($player->getName()) <= 0){
                $finalKill = "§3FINAL KILL";
            }

            $cause = $entity->getLastDamageCause();

            if($cause instanceof EntityDamageByEntityEvent){
                if($cause->getCause() === EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK){
                    if (is_null($cause->getDamager())){
                        $game->broadcastMessage(str_replace(["{player}", "{final_kill}"], [$player->getName(), $finalKill], Prefix::prefix_pitchout . Messages::PLAYER_DEATH));
                    }else{
                        $killer = $cause->getDamager();
                        $killerName = "entity";
                        if($killer instanceof $player){
                            $killerName = $killer->getName();
                        }

                        $game->broadcastMessage(str_replace(["{player}", "{killer}", "{final_kill}"], [$player->getName(), $killerName, $finalKill], Prefix::prefix_pitchout . Messages::PLAYER_DEATH_BY_PLAYER));
                        if($game->existsPlayer($killerName)){
                            $killerManager = $game->getPlayer($killerName);
                            $killerManager->addKill();
                        }
                    }
                }

            }else{
                $game->broadcastMessage(str_replace(["{player}", "{final_kill}"], [$player->getName(), $finalKill], Prefix::prefix_pitchout . Messages::PLAYER_DEATH));
            }

            if (!$game->lastPlayer() === false){
                $game->end();
                return;
            }

            if($game->isAlive($player->getName()) === false){
                $game->setSpectator($player, $game->getWorld()->getSafeSpawn());
                return;
            }

            $game->spawnPlayer($player);
            $game->addKit($player);
            $game->updateTopKiller();
        }
    }

}