<?php

declare(strict_types=1);

namespace API\event;

use API\Loader;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\event\Listener;

use pocketmine\math\Vector3;

use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Entity;

use pocketmine\utils\Config;
use pocketmine\utils\UUID;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;

use pocketmine\item\Item;
use pocketmine\item\ItemFactory;

use pocketmine\scheduler\CallbackTask;

use pocketmine\event\player\PlayerInteractEvent;

use pocketmine\level\particle\FloatingTextParticle;

use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\level\particle\FlameParticle;

use pocketmine\level\sound\ButtonClickSound;
use pocketmine\level\sound\ExpPickupSound;
use pocketmine\level\sound\BlazeShootSound;

use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\network\mcpe\convert\TypeConverter;

class BonusCases implements Listener {

    /** @var Loader */
    private $loader;
    
    /** @var array */
    private $bonusitem = [];

    /** @var array */
    private $bonusmoney = [];

    /** @var array */
    private $bonuseffect = [];

    /** @var array */
    private $bonusset = [];

    private $effects = [
        Effect::SPEED,
        Effect::STRENGTH,
        Effect::REGENERATION,
        Effect::INVISIBILITY,
        Effect::JUMP,
        Effect::FIRE_RESISTANCE,
        Effect::WATER_BREATHING,
        Effect::NIGHT_VISION,
        Effect::HEALTH_BOOST,
        Effect::ABSORPTION
    ];

    public function __construct(Loader $loader) {
        $this->loader = $loader;
    } 

    public function fakeOpen(Player $player, Vector3 $position, bool $value = true)
    {
        $viewers = [];
        $pk = BlockEventPacket::create(
            (int) floor($position->getX()),
            (int) floor($position->getY()),
            (int) floor($position->getZ()),
            BlockEventPacket::TYPE_CHEST,
            $value ? BlockEventPacket::DATA_CHEST_OPEN : BlockEventPacket::DATA_CHEST_CLOSED
        );
        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if ($players->distance($position) >= 35) {
                continue;
            }
            $viewers[] = $players;
            $players->dataPacket($pk);
        }

