<?php

namespace PitchOut\Managers;

use PitchOut\Games\Game;
use PitchOut\Main;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Binary;
use pocketmine\world\format\io\data\BedrockWorldData;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Webmozart\PathUtil\Path;

class GameManager
{
    public static $player = [];
    public static $game = [];

    public static function createGame(){
        $gameUUID = Uuid::uuid4()->toString();

        $world = self::copyWorld("pitchout", "pitchout_" . self::randomNumber());
        Server::getInstance()->getWorldManager()->loadWorld($world);
        $world = Server::getInstance()->getWorldManager()->getWorldByName($world);
        $game = new Game([], $world, $gameUUID);

        self::$game[$gameUUID] = $game;

        return $gameUUID;
    }

    public static function deleteGame(string $gameUUID){
        self::deleteWorld(self::getGame($gameUUID)->getWorld());
        unset(self::$game[$gameUUID]);
    }

    public static function unsetGame(string $playerName){
        unset(self::$player[$playerName]);
        if ($p = Server::getInstance()->getPlayerExact($playerName)){
            $scoreboard = new ScoreboardManager($p);
            $scoreboard->removeScoreboard();
        }
    }

    public static function getGames(): array{
        return self::$game;
    }

    public static function inGame(Player $player){
        if(isset(self::$player[$player->getName()])){
            return true;
        }
        return false;
    }

    public static function addPlayerInGame(PlayerManager $player, $gameUUID){
        $game = GameManager::getGame($gameUUID);
        $game->players[$player->getPlayerName()] = $player;
        self::$player[$player->getPlayerName()] = $gameUUID;
    }

    public static function getPlayer(string $playerName, $gameUUID){
        $game = GameManager::getGame($gameUUID);
        if(!$game->existsPlayer($playerName)) return false;
        return $game->getPlayer($playerName);
    }

    public static function getGame(string $uuid): ?Game
    {
        return self::$game[$uuid];
    }

    public static function getPlayerGameUUID(Player $player){
        return self::$player[$player->getName()];
    }

    public static function getPlayerGame(Player $player): ?Game{
        return self::$game[GameManager::getPlayerGameUUID($player)];
    }

    public static function randomNumber(): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < 10; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
        return $randomString;
    }

    public static function copyWorld(string $from, string $name ): string{
        $server = Server::getInstance();
        @mkdir($server->getDataPath() . "/worlds/$name/");
        @mkdir($server->getDataPath() . "/worlds/$name/db/");
        copy($server->getDataPath() . "/worlds/" . $from. "/level.dat", $server->getDataPath() . "/worlds/$name/level.dat");
        $oldWorldPath = $server->getDataPath() . "/worlds/$from/level.dat";
        $newWorldPath = $server->getDataPath() . "/worlds/$name/level.dat";

        $oldWorldNbt = new BedrockWorldData($oldWorldPath);
        $newWorldNbt = new BedrockWorldData($newWorldPath);

        $worldData = $oldWorldNbt->getCompoundTag();
        $newWorldNbt->getCompoundTag()->setString("LevelName", $name);


        $nbt = new LittleEndianNbtSerializer();
        $buffer = $nbt->write(new TreeRoot($worldData));
        file_put_contents(Path::join($newWorldPath), Binary::writeLInt(BedrockWorldData::CURRENT_STORAGE_VERSION) . Binary::writeLInt(strlen($buffer)) . $buffer);
        self::copyDir($server->getDataPath() . "/worlds/" . $from . "/db", $server->getDataPath() . "/worlds/$name/db/");

        return $name;
    }

    public static function copyDir($from, $to){
        $to = rtrim($to, "\\/") . "/";
        /** @var \SplFileInfo $file */
        foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from)) as $file){
            if($file->isFile()){
                $target = $to . ltrim(substr($file->getRealPath(), strlen($from)), "\\/");
                $dir = dirname($target);
                if(!is_dir($dir)){
                    mkdir(dirname($target), 0777, true);
                }
                copy($file->getRealPath(), $target);
            }
        }
    }

    public static function removeWorld($folderName): void {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $worldPath = Server::getInstance()->getDataPath() . "worlds/$folderName",
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var \SplFileInfo $file */
        foreach($files as $file) {
            if($filePath = $file->getRealPath()) {
                if($file->isFile()) {
                    unlink($filePath);
                } else {
                    rmdir($filePath);
                }
            }
        }
        rmdir($worldPath);
    }

    public static function deleteWorld(World $world){
        foreach ($world->getPlayers() as $player){
            $player->teleport(Server::getInstance()->getWorldManager()->getWorldByName(Main::WORLD)->getSpawnLocation());
        }
        $worldName = $world->getFolderName();
        //Server::getInstance()->getWorldManager()->unloadWorld($world);
        $dir = Server::getInstance()->getDataPath() . '/worlds/' . $worldName;
        //self::removeWorld($worldName);
        //Server::getInstance()->dispatchCommand(new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage()), "mw delete " . $world->getFolderName());
    }

    public static function deleteTree($dir){
        foreach(glob($dir . "/*") as $element){
            if(is_dir($element)){
                self::deleteTree($element);
                rmdir($element);
            } else {
                unlink($element);
            }
        }
    }

}