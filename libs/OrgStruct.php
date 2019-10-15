<?php
/**
 * Bootstrap файл для загрузки оргструктуры на основании конфига
 * User: aareviakin
 * Date: 07.10.2019
 * Time: 23:36
 */

require_once "CSQLOrgStructure.php";
require_once "CC1OrgStructure.php";
require_once "CSapOrgStructure.php";
require_once "CSQLUserList.php";
require_once "CC1UserList.php";
require_once "CSapUserList.php";

/**
 * Проверяет что все поля источника данных указаны
 * @param $src
 */
function validateDataSource(&$src,$descr) {
	echo "Validating $descr DataSource structure\n";
	if (!isset($src)) die( "Data structure $descr not set!");
	if (!isset($src['src'])) die( "Data source $descr not set!");
	if (!isset($src['ftype'])) die( "Data source $descr type not set!");
	if (!isset($src['path'])) die( "Data source $descr data path not set!");
}

function loadDataSource($src, &$sapOrg) {
	echo "Loading DataSource structure\n";
//грузим
	switch ($src['ftype']) {
		case 'csv':
			$sapOrg->loadFromCsv($src['path']);
			break;
		case 'json':
			$sapOrg->loadFromJson($src['path']);
			break;
		case 'xml':
			$sapOrg->loadFromXml($src['path']);
			break;
		case 'sql':
			$sapOrg->loadFromSQL(
				$src['path'][0],
				$src['path'][1],
				$src['path'][2],
				$src['path'][3]
			);
			break;
		default:
			die('Unknown org file type');
	}

}

/**
 * @param $dataSrc
 * @return COrgStructureStorage
 */
function initOrgStructure($dataSrc) {
//создаем объект оргструктуры
	validateDataSource($dataSrc['org'],'[org]');

	echo "Initializing DataSource structure\n";

	switch ($dataSrc['org']['src']) {
		case 'c1':
			echo "C1 mode\n";
			$sapOrg = new CC1OrgStructure();
			break;
		case 'sap':
			echo "SAP mode\n";
			$sapOrg = new CSapOrgStructure();
			break;
		case 'sql':
			echo "SQL mode\n";
			$sapOrg = new CSQLOrgStructure();
			break;
		default:
			die('Unknown org data source');
	}
	loadDataSource($dataSrc['org'],$sapOrg);
	return $sapOrg;
}

/**
 * @param $dataSrc
 * @return COrgStructureStorage
 */
function initUserList($dataSrc) {
//создаем объект оргструктуры
	validateDataSource($dataSrc['org'],'[usr]');

	echo "Initializing DataSource structure\n";

	switch ($dataSrc['usr']['src']) {
		case 'c1':
			echo "C1 mode\n";
			$sapOrg = new CC1UserList();
			break;
		case 'sap':
			echo "SAP mode\n";
			$sapOrg = new CSapUserList();
			break;
		case 'sql':
			echo "SQL mode\n";
			$sapOrg = new CSQLUserList();
			break;
		default:
			die('Unknown org data source');
	}

	loadDataSource($dataSrc['usr'],$sapOrg);
	return $sapOrg;
}