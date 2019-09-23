<?php
/*
 * Класс хранилище оргструктуры
 * На самом деле абстрактный класс не используется непосредственно
 * вместо этого из него наследуются и кастомизируются 5 хранилищ 
 * - оргструктуры в битриксе и САП
 * - пользователей в битриксе и САП
 * - групп пользователей в битриксе
 * 
 *	В битриксе значит оргструктура устроена таким образом:
 *	Внутри каждого объекта "Информационный блок" имеются элементы и секции
 *	оргстуктура как раз строится из секций внутри информационного блока "подразделения"
 *	элементы там не используются
 *	
 * Элемент пользователя в САП грузится вот из такого итема:
<item>
    <Pernr>00000869</Pernr> - Табельный номер
    <Ename>Баландина Анна Сергеевна</Ename>
    <Nachn>Баландина</Nachn>
    <Vorna>Анна</Vorna>
    <Midnm>Сергеевна</Midnm>
    <Orgeh>10000032</Orgeh> - Узел в оргструктуре
    <Orgtx>Дирекция по продажам</Orgtx> - Подразделение
    <Uname/>	- Логин
    <Doljnost>Интеграция: штатная должн</Doljnost> - Должность
    <Uvolen>X</Uvolen>	- Признак увольнения (должен стоять X)
    <Persg>1</Persg>	- Тип трудоустройства (1 - В штате, 2 - Совместители внутрен, 3 - Совместители внешние, 4 - ДГПХ, 5 - Инвалиды, 6 – Пенсионеры, 7 – Несовершеннолетние)
    <Werks>1102</Werks> - Завод - численное представление
    <NameF>АО "Азимут" в г.Москва</NameF> - Завод текстовая рашифровка
</item>
 *
 * Оргструктура из такого:
<item>
     <Seqnr>71</Seqnr> - номер узла в дереве
     <Level>3</Level>
     <Otype>O</Otype>
     <Objid>10000304</Objid> - идентификатор подразделения (использвется для синхронизации со структурой в портале)
     <Orgtx>Бухгалтерия</Orgtx> - имя подразделения
     <Pdown>0</Pdown> - Дочерний узел, хз зачем он, ведь дочерних несколько а тут только одно значение. Я не использую
     <Dflag/>
     <Vcount>0</Vcount>
     <Pnext>72</Pnext>
     <Pup>22</Pup> - Родительский узел. Используется для выстраивания дерева
     <Pprev>70</Pprev>
     <Vrsign>B</Vrsign>
     <Vrelat>002</Vrelat>
     <Vpriox>AM</Vpriox>
     <Vistat>1</Vistat>
     <Vbegda>2016-06-01</Vbegda>
     <Vendda>9999-12-31</Vendda>
     <Vprozt>0.0</Vprozt>
     <Vadata/>
     <Subrc>0</Subrc>
     <Rflag/>
     <Sbegd>2017-05-23</Sbegd>
     <Sendd>2017-05-23</Sendd>
     <Vsign/>
     <Vseqnr>000</Vseqnr>
</item>
 *
 * v1.2 Добавлена корневая процедура сравнения COrgStructureStorage::fieldCompareInt для поиска по
 *		целочисленным значениям. Удобно при поиске по табельному номеру, который сап отдает со 
 *		впередиидущими нулями.
 * v1.1 Исправления по поиску, все поисковые фукнции завязаны на корневую функцию сравнения
 * 		COrgStructureStorage::fieldCompare, практически во все функции поиска и фильтрации добавлены
 * 		параметры case и soft которые отключают проверку регистра или включают равенство букв е и ё
 * v1.0 Initial release
 * 
 */


define('EMPLOYMENTSEARCH_MANY_RESULTS',2);
define('EMPLOYMENTSEARCH_MANY_RESULTS_TEXT','Запрос обнаружил более одного сотрудника с основым трудоустройством');


/*
 * класс для управления массивом элементов - списков полей и значений
 * от него мы породим структуры сапа и битрикса
 * v2.0 - добавлена работа с файлами (фото пользователей)
 */
