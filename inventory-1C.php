#!/usr/bin/php
<?php
/*
 * Скрипт синхронизации базы пользователей инвенторизации с САП
 * выполняется из оболочки, а не через веб
 * 
 * v1.0 - Initial
 */

require "CC1OrgStructure.php";

//включаем вывод всех ошибок
error_reporting(E_WARNING||E_ALL);



echo date('c')." Script started\n";
require_once "config.php";
require_once "CC1OrgStructure.php";
require_once "CC1UserList.php";
require_once "CSapOrgStructure.php";
require_once "CSapUserList.php";






echo "Initializing DataSource structure\n";

//создаем объект оргструктуры
switch ($dataSrc['org']['src']) {
	case 'c1':
		echo "C1 mode\n";
		$sapOrg = new CC1OrgStructure();
		break;
	case 'sap':
		echo "SAP mode\n";
		$sapOrg = new CSapOrgStructure();
		break;
	default:
		die('Unknown org data source');
		break;
}
echo "Loading DataSource structure\n";
//грузим
switch ($dataSrc['org']['ftype']) {
	case 'csv':
		$sapOrg->loadFromCsv($dataSrc['org']['path']);
		break;
	case 'json':
		$sapOrg->loadFromJson($dataSrc['org']['path']);
		break;
	case 'xml':
		$sapOrg->loadFromXml($dataSrc['org']['path']);
		break;
	default:
		die('Unknown org file type');
		break;
}

echo "Initializing DataSource userlist\n";
//создаем объект списка пользователей
switch ($dataSrc['usr']['src']) {
	case 'c1':
		echo "C1 mode\n";
		$sapUsers = new CC1UserList();
		break;
	case 'sap':
		echo "SAP mode\n";
		$sapUsers = new CSapUserList();
		break;
	default:
		die('Unknown usr data source');
		break;
}

echo "Loading DataSource userlist\n";
//грузим
switch ($dataSrc['usr']['ftype']) {
	case 'csv':
		$sapUsers->loadFromCsv($dataSrc['usr']['path']);
		break;
	case 'json':
		$sapUsers->loadFromJson($dataSrc['usr']['path']);
		break;
	case 'xml':
		$sapUsers->loadFromXml($dataSrc['usr']['path']);
		break;
	default:
		die('Unknown usr file type');
		break;
}


$db = new mysqli(
	$inventory['ip'],
	$inventory['user'],
	$inventory['passwd'],
	$inventory['db']
);

$req_sql = 'set names "utf8"';
$req_obj = $db->query($req_sql);

$sync_errors=0;

echo "Initializing 1C structure\n";


foreach ($sapOrg->getIds() as $sapID) {
	$id=$sapOrg->getItemField($sapID,'Objid');
	$name=$sapOrg->getItemField($sapID,'Orgtx');
	$pup=$sapOrg->getItemField($sapID,'Pup');
	$req_sql="INSERT INTO ".
        "org_struct(id,pup,name) ".
        "values(".
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


var_dump($sapUsers);
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
	$id_obj=$db->query("select id from users where id='${item['Pernr']}';");

	if (is_object($id_obj)) {
		$res = $id_obj->fetch_assoc();
		if (is_array($res)&&isset($res['id']))
		    $id=$res['id'];
    }
    if (is_null($id))
	    $req_sql="insert into ".
        "users (id,Orgeh,Doljnost,Ename,Uvolen,Bday,Mobile,Persg,employ_date,resign_date) ".
		"values (".
		    "'${item['Pernr']}',".      //табельный номер
		    "'${item['Orgeh']}',".      //ссылка на подразделение
		    "'${item['Doljnost']}',".   //должность
		    "'${item['Ename']}',".      //ФИО
    		"$dismissed,".              //уволен
    		"'${item['Bday']}',".       //д.р.
		    "'${item['Mobile']}',".     //мобилный
		    "${item['Persg']},".         //трудоустройство
		    "'${item['Employ_date']}',".         //трудоустройство
		    "'${item['Resign_date']}'".         //трудоустройство
        ")";
	else $req_sql="update users ".
        "set ".
            "Orgeh='${item['Orgeh']}',".
            "Doljnost='${item['Doljnost']}',".
            "Ename='${item['Ename']}',".
    		"Bday='${item['Bday']}',".               //уволен
    		"Mobile='${item['Mobile']}',".               //уволен
    		"Persg=${item['Persg']},".               //уволен
    		"employ_date='${item['Employ_date']}',".               //уволен
	    	"resign_date='${item['Resign_date']}',".               //уволен
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

   