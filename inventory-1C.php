#!/usr/bin/php
<?php
/*
 * Скрипт синхронизации базы пользователей SAP -> Инвенторизация
 * выполняется из оболочки, а не через веб
 *
 * v1.1 - инициализация вынесена в библиотеку
 * v1.0 - Initial
 */

//включаем вывод всех ошибок
error_reporting(E_WARNING||E_ALL);


echo date('c')." Script started\n";

//загружаем конфигурацию
require_once "config.php";

//подключаем библиотеку с объектами
require_once "libs/OrgStruct.php";

$db = new mysqli(
	$inventory['ip'],
	$inventory['user'],
	$inventory['passwd'],
	$inventory['db']
);

$req_sql = 'set names "utf8"';
$req_obj = $db->query($req_sql);

foreach ($inventory_import as $dataSrc) {
	//выбираем из конфигурации источник данных
	//$dataSrc=$dataSrc_1c_gmn;
	$sapOrg=initOrgStructure($dataSrc);
	$sapUsers=initUserList($dataSrc);
	$org_id=$dataSrc['org_id'];

	$sync_errors=0;


	//грузим оргструткуру
	foreach ($sapOrg->getIds() as $sapID) {
		$id=$sapOrg->getItemField($sapID,'Objid');
		$name=$sapOrg->getItemField($sapID,'Orgtx');
		$pup=$sapOrg->getItemField($sapID,'Pup');
		$req_sql="INSERT INTO ".
    	    "org_struct(org_id,id,pup,name) ".
			"values(".
				"$org_id,".
    			"'$id',".
				"'$pup',".
				"'$name'".
			") on duplicate key update ".
				"pup='$pup',".
				"name='$name';";
		if ($db->query($req_sql)===false){
			echo "Error:REQ: $req_sql\n";
			$sync_errors++;
		}
	}


	/*
	 * var_dump($sapUsers);
	*/
	/*
	id
	struct_id
	struct_name
	position
	full_name
	dismissed
	login
	*/

	foreach ($sapUsers->getIds() as $id) {
		$item=$sapUsers->getItem($id);
		/*
		 if (
	    	    $item['Persg']!=1
	        &&
	        	$item['Persg']!='Основное место работы'
		        ) continue;
		*/

		//это специфика выгрузки из 1С
		$dismissed=(strlen($item['Uvolen'])&&$item['Uvolen']=='Уволен')?1:0;

		//ищем пользователя с этим табельным в этой организации
		$id=null;
		//считаем что синхронизиоваться по умолчанию он должен
		$nosync=false;

		$id_obj=$db->query("select id,nosync from users where employee_id='${item['Pernr']}' and org_id=$org_id;");

		if (is_object($id_obj)) {
			$res = $id_obj->fetch_assoc();
			if (is_array($res)){
				if (isset($res['id'])) $id=$res['id'];
				if (isset($res['nosync'])) $nosync=$res['nosync'];
			}
		}

		//не нашли - добавляем
		if (is_null($id))
			$req_sql="insert into ".
        	"users (org_id,employee_id,Orgeh,Doljnost,Ename,Uvolen,Bday,Mobile,Persg,employ_date,resign_date) ".
			"values (".
			    "$org_id,".                 //организация
			    "'${item['Pernr']}',".      //табельный номер
			    "'${item['Orgeh']}',".      //ссылка на подразделение
			    "'${item['Doljnost']}',".   //должность
			    "'${item['Ename']}',".      //ФИО
    			"$dismissed,".              //уволен
    			"'${item['Bday']}',".       //д.р.
			    "'${item['Mobile']}',".     //мобилный
			    "${item['Persg']},".        //трудоустройство
			    "'${item['Employ_date']}',".//начало трудоустройства
		    	"'${item['Resign_date']}'". //окончание
	        ")";
		else $req_sql="update users ".  //иначе обновляем
        	"set ".
            	"Orgeh='${item['Orgeh']}',".
	            "Doljnost='${item['Doljnost']}',".
    	        "Ename='${item['Ename']}',".
    			"Bday='${item['Bday']}',".
    			"Mobile='${item['Mobile']}',".
	    		"Persg=${item['Persg']},".
    			"employ_date='${item['Employ_date']}',".
	    		"resign_date='${item['Resign_date']}',".
            	"Uvolen=$dismissed ".
	        "where id='$id'";
		if (!$nosync) {
			if ($db->query($req_sql)===false){
				echo "Error:REQ: $req_sql\n";
				$sync_errors++;
			}
	    } else echo "Skip $id ${item['Pernr']}\n";
	}

	unset ($sapOrg);
	unset ($saUsers);
}

if (isset($syncErrorsFile)){
    file_put_contents($syncErrorsFile,$sync_errors);
} else echo $sync_errors;
