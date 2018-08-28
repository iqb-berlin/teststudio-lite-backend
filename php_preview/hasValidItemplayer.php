<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		require_once('../itemdb_code/DBConnectionAuthoring.php');

		// *****************************************************************

		$myreturn = false;

		$myerrorcode = 503;

		$myDBConnection = new DBConnectionAuthoring();
		if (!$myDBConnection->isError()) {
			$data = json_decode(file_get_contents('php://input'), true);
			$myerrorcode = 0;
			$myUnitId = $data["u"];
			$myPlayerId = $myDBConnection->getUnitItemPlayerId($myUnitId);
			if (strlen($myPlayerId) > 0) {
				require_once('../itemdb_code/ItemAuthoringToolsFactory.php');
				$myLink = ItemAuthoringToolsFactory::getItemPlayerLinkById($myPlayerId);
				$myreturn = strlen($myLink) > 0;
			}
		}
		unset($myDBConnection);

		if ($myerrorcode > 0) {
			http_response_code($myerrorcode);
		} else {
			echo(json_encode($myreturn));
		}
	}
?>