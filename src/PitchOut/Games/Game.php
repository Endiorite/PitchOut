<?php

namespace PitchOut\Games;

use PitchOut\Main;
use PitchOut\Managers\GameManager;
use PitchOut\Managers\PlayerManager;
use pocketmine\entity\Location;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\Stick;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\GuiDataPickItemPacket;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;
use raklib\utils\ExceptionTraceCleaner;

class Game
{

    public $players = [];
    public ?World $world = null;
    public $UniqueID = "";
    public $yLimit = 0;
    public $spawnPosition = [
        "256:76:255",
        "244:76:255",
        "256:76:243",
        "268:76:255",
        "268:76:256",
        "301:80:214",
        "256:76:288",
        "256:76:218",
        "221:76:256",
        "221:76:256",
        "291:76:256",
        "304:80:298",
        "216:80:303",
    ];

    public ?int $startTime = null;
    public string $hoster = "CONSOLE";
    public string $topkill = "null";

    public function __construct(array $players, World $world, string $gameUUID){
        $this->players = $players;
        $this->world = $world;
        $this->UniqueID = $gameUUID;
    }

    public function getHoster() : string{
        return $this->hoster;
    }

    public function worldName() : string{
        return $this->getWorld()->getFolderName();
    }

    public function initGame() : void{

        $this->startTime = time();
        $uuid = $this->UniqueID;
        $exludePos = [];
        var_dump("init game " . $this->UniqueID . " !");
        foreach ($this->players as $player => $value){
            if($p = Server::getInstance()->getPlayerExact($player)){
                $spawn = $this->spawnPlayer($p, $exludePos);
                if(!is_null($spawn)){
                    $exludePos[] = $spawn;
                }
                $p->setImmobile();
            }
            $this->startScreenCallback($player, function () use ($player, &$exludePos, $uuid){
                $game = GameManager::getGame($uuid);
                if($p = Server::getInstance()->getPlayerExact($player)){
                    $playerManager = new PlayerManager($p, $uuid);
                    GameManager::addPlayerInGame($playerManager, $uuid);
                    var_dump($p->getName());
                    $game->addKit($p);

                    $pManager = $game->getPlayer($player);
                    $p->setNameTag($pManager->getMalusString() . "\n§f" . $pManager->getPlayerName());
                    $p->setImmobile(false);
                }else{
                    if(isset($game->players[$player])){
                        unset($game->players[$player]);
                    }
                }
            });
        }

    }

    public function getPlayersAlive(){
        $players = [];
        foreach ($this->players as $playerName => $player){
            if($player instanceof PlayerManager){
                if($player->isAlive()){
                    $players[$playerName] = $player;
                }
            }
        }

        return $players;
    }

    public function getChronoString() : string{
        $time = Main::getInstance()->convertTime($this->startTime);
        $minute = abs($time["minuts"])-1;
        if($minute < 10){
            $minute = "0" . $minute;
        }
        $seconde = abs($time["seconds"]);
        if($seconde < 10){
            $seconde = "0" . $seconde;
        }
        return "00:$minute:$seconde";
    }

    /**
     * @return World|null
     */
    public function getWorld(): ?World
    {
        return $this->world;
    }

    public function addKit(Player $player){
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $stick = VanillaItems::FISHING_ROD();
        $player->getInventory()->setItem(0, $stick);

        $snow = ItemFactory::getInstance()->get(ItemIds::SNOWBALL, 0, 16);
        $player->getInventory()->setItem(1, $snow);
    }

    public function setSpectator(Player $player, Position $position){
        $player->getArmorInventory()->clearAll();
        $player->getInventory()->clearAll();
        $player->setGamemode(GameMode::SPECTATOR());
        $player->teleport($position);
    }

    public function broadcastMessage(string $text){
        foreach ($this->world->getPlayers() as $player){
            $player->sendMessage($text);
        }
    }

    public function isAlive(string $player): bool{
        if(!$this->existsPlayer($player)) return false;
        $player = $this->getPlayer($player);
        return $player->isAlive();
    }

