<?php
/*
 * Отдает данные по пользователю
 * 
 *  формат запроса:
 *  sapReq.php?name=ФИО&number=Табельный_номер[&encoding=кодировка]
 *
 *	v1.3 поиск по табельному номеру переделан со строкового на целочисленный
 *  v1.2 добавлена выдача расшифровки ошибок
 *  v1.1 добавлена возможность указать кодировку
 *  v1.0 обрабатывает единственный запрос
 *  
 */

//подключаем библиотеку оргструктуры
require_once '/usr/local/etc/sapSync/CSQLUserList.php';
//подключаем словари азимута
//require_once 'Azimut_dict.php';

//грузим список пользователей
$sapUsers = new CSQLUserList();
//$sapUsers->loadFromJson("cache.json");
$sapUsers->loadFromSQL($inventory['ip'],	$inventory['user'],	$inventory['passwd'],	$inventory['db']);
//$sapUsers->attachOrgDict($AZM_factories);

$name	=isset($_GET['name'])?trim($_GET['name']):null;
$number	=isset($_GET['number'])?$_GET['number']:null;

//чистим имя от лишних пробелов
while (mb_strpos($name,'  ')!==false)
	$name=str_replace('  ',' ',$name);


function outData($data,$code,$error){
	echo '{"result":"'.($code?'ERR':'OK').'","error":"'.$error.'","code":"'.$code.'","data":'.(is_null($data)?'"NODATA"':$data).'}';
	exit;
}

if (isset($_GET['massive'])) {
	//$sapUsers->buildExFieldsAll();
	echo '{"OPersonal":{"item":'.$sapUsers->getJsonData().'}}';
	exit;
}

if (strlen($phone=isset($_GET['phone'])?$_GET['phone']:'')) {
	if (is_array($ids=$sapUsers->findIdsBy(['Phone'=>$phone,'Uvolen'=>0]))&&count($ids)) {
		ksort($ids);
		echo $sapUsers->getItemField($ids[count($ids)-1],'Ename');
	};
} else {

	//ищем пользователя
	if (
		is_null($id=$sapUsers->findIdBy('Pernr', $number))&&	//сначала по табельному номеру
		is_null($id=$sapUsers->findIdBy('Pernr', $name))		//потом по табельному номеру вбитому в имя
	) {
		//потом по имени среди активных
		try {$id=$sapUsers->getMainEmployment(['Ename'=>$name,'Uvolen'=>0]);} //этот запрос может выбросить исключение если найдено более одного пользователя
		catch(Exception $e) {outData(null,$e->getCode(),$e->getMessage());} //ловим все исключения и возвращаем их запросившему
	}

	if (!$id) {
		//потом по имени среди уволенных
		try {$id=$sapUsers->getMainEmployment(['Ename'=>$name,'Uvolen'=>1]);} //этот запрос может выбросить исключение если найдено более одного пользователя
		catch(Exception $e) {outData(null,$e->getCode(),$e->getMessage());} //ловим все исключения и возвращаем их запросившему
	}

	if (!$id)	outData(null,1,"User $name $number not found");		//если ничего не найдено, то возвращаем информацию об этом

	$userjson=json_encode($sapUsers->getUser($id),JSON_UNESCAPED_UNICODE); //упаковываем найденные данные в json

	if (isset($_GET['encoding'])) 								//если нужно, то меняем кодировку
		$userjson=mb_convert_encoding($userjson,$_GET['encoding']);

	outData($userjson, 0, 'User found');						//вывод данных
};
?>