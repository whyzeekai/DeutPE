<?php

declare(strict_types=1);

namespace API\vkapi;

use pocketmine\plugin\PluginBase;

class VKAPI
{

const TOKEN = "vk1.a.V5PyF1h-zkCN1TCSkukhTxzwefJXM9Fs-2cl5C6CzCwhN5EjVUn0RHromkKAs1skI87-D7NyxIzpJgxaJTdfwcUZ4IQZ4JhUdY8h4xJwCsB1tEeJlYJzJv4hyonLZHHJM3IVMtxkWHtEeLu0yh-IlosSjjEDP2t6VutBJTkKe8wtsCJYrfusqOUMzkdcAoYUusyZHqE7159dztWqAQ8vRg";

const API_VERSION = "5.85";

public static function getLogger(string $message, string $where = "conservation"): void {
$params = [
"message" => $message,
"chat_id" => 1,
"v" => self::API_VERSION,
"access_token" => self::TOKEN
];

$url = "https://api.vk.com/method/messages.send?" . http_build_query($params);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_exec($ch);
curl_close($ch);
}

}