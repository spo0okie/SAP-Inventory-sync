#!/usr/bin/php
<?php
/*
 * Скрипт синхронизации базы пользователей инвенторизации с САП
 * выполняется из оболочки, а не через веб
 * 
 * v1.0 - Initial
 */


require "COrgStructureStorage.php";

//включаем вывод всех ошибок
error_reporting(E_WARNING||E_ALL);



echo date('c')." Script started\n";

/*
$db = mysql_connect("inventory.azimuth.holding.local", "user_sync", "sync_user");
if (!$db) {
	die ("Error connecting DB\n");
} else {
	mysql_query('use arms');
	mysql_query('set names "utf8"');
}

*/
echo "Initializing SAP structure\n";
//$sapOrg = new CSapOrgStructure();
//$sapOrg->loadFromJson("http://zabbix.azimut-gk.local:8080/sapProxy.php");
$sapUsers = new CSapUserList();
$sapUsers->loadFromJson("http://zabbix.azimut-gk.local:8080/sapReq.php?massive=y");


//var_dump($sapUsers);
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
	if ($item['Persg']!=1) continue;
	$dismissed=strlen($item['Uvolen']);
	$res=mysql_query("insert into users (id,struct_id,struct_name,position,full_name,dismissed) ".
		 "values (${item['Pernr']},${item['Orgeh']},'${item['Orgtx']}','${item['Doljnost']}','${item['Ename']}',$dismissed) ".
		 "on duplicate key update struct_id=${item['Orgeh']},struct_name='${item['Orgtx']}',position='${item['Doljnost']}',full_name='${item['Ename']}',dismissed=$dismissed;\n");
	if (!$res) die("Error updating DB\n");
}
	
?>
   