    public function kill(string $player){
        if(!$this->existsPlayer($player)) return;

        $player = $this->getPlayer($player);

        if($this->getHealth($player->getPlayerName()) <= 0){
            $player->setAlive(false);
        }

        $this->getPlayer($player->getPlayerName())->reduceHealth();
    }

    public function getHealth(string $player): int{
        if(!$this->existsPlayer($player)) return 0;

        return $this->getPlayer($player)->getHealth();
    }

    public function existsPlayer(string $player){
        if(isset($this->players[$player])){
            return true;
        }

        return false;
    }

    public function getPlayer(string $player) : PlayerManager{
        return $this->players[$player];
    }

    public function getTopKiller() : string{
        return $this->topkill;
    }

    public function updateTopKiller(): string
    {
        $kill = 0;
        $killer = "null";
        foreach ($this->getPlayersAlive() as $playerName => $pManager){
            if($pManager instanceof PlayerManager){
                if($pManager->getKill() > $kill){
                    $kill = $pManager->getKill();
                    $killer = $pManager->getPlayerName();
                }
            }
        }

        $this->topkill = $killer;
        return $killer;
    }

    public function inVoidLimite(Player $player){
        if($player->getPosition()->getY() <= $this->yLimit){
            return true;
        }else return false;
    }

    public function lastPlayer() : bool|PlayerManager{
        if(count($this->getPlayersAlive()) <= 1){
            $r = "Aucun";
            foreach ($this->getPlayersAlive() as $pname => $playerManager){
                $r = $playerManager;
                break;
            }

            return $r;
        }

        return false;
    }

    public function startScreenCallback(string $playerName, callable $call){
        $period = 5;
        $task = new ClosureTask(function () use ($call, &$period, &$task, $playerName){
            if($p = Server::getInstance()->getPlayerExact($playerName)){
                $p->sendTitle("§c" . $period);

            }
            if($period <= 0){
                call_user_func($call);
                $task->getHandler()->cancel();
            }

            $period--;
        });
        Main::getInstance()->getScheduler()->scheduleRepeatingTask($task, 20);
    }

    public function spawnPlayer(Player $player, $exludePos = [null]){
        $posr = null;
        $spawnPos = $this->spawnPosition;
        shuffle($spawnPos);
        foreach ($spawnPos as $pos){
            if(!in_array($pos, $exludePos)){
                $exp = explode(":", $pos);
                $location = new Location((int)$exp[0], (int)$exp[1], (int)$exp[2], $this->getWorld(), $player->getLocation()->getYaw(), $player->getLocation()->getPitch());
                $player->teleport($location);
                $posr = $pos;
                var_dump("tp to position");
                break;
            }
        }

        $pManager = $this->getPlayer($player->getName());

        if($pManager->isAlive()){
            //$player->setMaxHealth($this->getPlayer($player->getName())->getHealth()*2);
            $player->setHealth($this->getPlayer($player->getName())->getHealth()*2);
            GameListener::$snowball[$player->getName()] = 16;
            GameListener::$fishingRod[$player->getName()] = 5;

            if ($pManager->getHealth() < 2){
                $currentMalus = $pManager->getMalus();
                $newMalus = intval($currentMalus/2) < 0 ? 0 : intval($currentMalus/2);
                $pManager->malusPourcent = $newMalus;
            }
        }

        return $posr;
    }

    public function end(){
        $this->updateTopKiller();
        $lastPlayer = $this->lastPlayer();
        $this->broadcastMessage("§eAlias §7» §brésumé de la partie\n§6------------------------------\n§bWinners: " . $lastPlayer->getPlayerName() . "\n§eTop Kill: " . $this->getTopKiller() . " (".$lastPlayer->getKill().")" . "\n§6------------------------------");

        foreach ($this->players as $pname => $pm){
            if ($p = Server::getInstance()->getPlayerExact($pname)){
                if(GameManager::inGame($p)){
                    GameManager::unsetGame($p->getName());
                }

                $p->teleport(Server::getInstance()->getWorldManager()->getWorldByName(Main::WORLD)->getSpawnLocation());
                $p->setGamemode(GameMode::SURVIVAL());
            }
        }

        GameManager::deleteGame($this->UniqueID);
    }

}