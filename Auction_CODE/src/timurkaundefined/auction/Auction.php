<?php

declare(strict_types=1);

namespace timurkaundefined\auction;

use _64FF00\PurePerms\data\UserDataManager;
use _64FF00\PurePerms\PurePerms;
use Lambo\CombatLogger\Main;
use api\Loader;
use pocketmine\block\Block;
use pocketmine\block\BlockIds;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\math\Facing;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\mcpe\protocol\UpdateBlockPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use timurkaundefined\auction\utils\Helper;
use timurkaundefined\auction\utils\inventory\PersonalDoubleInventory;
use timurkaundefined\auction\utils\ItemNamesConverter;
use timurkaundefined\auction\utils\tile\VirtualChest;

use function array_chunk;
use function array_filter;
use function array_key_last;
use function array_map;
use function array_shift;
use function class_exists;
use function count;
use function explode;
use function in_array;
use function is_dir;
use function max;
use function mkdir;
use function number_format;
use function rand;
use function strtolower;

class Auction extends PluginBase{

	public const NPC_NAMETAG = '       §r §a§lＡＵＣＴＩＯＮ§r ' . "\n" . '§r⨞ §fНажми на NPC для просмотра! §r⨟';
	public const AUTODONATE_SITE = '§asʜᴏᴘ.ᴏɴᴇᴡɪx-ᴘᴇ.ꜰᴜɴ§r';
	public const EVRMAKOV = 'NEW AUCTION!'. "\n". 'By Evrmakov';

	public const AUCTION_SIZE = 300;

	public const MIN_PRICE = 1;
	public const MAX_PRICE = 10000000;

	/** запрещённые к продаже предметы */
	public const RESTRICTED_ITEM_IDS = [
		BlockIds::COMMAND_BLOCK, BlockIds::REPEATING_COMMAND_BLOCK, BlockIds::CHAIN_COMMAND_BLOCK, BlockIds::BEDROCK
	];

	/** @var Auction */
	private static $instance;

	public static function getInstance() : Auction{
		return self::$instance;
	}

	/** @var UserDataManager */
	private $purePerms;

	/** @var Config */
	private $data, $invalidated_items;

	/** @var int */
	private $ticksFromSave = 0;

	/** @var bool */
	private $hasChanged = false;

	/** @var array */
	private $viewers = [], $availableSlots = [], $cachedPages = [];
	
	/** @var array */
	private $page = [];

	public function onEnable(){
		foreach(['\API\Loader', '\API\Loader'] as $class){
			if(!class_exists($class, false)){
				$nicePluginName = explode('\\', $class)[array_key_last(explode('\\', $class))];
				$this->getLogger()->critical("§7► §aНе найден плагин " . $nicePluginName . ". Выключение...");
				Server::getInstance()->getPluginManager()->disablePlugin($this);
				return;
			}
		}

		$this->initAvailableSlots();

		/** @var PurePerms $purePerms */
		$purePerms = Server::getInstance()->getPluginManager()->getPlugin('DeadAPI');

		self::$instance = $this;

		if(!is_dir($dataFolder = $this->getDataFolder())){
			mkdir($dataFolder, 0777, true);
		}
		$this->data = new Config($dataFolder . 'data.json', Config::JSON);
		$this->invalidated_items = new Config($dataFolder . 'invalidated_items.json', Config::JSON);

		Tile::registerTile(VirtualChest::class);

		Server::getInstance()->getPluginManager()->registerEvents(new EventHandler(), $this);
		$this->getScheduler()->scheduleRepeatingTask(new CallbackTask(array($this, "onUpdate")),  20 * 8);
		$this->refreshPages();
	}


	public function onUpdate() {
		if(++$this->ticksFromSave === 30){
			$this->doSave();
			$this->ticksFromSave = 0;
		}
		$this->refreshPages();
	}

