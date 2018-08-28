<?php 
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {

		require_once('../itemdb_code/DBConnectionSuperadmin.php');

		// *****************************************************************

		$myreturn = '';

		$myerrorcode = 503;

		$myDBConnection = new DBConnectionSuperadmin();
		if (!$myDBConnection->isError()) {
			// Achtung: Wenn Datei zu groß, dann ist $_POST nicht gesetzt
			$myToken = $_POST['t'];

			if (isset($myToken)) {
				$myerrorcode = 401;

				if ($myDBConnection->isSuperAdmin($myToken)) {
					require_once('../itemdb_code/ItemAuthoringToolsFactory.php');
					$targetFolder = ItemAuthoringToolsFactory::getItemPlayerFolder();

					if (strlen($targetFolder) > 0) {
						$myerrorcode = 0;

						$myreturn = 'e:Interner Fehler: Dateiname oder Formularelement-Name nicht im Request gefunden.';
						$originalTargetFilename = $_FILES['itemplayerfile']['name'];
						if (isset($originalTargetFilename) and strlen($originalTargetFilename) > 0) {
							$originalTargetFilename = basename($originalTargetFilename);
							$tempPrefix = '../itemdb_data/' . uniqid('at_', true) . '_';
							$tempFilename = $tempPrefix . $originalTargetFilename;

							// +++++++++++++++++++++++++++++++++++++++++++++++++++++++
							// move file from php-server-tmp folder to tmp-folder
							if (move_uploaded_file($_FILES['itemplayerfile']['tmp_name'], $tempFilename)) {
								$targetFilename = $targetFolder . '/' . $originalTargetFilename;
								$myreturn = 'OK';
								if (file_exists($targetFilename)) {
									if (!unlink($targetFilename)) {
										$myreturn = 'e:Interner Fehler: Konnte alte Datei nicht löschen.';
										$targetFilename = '';
									}
								}
								if (strlen($targetFilename) > 0) {
									if (!rename($tempFilename, $targetFilename)) {
										$myreturn = 'e:Interner Fehler: Konnte Datei nicht in Zielordner verschieben.';
									}
								}
							} else {
								$myreturn = 'e:Datei abgelehnt (Sicherheitsrisiko?)';
							}
							// +++++++++++++++++++++++++++++++++++++++++++++++++++++++
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
