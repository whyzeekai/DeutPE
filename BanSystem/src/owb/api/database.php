<?php

namespace owb\api;

use owb\main;

class database
{
    /** @var main */
    private $main;

    public function __construct(main $main)
    {
        $this->main = $main;
        $this->loadDB();
    }

    public function loadDB()
    {
        main::getInstance()->bd->query("CREATE TABLE IF NOT EXISTS owbff(nickname TEXT NOT NULL, vk_id TEXT, bantime INTEGER NOT NULL, reasonban TEXT, dateban TEXT, banned TEXT, wallban INTEGER NOT NULL, playerip TEXT, baniptime INTEGER NOT NULL, reasonbanip TEXT, datebanip TEXT, bannedip TEXT, wallbanip INTEGER NOT NULL, mutetime INTEGER NOT NULL, reasonmute TEXT, muted TEXT)");
    }

    public static function getData($nickname, $key)
    {
        $result = main::getInstance()->bd->query("SELECT `$key` FROM owbff WHERE `nickname` = '$nickname'")->fetchArray(SQLITE3_ASSOC);
        return $result[$key];
    }

    public static function addData($nickname, $key, $value)
    {
        main::getInstance()->bd->query("UPDATE owbff SET `$key` = '$value' WHERE `nickname` = '$nickname'");
    }

    public static function pardonBan($nickname)
    {
        database::addData($nickname, "wallban", null);
        database::addData($nickname, "bantime",  null);
        database::addData($nickname, "banned", null);
        database::addData($nickname, "dateban", null);
        database::addData($nickname, "reasonban", null);
    }

    public static function pardonBanIp($nickname)
    {
        database::addData($nickname, "wallbanip", null);
        database::addData($nickname, "baniptime",  null);
        database::addData($nickname, "bannedip", null);
        database::addData($nickname, "datebanip", null);
        database::addData($nickname, "reasonbanip", null);
        database::addData($nickname, "playerip", null);
    }

    public static function pardonMute($nickname)
    {
        database::addData($nickname, "mutetime",  null);
        database::addData($nickname, "muted", null);
        database::addData($nickname, "reasonmute", null);
    }


}