        if ($value == true) {
            $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "fakeOpen"), array($player, $position, false)), 20 * 5);
        }
    }

    public function createFakeItem(Player $player, Vector3 $position, Item $item, $x = 0.5, $y = 1.1, $z = 0.5){
        $viewers = [];
        $fakeItemId = Entity::$entityCount++;
        $itemPos = new Vector3($position->getX() + $x, $position->getY() + $y, $position->getZ() + $z);

        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if ($players->distance($position) >= 35) {
                continue;
            }
            $viewers[] = $players;
            $pk = AddItemActorPacket::create(
                $fakeItemId,
                $fakeItemId,
                ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet($item, $players->getProtocolVersion())),
                $itemPos,
                new Vector3(0, 0, 0),
                [],
                false
            );
            $players->dataPacket($pk);
        }
        $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "clearEntityPacket"), array($viewers, $fakeItemId)), 20 * 5);
    }

    public function addFloatingText(Player $player, Vector3 $position, $floatingText){
        $viewers = [];
        $floatEid = Entity::$entityCount++;
        $packet = new AddActorPacket();
        $packet->entityUniqueId = $floatEid;
        $packet->entityRuntimeId = $floatEid;
        $packet->type = EntityIds::NPC;
        $packet->position = new Vector3($position->getX() + 0.5, $position->getY() + 1.2, $position->getZ() + 0.5);
        $packet->motion = new Vector3(0, 0, 0);
        $packet->pitch = 0.0;
        $packet->yaw = 0.0;
        $packet->headYaw = 0.0;
        $packet->bodyYaw = 0.0;
        $packet->attributes = [];
        $flags = (
            (1 << Entity::DATA_FLAG_CAN_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_ALWAYS_SHOW_NAMETAG) |
            (1 << Entity::DATA_FLAG_IMMOBILE) |
            (1 << Entity::DATA_FLAG_SILENT)
        );
        $packet->metadata = [
            Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, $flags],
            Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $floatingText],
            Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01], //zero causes problems on debug builds
            Entity::DATA_BOUNDING_BOX_WIDTH => [Entity::DATA_TYPE_FLOAT, 0.0],
            Entity::DATA_BOUNDING_BOX_HEIGHT => [Entity::DATA_TYPE_FLOAT, 0.0]
        ];
        $packet->syncedProperties = new PropertySyncData([], []);
        $packet->links = [];

        foreach (Server::getInstance()->getOnlinePlayers() as $players) {
            if ($players->distance($position) >= 35) {
                continue;
            }
            $viewers[] = $players;
            $players->dataPacket($packet);
        }
        $this->loader->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "clearEntityPacket"), array($viewers, $floatEid)), 20 * 5);
    }

    public function clearEntityPacket(array $players, $eid){
        foreach ($players as $player) {
            if ($player->isOnline()) {
                $packet = new RemoveActorPacket();
                $packet->entityUniqueId = $eid;
                $player->dataPacket($packet);
            }
        }
    }

    public function giveRandomEffect(Player $player) {
        $effectId = $this->effects[array_rand($this->effects)];
        $effect = new EffectInstance(Effect::getEffect($effectId), 1200, 1); 
        $player->addEffect($effect);
        $player->addTitle("§l§dБОНУС§7", "§rВы получили §dэффект§r!");
    }

    public function PlayerChatEvent(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $bid = $block->getId();
        $m = number_format(Loader::getInstance()->getMoney($player));
        $freemoney = mt_rand(1000, 3000);

        if($block->x === 5 && $block->y === 39 && $block->z === -7){
            if ($player->getGamemode() !== 0) {
                $player->sendMessage("§l§aБонус§7-§aКейс §8:: §rДля взятия §aбонуса§r нужно выключить§a креатив§r!");
                return false;
            }

            $vector = new Vector3($block->getX(), $block->getY(), $block->getZ());

            if (!isset($this->bonusmoney[$player->getName()])) {
                $this->bonusmoney[$player->getName()] = 1;

                $player->addTitle("§l§aБОНУС§7", "§rВы получили §a+". number_format($freemoney) ."§2$");
                Loader::getInstance()->addMoney($player, $freemoney);
                $this->fakeOpen($player, $vector, true);
                $this->addFloatingText($player, $vector, "§rВы получили§7: §a+". number_format($freemoney) ."§2$");
                $item = Item::get(265, 0, 1);
                $this->createFakeItem($player, $vector, $item, 0.5, 2.3, 0.9);
            } else {
                $player->getLevel()->addSound(new \pocketmine\level\sound\AnvilFallSound($player), [$player]);
                $player->sendMessage("§l§aБонус§7-§aКейс §8:: §rВы уже взяли этот §aбонус§r попробуйте позже!");
            }
        }

        if($block->x === 2 && $block->y === 39 && $block->z === -5){
            if ($player->getGamemode() !== 0) {
                $player->sendMessage("§l§dБонус§7-§dКейс §8:: §rДля взятия §dбонуса§r нужно выключить§d креатив§r!");
                return false;
            }

            $vector = new Vector3($block->getX(), $block->getY(), $block->getZ());

            if (!isset($this->bonuseffect[$player->getName()])) {
                $this->bonuseffect[$player->getName()] = 1;

                $player->addTitle("§l§dБОНУС§7", "§rВы получили §dэффекты§r!");
                $this->fakeOpen($player, $vector, true);
                $this->addFloatingText($player, $vector, "§rВы получили§7: §dРандомный эффект");
                $item = Item::get(374, 0, 1);
                $this->createFakeItem($player, $vector, $item, 0.5, 2.3, 0.9);
                $this->giveRandomEffect($player);
            } else {
                $player->getLevel()->addSound(new \pocketmine\level\sound\AnvilFallSound($player), [$player]);
                $player->sendMessage("§l§dБонус§7-§dКейс §8:: §rВы уже взяли этот §dбонус§r попробуйте позже!");
            }
        }

        if($block->x === 2 && $block->y === 39 && $block->z === -21){
            if ($player->getGamemode() !== 0) {
                $player->sendMessage("§l§bБонус§7-§bКейс §8:: §rДля взятия §bбонуса§r нужно выключить§b креатив§r!");
                return false;
            }

            if (!isset($this->bonusset[$player->getName()])) {
                $this->bonusset[$player->getName()] = 1;

                $armorSets = [
                    [Item::DIAMOND_HELMET, Item::DIAMOND_CHESTPLATE, Item::DIAMOND_LEGGINGS, Item::DIAMOND_BOOTS],
                    [Item::IRON_HELMET, Item::IRON_CHESTPLATE, Item::IRON_LEGGINGS, Item::IRON_BOOTS],
                    [Item::GOLD_HELMET, Item::GOLD_CHESTPLATE, Item::GOLD_LEGGINGS, Item::GOLD_BOOTS],
                ];

                $selectedArmorSet = $armorSets[array_rand($armorSets)];

                $randomEnchantmentLevel = mt_rand(1, 2);

                foreach ($selectedArmorSet as $armorType) {
                    $armor = ItemFactory::get($armorType);
                    $player->getInventory()->addItem($armor);
                 }

                 $swords = [
                    Item::DIAMOND_SWORD,
                    Item::IRON_SWORD,
                    Item::GOLD_SWORD,
                    Item::STONE_SWORD,
                    Item::WOODEN_SWORD
                ];

                $selectedSword = $swords[array_rand($swords)];
                $sword = ItemFactory::get($selectedSword);
                $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS), mt_rand(1, 5)));
                $player->getInventory()->addItem($sword);

                $player->addTitle("§l§bБОНУС§7", "§rВы получили §bБесплатный рандомный сет§r!");
            } else {
                $player->getLevel()->addSound(new \pocketmine\level\sound\AnvilFallSound($player), [$player]);
                $player->sendMessage("§l§bБонус§7-§bКейс §8:: §rВы уже взяли этот §bбонус§r, попробуйте позже!");
            }
        }

        if($block->x === 5 && $block->y === 39 && $block->z === -19){
            if ($player->getGamemode() !== 0) {
                $player->sendMessage("§l§cБонус§7-§cКейс §8:: §rДля взятия §cбонуса§r нужно выключить§c креатив§r!");
                return false;
            }

            if (!isset($this->bonusitem[$player->getName()])) {
                $this->bonusitem[$player->getName()] = 1;

                $itemIds = [
            450,
            322,
            466, 
            276, 
            278, 
            310, 
            311, 
            312, 
            313, 
            264, 
            266, 
            265, 
            357,
            360  
        ];

                $randomIndex = array_rand($itemIds);
                $itemId = $itemIds[$randomIndex];
                $player->getInventory()->addItem(\pocketmine\item\Item::get($itemId, 0, 1));
                $vector = new Vector3($block->getX(), $block->getY(), $block->getZ());

                $player->addTitle("§l§cБОНУС§7", "§rВы получили §cпредмет§r!");
                $this->fakeOpen($player, $vector, true);
                $this->addFloatingText($player, $vector, "§rВы получили§7: §bПредмет");
                $item = Item::get(450, 0, 1);
                $this->createFakeItem($player, $vector, $item, 0.5, 2.3, 0.9);
            } else {
                $player->getLevel()->addSound(new \pocketmine\level\sound\AnvilFallSound($player), [$player]);
                $player->sendMessage("§l§cБонус§7-§cКейс §8:: §rВы уже взяли этот §cбонус§r попробуйте позже!");
            }
        }
    }
}