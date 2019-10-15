#!/usr/bin/php
<?php
/*
 * Скрипт синхронизации базы пользователей инвенторизации с САП
 * выполняется из оболочки, а не через веб
 *
 * Конкретно этот скрипт выполняет одностороннюю синхронизацию данных из 1С/SAP в Инвентаризацию
 * 
 * v1.0 - Initial
 */

require "CC1OrgStructure.php";

//включаем вывод всех ошибок
error_reporting(E_WARNING||E_ALL);



echo date('c')." Script started\n";
require_once "config.php";
require_once "libs/OrgStruct.php";

$dataSrc=$dataSrc_1c_yml;
$sapOrg=initOrgStructure($dataSrc);
$sapUsers=initUserList($dataSrc);
$org_id=$dataSrc['org_id'];

$db = new mysqli(
	$inventory['ip'],
	$inventory['user'],
	$inventory['passwd'],
	$inventory['db']
);

$req_sql = 'set names "utf8"';
$req_obj = $db->query($req_sql);


$sync_errors=0;


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

	$dismissed=(strlen($item['Uvolen'])&&$item['Uvolen']=='Уволен')?1:0;

	$id=null;
	$id_obj=$db->query("select id from users where employee_id='${item['Pernr']}' and org_id=$org_id;");

	if (is_object($id_obj)) {
		$res = $id_obj->fetch_assoc();
		if (is_array($res)&&isset($res['id']))
		    $id=$res['id'];
    }
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
	else $req_sql="update users ".
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
	if ($db->query($req_sql)===false){
		echo "Error:REQ: $req_sql\n";
		$sync_errors++;
	}
}

if (isset($syncErrorsFile)){
    file_put_contents($syncErrorsFile,$sync_errors);
} else echo $sync_errors;

   