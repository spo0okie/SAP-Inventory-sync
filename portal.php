#!/usr/bin/php
<?php
/*
 * Скрипт синхронизации оргструктуры и пользователей из БД инвентаризации -> Bitrix
 * выполняется из оболочки, а не через веб
 *
 * v1.5 - Добавлена работа с файлами (Загрузка фото)
 * v1.4 - Корректировка вывода информации
 * v1.3 - Рефакторинг и небольшие багфиксы (после того как портал пришлось откатить изза потертых групп)
 * v1.2 - Вносит пользователей в группы
 * v1.1 - Создает полностью дерево оргструктуры если каких-то узлов нет
 * 			раскладывает пользователей по узлам
 * v1.0 - Перемещает узлы оргструктуры, если идентифицирует ищ по ID из САП
 */
// Подключаем битрикс фреймворк
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
$_SERVER ["DOCUMENT_ROOT"] = '/home/bitrix/www';
require ($_SERVER ["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
// Будем работать с информационными блоками
CModule::IncludeModule('iblock');

//загружаем конфигурацию
require_once "config.php";

//подключаем библиотеку с объектами
require_once "libs/OrgStruct.php";
require_once "libs/CBxGroupList.php";
require_once "libs/CBxUserList.php";
require_once "libs/CBxOrgStructure.php";

//САП нам отдавал фотографии, а 1С не отдает. Потому обходим стороной эту область данных
//require "CUserPhotos.php";

echo date('c')." Script started\n";


$dataSrc=$dataSrc_inv_yml;
$sapOrg=initOrgStructure($dataSrc);
$sapUsers=initUserList($dataSrc);
$org_id=$dataSrc['org_id'];

//включаем вывод всех ошибок
//error_reporting(E_ALL);

echo "Initializing BX structure\n";
$bxOrg= new CBxOrgStructure();

echo "Initializing BX groups\n";
$bxGrp = new CBxGroupList ();
$bxUsers = new CBxUserList();

//подключаем базы друг к другу
$sapOrg->attachBx($bxOrg);
$sapUsers->attachBxOrg($bxOrg);
$sapUsers->attachSapOrg($sapOrg);
$bxUsers->attachSap($sapUsers);


// ****** инициализация завершена ******
// далее вносим изменения в портал

echo "Checking Groups\n";

//ищем идентификаторы групп для всеобщего включения
foreach ( $grpListAll as $grp )
	if (is_null ( $grpListAll_id [] = $bxGrp->findIdBy ( 'NAME', $grp ) ))
		die ( "Can't find group $grp" );
		
//ищем идентификаторы групп в которые включить менеджеров
/*
foreach ( $grpListManagers as $grp )
	if (is_null ( $grpListManagers_id [] = $bxGrp->findIdBy ( 'NAME', $grp ) ))
		die ( "Can't find group $grp" );
*/

/*
//вывод всех груп с ингдексами		 (просто для информации)
foreach ($bxGrp->getIds() as $grp)
	echo $grp.': '.$bxGrp->getItemField($grp, 'NAME')."\n";
*/

//die ( "dont't touch anything!\n" );

echo "Checking structure\n";

/*
 * пробегает по всей оргструктуре SAP и выстраивает подразделения в портале
 * в соответствии с SAP. недостающие подразделения добавляются. Идентификация подразделения
 * производится по полю CODE со стороны Битрикс и Objid со стороны SAP
 */
foreach ($sapOrg->getIds() as $sapID) if ($sapOrg->getItemField($sapID,'wcount',0)) {
	echo $sapID.': '.($sapOrg->ckBxItemFields($sapID)?'OK':'Err')."\n";
	ob_flush();
}


echo "Checking users\n";
/*
 * Пробегает по всем активным пользователям
 */
foreach ($bxUsers->getIds() as $bxID) {
	$prefx=str_pad($bxID,5).' <bx: '.str_pad($bxUsers->getItemField($bxID,'LOGIN'),20).":sap> ";

	//ищем пользователя в сап
	if (!($sapID=$bxUsers->getSapID($bxID))) {
		/* 
		 * Нет такого пользователя
		 * если он активирован с портале - отключаем
		 * если его нет в сапе и он не активен в портале - просто пропускаем молча
		 */

		//echo '?';ob_flush();
		if ($bxUsers->getItemField($bxID,'ACTIVE')=='Y') {
			echo $prefx."No SAP ID ($sapID) - Deactivating...\n";
			$bxUsers->updItem($bxID,['ACTIVE'=>'N']);
		}
		
		continue; //пропускаем всех кого в сапе нет
	} 
	
	/*
	 * Если пользователь в сап есть, то проверяется соответствие полей
	 * - Должность
	 * - Отдел
	 * - XML_ID (соответствует табельному номеру из SAP)
	 * - Подразделение (правка этого поля меняет положение пользователя в оргструктуре)
	 */

	//выводим биртикс ИД, логин, Сап ИД
	$prefx.=str_pad($sapID,12);

	//echo '2';ob_flush();

	
	
	//проверка полей
	$prefx.="Fields - ".str_pad(($bxUsers->correctItem($bxID)?'OK ':'Err'),3).'  ';


	//echo '3';ob_flush();

	
	//проверяем наличие пользователя в группе в которой должны быть все
	$prefx.='All grp - '.str_pad(((CBxUserList::fixUserGroups($bxID, $grpListAll_id))?'OK ':'Err'),3)."  ";


	//echo '4';ob_flush();

	/*
	 * Работа с фотографиями
	 */
/*    $userPhoto = new CUserPhotoStruct();
    $userPhoto->loadFromJson("http://zabbix.azimut-gk.local:8080/sapProxy.php?req=YfmGetPersPhoto&IPernr=$sapID");
    $photoStatus='missing'; //по умолчанию считаем что фото нет
    if (($sapPhotoTStamp=$userPhoto->getTimeStamp())>0) {
        //тут мы выяснили что в сапе есть данные по фотографии сотрудника
        $userPhoto->loadBxData($bxID);
        if (strcmp($userPhoto->getBxFilename(),$userPhoto->getSapFilename($sapID))) {
	        $userPhoto->updateBxData($sapID,$bxID);
            $photoStatus='updated ';
        } else {
            $photoStatus='same';
        }
    }
    unset($userPhoto);
    $prefx.= 'Photo - '.str_pad($photoStatus,8).'  ';

	//echo '5';	ob_flush();


    */
    //проверяем наличие пользователя в группах для руководителей
	//пробегаемся по ключевым словам должностей руководителей и ищем их в должности обрабатываемого пользователя
	/*$manager=false;
	foreach ($managersTitles as $title)	if (false!==strpos(mb_strtolower($bxUsers->getItemField($bxID, 'WORK_POSITION')),$title)) 
		$manager=true;
	if ($manager) 
		$mgr_prfx="Manger grp - ".(CBxUserList::fixUserGroups($bxID, $grpListManagers_id)?'OK ':'Err');
	else 
		$mgr_prfx='';
	$prefx.=str_pad($mgr_prfx,18);

	//echo '6';ob_flush();
    */
	//выводим подразделение	
	$prefx.='Dep: '.str_pad($bxUsers->getItemField($bxID,'UF_DEPARTMENT')[0],6);

	//echo '7';	ob_flush();
	//выводим список идентификаторов групп пользователя
	//$prefx.='Grps: ['.implode(',',CBxUserList::getUserGroups($bxID))."]";

	//echo '8';echo "\n";	ob_flush();
	//echo "\n";
	
	echo $prefx;
	//ob_flush();

	//если обнаружены дубликаты пользователя - сообщаем об этом (возникают при импорте с разных доменов)
	//if (count($dupes=$bxUsers->findDupes($bxID))&&($dupes[0]>$bxID)) echo ' Dupes found ['.	implode(',',$dupes).'] ';

	
	echo "\n";
	ob_flush();
}
