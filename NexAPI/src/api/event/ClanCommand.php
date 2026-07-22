<?php

declare(strict_types=1);

namespace API\event;

use API\Loader;
use Statistics\Main as Stats;

use pocketmine\{Player, Server};
use pocketmine\math\Vector3;
use pocketmine\command\{Command, CommandSender};
use pocketmine\item\Item;
use pocketmine\level\sound\EndermanTeleportSound;
use ChestAPI\ChestAPI;
use ChestAPI\ChestClickEvent;
use ChestAPI\ChestOpenEvent;
use ChestAPI\ChestCloseEvent;

class ClanCommand extends Command{


    public function __construct(){
        parent::__construct("clan");
        parent::setAliases(["clan", "c"]);
    }

    private function notifyClanMembers(CommandSender $s, string $message) {
        $clan = Loader::getPlayerClan($s);
        foreach ($clan['members'] as $memberName => $memberData) {
            $member = Server::getInstance()->getPlayer($memberName);
            if ($member) {
                $member->sendMessage("§l§aCLAN §8:: §r" . $message);
            }
        }

        foreach ($clan['officers'] as $officerName => $officerData) {
            $officer = Server::getInstance()->getPlayer($officerName);
            if ($officer) {
                $officer->sendMessage("§l§aCLAN §8:: §r" . $message);
            }
        }

        $leaderName = strtolower($clan["leader"]);
        $leader = Server::getInstance()->getPlayer($leaderName);
        if ($leader) {
            $leader->sendMessage("§l§aCLAN §8:: §r" . $message);
        }
    }
    