class COrgStructureStorage {
	/*
	 * хранилище структуры - массив элементов
	 * @access protected
	 */
	protected $data = null;
	
	/*
	 * @return массив ключей загруженных элементов
	 */
	public function getIds(){return array_keys($this->data);}
	
	/*
	 * Проверяет наличие элемента с ID
	 * @param string $id ключ для проверки
	 * @return bool признак наличия объекта с таким ключем
	 */
	public function exist($id){return isset($this->data[$id]);}
	
	/*
	 * Конвертирует объектно хранимые структуры элементов в массиво-хранимые
	 * @param object $obj
	 * @return array поля элемента оргструктуры
	 */
	public static function itemFromObject($obj) {
		$items = get_object_vars($obj);
		foreach ($items as $key=>$item)
			if (is_object($item)) $items[$key] = COrgStructureStorage::itemFromObject($item);
		return $items;
	}
	
	/*
	 * Формирует массив элементов с ключами по полю $id из массива объектов (удобно при загрузке из XML)
	 * @param array $obj массив объектов
	 * @param string $id поле которое будет ключом в выходном массиве
	 * @return array список элементов
	 */
	public static function dataFromObjlist($objlist,$id=null) {
		$items = [];
		foreach ($objlist as $item) {
			$arItem=COrgStructureStorage::itemFromObject($item);
			if (is_null($id)) { 
				//если ключей не предусмотрено то просто по порядочку заполняем
				$items[]=$arItem;
			} elseif (is_array($id)) { 
				//если передан массив, значит ключ составной
				$key=[];
				foreach ($id as $subkey) { //массив из ключевых из полей эмента
					if (isset($arItem[$subkey])) $key[]=$arItem[$subkey];
				}
				$items[implode('-',$key)]=$arItem;
			} elseif (isset($arItem[$id])) {
				//иначе заполняем по переданному ключу, если он вообще есть
				$items[$arItem[$id]]=$arItem;
			}
		}
		return $items;
	}

	
	/*
	 * Грузит данные из XML файла
	 * @param string $filename файл для загрузки
	 * @param string $child имя дочерней структуры для загрузки (в общей структуре есть и оргструктура и список сотрудников)
	 * @param string $item имя объекта-элемента списка в структуре
	 * @return bool успех загрузки данных
	 */
	public function loadFromXml($filename,$child,$item){
		//грузим данные САП из файла:
		$xml=simplexml_load_file($filename);
		return COrgStructureStorage::dataFromObjlist($xml->$child->item,$item);
	}


	/*
	 * Грузит данные из JSON файла
	 * @param string $filename файл для загрузки
	 * @param string $child имя дочерней структуры для загрузки (в общей структуре есть и оргструктура и список сотрудников)
	 * @param string $item имя объекта-элемента списка в структуре
	 * @return bool успех загрузки данных
	 */
	public function loadFromJson($filename,$child,$item){
		//грузим данные САП из файла:
		if (($rawdata=file_get_contents($filename))===false) return false;
		$jsondata=json_decode($rawdata);
		return COrgStructureStorage::dataFromObjlist($jsondata->$child->item,$item);
	}

