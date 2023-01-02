<?php

namespace PitchOut\Managers;

use PitchOut\Games\Game;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class PlayerManager
{
    public ?Player $player = null;
    public ?string $playerName = null;
    public ?string $gameUUID = null;
    public bool $alive = true;
    public int $health = 2;
    public float $malusPourcent = 0;
    public int $kill = 0;

    public function __construct(Player $player, String $gameUUID, $health = 2)
    {
        $this->player = $player;
        $this->playerName = $player->getName();
        $this->health = $health;
        $this->gameUUID = $gameUUID;
    }

    public function reduceHealth(){
        $this->health--;
        if ($this->health <= 0){
            $this->alive = false;
        }
    }

    public function getPlayerName() : string{
        return $this->playerName;
    }

    public function addMalus(float $malus = 0) : void{
        if(!is_null($this->getPlayer())){
            $this->getPlayer()->setNameTag($this->getMalusString() . "\n§f" . $this->getPlayerName());
        }
        if($this->malusPourcent + $malus >= 250) {
            $this->malusPourcent = 250;
            return;
        }
        $this->malusPourcent += $malus;

    }

    public function getMalus() : float{
        return $this->malusPourcent;
    }

    public function getMalusString() : string{
        $number = $this->malusPourcent;
        $color = match(true){
            $number <= 0 && $number <= 50 => TextFormat::GREEN,
            $number >= 50 && $number <= 100 => TextFormat::YELLOW,
            $number >= 100 && $number <= 150 => TextFormat::GOLD,
            $number >= 150 && $number <= 250 => TextFormat::RED,
            default => TextFormat::GREEN
        };
        return $color . $this->malusPourcent . "%%";
    }

    public function kill(Position $position){
        $this->health--;
        $game = $this->getGame();
        $game->kill($this->playerName, $position);
    }

    public function getPlayer() : ?Player{
        return Server::getInstance()->getPlayerExact($this->getPlayerName());
    }

    public function getHealth() : int{
        return $this->health;
    }

    public function getGame() : Game{
        return GameManager::getGame($this->gameUUID);
    }

    public function isAlive() : bool {
        return $this->alive;
    }

    public function getKill() : int{
        return $this->kill;
    }

    public function addKill() : int{
        return $this->kill++;
    }

    public function setAlive(bool $value = true) : void{
        $this->alive = $value;
    }

    public function randomFloat($min = 0, $max = 1) : float{
        return mt_rand($min, $max) . "." . mt_rand(0, 9);
    }
}