	public function refreshPages() : void{
		$data = $this->data->getAll();
		$countBefore = count($data);

		$this->invalidateExpiredItems();

		$data = $this->data->getAll();
		$countAfter = count($data);

		if($countBefore !== $countAfter){
			$this->hasChanged = true;
		}

		$monetaryUnit = "⩐";
		$currentTime = Helper::breakTime();

		$data = array_map(function(array $data) use ($monetaryUnit, $currentTime) : Item{
			$item = Helper::deserializeItem($data['item']);

			if(!$item->hasCustomName()){
				$itemName = $item->hasEnchantments() ? "§r§a§l" : "§r §f§l";
				$itemName .= ItemNamesConverter::convertName($item);
			}else{
				$itemName = "§r" . $item->getCustomName();
			}

			$itemName .= "§r §7x" . $item->getCount();

			$player = Server::getInstance()->getPlayer($data['player']);
			$sellerStatus = $player !== null && $player->isOnline() ? "§r" : "§r";

			$item->setCustomName(
				$itemName . "§r\n\n" .
				"§r Цена: §a" . Helper::toPrettyNumber(number_format($data['price'])) . "§2" . $monetaryUnit . "\n" .
				"§r Осталось времени: §a". Helper::convertTimeOptimized($data['exp'], $currentTime) . "§r\n" .
				"§r Продавец: §a" . $data['playerWithCase'] . "§r ". $sellerStatus ."§r\n"
			);

			$nbt = $item->getNamedTag();
			$nbt->setTag(new StringTag('owner', $data['player']));
			$nbt->setTag(new IntTag('price', $data['price']));
			$nbt->setTag(new IntTag('expirationDate', $data['exp']));
			$nbt->setTag(new IntTag('uniqueKey', $data['key']));

			return $item->setNamedTag($nbt);
		}, $data);

		if(empty($this->availableSlots)){
			$this->initAvailableSlots();
		}
		if(empty($data)){
			$this->cachedPages = [];
		}else{
			$this->cachedPages = array_chunk($data, count($this->availableSlots), true);
		}
		if($this->hasChanged and count($this->viewers) > 0){
			$this->showChangesToViewers();
		}
		$this->hasChanged = false;
	}

	public function hasItemInvalidated(int $uniqueKey) : bool{
		return !$this->data->exists($uniqueKey);
	}

	public function showChangesToViewers() : void{
		$players = [];
		foreach(Server::getInstance()->getOnlinePlayers() as $p){
			if($p->isOnline() and isset($this->viewers[$p->getName()])){
				$players[] = [
					$p,
					$this->viewers[$p->getName()][0][0],
					$this->viewers[$p->getName()][self::TYPE_CURRENT_PAGE]
				];
			}
		}
		foreach($players as [$playerInstance, $chest, $page]){
			if(!$chest instanceof VirtualChest or $chest->closed){
				continue;
			}
			if(!$chest->getInventory() instanceof PersonalDoubleInventory){
				continue;
			}
			$page = isset($this->cachedPages[$page]) ? 0 : -1;
			$this->openPage($playerInstance, $chest->getInventory(), $page);
		}
	}

	public function isViewingAuction(Player $player) : bool{
		if(isset($this->viewers[$player->getName()])){
			if($player->distance($this->viewers[$player->getName()][3]) > 5.0){//duct tape
				unset($this->viewers[$player->getName()]);
				return false;
			}
			return true;
		}
		return false;
	}

	public function upForAuction(Player $player, Item $item, int $price) : void{
		$player->getInventory()->clear($player->getInventory()->getHeldItemSlot());

		$data = $this->data->getAll();

		$generateUniqueKey = function() use ($data) : int{
			while(!isset($key) or isset($data[$key])){
				$key = rand(0, 0xfff);
			}
			return $key;
		};

		$data[$uniqueKey = $generateUniqueKey()] = [
			'player' => $player->getLowerCaseName(),
			'playerWithCase' => $player->getDisplayName(),
			'item' => Helper::serializeItem($item),
			'price' => $price,
			'exp' => Helper::breakTime() + 60 * 60 * 48,
			'key' => $uniqueKey
		];

		$this->data->setAll($data);

		$this->hasChanged = true;
		$this->refreshPages();

		$rusItemName = ItemNamesConverter::convertName($item);
		$prettyPrice = Helper::toPrettyNumber(number_format($price));
		$count = $item->getCount();
		$monetaryUnit = "$";
		$player->sendMessage(
			"§r §rНа аукцион выставлен предмет §a" . $rusItemName . " §r§7x" . $count . " §rпо цене §a". $prettyPrice . "§2" . $monetaryUnit
		);
		$player->sendMessage("§r §rЕсли предмет не купят в течение §a48 ч§r, ты сможешь его вернуть по команде §a/auc back!");
	}