    public function execute(CommandSender $s, $label, array $args){
        if(!isset($args[0])) return $s->sendMessage(
            "§l§7► §rПомощь по §a§lКЛАНАМ§r§7:\n" .
            " §a/c create (название) §8-§r создать клан\n" .
            " §a/c invite (никнейм игрока) §8-§r пригласить игрока в клан\n" .
            " §a/c accept §8- §rпринять приглашение в клан\n" .
            " §a/c leave §8-§r выйти с клана\n" .
            " §a/c kick (никнейм игрока) §8-§r выгнать игрока с клана\n" .
            " §a/c promote (никнейм игрока) §8-§r повысить Участника до Офицера\n" .
            " §a/c demote (никнейм игрока) §8-§r понизить Офицера до Участника\n" .
            " §a/c info (название/тег) §8-§r узнать информацию про определенный клан\n" .
            " §a/c menu §8-§r открыть меню клана\n" .
            " §a/c sethome §8-§r установить точку дома клана\n" .
            " §a/c delhome §8-§r удалить точку дома клана\n" .
            " §a/c home §8-§r телепортироваться на точку дома клана\n" .
            " §a/c delete §8-§r удалить клан\n"
        );
        switch($args[0]){
            /*case "create":
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c create (название)");;
                if(Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы уже состоите в §aклане§r! Дабы создать §8- §rвыйдите с клана");
                if (strlen($args[1]) > 12) return $s->sendMessage("§l§7► §rМаксимальная длина названия клана §8- §a12 символов");
                if (strlen($args[1]) < 3) return $s->sendMessage("§a► §rМинимальная длина названия клана §8- §a3 символа");
                if (!preg_match("#^[aA-zZАа-яЯ0-9\§_]+$#", $args[1])) return $s->sendMessage("§l§7► §cВ название можно использовать только английские буквы и цифры");
                if(Loader::isClan($args[1])) return ("§l§7► §cКлан с таким названием уже существует");
                if(Loader::getInstance()->getMoney($s) < 50000) return $s->sendMessage("§l§7► §rСтоимость создания клана §8- §a50000§2$");
                Loader::getInstance()->remMoney($s, 50000);
                $s->sendMessage("§l§7► §rКлан успешно создан!");
                Loader::createClan($s, $args[1]);
                //$s->getLevel()->addSound(new \pocketmine\level\sound\TotemSound($s));
            break;
            */

            case "create":
                if (!isset($args[1])) {
                    return $s->sendMessage("§l§7► §rИспользование§8: §a/c create (название)");
                }

                if (Loader::isInClan($s->getName())) {
                    return $s->sendMessage("§l§7► §rВы уже состоите в §aклане§r! Дабы создать §8- §rвыйдите с клана");
                }

                if (strlen($args[1]) > 12) {
                    return $s->sendMessage("§l§7► §rМаксимальная длина названия клана §8- §a12 символов");
                }

                if (strlen($args[1]) < 3) {
                    return $s->sendMessage("§a► §rМинимальная длина названия клана §8- §a3 символа");
                }

                if (!preg_match("#^[aA-zZАа-яЯ0-9\§_]+$#", $args[1])) {
                    return $s->sendMessage("§l§7► §cВ название можно использовать только английские буквы и цифры");
                }

                if (Loader::isClan($args[1])) {
                    return $s->sendMessage("§l§7► §rКлан с таким названием уже существует");
                }

                if (Loader::getInstance()->getMoney($s) < 50000) {
                    return $s->sendMessage("§l§7► §rСтоимость создания клана§7: §a50,000§2$");
                }

                Loader::getInstance()->remMoney($s, 50000);
                $s->sendMessage("§l§7► §rКлан успешно создан!");
                Server::getInstance()->BroadcastMessage("\n\n§l§aCLANS §8:: §rНа сервере появился новый клан!\n§l§aCLANS §8:: §rСоздатель: §a{$s->getName()} §7| §rНазвание клана: §b{$args[1]}\n\n");
                Loader::createClan($s, $args[1]);
            break;

            case "invite":
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c invite (никнейм игрока)");
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!($p = Server::getInstance()->getPlayer($args[1])) instanceof Player) return $s->sendMessage("§l§7► §cИгрок с данным никнеймом не в сети");
                if(Loader::isInClan($p->getName())) return $s->sendMessage("§l§7► §rИгрок с данным никнеймом уже состоит в клане");
                if(!Loader::isLeader($s) and !Loader::isOfficer($s)) return $s->sendMessage("§l§7► §rВы не лидер или офицер клана");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $clan = Loader::getPlayerClan($s);
                if(count($clan["members"]) + count($clan["officers"]) >= $clan["max-members"]) return $s->sendMessage("§l§7► §rДостигнут лимит участников клана");
                $s->sendMessage("§l§7► §rВы успешно пригласили игрока §6§l" . $p->getName() . "§r§r в свой клан");
                $p->sendMessage("§8[§l§cINFO§r§8] §rВас пригласили в клан§a " . Loader::getPlayerClan($s)["name"] . "\n §r§rДля просмотра информации клана введите§8: §a/c info " . Loader::getPlayerClan($s)["name"] . "\n§r§l§7► §rДля принятия запроса используйте§8: §a/c accept");
                Loader::$invites[strtolower($p->getName())] = strtolower($s->getName());
            break;
            case "accept":
                if(!isset(Loader::$invites[strtolower($s->getName())])) return $s->sendMessage("§l§7► §rУ вас нету активных приглашений");
                if(Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы уже в клане. Чтобы выйти из него используйте§8: §a/c leave");
                if(!($p = Server::getInstance()->getPlayer(Loader::$invites[strtolower($s->getName())])) instanceof Player) return $s->sendMessage("§l§7► §rИгрок, который вас пригласил сейчас не в сети");
                unset(Loader::$invites[strtolower($s->getName())]);
                if(!Loader::isInClan($p->getName())) return $s->sendMessage("§l§7► §rИгрок, который вас пригласил сейчас не состоит ни в каком клане");
                $clan = Loader::getPlayerClan($p)["name"];
                $p->sendMessage("§l§7► §rИгрок §6§l" . $s->getName() . "§r§r, приглашенный вами, принял запрос на вступления в ваш клан!");
                $s->sendTitle("§a§lКЛАН", "§7Добро пожаловать!");
                Loader::addMember($s->getName(), $clan);
            break;
            case "kick":
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c kick (никнейм игрока)");
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s) and !Loader::isOfficer($s)) return $s->sendMessage("§l§7► §rВы не лидер или офицер клана");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $nick = strtolower($args[1]);
                if($nick === strtolower($s->getName())) return $s->sendMessage("§l§7► §rВы не можете выгнать самого себя");
                if($nick === strtolower(Loader::getPlayerClan($s)["leader"])) return $p->sendMessage("§l§7► §rВы не можете выгнать лидера клана");
                if(!Loader::isInClan($nick) or Loader::getPlayerClan($nick) !== Loader::getPlayerClan($s)) return $s->sendMessage("§l§7► §rИгрок, которого вы хотите выгнать, не состоит в вашем клане");
                if(Loader::isOfficer($nick)) Loader::removeOfficer($nick, Loader::getPlayerClan($s)["name"]);
                else Loader::removeMember($nick, Loader::getPlayerClan($s)["name"]);
                $s->sendMessage("§l§7► §rВы успешно выгнали игрока §6§l" . $nick . "§r§r с клана");
                
