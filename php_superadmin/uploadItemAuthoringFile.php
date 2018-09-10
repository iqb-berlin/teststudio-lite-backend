<?php 
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {

		require_once('../vo_code/DBConnectionSuperadmin.php');

		// *****************************************************************

		$myreturn = '';

		$myerrorcode = 503;

		$myDBConnection = new DBConnectionSuperadmin();
		if (!$myDBConnection->isError()) {
			// Achtung: Wenn Datei zu groß, dann ist $_POST nicht gesetzt
			$myToken = $_POST['t'];
			$authoringId = $_POST['i'];

			if (isset($myToken) and isset($authoringId)) {
				$myerrorcode = 401;

				if ($myDBConnection->isSuperAdmin($myToken)) {
					require_once('../vo_code/ItemAuthoringToolsFactory.php');
					$targetFolder = ItemAuthoringToolsFactory::getItemAuthoringFolder($authoringId);

					if (strlen($targetFolder) > 0) {
						$myerrorcode = 0;

						$myreturn = 'e:Interner Fehler: Dateiname oder Formularelement-Name nicht im Request gefunden.';
						$originalTargetFilename = $_FILES['authoringtoolfile']['name'];
						if (isset($originalTargetFilename) and strlen($originalTargetFilename) > 0) {
							$originalTargetFilename = basename($originalTargetFilename);
							$tempPrefix = DBConnectionSuperAdmin::getTempFilePath() . '/' . uniqid('at_', true) . '_';
							$tempFilename = $tempPrefix . $originalTargetFilename;

							// +++++++++++++++++++++++++++++++++++++++++++++++++++++++
							// move file from php-server-tmp folder to tmp-folder
							if (move_uploaded_file($_FILES['authoringtoolfile']['tmp_name'], $tempFilename)) {
								$myreturn = 'OK';

								$filenameextension = strtoupper(substr($originalTargetFilename, -4));
								if ($filenameextension === '.ZIP') {
									$zip = new ZipArchive;
									if ($zip->open($tempFilename) === TRUE) {
										$zip->extractTo($targetFolder . '/');
										$zip->close();
										unlink($tempFilename);
									} else {
										$myreturn = 'e:Interner Fehler: Konnte ZIP-Datei nicht entpacken.';
									}
								} else {
									$targetFilename = $targetFolder . '/' . $originalTargetFilename;
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
