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
			'is_superadmin' => False
		];
		$myerrorcode = 503;
		require_once('vo_code/DBConnection.php');

        error_log("Database Connection attempt ...");
        $myDBConnection = new DBConnection();
		if ($myDBConnection->isError()) {
            error_log("Database Connection couldn't established: " . $myDBConnection->errorMsg);
        } else {
            error_log("Database Connection successful!");

            $myerrorcode = 401;

			$data = json_decode(file_get_contents('php://input'), true);
			$myName = $data["n"];
			$myPassword = $data["p"];

			if (isset($myName) and isset($myPassword)) {
				$myToken = $myDBConnection->login($myName, $myPassword);
				
				if (isset($myToken) and (strlen($myToken) > 0)) {
					$myerrorcode = 402;
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
