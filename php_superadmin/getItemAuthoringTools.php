<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		require_once('../vo_code/DBConnection.php');

		// *****************************************************************

		$myreturn = [];

		$myerrorcode = 503;

		$myDBConnection = new DBConnection();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;

			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["t"];
			if (isset($myToken)) {
				if ($myDBConnection->isSuperAdmin($myToken)) {
					$myerrorcode = 0;
					require_once('../vo_code/ItemAuthoringToolsFactory.php');
					$myreturn = ItemAuthoringToolsFactory::getItemAuthoringToolsList(false);
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