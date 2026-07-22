<?php

declare(strict_types=1);

namespace DenOrekhov\Marry;

use pocketmine\plugin\PluginBase;

use pocketmine\Server;

use DenOrekhov\Marry\manager\MarryManager;

class Loader extends PluginBase
{

	public static $instance;
	public static $sqlite3;

	public static $requests = [];

	public static function getInstance(): self {
		return self::$instance;
	}

	public function onEnable(): void {

		self::$instance = $this;

		$this->initDatabase();
		$this->registerCommand();
	}

	public function getPath(): string {
		return (string) $this->getDataFolder() . 'resources/';
	}

	public function initDatabase(): void {
		/**
		 * Я использую static переменные, так как значение переменной достать очень легко. Loader::$sqlite3. 
		 * Достать значение static переменной, объявленной в этом классе можно так: self::$static.
		 */
		
		/**
		 * Регистрируем базу данных marry, содержащую три столбца: nickname1, nickname2, home.
		 */
		@mkdir($this->getDataFolder(), 0777, true);
		@mkdir($this->getPath(), 0777, true);
		self::$sqlite3 = new \SQLite3($this->getPath() . "db.sqlite3");
		self::$sqlite3->query("CREATE TABLE IF NOT EXISTS `marry`(`nickname1` TEXT NOT NULL, `nickname2` TEXT NOT NULL, `home` TEXT NOT NULL, `pvp` TEXT NOT NULL);");
	}

	public function registerCommand(): void {
		Server::getInstance()->getCommandMap()->register("marry", new \DenOrekhov\Marry\commands\MarryCommand("marry"));
	}

	public function clearWorlds(): void {
		foreach(Server::getInstance()->getLevels() as $levels => $level) {
			foreach ($level->getEntities() as $entities => $entity) {
				if((explode(' ', $entity->getNameTag())[0])) {
					$explode = explode(' ', $entity->getNameTag());

					if($explode[0] === "§e§l►§r §fРебенок") {
						$entity->close("", "");
					}

				}

			}

		}

	}

	public function getMarryManager(): MarryManager {
		return new MarryManager($this);
	}

	public function onDisable(): void {
		$this->clearWorlds();
	}

}