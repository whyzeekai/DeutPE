<?php

declare(strict_types=1);

namespace API\vkapi;

use pocketmine\plugin\PluginBase;

class VKHELPER
{

const TOKEN = "vk1.a.r0UJ1oLgM2Ktob2HsQkUgHprLD9uWzrcfizyXO1nIAQgbeSeMVbnluw849UGBkoPQGI82IJpfnsa3ufX4tjZDIh0dIHnM7CC0PTLixfger2o0y1RDKmH-4Ts9690NNtDE6yhEb9M3aD7njmojF83rBvJkvWaLmQg3ba5bzjB8Qun6crfRaQ5qWNQzW3ma69-zCHMV1oviTbYKBkCAElo2g";

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