	/**
	 * Правим номер телефона
	 */
	public static function correctPhoneNumber($input){
		//заменяем все разделители на запятую
		$input=str_replace([';',"\n","\t"],	',',	$input);
		//убираем весь прочий мусор
		$iteration0=preg_replace('/[^0-9, ]+/','',$input);

		//разбиваем на токены с разделителем ,
		$iteration1=explode(',',$iteration0);

		//echo(implode(',',$iteration1)."\n");
		//далее уже собираем целые номера
		$phones=[''];
		$ph=0;//номер телефона, который собираем

		//формируем уже конкретно список телефонов
		//перебираем все телефонные номера
		foreach ($iteration1 as $token) if (strlen(trim($token))) {
			//разбиваем их на секции разделенные пробелами
			foreach (explode(' ',trim($token)) as $word) if (strlen(trim($word))) {
				$phones[$ph].=trim($word);
				if (strlen($phones[$ph])>=10) {
					if (strlen($phones[$ph])==10) {
						//если набралось 10 цифр, то это наверняка федеральный номер без 8ки или кода страны 7
						$phones[$ph] = '8' . $phones[$ph];
					}
					$phones[++$ph]='';
				}
			}

			//если получился номер недоносок (не хватает цифр)
			if (strlen($phones[$ph])<11) {

				if (strlen($phones[$ph])==10){
					//если набралось 10 цифр, то это наверняка федеральный номер без 8ки или кода страны 7
					$phones[$ph]='8'.$phones[$ph];
				} elseif ($ph) {
					//если остались какието знаки ни туда ни сюда и есть предыдущий номер - добавляем их к нему
					$phones[$ph-1].=$phones[$ph];
					$phones[$ph]='';
				}
			}
		}

		//пустой номер телефона убираем
		foreach ($phones as $ph=>$phone) if (!strlen(trim($phones[$ph]))) unset($phones[$ph]);


		foreach ($phones as $ph=>$phone) {
			//заменяем 810 в начале строки (выход на МГ) на +
			if (substr($phone,0,3)=='810') {
				$phone='+'.substr($phone,3);
			}
			//выход на МГ заменяем на код страны 7
			if (substr($phone,0,1)=='8') {
				$phone='7'.substr($phone,1);
			}
			//добавляем + (выход на МГ) там где его нет
			if (substr($phone,0,1)!=='+') {
				$phone='+'.$phone;
			}
			$phones[$ph]=$phone;
		}
		//var_dump($phones);
		//die();
		return $phones;
	}

	/*
	 * Грузит данные из CSV файла
	 * @param string $filename файл для загрузки
	 * @param string $fields список полей в файле ['first_name','second',age]
	 * @param string $key имя ключевого поля
	 * @param bool  $skiphdr пропускать первую строку файла(заголовок)
	 * @return bool успех загрузки данных
	 */
	public function loadFromCsv($filename,$fields,$key,$skiphdr=true){
		//грузим данные САП из файла:
		if (($rawdata=file_get_contents($filename))===false) {
			die ("error loading $filename\n");
			return false;
		}
		$mobile_pos=array_search('Mobile',$fields);
		//if ($mobile_pos) die('mobile');
		//разбираем на строки
		$strings=explode("\n",$rawdata);
		//готовим вместилище данных
		$ardata=[];
		//перебираем строки
		foreach ($strings as $idx=>$string) {
			if (!$idx&&$skiphdr) continue; //если указано пропустить шапку - пропускаем
			//готовим новый элемент
			$item=[];
			//перебираем столбцы
			$src=explode(';',$string);
			if ($mobile_pos) {
				//Хитрый хак:
				//если у нас в полях есть мобильник и после експлода полей получилось больше чем нужно
				//то считаем что лишние поля образовались как раз в мобильном и схлопываем лишние в это поле
				if (count($src)>count($fields)) while (count($src)>count($fields)) {
					$src[$mobile_pos]=implode(';',[$src[$mobile_pos],$src[$mobile_pos+1]]);
					for ($i=$mobile_pos+1;$i<count($src)-1;$i++) $src[$i]=$src[$i+1];
					unset($src[count($src)-1]);
				}
				//если есть номер телефона - корректируем его
				$src[$mobile_pos]=implode(',',static::correctPhoneNumber($src[$mobile_pos]));
			}
			foreach ($src as $fidx=>$fvalue) {
				//если для столбца есть имя - используем его, иначе индекс
				$field=isset($fields[$fidx])?$fields[$fidx]:$fidx;
				$item[$field]=trim($fvalue);
			}

			//определяем ключ этой строки: или ключевое поле или номер строки
			$id=is_null($key)?$idx:$item[$key];
			if (strlen($id)) $ardata[$id]=$item;
		}
		return $ardata;
	}


