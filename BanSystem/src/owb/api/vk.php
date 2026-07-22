<?php

namespace owb\api;

use owb\main;

class vk
{

    public static $token_owner = 'vk1.a.nz6m92LiEfT4baIXMl63jkiadGOTUunfH1Wr9FT--R4vXpStApr3VzfbIsF2dDIkWpDCoSoeLvioSnFx-yowPKlm2h6uYcL2iLOGUzJj2xXbOyyU-Av1hdx6xO8GFzRLdoNP63mB_kReroy7A3KBWiv_d5CXTvxOhg1yKriDLkQjx4LpiHpd7BJPDSrKlp6AMPo8ExXI3aWGlmScGYroBw&expires_in=0&user_id=808617781'; //Токен владельца\администратора группы
    public static $token_group = 'vk1.a.KOnYvPk9psUp-lG8O1zEW_2KKSZDUtKzAuXoeJDoU1T2jfG_kES3s1krpJ2tkGMYTq6L_VbFCm_aD1SsxXkFPNTjM95gm5K_PhAurM6oZa5wUjcy1Yg7iZ7vYffJeWTrq6IxOkj4Yy6B-Aqd5c6fGQmy2KT_K5qeTJ8dEPBcJbCtfBSMgkKBnJI4y9ycE_rFvc3DY3P1qIIQKdQdugGgaA'; //Токен группы
    public static $id_owner = '229866298'; //Айди группы
    public static $post_accept = '457239017'; //Айди фото одобрения (фото должно быть в группе)
    public static $post_deny = '457239019'; //Айди фото отказа (фото должно быть в группе)
    public static $post_wait = '457239026'; //Айди фото ожидания (фото должно быть в группе)
    public static $id_chat = '3'; //Айди беседы
    public static $ids = ['808617781', '891867609', '@vlch2406', '1054290953']; //Айди администраторов

    public function construct(main $main)
    {
        $this->main = $main;
    }

    public static function url($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public static function parsingCommentsBan($post, $nickname)
    {
        $response = self::url("https://api.vk.com/method/wall.getComments?owner_id=-" . self::$id_owner . "&post_id=$post&count=100&v=5.103&access_token=" . self::$token_owner);
        $result = json_decode($response, true);
        if (!isset($result["error"])) {
            foreach ($result["response"]["items"] as $value => $key) {
                $vk = $key["from_id"];
                if (in_array($vk, self::$ids)) {
                    if ($key["text"] == "+") {
                        self::postAccept($post, $key, $vk);
                        database::addData($nickname, 'wallban', null);
                    } elseif ($key["text"] == "-") {
                        self::postDeny($post, $key, $vk);
                        database::pardonBan($nickname);
                    }
                }
            }
        } else {
            database::pardonBan($nickname);
        }
    }

    public static function parsingCommentsBanIp($post, $nickname)
    {
        $response = self::url("https://api.vk.com/method/wall.getComments?owner_id=-" . self::$id_owner . "&post_id=$post&count=100&v=5.103&access_token=" . self::$token_owner);
        $result = json_decode($response, true);
        if (!isset($result["error"])) {
            foreach ($result["response"]["items"] as $value => $key) {
                $vk = $key["from_id"];
                if (in_array($vk, [self::$ids])) {
                    if ($key["text"] == "+") {
                        self::postAccept($post, $key, $vk);
                        database::addData($nickname, 'wallbanip', null);
                    } elseif ($key["text"] == "-") {
                        self::postDeny($post, $key, $vk);
                        database::pardonBanIp($nickname);
                    }
                }
            }
        } else {
            database::pardonBanIp($nickname);
        }
    }

    public static function postAccept($post, $key, $vk)
    {
        $id = $key["id"];
        $raw_text = "\n\nПост проверен администратором: @id{$vk}";
        $second = str_replace(["\n", " "], ["%0A", "%20"], $raw_text);
        $delete = self::url("https://api.vk.com/method/wall.deleteComment?owner_id=-" . self::$id_owner . "&comment_id=$id&v=5.103&access_token=" . self::$token_owner);
        $textpost = self::url("https://api.vk.com/method/wall.getById?posts=-" . self::$id_owner . "_$post&v=5.103&access_token=" . self::$token_owner);
        $textpost = json_decode($textpost, true);
        $textpost = urlencode($textpost["response"][0]["text"]);
        $textpost = $textpost . $second;
        $edit = self::url("https://api.vk.com/method/wall.edit?owner_id=-" . self::$id_owner . "&post_id=$post&message=$textpost&attachments=photo-" . self::$id_owner . "_" . self::$post_accept . "&v=5.103&access_token=" . self::$token_owner);
    }

    public static function postDeny($post, $key, $vk)
    {
        $id = $key["id"];
        $raw_text = "\n\nПост проверен администратором: @id{$vk}";
        $second = str_replace(["\n", " "], ["%0A", "%20"], $raw_text);
        $delete = self::url("https://api.vk.com/method/wall.deleteComment?owner_id=-" . self::$id_owner . "&comment_id=$id&v=5.103&access_token=" . self::$token_owner);
        $textpost = self::url("https://api.vk.com/method/wall.getById?posts=-" . self::$id_owner . "_$post&v=5.103&access_token=" . self::$token_owner);
        $textpost = json_decode($textpost, true);
        $textpost = urlencode($textpost["response"][0]["text"]);
        $textpost = $textpost . $second;
        $edit = self::url("https://api.vk.com/method/wall.edit?owner_id=-" . self::$id_owner . "&post_id=$post&message=$textpost&attachments=photo-" . self::$id_owner . "_" . self::$post_deny . "&v=5.103&access_token=" . self::$token_owner);
    }

    public static function sendToWall(string $msg)
    {
        $message = str_replace(["\n", " "], ["%0A", "%20"], $msg);
        $post_wait = self::$post_wait;
        $url = "https://api.vk.com/method/wall.post?owner_id=-" . self::$id_owner . "&from_group=1&signed=0&message=" . $message . "&access_token=" . self::$token_owner . "&v=5.131&attachments=photo-" . self::$id_owner . "_{$post_wait}";
        return self::url($url);
    }


    public static function getLastPost(): string
    {
        $response = self::url("https://api.vk.com/method/wall.get?owner_id=-" . self::$id_owner . "&count=1&filter=owner&v=5.103&access_token=" . self::$token_owner);
        $result = json_decode($response, true);
        return $result["response"]["items"][0]["id"];
    }

    public static function sendChat(string $msg, $id): string
    {
        $msg = urlencode($msg);
        return self::url("https://api.vk.com/method/messages.send?message={$msg}&chat_id=$id&v=5.82&access_token=" . self::$token_group);
    }

    public static function sendToChat(string $msg, $id): string
    {
        $msg = urlencode($msg);
        return self::url("https://api.vk.com/method/messages.send?message={$msg}&chat_id=$id&attachment=wall-" . self::$id_owner . "_" . self::getLastPost() . "&v=5.82&access_token=" . self::$token_group);
    }
}