            break;
            case "leave":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не можете покинуть клан, так как вы являетесь его лидером");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $s->sendMessage("§l§7► §rВы успешно покинули клан");
                if(Loader::isOfficer($s)) Loader::removeOfficer($s->getName(), Loader::getPlayerClan($s)["name"]);
                else Loader::removeMember($s->getName(), Loader::getPlayerClan($s)["name"]);
                
            break;
            case "delete":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не лидер клана");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $s->sendMessage("§l§7► §rКлан успешно удален");
                Loader::removeClan(Loader::getPlayerClan($s)["name"]);
                
            break;
            case "promote":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не лидер клана");
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c promote (никнейм игрока)");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $p = Server::getInstance()->getPlayer($args[1]);
                $nick = $p instanceof Player ? strtolower($p->getName()) : $args[1];
                if(strtolower($nick) === strtolower($s->getName())) return $s->sendMessage("§l§7► §rВы лидер, поэтому не можете стать офицером своего клана");
                if(!Loader::isInClan($nick) or Loader::getPlayerClan($nick) !== Loader::getPlayerClan($s)) return $s->sendMessage("§l§7► §rИгрок с данным никнеймом не найден в вашем клане");
                if(Loader::isOfficer($nick)) return $s->sendMessage("§l§7► §rИгрок уже является §6Офицером§r!");
                $s->sendMessage("§l§7► §rВы успешно повысили игрока §6§l".$nick."§r§r до §6Офицера§r!");
                if($p instanceof Player) $p->sendMessage("§l§7► §rЛидер §6§l".$s->getName()."§r§r повысил вас до §6Офицера§r!");
                Loader::removeMember($nick, Loader::getPlayerClan($nick)["name"], true);
                $this->notifyClanMembers($s, "§rИгрок §a" . ucfirst($nick) . "§r был повышен до §l§dХелпера клана§r.");
            break;
            case "demote":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не лидер клана");
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c demote (никнейм игрока)");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $p = Server::getInstance()->getPlayer($args[1]);
                $nick = $p instanceof Player ? strtolower($p->getName()) : $args[1];
                if(strtolower($nick) === strtolower($s->getName())) return $s->sendMessage("§l§7► §rНельзя понизить себя!");
                if(!Loader::isInClan($nick) or Loader::getPlayerClan($nick) !== Loader::getPlayerClan($s)) return $s->sendMessage("§l§7► §rИгрок с данным никнеймом не найден в вашем клане");
                if(!Loader::isOfficer($nick)) return $s->sendMessage("§l§7► §rИгрок уже является §6Участником§r!");
                $s->sendMessage("§l§7► §rВы успешно понизили игрока §6§l".$nick."§r§r до §6Участника§r!");
                if($p instanceof Player) $p->sendMessage("§l§7► §rЛидер §6§l".$s->getName()."§r§r понизил вас до §6Участника§r!");
                Loader::removeOfficer($nick, Loader::getPlayerClan($nick)["name"], true);
                $this->notifyClanMembers($s, "§rИгрок §a" . ucfirst($nick) . "§r был снят с §l§dХелпера клана§r.");
            break;
            case "info":
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c info (тег/название)");
                if(!Loader::isClan($args[1])){
                    if(!Loader::isClanByTag($args[1])) return $s->sendMessage("§l§7► §сГильдии §6".$args[1]." §с не существует!");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                    $clan = Loader::getClanByTag($args[1]);
                }else $clan = Loader::getClan($args[1]);
                $tag = $clan["tag"] ? $clan["tag"] : "нет";
                $s->sendMessage("§l§7► §rИнформация о клане §6".$clan["name"]."§8:\n §aДата создания§8: §r".$clan["date"]."\n §aЛидер§8: §r".$clan["leader"]."\n §aОфицеров§8: §r".count($clan["officers"])."\n §aУчастников§8: §r".count($clan["members"])."\n §aТег§8: §r".$tag."\n §aУбийств§8: §r".$clan["kills"]);
            break;
            case "tag":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не лидер клана");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                if(!isset($args[1])) return $s->sendMessage("§l§7► §rИспользование§8: §a/c tag (тег/название)");
                if(strpos($args[1], "§") !== false) return $s->sendMessage("§l§7► §cНельзя установить цветной Тег!");
                if(isset($args[1][8])) return $s->sendMessage("§l§7► §cСлишком длинный Тег");
                $s->sendMessage("§l§7► §rТег §6".strtoupper($args[1])."§r для клана успешно установлен!");
                $clan = Loader::getPlayerClan($s);
                $clan["tag"] = strtoupper($args[1]);
                Loader::$clans->set(strtolower($clan["name"]), $clan);
                Loader::$clans->save();
                
