<?php
require_once "COrgStructureStorage.php";

/*
 * Класс для взаимодействия структурой из САП
 * в контексте этого скритпа - данные САП опорные, и только для чтения
 */
class CSapOrgStructure extends COrgStructureStorage{
	
	/*
	 * Ссылка на внешний объект CbxOrgStructure
	 * нужен для проверки соответствия
	 */
	private $bx=null;
	
	/*
	 * прицепляет объет битрикс структуры для взаимодейстивия с ним
	 * @param CbxOrgStructure $bx объект структуры подразделений в битриксе
	 */
	public function attachBx(CbxOrgStructure &$bx){$this->bx=$bx;}
	
	/*
	 * Грузит данные из XML файла
	 * @param string $filename файл для загрузки
	 * @return bool успех загрузки данных
	 */
	public function loadFromXml($filename){
		$this->data=COrgStructureStorage::loadFromXml($filename,'OOrgStruct','Objid');
		//echo count($this->data)." items loaded in SAP Org storage\n";
		//var_dump($this->data);
		return is_array($this->data);
	}

	/*
	 * Грузит данные из JSON файла
	 * @param string $filename файл для загрузки
	 * @return bool успех загрузки данных
	 */
	public function loadFromJson($filename){
		$this->data=COrgStructureStorage::loadFromJson($filename,'OOrgStruct','Objid');
		//echo count($this->data)." items loaded in SAP Org storage\n";
		//var_dump($this->data);
		return is_array($this->data);
	}
	
	/*
	 * Ищет родительский элемент для $id
	 * @param string $id, чей родитель нас интересует
	 * @return string идентификатор родтельского для $id элемента или null, если родителя нет
	 */
	public function getParent($id){
		//обнаруживаем есть ли у элемента id чтото в поле pup
		//там должен идентификатор seqnr родительского элемента
		if(($parent=$this->getItemField($id,'Pup',0))==0) return null;
		
		//если есть, то ищем id элемента, у которого это значение в поле seqnr
		//echo "Parent [".$this->findIdBy('Seqnr',$parent).'] <-- ['.$id."]\n";
		return $this->findIdBy('Seqnr',$parent);
	}
	
	/*
	 * проверяет наличие указанного подразделения в битриксе
	 * при отсутствии автоматически пытается такое подразделение добавить
	 * @param string $id идентификатор элемента, чье соответствие надо найти в битриксе
	 * @return string id подразделения в структуре битрикса или
	 */
	public function ckBxItemExist($id){
		//если битрикс не подключен - работать не можем
		if (is_null($this->bx)) die('No bx object attached!');
		
		//если уже проверен - значит все ок
		if (!is_null($res=$this->getItemField($id,'bx_item_id'))) return $res;
		
		//если есть предок - проверяем сначала его, ибо проверять можно только от корня
		if (!is_null($parent=$this->getParent($id)))
			if (!$this->ckBxItemExist($parent)) return null;
			
			//если в структуре битрикса такого подразделения нет - косяк
			//собственно ради этого момента и вся проверка)
			if (
			is_null($bxID=$this->bx->findIDBy('CODE',$id))&&	//не нашли
			is_null($bxID=$this->bx->addItem($this->bxGenFields($id))) //и создать не получилось
			) return null;
			
			return $this->data[$id]['bx_item_id']=$bxID;
	}
	
	/*
	 * проверяет наличие у этого элемента соответствия в битриксе с правильно проставленными полями
	 * если подразделения нет - создает (через метод выше), если оно некорректное - правит
	 * @param string $id идентификатор элемента, чье соответствие надо найти в битриксе
	 * @return bool
	 */
	public function ckBxItemFields($id){
		//если битрикс не подключен - работать не можем
		if (is_null($this->bx)) die('No bx object attached!');
		
		//если уже проверен - значит все ок
		if ($this->getItemField($id,'checked_bxfields_ok')) return true;
		
		//находим идентификатор в битриксе
		if (is_null($bxID = $this->ckBxItemExist($id))) return false;
		
		//если есть предок - проверяем сначала его, ибо проверять можно только от корня
		if (!is_null($parent=$this->getParent($id)))
			if (!$this->ckBxItemFields($parent)) return false;
			
			//возвращаем и кэшируем проверку (с учетом попытки исправления) полей в битриксе
			return $this->data[$id]['checked_bxfields_ok']=$this->bx->correctItem($bxID, $this->bxGenFields($id));
	}
	
	/*
	 * Генерирует список полей, которые должны быть в битриксе у секции соответствующей этому итему в САП
	 * @param string $id идентификатор элемента
	 * @return array список полей с заполненными значениями, которые должны быть в битриксе
	 */
	public function bxGenFields($id){
		return Array(
				"ACTIVE" => 'Y',
				"IBLOCK_ID" => $this->bx->getStructIBlock(),
				"IBLOCK_SECTION_ID" => $this->bx->findIDBy('CODE',$this->getParent($id)),
				"NAME" => $this->getItemField($id,'Orgtx'),
				"CODE" => $id
				);
	}
	
	/*
	 * Возвращает полный путь до корня дерева
	 * не тестировалось. использовать с осторожностью
	 * @param string $id - конечный узел дерева от которого движемся к корню
	 */
	public function getItemFullPath($id){
		$cnt=1; //счетчик узлов пути
		$current=$id; //текущий узел
		$straight[$cnt-1]=$this->getItem($current);
		
		while($current=$this->getParent($current)){
			$cnt++;
			$straight[$cnt-1]=$this->getItem($current);
		}
		
		for ($i=0;$i<$cnt;$i++){
			$reverse[$i]=$straight[$cnt-$i-1];
		}
		
		return $reverse;
	}
}



?>
   