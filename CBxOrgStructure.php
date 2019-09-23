<?php
/*
 * 2018.09.21 У битрикса есть особенность - один корневой элемент оргструктуры
 * в Азимуте с этим проблем не было, а тут появились
 *
 */

require_once "COrgStructureStorage.php";

class CBxOrgStructure extends COrgStructureStorage {
	/*
	 * Название информационного блока внутри которого хранится оргструктура
	 * Вроде как оно всегда "Подразделения"
	 */
	public static $StructIBlckName='Подразделения';
	
	/*
	 * Идентификатор информационного блока "Подразделения",
	 * изначально не определен, используется как кэш запроса
	 */
	private $StructIBlck=null;
	
	/*
	 * Возвращает id первого найденного по имени информационного блока
	 * @param sting $name искомое имя
	 * @return string id найденного элемента или null
	 */
	public static function getIBlockByName($name){
		//запрашиваем список по фильтру
		$res = CIBlock::GetList([],['NAME'=>$name,'ACTIVE'=>'Y'],true);
		//перебирая список возвращаем первый найденный
		while($ar_res = $res->Fetch()) return $ar_res['ID'];
		//или null
		return null;
	}
	
	/*
	 * @return strind id информационного блока в котором хранится оргструктура
	 */
	public function getStructIBlock(){
		if (
				is_null($this->StructIBlck)&&	//пусто в кэше
				is_null($this->StructIBlck=static::getIBlockByName(static::$StructIBlckName)) //и прямой запрос ничего не дает
			)	die("Не удалось обнаружить инфоблок ".static::$StructIBlckName."\n"); //не можем продолжать работать без этих данных
		return $this->StructIBlck;
	}
	
	
	/*
	 * При создании тут же подцепляемся к битриксу и загружаем оргструктуру
	 */
	public function __construct(){
		$arFilter = array('IBLOCK_ID' => static::getStructIBlock());
		$arSelect = [];
		$rsSection = CIBlockSection::GetTreeList($arFilter, $arSelect);
		while($arSection = $rsSection->GetNext())
			$this->initItem($arSection,'ID');

		//var_dump($this->data);
		///die('bxorg');
			echo count($this->data)." items loaded in Bitrix Org storage \n";
	}
	
	/*
	 * Загружает один элемент из битрикса
	 * релоад названо потомучто для загрузки по ID надо знать этот ID, что как бы намекает
	 * @return bool успех или неудача загрузки
	 */
	public function reloadItem($id){
		$res = CIBlockSection::GetByID($id);
		if($ar_res = $res->GetNext()){
			$this->initItem($ar_res,'ID');
			return true;
		} else
			return false;
	}
	
	/*
	 * Чистит параметры от генеренных полей и от ИД
	 * удобно для передачи как набор для создания или корректировки элемента
	 * @param array $arFields исходный набор
	 * @return array очищенный набор (или тот же самый, если нечего чистить)
	 */
	public static function cleanParams($arFields){
		//если мы передали параметры с генеренными вариантами ~PARAMETER - почистим их
		foreach ($arFields as $key=>$data)
			if (substr($key,0,1)=='~') unset($arFields[$key]);
			//удалим id
			if (isset($arFields['ID'])) unset($arFields['ID']);
			return $arFields;
	}
	
	/*
	 * добавляет элемент в оргструктуру битрикс
	 * не в наше внутреннее хранилище, а в сам Битрикс, а потом его подгружает оттуда
	 * @param array $params параметры нового элемента
	 * @return strind id нового элемента
	 */
	public function addItem($params){
		//почистим
		$params=CbxOrgStructure::cleanParams($params);
		
		//echo "Adding ".print_r($params,true)."\n";
		
		//добавляем через АПИ битрикса
		$bs = new CIBlockSection;
		$ID = $bs->Add($params);
		
		//получилось?
		if(!($res = ($ID>0)))
			echo $bs->LAST_ERROR; //нет - скажем почему
			else
				$this->reloadItem($ID); //да - загрузим иформацию по этому элементу
				
				//освобождаем память
				unset($bs);
				
				//возвращаем ИД созданого элемента
				return $res?$ID:null;
	}
	
	/*
	 * обновляет подразделение ID полями arFields
	 */
	public function updItem($id,$arFields){
		//почистим
		$arFields=static::cleanParams($arFields);
		
		//echo "Updating $id with ".print_r($arFields,true)."\n";
		
		//пишем
		$bs = new CIblockSection();
		$res = $bs->Update($id,$arFields);
		
		//проверяем
		if(!$res) echo $bs->LAST_ERROR;
		//die('Uh oh lets check what i have done...');
		
		//чистим память
		unset ($bs);
		
		//возвращаем ответ
		if ($res){ //если все до сих пор шло нормально то
			$this->reloadItem($id); //перезагрузим обновленный элемент
			return $this->ckItem($id, $arFields); //вернем соответствие загруженного элемента требуемым параметрам
		} else return $res;
	}
	
	
	/*
	 * Проверяет, что у итема ID все перечисленные в $params параметры соответствую переданным
	 * если нет, то устанавливает
	 */
	public function correctItem($id,$params){
		//либо все ок потомучто все уже как надо, либо потомучто мы все так пофиксили, либо все плохо)
		return $this->ckItem($id, $params)||$this->updItem($id, $params);
	}

	/*
	 * Генерирует список полей, которые должны быть в битриксе у секции с переданными параметрами
	 * @param string $id идентификатор элемента
	 * @param string $name имя элемента
	 * @param string $parent идентификатор родителя
	 * @return array список полей с заполненными значениями, которые должны быть в битриксе
	 */
	public function bxGenFields($id,$name,$parent){
		return [
			"ACTIVE" => 'Y',
			"IBLOCK_ID" => $this->getStructIBlock(),
			"IBLOCK_SECTION_ID" => $this->findIDBy('CODE',$parent),
			"NAME" => $name,
			"CODE" => $id
		];
	}

}


?>
   