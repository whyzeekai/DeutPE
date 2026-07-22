<?php

declare(strict_types=1);

namespace timurkaundefined\auction\utils;

use pocketmine\item\Item;

abstract class ItemNamesConverter{

	/**
	 * ID предмета => ["Стандартное название", 1 => "Особое название"]
	 */
	private const SPECIAL_NAMES = [
		383 => ["Яйцо призыва", 11 => "Яйцо призыва коровы", 12 => "Яйцо призыва свинья", 13 => "Яйцо призыва овца", 32 => "Яйцо призыва зомби", 33 => "Яйцо призыва крипер", 35 => "Яйцо призыва паук", 38 => "Яйцо призыва эндермен", 34 => "Яйцо призыва скелет", 48 => "Яйцо призыва иссушитель", 43 => "Яйцо призыва ифрит",],
		373 => ["Пузырёк воды", 1 => "Мирское зелья", 2 => "Мирское долгое зелья", 3 => "Густое зелья", 4 => "Неловкое зелья", 5 => "Зелья ночного зрения 3:00", 6 => "Зелья ночного зрения 8:00", 7 => "Зелья невидимости 3:00", 8 => "Зелья невидимости 8:00", 9 => "Зелья прыжка 3:00", 10 => "Зелья прыжка 8:00", 11 => "Зелья прыжка II", 12 => "Зелья огнистойкости 3:00", 13 => "Зелья огнистойкости 8:00", 14 => "Зелья ускорение 3:00", 15 => "Зелья ускорение 8:00", 16 => "Зелья ускорение II", 17 => "Зелья медлительности 1:00", 18 => "Зелья медлительности 4:00", 19 => "Зелья подводного дыхание 3:00", 20 => "Зелья подводного дыхание 8:00", 21 => "Зелья исцеления", 22 => "Зелья исцеления II", 23 => "Зелья прочи", 24 => "Зелья прочи II", 25 => "Зелья отправления 0:45", 26 => "Зелья отправления 2:00", 27 => "Зелья отправления II", 28 => "Зелья регенерации 0:45", 29 => "Зелья регенерации 2:00", 30 => "зелья регенерации II", 31 => "Зелья силы 3:00", 32 => "Зелья силы 8:00", 33 => "Зелья силы II", 34 => "Зелья слабости 1:00", 35 => "Зелья слабости 4:00", 36 => "Зелья разложения II"],
		351 => ["Краситель", 1 => "Красный краситель", 2 => "Зелёный краситель", 3 => "Какао-бобы", 4 => "Лазурит", 5 => "Фиолетовый краситель", 6 => "Бирюзовый краситель", 7 => "Светло-серый краситель", 8 => "Серый краситель", 9 => "Розовый краситель", 10 => "Лаймовый краситель", 11 => "Жёлтый краситель", 12 => "Голубой краситель", 13 => "Пурпурный краситель", 14 => "Оранжевый краситель", 15 => "Костная мука"],
		397 => ["Голова скелета", 1 => "Голова визера", 2 => "Голова зомби", 3 => "Голова стива", 4 => "Голова крипера", 5 => "Голова дракона"],
		262 => ["Стрела", 24 => "Стрела моментального урона", 25 => "Стрела моментального урона II", 26 => "Стрела отправления 0:05", 27 => "Стрела отправления 0:15"],
		325 => ["Пустой Ведро", 1 => "Ведро с молокой", 8 => "Ведро с водой", 10 => "Ведро с лавой"],
		438 => ["Баф вода", 1 => "Баф Мирское зелья", 2 => "Баф Мирское долгое зелья", 3 => "Баф Густое зелья", 4 => "Баф Неловкое зелья", 5 => "Баф Зелья ночного зрения 2:00", 6 => "Баф Зелья ночного зрения 6:00", 7 => "Баф Зелья невидимости 2:00", 8 => "Баф Зелья невидимости 6:00", 9 => "Баф Зелья прыжка 2:00", 10 => "Баф Зелья прыжка 6:00", 11 => "Баф Зелья прыжка II", 12 => "Баф Зелья огнистойкости 2:00", 13 => "Баф Зелья огнистойкости 6:00", 14 => "Баф Зелья ускорение 2:00", 15 => "Баф Зелья ускорение 6:00", 16 => "Баф Зелья ускорение II", 17 => "Баф Зелья медлительности 1:00", 18 => "Баф Зелья медлительности 3:00", 19 => "Баф Зелья подводного дыхание 2:00", 20 => "Баф Зелья подводного дыхание 6:00", 21 => "Баф Зелья исцеления", 22 => "Баф Зелья исцеления II", 23 => "Баф Зелья прочи", 24 => "Баф Зелья прочи II", 25 => "Баф Зелья отправления 0:33", 26 => "Баф Зелья отправления 1:00", 27 => "Баф Зелья отправления II", 28 => "Баф Зелья регенерации 0:33", 29 => "Баф Зелья регенерации 1:00", 30 => "Баф зелья регенерации II", 31 => "Баф Зелья силы 2:00", 32 => "Баф Зелья силы 6:00", 33 => "Баф Зелья силы II", 34 => "Баф Зелья слабости 1:00", 35 => "Баф Зелья слабости 3:00", 36 => "Баф Зелья разложения II"],
	];

