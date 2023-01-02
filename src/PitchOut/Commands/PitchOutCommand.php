<?php

namespace PitchOut\Commands;

use PitchOut\Constants\Prefix;
use PitchOut\Games\Game;
use PitchOut\Main;
use PitchOut\Managers\GameManager;
use PitchOut\Managers\PlayerManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\Translatable;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;

class PitchOutCommand extends Command
{
    public function __construct()
    {
        parent::__construct("pitchout", "", "/pitchout <join/start>", []);
    }

    /**
     * @inheritDoc
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if(!$sender instanceof Player) return;

        if(!isset($args[0])) {
            $sender->sendMessage(Prefix::prefix_pitchout . "§7Usage: <join/start/create>");
            return;
        }
        switch ($args[0]){
            case "create":
                if(!Server::getInstance()->isOp($sender->getName())) return;
                $gameUUID = GameManager::createGame();
                $sender->sendMessage("$gameUUID");
            break;
            case "start":
                foreach (GameManager::getGames() as $uuid => $game){
                    if($game instanceof Game){
                        $game->initGame();
                        $sender->sendMessage(Prefix::prefix_pitchout . "§7Vous avez lancer la game de pitchout ($uuid)");
                    }
                }
            break;
            case "join":
                $hasGame = false;
                if (GameManager::inGame($sender)){
                    $sender->sendMessage(Prefix::prefix_pitchout . "§7Vous devez quitter votre game actuelle §c/pitchout leave");
                    return;
                }

                foreach (GameManager::getGames() as $uuid => $game){
                    $game = GameManager::getGame($uuid);
                    if (count($game->players) < 12){
                        if (is_null($game->startTime)){
                            $playerManager = new PlayerManager($sender, $uuid);
                            GameManager::addPlayerInGame($playerManager, $uuid);
                            $sender->sendMessage(Prefix::prefix_pitchout . "§7Vous avez rejoint une game de pitchout (".$game->worldName().")");
                            $sender->teleport($game->getWorld()->getSpawnLocation());
                            $game->broadcastMessage(Prefix::prefix_pitchout . "§8[§e+§8]§8 " . $sender->getName());
                            $sender->setGamemode(GameMode::SURVIVAL());
                            $hasGame = true;
                            break;
                        }
                    }
                }

                if (!$hasGame){
                    $sender->sendMessage(Prefix::prefix_pitchout . "§7Création d'une game de pitchout");
                    $gameUUID = GameManager::createGame();
                    $game = GameManager::getGame($gameUUID);

                    $playerManager = new PlayerManager($sender, $gameUUID);
                    GameManager::addPlayerInGame($playerManager, $gameUUID);

                    $sender->sendMessage(Prefix::prefix_pitchout . "§7Vous avez rejoint une game de pitchout (".$game->worldName().")");
                    $sender->teleport($game->getWorld()->getSpawnLocation());

                    $game->broadcastMessage(Prefix::prefix_pitchout . "§8[§e+§8]§8 " . $sender->getName());

                    $sender->setGamemode(GameMode::SURVIVAL());
                }

                $sender->getInventory()->clearAll();
                $sender->getArmorInventory()->clearAll();
            break;

            case "leave":
                if (GameManager::inGame($sender)){
                    $game = GameManager::getPlayerGame($sender);
                    $pManager = $game->getPlayer($sender->getName());
                    $pManager->alive = false;
                    if (isset($game->players[$sender->getName()])){
                        unset($game->players[$sender->getName()]);
                    }
                    GameManager::unsetGame($sender->getName());
                    if (is_null($game->startTime)){
                        $game->broadcastMessage(Prefix::prefix_pitchout . "§8[§c-§8]§8 " . $sender->getName());
                    }
                    $sender->sendMessage(Prefix::prefix_pitchout . "§7Vous avez quitter votre game !");
                    $sender->teleport(Server::getInstance()->getWorldManager()->getWorldByName(Main::WORLD)->getSpawnLocation());
                    $sender->setGamemode(GameMode::SURVIVAL());
                    return;
                }

                $sender->sendMessage(Prefix::prefix_pitchout ."§fVous n'êtes pas dans une game !");
            break;

            case "list":
                $games = [];
                $sender->sendMessage("Games:");
                foreach (GameManager::getGames() as $gameUUID => $game){
                    if ($game instanceof Game){
                        $games[$game->worldName()] = ["hoster" => $game->getHoster(), "player" => count($game->players)];
                        $sender->sendMessage("- " . $game->worldName() ." hoster par " . $game->getHoster() . " player(s) " . count($game->players));
                    }
                }
            break;
        }
    }
}