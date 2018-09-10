<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		require_once('../vo_code/DBConnectionAuthoring.php');

		// *****************************************************************

		$myreturn = [];

		$myerrorcode = 503;

		$myDBConnection = new DBConnectionAuthoring();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;

			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["t"];
			$myWorkspace = $data["ws"];
			if (isset($myToken)) {
				if ($myDBConnection->canAccessWorkspace($myToken, $myWorkspace)) {
					$myerrorcode = 0;
					$myUnitId = $data["u"];
					$myreturn = $myDBConnection->getUnitPreviewData($myWorkspace, $myUnitId);

					require_once('../vo_code/ItemAuthoringToolsFactory.php');
					$myreturn['player'] = ItemAuthoringToolsFactory::getItemPlayerById($myreturn['player_id']);
				}
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