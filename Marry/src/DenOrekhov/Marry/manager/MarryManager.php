<?php

declare(strict_types=1);

namespace DenOrekhov\Marry\manager;

use pocketmine\Player;

use DenOrekhov\Marry\Loader;

class MarryManager
{

	public function isMarried(Player $player): bool { 
		return (bool) Loader::$sqlite3->query("SELECT *  FROM `marry` WHERE `nickname1` = '{$player->getLowerCaseName()}'")->fetchArray(SQLITE3_ASSOC);
	}

	public function setMarried(Player $player1, Player $player2): void {
		$nickname1 = $player1->getLowerCaseName(); $nickname2 = $player2->getLowerCaseName();

		/**
		 * Заполним базу данных соответствующими данными.
		 * 2 значения специально.
		 */
		Loader::$sqlite3->query("INSERT INTO `marry`(`nickname1`, `nickname2`, `home`, `pvp`) VALUES ('$nickname1', '$nickname2', '', 'on')");
		Loader::$sqlite3->query("INSERT INTO `marry`(`nickname1`, `nickname2`, `home`, `pvp`) VALUES ('$nickname2', '$nickname1', '', 'on')");
	}

	public function setDivorced(Player $player): void { 
		$nickname1 = $player->getLowerCaseName(); $nickname2 = $this->getTwoHalf($player);

		Loader::$sqlite3->query("DELETE FROM `marry` WHERE `nickname1` = '$nickname1'");
		Loader::$sqlite3->query("DELETE FROM `marry` WHERE `nickname1` = '$nickname2'");
	}


	public function getTwoHalf(Player $player): string {
		return (Loader::$sqlite3->query("SELECT *  FROM `marry` WHERE `nickname1` = '{$player->getLowerCaseName()}'")->fetchArray(SQLITE3_ASSOC))["nickname2"];
	}

	public function setHome(Player $player, $x, $y, $z, $level): void {
		$nickname1 = $player->getLowerCaseName(); $nickname2 = $this->getTwoHalf($player);

		$level = $level->getName();

		$data = "$x:$y:$z:$level";
		var_dump($data);

		Loader::$sqlite3->query("UPDATE `marry` SET `home` = '{$data}' WHERE `nickname1` = '$nickname1'");
		Loader::$sqlite3->query("UPDATE `marry` SET `home` = '{$data}' WHERE `nickname1` = '$nickname2'");
	}

	public function getHome(Player $player): string {
		return (Loader::$sqlite3->query("SELECT *  FROM `marry` WHERE `nickname1` = '{$player->getLowerCaseName()}'")->fetchArray(SQLITE3_ASSOC))["home"];
	}

	public function getStatusPvP(Player $player): string {
		return (Loader::$sqlite3->query("SELECT *  FROM `marry` WHERE `nickname1` = '{$player->getLowerCaseName()}'")->fetchArray(SQLITE3_ASSOC))["pvp"];
	}

	public function setStatusPvP(Player $player, string $status): void {
		$nickname1 = $player->getLowerCaseName(); $nickname2 = $this->getTwoHalf($player);

		Loader::$sqlite3->query("UPDATE `marry` SET `pvp` = '{$status}' WHERE `nickname1` = '$nickname1'");
		Loader::$sqlite3->query("UPDATE `marry` SET `pvp` = '{$status}' WHERE `nickname1` = '$nickname2'");
	}

	public function isMarriedPrefix(Player $player){
		if(Loader::$sqlite3->query("SELECT *  FROM `marry` WHERE `nickname1` = '{$player->getLowerCaseName()}'")->fetchArray(SQLITE3_ASSOC)){
			return "§8[§c❤§8]";
		} else {
            return "§8[§7❤§8]";
		}
	}

}