	public function pullFromTheAuction(Player $player, int $uniqueKey, bool $causePurchased = false) : void{
		$data = $this->data->getAll();
		if(!isset($data[$uniqueKey])){
			if($causePurchased){
				$player->sendMessage("§l§6➛ §rПредмет невозможно приобрести!");
			}else{
				$player->sendMessage("§l§6➛ §rПредмет был снят с продажи!");
			}
			return;
		}

		$remains = count($data) % count($this->availableSlots);
		$remains -= 1;

		$item = Helper::deserializeItem($data[$uniqueKey]['item']);
		if(!$player->getInventory()->canAddItem($item)){
			$player->sendMessage("§l§6➛ §rВ твоём инвентаре §aнет места!");
			return;
		}
		$what = ItemNamesConverter::convertName($item) . " §7x" . $item->getCount() . "§r";

		$p = $player->getName();

		if($causePurchased){
			Loader::getInstance()->remMoneyy($p, $price = $data[$uniqueKey]['price']);
			$ownerName = $data[$uniqueKey]['player'];
			Loader::getInstance()->addMoneyy($ownerName, $price);

			$prettyPrice = Helper::toPrettyNumber(number_format($price));
			$monetaryUnit = "$";
			$player->sendMessage("§l§6➛ §rТы купил(а) на аукционе §a" . $what . " §rза §a" . $prettyPrice . "§2" . $monetaryUnit);
			$player->sendTitle("§l§aПОКУПКА§r", "§a- §3" . $prettyPrice . " §a" . $monetaryUnit, 20, 70, 20);

			$owner = Server::getInstance()->getPlayerExact($ownerName);
			if($owner instanceof Player and $owner->isOnline()){
				$owner->sendMessage("\n§l§6➛ §rВаш предмет §a$what §eкупили на аукционе за §a" . $prettyPrice . "§2" . $monetaryUnit);
				$owner->sendTitle("§l§aАУКЦИОН§r", "§a+ §a" . $prettyPrice . " §2" . $monetaryUnit, 20, 70, 20);
			}
		}else{
			$player->sendMessage("§l§6➛ §rС продажи снят предмет §a". $what);
		}

		$player->getInventory()->addItem($item);

		unset($data[$uniqueKey]);
		$this->data->setAll($data);
		$this->hasChanged = true;

		if($remains === 0){
			$this->refreshPages();
			$this->showChangesToViewers();
		}
	}

