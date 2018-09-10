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

		// Authorisation
		$myerrorcode = 503;
		$myreturn = 0;

		$myDBConnection = new DBConnection();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;
			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["t"];
			$authoringIdList = $data["i"];

			if (isset($myToken)) {
				if ($myDBConnection->isSuperAdmin($myToken)) {
					$myerrorcode = 0;
					require_once('../vo_code/ItemAuthoringToolsFactory.php');

					foreach($authoringIdList as $authoringId) {
						if (ItemAuthoringToolsFactory::deleteItemAuthoringTool($authoringId)) {
							$myreturn = $myreturn + 1;
						}
					}
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