            break;
            case "deletetag":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s)) return $s->sendMessage("§l§7► §rВы не лидер клана");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана.");
                }
                $clan = Loader::getPlayerClan($s);
                $clan["tag"] = false;
                Loader::$clans->set(strtolower($clan["name"]), $clan);
                Loader::$clans->save();
                $s->sendMessage("§l§7► §rТег удален!");
                
            break;

            case 'banclan':
                if ($s->hasPermission("api.cmd.banclan")) {
                    if (!isset($args[1])) {
                        return $s->sendMessage("§l§7► §rИспользование§8: §a/c banclan (название)");
                    }

                    $clanName = $args[1];
                    if (!Loader::isClan($clanName)) {
                        return $s->sendMessage("§l§7► §cТакого клана не существует!");
                    }
                    $clan = Loader::getClan($clanName);
                    $clan["banclan"] = true;
                    Loader::$clans->set(strtolower($clan["name"]), $clan);
                    Loader::$clans->save();
                    Server::getInstance()->BroadcastMessage("\n\n§l§aCLANS §8:: §rКлан §c{$clanName}§r, был заблокирован!\n§l§aCLANS §8:: §rПричина: §cФарм статистики клана.\n\n");
                }
            break;
            case 'unbanclan':
                if ($s->hasPermission("api.cmd.banclan")) {
                    if (!isset($args[1])) {
                        return $s->sendMessage("§l§7► §rИспользование§8: §a/c banclan (название)");
                    }

                    $clanName = $args[1];
                    if (!Loader::isClan($clanName)) {
                        return $s->sendMessage("§l§7► §cТакого клана не существует!");
                    }
                    $clan = Loader::getClan($clanName);
                    $clan["banclan"] = false;
                    Loader::$clans->set(strtolower($clan["name"]), $clan);
                    Loader::$clans->save();
                    Server::getInstance()->BroadcastMessage("\n\n§l§aCLANS §8:: §rКлан §c{$clanName}§r, был разблокирован!\n§l§aCLANS §8:: §rПожелаем удачи этому клану.\n\n");
                }
            break;
            case "sethome":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isLeader($s->getName()) && !Loader::isOfficer($s->getName())) return $s->sendMessage("§l§7► §rВы не лидер или офицер клана");

                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§§cREASON BAN §8:: §r§cПричина блокировки: §сФарм статистики клана.");
                    return;
                }

                if($s->getLevel()->getFolderName() != 'world') {
                    if(strtolower($s->getName()) != 'Wassabi') {
                        $world = Loader::getInstance()->getWorld($s);
                        $s->sendMessage("§l§7► §rВ мире {$world}§r нельзя поставить §aклан-хом!");
                        return;
                    }
                }

                if(Loader::isClanHome(Loader::getPlayerClan($s)["name"])) return $s->sendMessage("§l§7► §rДля начала удалите предыдущую точку дома!");
                $clan = Loader::getPlayerClan($s);
                $clan["home"] = round($s->getX()).":".round($s->getY()).":".round($s->getZ());
                Loader::$clans->set(strtolower($clan["name"]), $clan);
                Loader::$clans->save();

                $this->notifyClanMembers($s, "§rКлан точка§r была поставлена в §aновом месте§7 ({$s->getName()})");
            break;

            case "delhome":
                if (!Loader::isInClan($s->getName())) {
                    return $s->sendMessage("§l§7► §rВы не состоите в клане");
                }

                if (!Loader::isLeader($s->getName()) && !Loader::isOfficer($s->getName())) {
                    return $s->sendMessage("§l§7► §rВы не лидер или офицер клана");
                }

                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§§cREASON BAN §8:: §r§cПричина блокировки: §сФарм статистики клана.");
                    return;
                }

                if (!Loader::isClanHome(Loader::getPlayerClan($s)["name"])) {
                    return $s->sendMessage("§l§7► §сТочка дома не установлена!");
                }

                $s->sendTitle("§a§lКЛАН", "§7Точка дома удалена");
                $clan = Loader::getPlayerClan($s);
                $clan["home"] = false;

                Loader::$clans->set(strtolower($clan["name"]), $clan);
                Loader::$clans->save();

                $this->notifyClanMembers($s, "§rКлан точка дома была удалена§7 ({$s->getName()})");
            break;

            case "home":
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                if(!Loader::isClanHome(Loader::getPlayerClan($s)["name"])) return $s->sendMessage("§l§7► §сТочка дома не установлена!");
                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    $s->sendMessage("§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§§cREASON BAN §8:: §r§cПричина блокировки: §сФарм статистики клана.");
                    return;
                }
                $home = explode(":", Loader::getPlayerClan($s)["home"]);
                $s->getLevel()->addSound(new EndermanTeleportSound($s));
                $s->teleport(Server::getInstance()->getLevelByName("world")->getSafeSpawn());
                $s->teleport(new \pocketmine\math\Vector3($home[0], $home[1], $home[2]));
                $s->sendTitle("§a§lКЛАН", "§7Телепортация..");
            break;
            
            case "menu":
                if($s->getGamemode() !== 0) return $s->sendMessage("§l§7► §cДоступно для открытия только в режиме Выживания");
                //if($s->getLevel()->getFolderName() != 'spawn') return $s->sendMessage("§l§7► §cОткрыть аукцион можно только на спавне!");
                if(!Loader::isInClan($s->getName())) return $s->sendMessage("§l§7► §rВы не состоите в клане");
                $clan = Loader::getPlayerClan($s);
                $nameclan = Loader::getInstance()->getPrefixClans($s);

                if ($clan["level"] < 3) {
                    $shop = "§r§l§aМАГАЗИН КЛАНА\n\n§rНедоступен для вашего клана!\n§aКлан-Магазин, §rдоступен кланам\nу которых имеется §a3 уровень§r.\n\n§cВам нужно имееть §43 уровень§c клана.";
                }else{
                    $shop = "§r§l§aМАГАЗИН КЛАНА\n\n§rМожно купить §aвещей§r\nдля пвп, и многое §aдругое§r!\n\nn§r§7Нажми дважды, чтобы перейти!";
                }

                $chest = ChestAPI::getInstance()->openChest($s, [
                    "45-53" => Item::get(102),
                    "0-8" => Item::get(102),
                    "28-43" => Item::get(102),
                    9 => Item::get(102),
                    18 => Item::get(102),
                    27 => Item::get(102),
                    36 => Item::get(102),
                    17 => Item::get(102),
                    26 => Item::get(102),
                    35 => Item::get(102),
                    44 => Item::get(102),
                    43 => Item::get(54, 0, 1)->setCustomName($shop),
                    37 => Item::get(339, 0, 1)->setCustomName("§r§l§fИНФОРМАЦИЯ КЛАНА:\n\n§7 - §rНазвание клана {$nameclan}\n§7 - §rУчастников §a" . count($clan["members"]) . "§7/§a" . $clan["max-members"] . "\n\n§7 - §rУбийства клана §c" . $clan["kills"] . "\n§7 - §rКазна клана §a" . number_format($clan["coins"]) . " §7(CLAN COINS).§r\n\n§7 - §rУровень клана §e" . $clan["level"] . "\n§7 - §rОпыт клана §d" . $clan["xp"] . "§7/§d10,000exp\n\n§7 - §rДата создания клана §e" . $clan["date"] . ""),
                   
                    //4 => Item::get(339,0, 1)->setCustomName("§r§a§lКЛАН§8:\n §r§7Название§8: §6".$clan["name"]."\n §r§7Дата создания§8: §r".$clan["date"]."\n §7Убийств§8: §r".$clan["kills"]."\n §7Тег§8: §r".$clan["tag"]),
                    //5 => Item::get(397, 3, 1)->setCustomName("§r§rЧлены гильдии§8:\n §r§7Лидер§8: §c".$clan["leader"]."\n §7Офицеры§8: §6".count($clan["officers"])."\n §7Участники§8: §r".count($clan["members"])."\n §7Всего: §r".(count($clan["members"]) + count($clan["officers"]))."§7/§6".$clan["max-members"])
                ], "§8§lКлан:§r {$nameclan} §l§8| §8Лидер: §c{$clan["leader"]}", ChestAPI::DOUBLE_CHEST);
                if (is_bool($chest)) return;
                $inventory = $chest["inventory"];

                if (Loader::isClanBan(Loader::getPlayerClan($s)["name"])) {
                    $inventory->setItem(31, Item::get(159, 14, 1)->setCustomName("§r§l§cCLAN BAN §8:: §r§cВы не можете пользоваться кланом, он заблокирован.\n§l§cREASON BAN §8:: §rПричина блокировки: §сФарм статистики клана."));
                }else{
                    $inventory->setItem(31, Item::get(102, 0, 1));
                }

                $slot = 10;
                $leaderName = $clan["leader"];
                $leaderPlayer = Server::getInstance()->getPlayer($leaderName);
                $leaderKills = $clan['officers'][strtolower($leaderName)]['kills'] ?? 0; 
                $leaderDonate = Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($leaderName));
                $online = $leaderPlayer instanceof Player ? "" : "";
                if ($inventory->getItem($slot)->getId() === 0) {
                    $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r §l§cВЛАДЕЛЕЦ КЛАНА§r§f \n\n§r§8[§r{$leaderDonate}§8]§r " . $leaderName . " §r" . $online . "\n\n§r Дата вступления§7: §a" . $clan["date"] . "\n\n§r Убийств§7: §c{$leaderKills}"));
                }

                foreach ($clan["officers"] as $member => $data) {
                    ++$slot;
                    $online = Server::getInstance()->getPlayer($data["nick"]) instanceof Player ? "" : "";
                    $officerPlayer = Server::getInstance()->getPlayer($data["nick"]);
                    $officerDonate = Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($data['nick'])); 
                    if ($inventory->getItem($slot)->getId() === 0) {
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r §l§dХЕЛПЕР КЛАНА§r§f \n\n§r§8[§r{$officerDonate}§8]§r " . $data["nick"] . " §r" . $online . "\n\n§r Дата вступления§7: §a" . $data["date"] . "\n\n§r Убийств§7: §c0"));
                    }
                }

                foreach ($clan["members"] as $member => $data) {
                    ++$slot;
                    $online = Server::getInstance()->getPlayer($data["nick"]) instanceof Player ? "" : "";
                    $memberPlayer = Server::getInstance()->getPlayer($data["nick"]); 
                    $kills = $data["kills"];
                    $memberDonate = Loader::getInstance()->getDonateName(Loader::getInstance()->getGroup($data['nick'])); 
                    if ($inventory->getItem($slot)->getId() === 0) {
                        $inventory->setItem($slot, Item::get(397, 3, 1)->setCustomName("§r §l§aУЧАСТНИК КЛАНА §r§f\n\n§r§8[§r{$memberDonate}§8]§r " . $data["nick"] . " §r" . $online . "\n\n§r Дата вступления§7: §a" . $data["date"] . "\n\n§r Убийств§7: §c{$kills}"));
                    }
                }
            break;
        }
    }
}
