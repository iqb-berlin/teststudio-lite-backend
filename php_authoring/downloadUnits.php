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

		// Authorisation
		$myerrorcode = 503;
		$myreturn = false;

		$myDBConnection = new DBConnectionAuthoring();
		if (!$myDBConnection->isError()) {
			$myerrorcode = 401;
			$allHeaders = getallheaders();
			$dataRaw = $allHeaders['Options'];
			$data = json_decode($dataRaw);
			error_log('$data: ' . print_r($data, true));
			$myToken = $data->t;
			$myWorkspace = $data->ws;
			error_log('$myToken: ' . $myToken);

			if (isset($myToken)) {
				if ($myDBConnection->canAccessWorkspace($myToken, $myWorkspace)) {
					$myerrorcode = 0;
					$okCount = 0;
					$targetZip = new ZipArchive;
					$targetFileName = '../vo_tmp/' . uniqid('unitexport_', true) . '.voud.zip';
					$targetZip->open($targetFileName, ZipArchive::CREATE);
					foreach($data->u as $unitId) {
						if ($myDBConnection->writeUnitDefToZipFile($myWorkspace, $unitId, $targetZip, 1000)) {
							$okCount = $okCount + 1;
						}
					}
					$myreturn = $targetZip->close();

					header('Content-Description: File Transfer');
					header('Content-Type: application/zip');
					header('Content-Disposition: attachment; filename="' . 'UnitDefs.voud.zip' . '"');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($targetFileName));
					readfile($targetFileName);

					error_log(strval($okCount) . ' Units gespeichert.');
				}
			}
		}
		unset($myDBConnection);

		if ($myerrorcode > 0) {
			http_response_code($myerrorcode);
		}
	}
?>
