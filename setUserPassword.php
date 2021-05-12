<?php 
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {

		require_once('./vo_code/DBConnection.php');

		// Authorisation
		$myerrorcode = 503;
		$myreturn = '';

		$myDBConnection = new DBConnection();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;
			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["t"];
			$oldPw = $data["old"];
			$newPw = $data["new"];

			if (isset($myToken)) {
				$ok = $myDBConnection->changePassword($myToken, $oldPw, $newPw);
				if ($ok) {
					$myerrorcode = 0;
					$myreturn = $ok;
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