	/*
	 * кладет полученный элемент битрикса во внутренне хранилище с ключем по $key
	 * добавляет или заменяет по обстоятельствам
	 * @param array $item набор параметров элемента
	 * @param string $key ключевое поле
	 */
	public function initItem($item,$key) {
		if (strlen($key)==0) return null;
		$this->data[$item[$key]]=$item;
	}
	
	/*
	 * Сравнивает строковое поле элемента с переданным значением
 	 * @param array $item указатель на проверяемый элемент
	 * @param string $field проверяемое поле
	 * @param string $value проверяемое значение
	 * @param bool $case чуствительность к регистру при проверке
	 * @param bool $soft флаг мягкого сравнения, если установлен, то е и ё считаются как один символ
	 * @return bool успех сравнения
	 */
	public static function fieldCompare(&$item,$field,$value,$case=false,$soft=false){
		if (is_array($value)) $value=implode(',',$value);
		$value=html_entity_decode((string)$value);
		if (!isset($item[$field])) 
			return strlen($value)==0;
		else {
			if (is_array($item[$field])) $compare=html_entity_decode(implode(',',$item[$field]));
			else $compare=html_entity_decode((string)$item[$field]);
		}
		if (!$case) { //если отключена чуствительность к регистру, то приводим все к нижнему
			$value=mb_strtolower($value);
			$compare=mb_strtolower($compare);
		} 
		
		if ($soft){ //если отключено строгое написание, то подменяем буквы
			$hard=['Ё','ё']; //строгое написание букв
			$soft=['Е','е']; //нестрогое
			$value=str_replace($hard,$soft,$value);
			$compare=str_replace($hard,$soft,$compare);
			$res=strcmp($value,$compare); 
			//echo "$value vs $compare = $res \n";
			//if ($res===0) return true;
				
		}

		//$res=strcmp($value,$compare);
		//echo "$value vs $compare = $res \n";
		//if ($res===0) return true;
		return (strcmp($value,$compare)===0);
	}

	/*
	 * Сравнивает целочисленное поле элемента с переданным значением
	 * @param array $item указатель на проверяемый элемент
	 * @param string $field проверяемое поле
	 * @param int $value проверяемое значение
	 * @return bool успех сравнения
	 */
	public static function fieldCompareInt(&$item,$field,$value){
		if (!isset($item[$field])) return false;
		return ((int)$value===(int)$item[$field]);
	}
	
	
	/*
	 * Проверяет попадает ли итем в фильтр
	 * @param string $id идентификатор проверяемого итема
	 * @param array $filter набор поле=>значение с которыми будут сравниваться поля элемента
	 * @param bool $and если установлено в true то режим проверки AND (необходимо совпадение всех полей) иначе OR (необходимо хотябы одно совпадение)
	 * @param bool $case чуствительность к регистру при поиске
	 * @param bool $soft флаг мягкого сравнения, если установлен, то е и ё считаются как один символ
	 * @return bool успех сравнения
	 */
	public function filterItem($id,$filter,$and=true,$case=false,$soft=false) {
		foreach ($filter as $field=>$value) {
			$test=COrgStructureStorage::fieldCompare($this->data[$id], $field, $value, $case, $soft);
			if ($and !== $test) return $test;
			//true (строгое сравнение) 		!== false (неудача) возвращает false (неудача сравнения) 
			//false (нестрогое сравнение)	!== true (удача) 	возвращает true (удача сравнения) 
		}
		//echo "got $value";
		return $and; //при строгом сравнении мы добираемся сюда при отсутствии неудач выше, а при нестрогом при отсутствии удач
	}
	
	/*
	 * Ищет элементы по полю
	 * @param string $filter фильтр типа поле=>значение, по которому ищем совпадения
	 * @param bool $and если установлено в true то режим проверки AND (необходимо совпадение всех полей) иначе OR (необходимо хотябы одно совпадение)
	 * @param bool $case чуствительность к регистру при поиске
	 * @param bool $soft флаг мягкого сравнения, если установлен, то е и ё считаются как один символ
	 * @return array идентификаторов найденных элементов
	 */
	public function findIdsBy($filter,$and=true,$case=false,$soft=false){
		$res=[];
		//перебираем все элементы
		foreach ($this->data as $id=>$item)
			if ($this->filterItem($id, $filter, $and, $case, $soft))
				$res[]= $id;
		return $res;
	}
	
