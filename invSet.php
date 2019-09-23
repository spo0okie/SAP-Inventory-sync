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
error_reporting(E_ALL);

//подключаем библиотеку оргструктуры
require_once '/usr/local/etc/sapSync/CSQLUserList.php';
//подключаем словари азимута
//require_once 'Azimut_dict.php';


if (!isset($_GET['IPernr']) || !strlen(trim($_GET['IPernr'])))
	die ('{"OOK":"Err","Error":"Empty personal number"}');
$number=trim($_GET['IPernr']);

if (!isset($_GET['ISubty']) || !strlen(trim($_GET['ISubty'])))
	die ('{"OOK":"Err","Error":"Empty set type"}');
$setvar=trim($_GET['ISubty']);

if (!isset($_GET['IValue']))
	die ('{"OOK":"Err","Error":"No set value"}');
$setval=trim($_GET['IValue']);

//грузим список пользователей
//$db = new mysqli('192.168.70.24', 'arms_admin', 'cG|Z}/=;txKX', 'arms');
$db = new mysqli($inventory['ip'],	$inventory['user'],	$inventory['passwd'],	$inventory['db']);
$db->query('set names "utf8"');

$user_obj=$db->query("select id from users where id='$number'");
if (
	!is_object($user_obj) ||
	!is_array($user_arr=$user_obj->fetch_assoc()) ||
	!isset($user_arr['id'])
)
	die ('{"OOK":"Err","Error":"Error fetching user '.$number.'"}');
$id=$user_arr['id'];


if($db->query("update users set $setvar='$setval' where id='$id'"))
	die ('{"OOK":"X","Error":"User '.$number.' updated"}');
else
	die ('{"OOK":"","Error":"User '.$number.' not updated"}');
?>