	public function openPage(Player $player, PersonalDoubleInventory $inventory, int $pageDirection, $category = null) : void{
		if(!$this->isViewingAuction($player)){
			return;
		}

		for($i = 0; $i < 54; ++$i){
			$inventory->setItem($i, Item::get(BlockIds::GLASS_PANE));
		}

		if(empty($this->cachedPages)){
			$item = Item::get(BlockIds::STAINED_CLAY, 14)->setCustomName(
				"§r §c§lПУСТО §r\n\n" .
				"§fСейчас никто ничего не продаёт!\n\n" .
				"§fПродать предметы: §a/auc sell"
			);
			$this->fillWindowSlot($inventory, 22, $item);
			return;
		}

		$this->page[$player->getName()] = $category;

		$page = $this->viewers[$player->getName()][self::TYPE_CURRENT_PAGE] + $pageDirection;
		if(!isset($this->cachedPages[$page]) || $page < 0){
			if($pageDirection < 0){
				$page = max(0, count($this->cachedPages) - 1);
			}else{
				$page = 0;
			}
		}
		if(!isset($this->cachedPages[$page])){
			return;
		}
		$this->viewers[$player->getName()][self::TYPE_CURRENT_PAGE] = $page;

		$data = $this->data->getAll();
		$prettyMoney = Helper::toPrettyNumber(number_format($money = Loader::getInstance()->getMoney($player)));
		$monetaryUnit = "$";
		/*if(mb_strlen($monetaryUnit) > 1){
			$monetaryUnit = " ". $monetaryUnit;
		}*/

		$num = 0;

		$categories = [
			'armor' => [298, 299, 300, 301, 302, 303, 304, 305, 306, 307, 308, 309, 310, 311, 312, 313, 314, 315, 316, 317],
			'tools' => [276, 277, 278, 279, 293, 256, 257, 258, 267, 268, 269, 270, 271, 272, 273, 274, 275, 261, 283, 284, 285, 286, 290, 291, 292, 294],
			'blocks' => [1, 2, 3, 4, 5, 14, 15, 16, 17, 24, 29, 33, 35, 41, 42, 43, 44, 47, 49, 54, 56, 57, 80, 86, 87, 97, 98, 103, 116, 121, 130, 133, 145, 155, 170, 179, 181, 200, 206, 218, 219, 220, 221, 222, 223, 224, 225, 226, 227, 228, 229, 231, 232, 233, 234, 235, 236, 237, 241, 355],
			'other' => [368, 466, 322, 397, 403, 351, 450, 369, 370, 371, 372, 375, 376, 377, 378, 432, 262, 399, 384],
			'foods' => [364, 365, 366, 367, 320, 349, 393, 394, 395, 396],
			'potions' => [373, 374, 438, 439, 440, 441, 442, 443]
];


		/** @var Item $item */
		foreach($this->cachedPages[$page] as $uniqueKey => $item){
			// Get uniqueKey from item NBT if not available from array key
			$itemNbt = $item->getNamedTag();
			if(!isset($data[$uniqueKey])){
				if($itemNbt->hasTag("uniqueKey")){
					$uniqueKey = $itemNbt->getTag("uniqueKey")->getValue();
				}
			}
			// If still not found in data, try to get price from NBT
			$itemPrice = null;
			if(isset($data[$uniqueKey])){
				$itemPrice = $data[$uniqueKey]['price'];
			}elseif($itemNbt->hasTag("price")){
				$itemPrice = $itemNbt->getTag("price")->getValue();
			}else{
				continue; // Skip if no price data available
			}

			if($category !== null) {
				if($this->getServer()->getPlayer($category) instanceof Player) {
					if(!strpos($item->getCustomName(), $category)) {
						continue;
					} 

				} else {
					if(!in_array($item->getId(), $categories[$category])) {
						continue;
					} 
				}
			}

			$item = clone $item;
			$nbt = $item->getNamedTag();
			if(!$nbt->hasTag("owner") || $nbt->getTag("owner")->getValue() !== $player->getLowerCaseName()){
				$customName = $item->getCustomName();
				$customName .= "\n\n§r§rТвои деньги: §a" . $prettyMoney . " §2" . $monetaryUnit;
				if($money < $itemPrice){
					$customName .= "\n\n§c §rДля покупки недостаточно денег";
				}else{
					$customName .= "\n§a §rНажми два раза для §a§lпокупки§r\n!";
				}
			}else{
				$customName = $item->getCustomName() . "\n\n           §7(Твой предмет)\n§f Нажми дважды для §a§lснятия§r §fс продажи";
			}
			if($item->hasEnchantments()){
				$customName .= "\n ";
			}
			if(isset($this->availableSlots[$num])){
				$this->fillWindowSlot($inventory, $this->availableSlots[$num++], $item->setCustomName($customName));
			}
		}

		if(count($this->cachedPages) > 1){
			$previousPage = Item::get(ItemIds::ARROW, 0, 1)->setCustomName(
				"§r§l§r⨟ §rПредыдущая страница§r"
			);
			$nbt = $previousPage->getNamedTag();
			$nbt->setTag(new ByteTag('page', -1));
			$this->fillWindowSlot($inventory, 45, $previousPage->setNamedTag($nbt));

			$nextPage = Item::get(ItemIds::ARROW, 0, 1)->setCustomName(
				"§rСледующая страница §l§r⨞§r"
			);
			$nbt = $nextPage->getNamedTag();
			$nbt->setTag(new ByteTag('page', 1));
			$this->fillWindowSlot($inventory, 53, $nextPage->setNamedTag($nbt));
		}

		$availableSlots = $this->getAvailableSlots($player);
		$totalItems = count($this->data->getAll());
		$item = Item::get(ItemIds::BOOK, 0, 1)->setCustomName(
			"§r §lИнформация об аукционе §r\n\n" .
			"§fДоступно слотов для продажи: §a" . $availableSlots . "\n" .
			"§fВсего предметов на аукционе: §a" . $totalItems

		);
		$this->fillWindowSlot($inventory, 47, $item);

		$categories = Item::get(ItemIds::DIAMOND_PICKAXE, 0, 1)->setCustomName("§r§a§lКатегории\n§r§rНажмите чтобы выбрать категорию предметов!");

		$this->fillWindowSlot($inventory, 51, $categories);

		$currentPage = Helper::toPrettyNumber($page + 1);
		$maxPage = Helper::toPrettyNumber(count($this->cachedPages));
		$info = Item::get(ItemIds::CHEST, 0, 1)->setCustomName(
			"§r§l§7➥ §rТы на странице §a" . $currentPage . "§r §rиз §a" . $maxPage . "§r" .
			" §l§7"
		);

		$this->fillWindowSlot($inventory, 49, $info);
		
		// Отправляем содержимое инвентаря клиенту
		if($player instanceof Player and $player->isOnline()){
			$inventory->sendContents($player);
		}
	}

