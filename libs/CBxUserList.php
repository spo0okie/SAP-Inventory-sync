<?php
/*
 * Класс хранилище гструктуры
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


class CBxUserList extends COrgStructureStorage {
	
	/*
	 * Список пользователей сап
	 */
	private $sap=null;
	
	public function attachSap(&$sap){$this->sap=$sap;}
	
	/*
	 * При создании загружает список пользователей
	 */
	public function __construct(){
		$by='ID';
		$sort='asc';
		$usrList=CUser::GetList($by,$sort,[],['SELECT'=>['UF_DEPARTMENT']]);
		while($ob = $usrList->GetNext(false,false))
			$this->initItem ($ob,'ID');
			echo count($this->data)." items loaded in Bitrix Usr storage \n";
			//print_r($this->data);
	}
	
	/*
	 * Загружает один элемент из битрикса
	 * релоад названо потомучто для загрузки по ID надо знать этот ID, что как бы намекает
	 * @return bool успех или неудача загрузки
	 */
	public function reloadItem($id){
		$res = CUser::GetByID($id);
		if($ar_res = $res->Fetch()){
			$this->initItem($ar_res,'ID');
			return true;
		} else
			return false;
	}
	
	/*
	 * Возвращает массив идентификаторов групп в которые входит пользователь
	 * @param string $id пользователя
	 * @return array список идентификаторов групп, в которые входит пользователь
	 */
	public static function getUserGroups($id){
		$res = CUser::GetUserGroupList($id);
		$grps = [];
		while ($arGroup = $res->Fetch()) {
			//print_r($arGroup);
			if (!is_array($arGroup)) $grps[]=$arGroup;
			elseif (isset($arGroup['GROUP_ID'])) $grps[]=$arGroup['GROUP_ID'];
		}
		return $grps;
	}

	/*
	 * Проверяет присутствие пользователя в группе
	 * @param string $user идентификатор пользователя
	 * @param array $groups список групп в которых должен быть пользователь. можно передать одну не в виде массива
	 * @return bool признак наличия пользователя во всех переданных группах
	 */
	public static function checkUserGroups($user,$groups){
		//echo "getting $user groups\n";
		//print_r($user_groups);
		if (!is_array($groups)) $groups=[$groups]; //определимся, что мы работаем именно с массивом, хотябы из одного элемента
		if (is_null($user)||!count($groups)) return true; //если список пустой или пользователя нет, то проверять нечего
		
		$user_groups= CBxUserList::getUserGroups($user);
		//echo "go into loop...\n";
		foreach ($groups as $grp) 
			if (!in_array($grp, $user_groups)) return false;
		return true;
	}
	
	/*
	 * Добавление пользователя в группы(у)
	 * @param string $user идентификатор пользователя
	 * @param array $groups список групп в которых должен быть пользователь. можно передать одну не в виде массива
	 */
	public static function addUserGroups($user,$groups){
		// привязка пользователя с кодом 10 дополнительно к группе c кодом 5
		if (!is_array($groups)) $groups=[$groups]; //определимся, что мы работаем именно с массивом, хотябы из одного элемента
		echo "adding $user groups [".implode(',',$groups)."] ";
		$arGroups = CBxUserList::getUserGroups($user);
		$arGroups = array_merge($arGroups,$groups);
		CUser::SetUserGroup($user, $arGroups);
		return CBxUserList::checkUserGroups($user, $groups);
	}
	
	/*
	 * Проверка наличия и добавление если нужно пользователя в группы(у)
	 * @param string $user идентификатор пользователя
	 * @param array $groups список групп в которых должен быть пользователь. можно передать одну не в виде массива
	 */
	public static function fixUserGroups($user,$groups){
		return (CBxUserList::checkUserGroups($user, $groups))||(CBxUserList::addUserGroups($user, $groups));
	}
	
	/*
	 * Ищет дубликаты пользователя
	 * @param string $id пользователя
	 * @return array список идентификаторов пользователей с таким же логином и именами
	 */
	public function findDupes($id){
		$name=$this->getItemField($id,'NAME');
		$lname=$this->getItemField($id,'LAST_NAME');
		$login=$this->getItemField($id,'LOGIN');
		$found=[];
		foreach ($this->getIds() as $cmp)
			if (($cmp!=$id)&&(
					//если совпадает ФИО 
					(($this->getItemField($cmp,'NAME')==$name)&&($this->getItemField($cmp,'LAST_NAME')==$lname))||
					//или логин
					($this->getItemField($cmp,'LOGIN')==$login)
					)) $found[]=$cmp;
					return $found;
	}
	
	
	/*
	 * Ищем объект в сапе по имени
	 */
	public function getSapID($id){
		//если внутри пользователя записан его номер сотрудника и по нему он ищется - то его и вернем
		//echo 'a'; ob_flush();
		if (!is_null($sapID=$this->getItemField($id,'XML_ID'))&&($this->sap->exist($sapID))) return $sapID;
		
		//ищем сначала по ФИО
		//echo $this->getItemField($id,'LAST_NAME').' '.$this->getItemField($id,'NAME'); ob_flush();
		try {
			$sapID=$this->sap->getMainEmployment(['Ename'=>($this->getItemField($id,'LAST_NAME').' '.$this->getItemField($id,'NAME'))]);
		} catch (Exception $e) {
			//если таких двое то прикидываемся как будто ничего и не нашли
			$sapID=null;
		}
		//потом по ИОФ
		//echo 'c'; ob_flush();
		if (!is_null($sapID)) return $sapID;
		try {
			$sapID=$this->sap->getMainEmployment(['Ename'=>($this->getItemField($id,'NAME').' '.$this->getItemField($id,'LAST_NAME'))]);
		} catch (Exception $e) {
			$sapID=null;
		}
		//echo 'd'; ob_flush();
		return $sapID;
	}
	
	/*
	 * обновляет подразделение ID полями arFields
	 * @param string $id идентификатор пользователя
	 * @param array $arFields набор полей для записи
	 * @return bool успех операции
	 */
	public function updItem($id,$arFields){
		//почистим
		$arFields=CbxOrgStructure::cleanParams($arFields);
		
		echo "Updating $id with ".print_r($arFields,true)."\n";
		
		//пишем
		$bs = new CUser();
		$res = $bs->Update($id,$arFields);
		
		//проверяем
		if(!$res) echo $bs->LAST_ERROR;
		
		//чистим память
		unset ($bs);
		
		//возвращаем ответ
		if ($res){ //если все до сих пор шло нормально то
			$this->reloadItem($id); //перезагрузим обновленный элемент
			return $this->ckItem($id, $arFields); //вернем соответствие загруженного элемента требуемым параметрам
		} else return $res;
	}
	
	/*
	 * Правит пользователя либо переданными параметрами либо если передан null берет параметры из SAP
	 * 
	 */
	public function correctItem($id,$fields=null){
		if (is_null($fields)) {
			//echo 'Using sap source ';
			if (!($sapID=$this->getSapID($id))) return false; //такого пользователя вообще нет в САП!
			$fields=$this->sap->bxGenFields($sapID);
		}
		//return true;
		return $this->ckItem($id,$fields)||$this->updItem($id,$fields);
	}
}

?>
   