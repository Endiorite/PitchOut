<?php

namespace PitchOut\Games;

use PitchOut\Constants\Messages;
use PitchOut\Constants\Prefix;
use PitchOut\Items\FishingRod;
use PitchOut\Managers\GameManager;
use PitchOut\Managers\PlayerManager;
use PitchOut\Managers\ScoreboardManager;
use pocketmine\item\Snowball;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;

class GameTask extends Task
{

    public array $reloadFishing = [];
    public array $reloadSnowball = [];
    public array $gameR = [];

    /**
     * @inheritDoc
     */
    public function onRun(): void
    {
        foreach (GameManager::$game as $gameUUID => $game){
            if($game instanceof Game){
                if (is_null($game->startTime)){
                    if (!isset($this->gameR[$game->UniqueID])){
                        $this->gameR[$game->UniqueID] = ["status" => "waiting", "time" => 60];
                    }

                    $pCount = count($game->players);
                    if ($pCount <= 0){
                        if (isset($this->gameR[$game->UniqueID])){
                            unset($this->gameR[$game->UniqueID]);
                        }
                        GameManager::deleteGame($game->UniqueID);
                        var_dump("deleted game " . $game->UniqueID . " no player");
                        return;
                    }

                    if ($pCount < 5){
                        if ($this->gameR[$game->UniqueID]["status"] === "waiting"){
                            $game->broadcastMessage(Prefix::prefix_pitchout . Messages::MIN_REQUIRED);
                            $this->gameR[$game->UniqueID] = ["status" => "min_required", "time" => 60];
                        }
                    }else if($pCount >= 5){
                        if ($this->gameR[$game->UniqueID]["status"] === "min_required"){
                            $game->broadcastMessage(Prefix::prefix_pitchout . Messages::READY_TO_LAUNCH);
                            $this->gameR[$game->UniqueID] = ["status" => "start", "time" => 60];
                        }
                    }else if($pCount >= 10){
                        if ($this->gameR[$game->UniqueID]["status"] === "start" && $this->gameR[$game->UniqueID]["time"] > 30){
                            $game->broadcastMessage(Prefix::prefix_pitchout . "Lancement de la partie dans §e30 seconde(s)");
                            $this->gameR[$game->UniqueID] = ["status" => "start", "time" => 30];
                        }
                    }

                    if ($this->gameR[$game->UniqueID]["status"] === "start"){
                        if ($this->gameR[$game->UniqueID]["time"] <= 5 && $this->gameR[$game->UniqueID]["time"] > 0){
                            $game->broadcastMessage(Prefix::prefix_pitchout . str_replace("{s}", $this->gameR[$game->UniqueID]["time"], Messages::PREPA_GAME));
                        }
                    }

                    if ($this->gameR[$game->UniqueID]["time"] <= 0){
                        switch ($this->gameR[$game->UniqueID]["status"]){
                            case  "min_required":
                                $game->broadcastMessage(Prefix::prefix_pitchout . Messages::MIN_REQUIRED);
                                $this->gameR[$game->UniqueID] = ["status" => "min_required", "time" => 60];
                            break;

                            case "start":
                                $game->broadcastMessage(Prefix::prefix_pitchout . Messages::LAUNCH_GAME);
                                $game->initGame();
                            break;
                        }
                    }

                    $this->gameR[$game->UniqueID]["time"]--;
                }else{
                    if (isset($this->gameR[$game->UniqueID])){
                        unset($this->gameR[$game->UniqueID]);
                    }
                }
                foreach ($game->players as $playerName => $player){
                    if($player instanceof PlayerManager){
                        if(!is_null($sender = $player->getPlayer()) && !is_null($game->startTime)){
                            $this->sendScoreboard($sender, $game);

                            $itemInHand = $sender->getInventory()->getItemInHand();
                            $snowuse = 16;
                            if(isset(GameListener::$snowball[$playerName])) $snowuse = GameListener::$snowball[$playerName];
                            $use = 5;
                            if(isset(GameListener::$fishingRod[$playerName])) $use = GameListener::$fishingRod[$playerName];

                            if ($snowuse < 16){
                                $number = $player->getMalus();
                                $reloadTimeS = match(true){
                                    $number <= 0 && $number <= 50 => 2,
                                    $number >= 50 && $number <= 100 => 3,
                                    $number >= 100 && $number <= 150 => 6,
                                    $number >= 150 && $number <= 250 => 8,
                                    default => 3
                                };

                                if (!isset($this->reloadSnowball[$playerName])){
                                    $this->reloadSnowball[$playerName] = 0;
                                }

                                $this->reloadSnowball[$playerName]++;

                                if($this->reloadSnowball[$playerName] >= $reloadTimeS){
                                    $this->reloadSnowball[$playerName] = 0;
                                    GameListener::$snowball[$playerName]++;
                                    $sender->getInventory()->addItem(VanillaItems::SNOWBALL());
                                }
                            }

                            if ($use < 5){
                                $number = $player->getMalus();
                                $reloadTime = match(true){
                                    $number <= 0 && $number <= 50 => 2,
                                    $number >= 50 && $number <= 100 => 5,
                                    $number >= 100 && $number <= 150 => 8,
                                    $number >= 150 && $number <= 250 => 13,
                                    default => 3
                                };

                                if (!isset($this->reloadFishing[$playerName])){
                                    $this->reloadFishing[$playerName] = 0;
                                }

                                $this->reloadFishing[$playerName]++;

                                if($this->reloadFishing[$playerName] >= $reloadTime){
                                    $this->reloadFishing[$playerName] = 0;
                                    GameListener::$fishingRod[$playerName]++;
                                }
                            }

                            if($itemInHand instanceof FishingRod){
                                $color = match (true){
                                    $use >= 5 && $use >= 3 => TextFormat::GREEN,
                                    $use <= 2 && $use >= 1 => TextFormat::YELLOW,
                                    $use === 0 => TextFormat::GOLD,
                                    default => TextFormat::GREEN
                                };

                                $sender->sendPopup($color . "Canne à pêche ($use/5)");
                            }

                            if($itemInHand instanceof Snowball){
                                $color = match (true){
                                    $snowuse >= 16 && $snowuse >= 11 => TextFormat::GREEN,
                                    $snowuse <= 11 && $snowuse >= 9 => TextFormat::YELLOW,
                                    $snowuse <= 9 && $snowuse >= 1 => TextFormat::GOLD,
                                    $snowuse === 0 => TextFormat::RED,
                                    default => TextFormat::GREEN
                                };

                                $sender->sendPopup($color . "Boule de neige ($snowuse/16)");
                            }
                        }
                    }
                }
            }
        }
    }

    public function sendScoreboard(Player $player, Game $game){
        $scoreboard = new ScoreboardManager($player);

        $pm = $game->getPlayer($player->getName());
        $scoreboard->removeScoreboard();
        $scoreboard->addScoreboard(Prefix::prefix_pitchout);
        $scoreboard->setLine(1, "§8(". $game->worldName() .")");
        $scoreboard->setLine(2, Prefix::grey_arrow . "§eHost : §7" . $game->getHoster());
        $scoreboard->setLine(3, Prefix::grey_arrow . "§eJoueur(s) : §f" . count($game->getPlayersAlive()) . "§7/15");
        $scoreboard->setLine(4, Prefix::grey_arrow . "§eMalus : §7" . $game->getPlayer($player->getName())->getMalusString());
        $scoreboard->setLine(5, "§f");
        $scoreboard->setLine(7, Prefix::grey_arrow . "§eChrono : §7" . $game->getChronoString());
        $scoreboard->setLine(8, Prefix::grey_arrow . "§eKill(s) : §7" . $pm->getKill());
        $scoreboard->setLine(8, "§f ");
        $scoreboard->setLine(11, "play.alias-uhc.best §b[S1]");
    }
}