	/*
	 * Ищет элемент по полю
	 * @param string $field имя поля по которому делается поиск
	 * @param string $field значение указанного поля
	 * @param bool $case чуствительность к регистру при поиске
	 * @param bool $soft флаг мягкого сравнения, если установлен, то е и ё считаются как один символ
	 * @return string objid найденного элемента или null
	 */
	public function findIdBy($field,$value,$case=false,$soft=false){
		//если нам забыли передать имя поля или искомое значение - ничего не будем искать
		if (is_null($value)||is_null($field)) return null;
		
		//перебираем все элементы
		foreach ($this->data as $id=>$item){
			//если такое поле есть и значения совпали, то это то что мы ищем
			if (COrgStructureStorage::fieldCompare($item,$field,$value,$case,$soft)) return $id;
		}
		//echo "Cannot find ID by $field = $value\n";
		return null;
	}

	/*
	 * Ищет элемент по полю принимающему целочисленные значения (Integer)
	 * @param string $field имя поля по которому делается поиск
	 * @param int $field значение указанного поля
	 * @return string objid найденного элемента или null
	 */
	public function findIdByInt($field,$value){
		//если нам забыли передать имя поля или искомое значение - ничего не будем искать
		if (is_null($value)||is_null($field)) return null;
		
		//перебираем все элементы
		foreach ($this->data as $id=>$item){
			//если такое поле есть и значения совпали, то это то что мы ищем
			if (COrgStructureStorage::fieldCompareInt($item,$field,$value)) return $id;
		}
		//echo "Cannot find ID by $field = $value\n";
		return null;
	}
	
	/*
	 * Возвращает значение поля $field элемента $id
	 * Если $id или $field не существуют, то возвращает $default
	 * @param string $id
	 * @param string $field
	 * @param string $default
	 */
	public function getItemField($id,$field,$default=null){
		if (isset($this->data[$id])&&isset($this->data[$id][$field])) return $this->data[$id][$field];
		return $default;
	}
	
	/*
	 * возвращает значение поля $returnField объекта, у которого в $getField содержится $needle
	 * @param string $getField поле для поиска
	 * @param string $needle значение для поиска
	 * @param string $returnField поле, значение которого вернуть
	 * @param bool $case чуствительность к регистру при поиске
	 * @param bool $soft флаг мягкого сравнения, если установлен, то е и ё считаются как один символ
	 */
	public function getFieldBy($getField,$needle,$returnField,$case=false,$soft=false){
		return $this->getItemField(
				$this->findBy($getField,$needle,$case,$soft),
				$returnField
				);
	}
	
	public function getJsonData(){
		return json_encode(array_values($this->data),JSON_UNESCAPED_UNICODE);
	}
	
	/*
	 * Проверяет, что у итема ID все перечисленные в $params параметры соответствую переданным
	 * @param string $id идентификатор элемента
	 * @param array $params массив поле=>значение для сравнения с элементом
	 */
	public function ckItem($id,$params){
		foreach ($params as $name=>$value)
			if (	
					(!COrgStructureStorage::fieldCompare($this->data[$id],$name,$value,true))&& //проверяем и оригинальные значения
					(!COrgStructureStorage::fieldCompare($this->data[$id],$name,$value,true))//и с HTML entities
				) {
					$current=$this->getItemField($id,$name);
					$val1=is_array($current)?(json_encode($current,JSON_UNESCAPED_UNICODE)):$current;
					$val2=is_array($value)?(json_encode($value,JSON_UNESCAPED_UNICODE)):$value;
					echo "Item #$id @$name: got [$val1] instead [$val2]\n";
					return false;
				}
		
		return true;
	}
	
	/*
	 * Возвращает элемет со всеми полями
	 */
	public function getItem($id){
		return isset($this->data[$id])?$this->data[$id]:null;
	}
	
	
}


?>
   