	public function openCategories(Player $player, PersonalDoubleInventory $inventory) : void{
		if(!$this->isViewingAuction($player)){
			return;
		}

		for($i = 0; $i < 54; ++$i){
			$inventory->setItem($i, Item::get(BlockIds::GLASS_PANE));
		}

		$armor = Item::get(311, 0, 1)->setCustomName("§r§7Категория: §bБроня§r");
		$tools = Item::get(276, 0, 1)->setCustomName("§r§7Категория: §cИнструменты§r");
		$blocks = Item::get(1, 0, 1)->setCustomName("§r§7Категория: §aБлоки§r");
		$other = Item::get(403, 0, 1)->setCustomName("§r§7Категория: §dДругое§r");
		$allItems = Item::get(54, 0, 1)->setCustomName("§r§7Категория: §eВсе предметы§r");

		$foods = Item::get(364, 0, 1)->setCustomName("§r§7Категория: §aЕда§r"); // Еда
		$potions = Item::get(373, 0, 1)->setCustomName("§r§7Категория: §9Зелья§r"); // Зелья

		$this->fillWindowSlot($inventory, 10, $armor);
		$this->fillWindowSlot($inventory, 13, $tools);
		$this->fillWindowSlot($inventory, 16, $blocks);
		$this->fillWindowSlot($inventory, 38, $other);
		$this->fillWindowSlot($inventory, 42, $allItems);
		$this->fillWindowSlot($inventory, 30, $foods);
		$this->fillWindowSlot($inventory, 32, $potions);
	}


	public function open(Player $player){
		if(!$player->isValid()){
			return;
		}
		if($this->isViewingAuction($player)){
			return;
		}

		

		// Размещаем сундуки на 1 блок ниже (вместо 3) и перед игроком
		$level = $player->getLevel();
		$direction = $player->getDirection();
		$basePos = $player->floor()->subtract(0, 1, 0);
		
		// Определяем позицию перед игроком в зависимости от направления
		switch($direction){
			case 0: // South
				$vector3 = $basePos->add(0, 0, 1);
				break;
			case 1: // West
				$vector3 = $basePos->add(-1, 0, 0);
				break;
			case 2: // North
				$vector3 = $basePos->add(0, 0, -1);
				break;
			case 3: // East
				$vector3 = $basePos->add(1, 0, 0);
				break;
			default:
				$vector3 = $basePos->add(0, 0, 1); // По умолчанию перед игроком (South)
		}
		
		$blockReplaced = $level->getBlock($vector3);
		$blockReplaced2 = $level->getBlock($pairVector3 = $vector3->getSide(Facing::WEST));

		$this->updateBlockImmediately($player, Block::get(BlockIds::CHEST, 2, Position::fromObject($vector3)));
		$this->updateBlockImmediately($player, Block::get(BlockIds::CHEST, 2, Position::fromObject($pairVector3)));

		/** @var VirtualChest $chest */
		$totalItems = count($this->data->getAll());
		$itemWord = $this->getItemWord($totalItems);
		$chest = Tile::createTile(
			'VirtualChest',
			$level,
			Helper::createTileNBT('Chest', "§r §a§lАУКЦИОН §8:: §rНа аукционе §a{$totalItems}§r {$itemWord}!", $vector3, $pairVector3),
			$playerName = $player->getName()
		);
		/** @var VirtualChest $chest2 */
		$chest2 = Tile::createTile(
			'VirtualChest',
			$level,
			Helper::createTileNBT('Chest', "§r §a§lАУКЦИОН §8:: §rНа аукционе §a{$totalItems}§r {$itemWord}!", $pairVector3, $vector3),
			$playerName
		);

		$inventory = new PersonalDoubleInventory($chest, $chest2, $playerName);

		$chest->setDoubleInventory($inventory);
		$chest2->setDoubleInventory($inventory);

		$chest->setShouldBeSpawned();
		$chest2->setShouldBeSpawned();

		$chest->spawnTo($player);
		$chest2->spawnTo($player);

		$this->viewers[$playerName] = [[$chest, $chest2], [$blockReplaced, $blockReplaced2], self::TYPE_CURRENT_PAGE => 0,
			$player->floor()];

		$this->refreshPages();
		$this->openPage($player, $inventory, 0);

		$this->getScheduler()->scheduleDelayedTask(new CallbackTask (array($this, "owin"), array($inventory, $player)), 5); 
	}
public function owin($inventory, $player) {
    $this->openWindow($inventory, $player, $player->getName());
}
	public function slotsLimited(Player $player) : bool{
		if($player->isOp()){
			return false;

		}

		$currentTime = Helper::breakTime();
        $groupName = Loader::getInstance()->getGroup($player->getName());
        $availableSlots = [
        	'Partner' => 60,
        	'Moderator' => 60,
        	'Supprot' => 60,
        	'Helper' => 60,
        	'Imperator' => 60,
        	'Prince' => 55,
        	'Korol' => 45,
        	'Gertsog' => 35,
        	'Markiz' => 25,
        	'Vikont' => 15,
            'Knight' => 10,
            'User' => 10,
        ][$groupName] ?? 10;

        return count(array_filter($this->data->getAll(), function(array $data) use ($player, $currentTime) : bool{
            return $data['player'] === $player->getLowerCaseName() and $data['exp'] > $currentTime;
        })) >= $availableSlots;
    }