	public static function convertName(Item $item) : string{
		$itemId = $item->getId();

		$attempt = function() use ($item, $itemId){
			if(isset(ItemNamesConverter::SPECIAL_NAMES[$itemId])){
				if(isset(ItemNamesConverter::SPECIAL_NAMES[$itemId][$itemMeta = $item->getDamage()])){
					return ItemNamesConverter::SPECIAL_NAMES[$itemId][$itemMeta];
				}elseif(isset(ItemNamesConverter::SPECIAL_NAMES[$itemId][0])){
					return ItemNamesConverter::SPECIAL_NAMES[$itemId][0];
				}
			}
			return null;
		};

		$name = $attempt();

		if($name === null){
			switch($itemId){
				case 0:
					$name = "Воздух";
				break;
				case 1:
					$name = "Блоки камня";
				break;
				case 2:
					$name = "Блоки травы";
				break;
				case 3:
					$name = "Блоки земли";
				break;
				case 4:
					$name = "Булыжник";
				break;
				case 5:
					$name = "Доски";
				break;
				case 6:
					$name = "Саженцы";
				break;
				case 7:
					$name = "Бедрок";
				break;
				case 8:
					$name = "Вода";
				break;
				case 9:
					$name = "Источник воды";
				break;
				case 10:
					$name = "Лава";
				break;
				case 11:
					$name = "Источник лавы";
				break;
				case 12:
					$name = "Песок";
				break;
				case 13:
					$name = "Гравий";
				break;
				case 14:
					$name = "Золотая руда";
				break;
				case 15:
					$name = "Железная руда";
				break;
				case 16:
					$name = "Угольная руда";
				break;
				case 17:
					$name = "Древесина";
				break;
				case 18:
					$name = "Листва";
				break;
				case 19:
					$name = "Губка";
				break;
				case 20:
					$name = "Стекло";
				break;
				case 21:
					$name = "Лазуртиная руда";
				break;
				case 22:
					$name = "Блок лазурита";
				break;
				case 23:
					$name = "Смотритель";
				break;
				case 24:
					$name = "Песчанник";
				break;
				case 25:
					$name = "Нотный блок";
				break;
				case 26:
					$name = "Кровать";
				break;
				case 27:
					$name = "Активированные рельсы";
				break;
				case 28:
					$name = "Рельсы с активатором";
				break;
				case 29:
					$name = "Липкий поршень";
				break;
				case 30:
					$name = "Паутина";
				break;
				case 31:
					$name = "Высокая трава";
				break;
				case 32:
					$name = "Мёртвый куст";
				break;
				case 33:
					$name = "Поршень";
				break;
				case 34:
					$name = "Ядро поршня";
				break;
				case 35:
					$name = "Шерсть" . ($item->getDamage() === 0 ? "" : " цветная");
				break;
				case 36:
					$name = "Мяу-мур меч";
				break;
				case 37:
					$name = "Жёлтый цветок";
				break;
				case 38:
					$name = "Роза";
				break;
				case 39:
					$name = "Подосиновик";
				break;
				case 40:
					$name = "Мухомморчик";
				break;
				case 41:
					$name = "Золотой блок";
				break;
				case 42:
					$name = "Железный блок";
				break;
				case 43:
					$name = "Резной камень";
				break;
				case 44:
					$name = "Полублоки";
				break;
				case 45:
					$name = "Блоки кирпича";
				break;
				case 46:
					$name = "Динамит";
				break;
				case 47:
					$name = "Книжные полки";
				break;
				case 48:
					$name = "Замшелый булыжник";
				break;
				case 49:
					$name = "Обсидиан";
				break;
				case 50:
					$name = "Факела";
				break;
				case 51:
					$name = "Огонь в чистом виде";
				break;
				case 52:
					$name = "Спавнер монстров";
				break;
				case 53:
					$name = "Деревянные ступеньки";
				break;
				case 54:
					$name = "Сундук";
				break;
				case 55:
					$name = "Редстоун";
				break;
				case 56:
					$name = "Алмазная руда";
				break;
				case 57:
					$name = "Алмазный блок";
				break;
				case 58:
					$name = "Верстак";
				break;
				case 59:
					$name = "Блок пшеницы";
				break;
				case 60:
					$name = "Распаханная земля";
				break;
				case 61:
					$name = "Печка";
				break;
				case 62:
					$name = "Работающая печка";
				break;
				case 63:
					$name = "Таблички";
				break;
				case 64:
					$name = "Блок двери";
				break;
				case 65:
					$name = "Лестница";
				break;
				case 66:
					$name = "Рельсы";
				break;
				case 68:
					$name = "Настенная табличка";
				break;
				case 67:
					$name = "Ступеньки из булыжника";
				break;
				case 69:
					$name = "Рычаг";
				break;
				case 70:
					$name = "Каменная нажимная плита";
				break;
				case 71:
					$name = "Блок железной двери";
				break;
				case 72:
					$name = "Деревянная нажимная плита";
				break;
				case 73:
					$name = "Руда редстоуна";
				break;
				case 74:
					$name = "Светящаяся руда редстоуна";
				break;
				case 75:
					$name = "Потушенный красный факел";
				break;
				case 76:
					$name = "Красный факел";
				break;
				case 77:
					$name = "Кнопка";
				break;
				case 78:
					$name = "Снег";
				break;
				case 79:
					$name = "Лёд";
				break;
				case 80:
					$name = "Блок снега";
				break;
				case 81:
					$name = "Кактус";
				break;
				case 82:
					$name = "Глина";
				break;
				case 83:
					$name = "Тростник";
				break;
				case 84:
					$name = "Терра-меч";
				break;
				case 85:
					$name = "Забор из дерева";
				break;
				case 86:
					$name = "Тыква";
				break;
				case 87:
					$name = "Адский камень";
				break;
				case 88:
					$name = "Песк душ";
				break;
				case 89:
					$name = "Светокамень";
				break;
				case 90:
					$name = "Блок Портала";
				break;
				case 91:
					$name = "Тыква Джека";
				break;
				case 92:
					$name = "Тортик";
				break;
				case 93:
					$name = "Повторитель";
				break;
				case 94:
					$name = "Компроматор";
				break;
				case 95:
					$name = "Невидимый бедрок";
				break;
				case 96:
					$name = "Люк";
				break;
				case 97:
					$name = "Каменные кирпичи";
				break;
				case 98:
					$name = "Каменные кирпичи";
				break;
				case 99:
					$name = "Грибной блок I";
				break;
				case 100:
					$name = "Грибной блок I";
				break;
				case 101:
					$name = "Железная решётка";
				break;
				case 102:
					$name = "Стеклянная панель";
				break;
				case 103:
					$name = "Арбуз";
				break;
				case 104:
					$name = "Недозрелый арбуз";
				break;
				case 105:
					$name = "Недозрелый арбуз";
				break;
				case 106:
					$name = "Лоза";
				break;
				case 107:
					$name = "Калитка";
				break;
				case 108:
					$name = "Ступеньки из кирпича";
				break;
				case 109:
					$name = "Ступеньки из каменного кирпича";
				break;
				case 110:
					$name = "Мицелий";
				break;
				case 111:
					$name = "Лист кувшинки";
				break;
				case 112:
					$name = "Адские кирпичи";
				break;
				case 113:
					$name = "Адский забор";
				break;
				case 114:
					$name = "Ступеньки из адского кирпича";
				break;
				case 115:
					$name = "Блок адского нароста";
				break;
				case 116:
					$name = "Стол зачарований";
				break;
				case 117:
					$name = "Варочная стойка";
				break;
				case 118:
					$name = "Котёл";
				break;
				case 119:
					$name = "Блок портала в край";
				break;
				case 120:
					$name = "Блок портала в край";
				break;
				case 121:
					$name = "Эндерняк";
				break;
				case 122:
					$name = "Стингер";
				break;
				case 123:
					$name = "Лампа";
				break;
				case 124:
					$name = "Активированная лампа";
				break;
				case 125:
					$name = "Раздатчик";
				break;
				case 126:
					$name = "Рельсы";
				break;
				case 127:
					$name = "Блок какао-бобов";
				break;
				case 128:
					$name = "Ступеньки из песчанника";
				break;
				case 129:
					$name = "Замшелый булыжник";
				break;
				case 130:
					$name = "Эндер сундук";
				break;
				case 131:
					$name = "Растяжка";
				break;
				case 132:
					$name = "Натянутая растяжка";
				break;
				case 133:
					$name = "Изумрудный блок";
				break;
				case 134:
					$name = "Еловые ступеньки";
				break;
				case 135:
					$name = "Берёзовые ступеньки";
				break;
				case 136:
					$name = "Ступеньки из тропического дерева";
				break;
				case 137:
					$name = "Командный блок";
				break;
				case 138:
					$name = "Маяк";
				break;
				case 139:
					$name = "Забор из булыжника";
				break;
				case 140:
					$name = "Цветочный горшок";
				break;
				case 141:
					$name = "Репа";
				break;
				case 142:
					$name = "Репа";
				break;
				case 143:
					$name = "Деревянная кнопка";
				break;
				case 144:
					$name = "Голова игрока";
				break;
				case 145:
					$name = "Наковальня";
				break;
				case 146:
					$name = "Сундук-ловушка";
				break;
				case 147:
					$name = "Золотая нажимная плита";
				break;
				case 148:
					$name = "Железная нажимная плита";
				break;
				case 149:
					$name = "Компроматор";
				break;
				case 150:
					$name = "Компроматор";
				break;
				case 151:
					$name = "Датчик дневного света";
				break;
				case 152:
					$name = "Блок красного камня";
				break;
				case 153:
					$name = "Руда кварца";
				break;
				case 154:
					$name = "Воронка";
				break;
				case 155:
					$name = "Блок кварца";
				break;
				case 156:
					$name = "Ступеньки из кварца";
				break;
				case 157:
					$name = "Доски";
				break;
				case 158:
					$name = "Деревянные полублоки";
				break;
				case 159:
					$name = "Цветная глина";
				break;
				case 160:
					$name = "Цветная стеклянная панель";
				break;
				case 161:
					$name = "Листва";
				break;
				case 162:
					$name = "Древесина акации";
				break;
				case 163:
					$name = "Ступеньки из акации";
				break;
				case 164:
					$name = "Ступеньки из тёмного дуба";
				break;
				case 165:
					$name = "Блок слизи";
				break;
				case 166:
					$name = "Крюк-кошка";
				break;
				case 167:
					$name = "Железный люк";
				break;
				case 168:
					$name = "Морской камень";
				break;
				case 169:
					$name = "Морской фонарь";
				break;
				case 170:
					$name = "Блок сена";
				break;
				case 171:
					$name = "Ковёр";
				break;
				case 172:
					$name = "Глина";
				break;
				case 173:
					$name = "Глина";
				break;
				case 174:
					$name = "Твёрдый лёд";
				break;
				case 175:
					$name = "Высокий цветок";
				break;
				case 176:
					$name = "Портальная пушка";
				break;
				case 177:
					$name = "Марсианский дрон";
				break;
				case 178:
					$name = "Активный датчик ночного света";
				break;
				case 179:
					$name = "Марсианский песчанник";
				break;
				case 180:
					$name = "Резной марсианский песчанник";
				break;
				case 181:
					$name = "Розовые кирпичи эндермира";
				break;
				case 182:
					$name = "Полублоки";
				break;
				case 183:
					$name = "Калитка";
				break;
				case 184:
					$name = "Калитка";
				break;
				case 185:
					$name = "Калитка";
				break;
				case 186:
					$name = "Калитка";
				break;
				case 187:
					$name = "Калитка";
				break;
				case 188:
					$name = "Командный блок";
				break;
				case 189:
					$name = "Командный блок";
				break;
				case 190:
					$name = "Террариан";
				break;
				case 191:
					$name = "Голова Джеба";
				break;
				case 192:
					$name = "Компьютер Mojang";
				break;
				case 193:
					$name = "Деревянная дверь";
				break;
				case 194:
					$name = "Деревянная дверь";
				break;
				case 195:
					$name = "Деревянная дверь";
				break;
				case 196:
					$name = "Деревянная длверь";
				break;
				case 197:
					$name = "Деревянная дверь";
				break;
				case 198:
					$name = "Земляная дорога";
				break;
				case 199:
					$name = "Рамка";
				break;
				case 200:
					$name = "Стебель хоруса";
				break;
				case 201:
					$name = "Розовые эндер кирпичи";
				break;
				case 202:
					$name = "Арбалет";
				break;
				case 203:
					$name = "Ступеньки из розового эндер кирпича";
				break;
				case 204:
					$name = "Месть ночи";
				break;
				case 205:
					$name = "Куб-кампаньон";
				break;
				case 206:
					$name = "Эндер кирпичи";
				break;
				case 207:
					$name = "Лёд";
				break;
				case 208:
					$name = "Палочка из эндер мира";
				break;
				case 209:
					$name = "Чёрный резной камень";
				break;
				case 210:
					$name = "Коричневый резной камент";
				break;
				case 211:
					$name = "Серый резной камень";
				break;
				case 212:
					$name = "Кровавый забор";
				break;
				case 218:
					$name = "Ящик шалкера";
				break;
				case 219:
					$name = "Терракотта";
				break;
				case 220:
					$name = "Терракотта";
				break;
				case 221:
					$name = "Терракотта";
				break;
				case 222:
					$name = "Терракотта";
				break;
				case 223:
					$name = "Терракотта";
				break;
				case 224:
					$name = "Терракотта";
				break;
				case 225:
					$name = "Терракотта";
				break;
				case 226:
					$name = "Терракотта";
				break;
				case 227:
					$name = "Терракотта";
				break;
				case 228:
					$name = "Терракотта";
				break;
				case 229:
					$name = "Терракотта";
				break;
				case 230:
					$name = "Табличка EDU edition";
				break;
				case 231:
					$name = "Терракотта";
				break;
				case 232:
					$name = "Терракотта";
				break;
				case 233:
					$name = "Терракотта";
				break;
				case 235:
				case 234:
					$name = "Терракотта";
				break;
				case 237:
				case 236:
					$name = "Бетон";
				break;
				case 238:
					$name = "Куб Пандоры";
				break;
				case 240:
					$name = "Стебель хоруса";
				break;
				case 241:
					$name = "Цветное стекло";
				break;
				case 242:
					$name = "Камера";
				break;
				case 243:
					$name = "Подзол";
				break;
				case 457:
				case 244:
					$name = "Свекла";
				break;
				case 245:
					$name = "Резчик камня";
				break;
				case 246:
					$name = "Красный реактор";
				break;
				case 247:
					$name = "Реактор";
				break;
				case 250:
					$name = "Барьер";
				break;
				case 256:
					$name = "Железная лопата";
				break;
				case 257:
					$name = "Железная кирка";
				break;
				case 258:
					$name = "Железный топор";
				break;
				case 259:
					$name = "Зажигалка";
				break;
				case 260:
					$name = "Яблочка";
				break;
				case 261:
					$name = "Лук";
				break;
				case 262:
					$name = "Стрела";
				break;
				case 263:
					$name = "Уголь";
				break;
				case 264:
					$name = "Алмаз";
				break;
				case 265:
					$name = "Железный слиток";
				break;
				case 266:
					$name = "Золотой слиток";
				break;
				case 267:
					$name = "Железный меч";
				break;
				case 268:
					$name = "Деревянный меч";
				break;
				case 269:
					$name = "Деревянная лопата";
				break;
				case 270:
					$name = "Деревянная кирка";
				break;
				case 271:
					$name = "Деревянный топор";
				break;
				case 272:
					$name = "Каменный меч";
				break;
				case 273:
					$name = "Каменная лопата";
				break;
				case 274:
					$name = "Каменная кирка";
				break;
				case 275:
					$name = "Каменный топор";
				break;
				case 276:
					$name = "Алмазный меч";
				break;
				case 277:
					$name = "Алмазная лопата";
				break;
				case 278:
					$name = "Алмазная кирка";
				break;
				case 279:
					$name = "Алмазный топор";
				break;
				case 280:
					$name = "Деревянная палка";
				break;
				case 281:
					$name = "Деревянная тарелка";
				break;
				case 282:
					$name = "Суп";
				break;
				case 283:
					$name = "Золотой меч";
				break;
				case 284:
					$name = "Золотая лопата";
				break;
				case 285:
					$name = "Золотая кирка";
				break;
				case 286:
					$name = "Золотй топор";
				break;
				case 287:
					$name = "Нитки";
				break;
				case 288:
					$name = "Перо";
				break;
				case 289:
					$name = "Порох";
				break;
				case 290:
					$name = "Деревянная мотыга";
				break;
				case 291:
					$name = "Каменная мотыга";
				break;
				case 292:
					$name = "Железная мотыга";
				break;
				case 293:
					$name = "Алмазная мотыга";
				break;
				case 294:
					$name = "Золотая мотыга";
				break;
				case 295:
					$name = "Пшеница";
				break;
				case 296:
					$name = "Саженцы";
				break;
				case 297:
					$name = "Хлебушек";
				break;
				case 298:
					$name = "Кожаный шлем";
				break;
				case 299:
					$name = "Кожаный нагрудник";
				break;
				case 300:
					$name = "Кожаные поножи";
				break;
				case 301:
					$name = "Кожаные ботинки";
				break;
				case 302:
					$name = "Кольчужный шлем";
				break;
				case 303:
					$name = "Кульчужный нагрудник";
				break;
				case 304:
					$name = "Штаны из кольчуги";
				break;
				case 305:
					$name = "Кольчужные ботинки";
				break;
				case 306:
					$name = "Железный шлем";
				break;
				case 307:
					$name = "Железный нагрудник";
				break;
				case 308:
					$name = "Железные поножи";
				break;
				case 309:
					$name = "Железные ботинки";
				break;
				case 310:
					$name = "Алмазный шлем";
				break;
				case 311:
					$name = "Алмазный нагрудник";
				break;
				case 312:
					$name = "Алмазные поножи";
				break;
				case 313:
					$name = "Алмазные ботинки";
				break;
				case 314:
					$name = "Золотой шлем";
				break;
				case 315:
					$name = "Золотой нагрудник";
				break;
				case 316:
					$name = "Золотые поножи";
				break;
				case 317:
					$name = "Золотые ботинки";
				break;
				case 318:
					$name = "Кремень";
				break;
				case 319:
					$name = "Сырая свинина";
				break;
				case 320:
					$name = "Жареная свинина";
				break;
				case 321:
					$name = "Картина";
				break;
				case 322:
					$name = "Золотое яблоко";
				break;
				case 323:
					$name = "Табличка";
				break;
				case 324:
					$name = "Дверь";
				break;
				case 325:
					$name = "Ведро";
				break;
				case 328:
					$name = "Вагонетка";
				break;
				case 329:
					$name = "Седло";
				break;
				case 330:
					$name = "Железная дверь";
				break;
				case 331:
					$name = "Красный камень";
				break;
				case 332:
					$name = "Снежок";
				break;
				case 333:
					$name = "Лодка";
				break;
				case 334:
					$name = "Кожа";
				break;
				case 336:
					$name = "Кирпич";
				break;
				case 337:
					$name = "Кусочек глины";
				break;
				case 338:
					$name = "Тростник";
				break;
				case 339:
					$name = "Лист бумаги";
				break;
				case 340:
					$name = "Книга";
				break;
				case 341:
					$name = "Липкий шарик";
				break;
				case 342:
					$name = "Вагонетка с сундуком";
				break;
				case 344:
					$name = "Яйцо";
				break;
				case 345:
					$name = "Компас";
				break;
				case 346:
					$name = "Удочка";
				break;
				case 347:
					$name = "Часы";
				break;
				case 348:
					$name = "Светящаяся пыль";
				break;
				case 349:
					$name = "Сырая рыба";
				break;
				case 350:
					$name = "Жареная рыба";
				break;
				case 351:
					$name = "Краситель";
				break;
				case 352:
					$name = "Косточка";
				break;
				case 353:
					$name = "Сахар";
				break;
				case 354:
					$name = "Тортик";
				break;
				case 355:
					$name = "Кровать";
				break;
				case 356:
					$name = "Повторитель";
				break;
				case 357:
					$name = "Печенюшка";
				break;
				case 358:
					$name = "Карта";
				break;
				case 359:
					$name = "Ножницы";
				break;
				case 360:
					$name = "Арбузик";
				break;
				case 362:
				case 361:
					$name = "Семена арбуза";
				break;
				case 363:
					$name = "Сырая говядина";
				break;
				case 364:
					$name = "Стейк";
				break;
				case 365:
					$name = "Сырая курица";
				break;
				case 366:
					$name = "Жареная курица";
				break;
				case 367:
					$name = "Гнилая плоть";
				break;
				case 368:
					$name = "Жемчуг эндера";
				break;
				case 369:
					$name = "Палочка ифрита";
				break;
				case 370:
					$name = "Слеза гаста";
				break;
				case 371:
					$name = "Золотой самородок";
				break;
				case 372:
					$name = "Адский нарост";
				break;
				case 373:
					$name = "Бутылочка водки";
				break;
				case 374:
					$name = "Пустой пузырёк";
				break;
				case 375:
					$name = "Глаз паука";
				break;
				case 376:
					$name = "Маринованный паучий глаз";
				break;
				case 377:
					$name = "Порошок ифрита";
				break;
				case 378:
					$name = "Шарик магмы";
				break;
				case 379:
					$name = "Варочная стойка";
				break;
				case 380:
					$name = "Котёл";
				break;
				case 381:
					$name = "Око эндера";
				break;
				case 382:
					$name = "Золотой арбузик";
				break;
				case 383:
					$name = "Яйцо спавна";
				break;
				case 384:
					$name = "Пузырёк опыта";
				break;
				case 385:
					$name = "Файрбол";
				break;
				case 388:
					$name = "Изумруд";
				break;
				case 389:
					$name = "Рамка";
				break;
				case 390:
					$name = "Цветочный горшок";
				break;
				case 391:
					$name = "Морковь";
				break;
				case 392:
					$name = "Картошка";
				break;
				case 393:
					$name = "Жареная картошка";
				break;
				case 394:
					$name = "Отравленный картофель";
				break;
				case 395:
					$name = "Пустая карта";
				break;
				case 396:
					$name = "Золотая морковь";
				break;
				case 397:
					$name = "Голова моба";
				break;
				case 398:
					$name = "Удочка с приманкой";
				break;
				case 399:
					$name = "Звезда нижнего мира";
				break;
				case 400:
					$name = "Тыквенный пирог";
				break;
				case 403:
					$name = "Зачарованная книга";
				break;
				case 404:
					$name = "Компроматор";
				break;
				case 405:
					$name = "Адский кирпич";
				break;
				case 406:
					$name = "Кварц";
				break;
				case 407:
					$name = "Вагонетка с динамитом";
				break;
				case 408:
					$name = "Вагонетка с воронкой";
				break;
				case 409:
					$name = "Призмарин";
				break;
				case 410:
					$name = "Воронка";
				break;
				case 411:
					$name = "Сырая крольчатина";
				break;
				case 412:
					$name = "Жареная крольчатина";
				break;
				case 413:
					$name = "Суп из кролика";
				break;
				case 414:
					$name = "Лапка кролика";
				break;
				case 415:
					$name = "Кожа кролика";
				break;
				case 416:
					$name = "Слабая конская броня";
				break;
				case 417:
					$name = "Железная конская броня";
				break;
				case 418:
					$name = "Золотая конская броня";
				break;
				case 419:
					$name = "Алмазная конская броня";
				break;
				case 420:
					$name = "Поводок";
				break;
				case 421:
					$name = "Бирка";
				break;
				case 422:
					$name = "Осколки кристалла";
				break;
				case 423:
					$name = "Мясо";
				break;
				case 424:
					$name = "Жареное мясо";
				break;
				case 426:
					$name = "Эндер-кристалл";
				break;
				case 427:
					$name = "Еловая дверь";
				break;
				case 428:
					$name = "Берёзовая дверь";
				break;
				case 429:
					$name = "Дверь из тропического дерева";
				break;
				case 430:
					$name = "Дверь из акации";
				break;
				case 431:
					$name = "Дверь из тёмного дуба";
				break;
				case 432:
					$name = "Хорус";
				break;
				case 433:
					$name = "Переработанный хорус";
				break;
				case 437:
					$name = "Драконье дыхание";
				break;
				case 438:
					$name = "Метающееся зелье";
				break;
				case 441:
					$name = "Ящик с водой";
				break;
				case 443:
					$name = "Вагонетка с командным блоком";
				break;
				case 444:
					$name = "Элитры";
				break;
				case 445:
					$name = "Панцирь шалкера";
				break;
				case 450:
					$name = "Тотем";
				break;
				case 452:
					$name = "Самородок железа";
				break;
				case 458:
					$name = "Семена свёклы";
				break;
				case 459:
					$name = "Борщ";
				break;
				case 460:
					$name = "Сырой окунь";
				break;
				case 461:
					$name = "Рыба-клоун";
				break;
				case 462:
					$name = "Рыба-фугу";
				break;
				case 463:
					$name = "Жареный окунь";
				break;
				case 466:
					$name = "Золотое яблоко Нотча";
				break;
				default:
					$name = $item->getName();
				break;
			}
		}
		return $name;
	}
}