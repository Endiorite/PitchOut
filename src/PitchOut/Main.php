<?php

namespace PitchOut;

use Largage\Entity\LargageEntity;
use PitchOut\Commands\PitchOutCommand;
use PitchOut\Games\Game;
use PitchOut\Games\GameListener;
use PitchOut\Games\GameTask;
use PitchOut\Items\FishingRod;
use PitchOut\Items\HookEntity;
use PitchOut\Managers\GameManager;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

class Main extends PluginBase
{
    use SingletonTrait;

    private static $fishing = [];
    const WORLD = "Lobby";

    public static function getFishingHook(Player $player) : ?HookEntity {
        return self::$fishing[$player->getName()] ?? null;
    }

    public static function setFishingHook(?HookEntity $fish, Player $player) {
        self::$fishing[$player->getName()] = $fish;
    }

    protected function onEnable(): void
    {
        self::setInstance($this);
        $this->getLogger()->info("PitchOut enable");
        $this->getServer()->getPluginManager()->registerEvents(new GameListener(), $this);
        $this->getServer()->getCommandMap()->register("pitchout", new PitchOutCommand());
        ItemFactory::getInstance()->register(new FishingRod(new ItemIdentifier(ItemIds::FISHING_ROD, 0), 'Fishing Rod'), true);
        $this->getScheduler()->scheduleRepeatingTask(new GameTask(), 20);
        EntityFactory::getInstance()->register(HookEntity::class, function(World $world, CompoundTag $nbt): HookEntity{
            return new HookEntity(EntityDataHelper::parseLocation($nbt, $world), null,  $nbt);
        }, ["largage", "minecraft:event_largage"]);
    }

    protected function onDisable(): void
    {
        foreach (GameManager::$game as $gameUUID => $game){
            GameManager::deleteGame($gameUUID);
        }
    }

    public function convertTime(int $time){
        $timer = $time - time();
        $day = floor($timer / 86400);
        $hourSeconds = $timer % 86400;
        $hour = floor($hourSeconds / 3600);
        $minuteSec = $hourSeconds % 3600;
        $minute = floor($minuteSec / 60);
        $remainingSec = $minuteSec % 60;
        $second = ceil($remainingSec);
        return [
            "day" => $day,
            "hours" => $hour,
            "minuts" => $minute,
            "seconds" => $second
        ];
    }
}