    public function getAvailableSlots(Player $player) : int{
    if($player->isOp()){
        return 100; // or any other value for ops
    }

    $groupName = Loader::getInstance()->getGroup($player->getName());
    $availableSlots = [
        'Partner' => 60,
        'Moderator' => 60,
        'Supprot' => 60,
        'Helper' => 60,
        'Imperator' => 60,
        'Prince' => 55,
        'Korol' => 45,
        'Gertsog' => 35,
        'Markiz' => 25,
        'Vikont' => 15,
        'Knight' => 10,
        'User' => 10,
    ][$groupName] ?? 10;

    $currentTime = Helper::breakTime();
    $usedSlots = count(array_filter($this->data->getAll(), function(array $data) use ($player, $currentTime) : bool{
        return $data['player'] === $player->getLowerCaseName() and $data['exp'] > $currentTime;
    }));

    return $availableSlots - $usedSlots;
}


	public function noFreeSpace() : bool{
		$currentTime = Helper::breakTime();
		return count(array_filter($this->data->getAll(), function(array $data) use ($currentTime) : bool{
				return $data['exp'] > $currentTime;
			})) >= self::AUCTION_SIZE;
	}

	/** @noinspection PhpUnused */
	public function openWindow(PersonalDoubleInventory $inventory, ?Player $player, string $playerName) : void{
		if($player instanceof Player and $player->isOnline()){
			// Проверяем, что инвентарь все еще валиден перед вызовом getHolder()
			try {
				$holder = $inventory->getHolder();
				if($holder !== null){
					$windowId = $player->addWindow($inventory);
					if($windowId !== -1){
						// Отправляем содержимое с небольшой задержкой после открытия окна
						$this->getScheduler()->scheduleDelayedTask(new CallbackTask(function() use ($inventory, $player) : void {
							if($player instanceof Player and $player->isOnline() and $this->isViewingAuction($player)){
								// Отправляем каждый слот отдельно, так как getContents возвращает пустой массив
								for($i = 0; $i < 54; $i++){
									$item = $inventory->getItem($i);
									if(!$item->isNull()){
										$inventory->sendSlot($i, $player);
									}
								}
							}
						}), 2);
					}
					return;
				}
			} catch (\Throwable $e) {
				// Инвентарь был закрыт или удален - просто выходим без ошибки
				if(isset($this->viewers[$playerName])){
					unset($this->viewers[$playerName]);
				}
				return;
			}
		}
		if(isset($this->viewers[$playerName])){
			unset($this->viewers[$playerName]);
		}
	}

	public function fillWindowSlot(PersonalDoubleInventory $inventory, int $slot, Item $item) : void{
		$nbt = $item->getNamedTag() ?? new CompoundTag();
		$nbt->setTag(new ByteTag('auctionWindowItem', 1));
		// Removed dynamic property assignment - Item class doesn't have a 'block' property
		$inventory->setItem($slot, $item->setNamedTag($nbt));
	}

	private function updateBlockImmediately(Player $recipient, Block $block){
		$pk = new UpdateBlockPacket();
		$pk->blockId = BlockIds::CHEST;
		$pk->blockMeta = 0;
		$pk->x = $block->x;
		$pk->z = $block->z;
		$pk->y = $block->y;
		$pk->flags = UpdateBlockPacket::FLAG_ALL;
		$recipient->dataPacket($pk);
	}

