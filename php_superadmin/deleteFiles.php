<?php
	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		require_once('items_code/DBConnectionAdmin.php');
		$dataroot = 'items_data';

		// *****************************************************************

		$myreturn = '';

		$myerrorcode = 503;

		$myDBConnection = new DBConnectionAdmin();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;

			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["at"];
			$wsId = $data["ws"];
			if (isset($myToken)) {
				if ($wsId > 0) {
					$myerrorcode = 0;

					$workspaceDirName = $dataroot . '/ws_' . $wsId;
					if (file_exists($workspaceDirName)) {
						$errorcount = 0;
						$successcount = 0;
						foreach($data["f"] as $fileToDelete) {
							$mysplits = explode('::', $fileToDelete);
							if (count($mysplits) == 2) {
								if (unlink($workspaceDirName . '/' . $mysplits[0] . '/' . $mysplits[1])) {
									$successcount = $successcount + 1;
								} else {
									$errorcount = $errorcount + 1;
								}
							}
						}
						if ($errorcount > 0) {
							$myreturn = 'e:Konnte ' . $errorcount . ' Dateien nicht löschen.';	
						} else {
							if ($successcount == 1) {
								$myreturn = 'Eine Datei gelöscht.';
							} else {
								$myreturn = 'Erfolgreich ' . $successcount . ' Dateien gelöscht.';	
							}
						}
					} else {
						$myreturn = 'e:Workspace-Verzeichnis nicht gefunden.';
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