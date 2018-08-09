<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		$myreturn = [
			'token' => '',
			'name' => '',
			'is_superadmin' => false
		];
		$myerrorcode = 503;

		$data = json_decode(file_get_contents('php://input'), true);
		$myToken = $data["t"];

		if (isset($myToken)) {
			require_once('itemdb_code/DBConnection.php');

			$myDBConnection = new DBConnection();
			if (!$myDBConnection->isError()) {
				$myerrorcode = 401;
			
				$myName = $myDBConnection->getLoginName($myToken);
			
				if (isset($myName) and (strlen($myName) > 0)) {
					$myerrorcode = 0;
				
					$myreturn = [
						'token' => $myToken,
						'name' => $myName,
						'is_superadmin' => $myDBConnection->isSuperAdmin($myToken)
					];
				}
			}
			unset($myDBConnection);
		}

		if ($myerrorcode > 0) {
			http_response_code($myerrorcode);
		} else {
			echo(json_encode($myreturn));
		}
	}
?>