	public function addToDelayedClose(Player $player){
		if(!$this->isViewingAuction($player)){
			return;
		}
		// Проверяем, что плагин все еще включен и сервер не выключается
		if(!$this->isEnabled() or $this->getServer()->isRunning() === false){
			// Если плагин отключен или сервер выключается, сразу удаляем блоки
			$this->removeBlockAndTile($player);
			return;
		}
		// Увеличиваем задержку до 40 тиков (2 секунды), чтобы дать время окну открыться
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask(function() use ($player) : void {
			if($player instanceof Player and $player->isOnline() and $this->isViewingAuction($player)){
				$playerName = $player->getName();
				if(isset($this->viewers[$playerName])){
					$chest = $this->viewers[$playerName][0][0];
					if($chest instanceof \timurkaundefined\auction\utils\tile\VirtualChest){
						$inventory = $chest->getInventory();
						if($inventory instanceof PersonalDoubleInventory){
							// Проверяем, открыто ли окно у игрока
							$windowId = $player->getWindowId($inventory);
							if($windowId === -1){
								// Окно закрыто, можно удалять блоки
								$this->removeBlockAndTile($player);
							}
						}
					}
				} else {
					$this->removeBlockAndTile($player);
				}
			} else {
				$this->removeBlockAndTile($player);
			}
		}), 40); 
	}

	/** @noinspection PhpUnused */
	public function removeBlockAndTile(Player $player = null){
		if(!$player instanceof Player or !$player->isOnline()){
			return;
		}

		if(!isset($this->viewers[$playerName = $player->getName()])){
			return;
		}
		$tiles = $this->viewers[$playerName][0];
		$blocksReplaced = $this->viewers[$playerName][1];
		if($player->isValid()){
			$player->getLevel()->sendBlocks([$player], $blocksReplaced, UpdateBlockPacket::FLAG_ALL_PRIORITY);
		}
		foreach($tiles as $tile){
			if($tile instanceof VirtualChest and !$tile->closed){
				$tile->close();
			}
		}
		unset($this->viewers[$playerName]);
	}

	public function hasInvalidatedItems(Player $player) : bool{
		return $this->invalidated_items->exists($player->getLowerCaseName());
	}

	public function invalidateExpiredItems() : void{
		$currentTime = Helper::breakTime();
		foreach($this->data->getAll() as $key => $data){
			if($data['exp'] < $currentTime){
				$this->data->remove($key);
				$d = (array)$this->invalidated_items->get($playerName = $data['player'], []);
				$d[] = $data['item'];
				$this->invalidated_items->set($playerName, $d);
			}
		}
	}

	public function onCommand(CommandSender $player, Command $command, $commandLabel, array $args){
		if(!$player instanceof Player){
			$player->sendMessage("§l§7► §rКоманда доступна только в игре!");
			return true;
		}
		
		if(!$player->isSurvival()){
			$player->sendMessage("§r⩕ §rВыключите §aкреатив!");
			return true;
		}
		
		$sendHelpMessage = function() use ($player, $commandLabel) : void{
			$player->sendMessage("§r §rОткрыть аукцион §a§l/auc§r");
			$player->sendMessage("§r §rПродать предмет §a§l/auc sell§r");
			$player->sendMessage("§r §rВернуть непроданные предметы §a§l/auc back§r");
		};
		if(!isset($args[0])){
			$this->open($player);
			$sendHelpMessage();
			return true;
		}
		$argument = strtolower(array_shift($args));
		if($argument === 'open'){
			$this->open($player);
		} elseif ($argument === 'search') {
        if (!isset($args[0])) {
            $player->sendMessage("§l§6➛ §rИспользование: §a/" . $commandLabel . " search <предмет>");
            return true;
        }

        $itemName = implode(" ", $args);
        $this->searchItems($player, $itemName);
    }elseif($argument === 'back'){
			if(!$this->hasInvalidatedItems($player)){
				$player->sendMessage("§l§6➛ §rТы ничего не продаёшь или твои предметы §aещё не были сняты §rс аукциона!");
				return true;
			}
			$d = (array)$this->invalidated_items->get($playerName = $player->getLowerCaseName(), []);
			$items = array_map(function(string $serialized) : Item{
				return Helper::deserializeItem($serialized);
			}, $d);
			foreach($items as $item){
				if(!$player->getInventory()->canAddItem($item)){
					$player->sendMessage("§l§6➛ §rОсвободи больше места в инвентаре!");
					return true;
				}
			}
			$player->getInventory()->addItem(...$items);
			$this->invalidated_items->remove($playerName);
			$this->invalidated_items->save();
			$player->sendMessage("§l§6➛ §rВсе непроданные предметы успешно §dвозвращены!");
		}elseif($argument === 'sell'){
			if($this->slotsLimited($player)){
				$player->sendMessage(
					"§l§6➛ §rЧтобы продавать §a§lбольше §rпредметов, покупай привилегию на сайте §a".
					self::AUTODONATE_SITE
				);
				return true;
			}
			if($this->noFreeSpace()){
				$player->sendMessage("§l§6➛ §rНа аукционе сейчас продаётся §aслишком много §fпредметов!");
				return true;
			}
			if(!isset($args[0])){
				$player->sendMessage("§l§6➛ §rИспользования: §a/" . $commandLabel . " sell (цена)");
				return true;
			}
			$item = $player->getInventory()->getItemInHand();
			if($item->getId() === 0){
				$player->sendMessage("§l§6➛ §rВозьми §aв руку §fпредмет, чтобы выставить его на продажу!");
				return true;
			}
			if(in_array($item->getId(), self::RESTRICTED_ITEM_IDS, true)){
				$player->sendMessage("§l§6➛ §rЭтот предмет §aзапрещено §fвыставлять на §aАукцион!");
				return true;
			}
			if($item->hasCompoundTag() and $item->getNamedTag()->hasTag('auctionWindowItem', ByteTag::class)){
				$player->getInventory()->setItemInHand(Item::get(ItemIds::AIR));
				$player->sendMessage("§l§6➛ §rЭтот предмет §aзапрещено §fвыставлять на §aАукцион!");
				return true;
			}
			$price = (int)array_shift($args);
			$monetaryUnit = "$";
			if($price < max(0, self::MIN_PRICE)){
				$prettyMinPrice = Helper::toPrettyNumber(number_format(max(0, self::MIN_PRICE)));
				$player->sendMessage("§l§6➛ §rМинимальная цена: §a" . $prettyMinPrice . " " . $monetaryUnit);
				return true;
			}
			if($price > self::MAX_PRICE){
				$prettyMaxPrice = Helper::toPrettyNumber(number_format(self::MAX_PRICE));
				$player->sendMessage("§l§6➛ §rМаксимальная цена за предмет: §a" . $prettyMaxPrice . " " . $monetaryUnit);
				return true;
			}
			$this->upForAuction($player, $item, $price);
		}
		return true;
	}

	public function doSave() : void{
		if($this->data->hasChanged()){
			$this->data->save();
		}
		if($this->invalidated_items->hasChanged()){
			$this->invalidated_items->save();
		}
	}

	public function onDisable(){
		$this->doSave();
	}

	public function initAvailableSlots() : void{
		$unavailableSlots = [45, 46, 47, 48, 49, 50, 51, 52, 53];
		for($i = 0; $i < 45; ++$i){
			if(in_array($i, $unavailableSlots, true)){
				continue;
			}
			$this->availableSlots[] = $i;
		}
	}

	public function searchItems(Player $player, string $itemName): void {
    $data = $this->data->getAll();
    $foundItems = [];

    // Приводим поисковый запрос к нижнему регистру
    $itemName = strtolower($itemName);

    foreach ($data as $uniqueKey => $itemData) {
        $item = Helper::deserializeItem($itemData['item']);
        $serializedName = strtolower(ItemNamesConverter::convertName($item));

        if (strpos($serializedName, $itemName) !== false) {
            $foundItems[$uniqueKey] = $itemData; // Сохраняем найденные предметы
        }
    }

    if (empty($foundItems)) {
        $player->sendMessage("§l§6➛ §rНе найдено товаров по запросу: §a" . $itemName);
        return;
    }

    // Обновить страницу с найденными товарами
    $this->displayFoundItems($player, $foundItems);
}

private function displayFoundItems(Player $player, array $foundItems): void {
    $inventory = new PersonalDoubleInventory(); // Создайте инвентарь для отображения результатов
    foreach ($foundItems as $key => $itemData) {
        $item = Helper::deserializeItem($itemData['item']);
        $item->setCustomName(ItemNamesConverter::convertName($item) . " §7- " . number_format($itemData['price']) . "$"); // Обновляем название для отображения

        // Добавьте товар в инвентарь
        $this->fillWindowSlot($inventory, $this->availableSlots[array_rand($this->availableSlots)], $item);
    }

    $player->addWindow($inventory);
}


	private function toPrettyNumber(int $number) : string{
		$str = "";
		foreach(str_split((string)$number) as $char){
			$str .= is_numeric($char) ? ["０", "１", "２", "３", "４", "５", "６", "７", "８", "９"][(int)$char] : $char;
		}
		return $str;
	}

	private function getItemWord($count) {
		if ($count == 1) {
			return "товар";
		} elseif ($count >= 2 && $count <= 4) {
			return "товара";
		} else {
			return "товаров";
		}
	}

	/** @var int */
	public const DEDUCTIBLE = 0x61103ed4;
	/** @var int */
	private const TYPE_CURRENT_PAGE = 2;
}