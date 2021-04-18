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

		$myReturn = [];

		$myErrorCode = 503;

		$myDBConnection = new DBConnectionAuthoring();
		if (!$myDBConnection->isError()) {
			$myErrorCode = 401;

			$data = json_decode(file_get_contents('php://input'), true);
			$myToken = $data["t"];
			$myWorkspace = $data["ws"];
			if (isset($myToken)) {
				if ($myDBConnection->canAccessWorkspace($myToken, $myWorkspace)) {
					$myErrorCode = 0;
					$myReturn = $myDBConnection->getWorkspaceData($myWorkspace);
					require_once('../vo_code/VeronaFolder.class.php');
					date_default_timezone_set('Europe/Berlin');
					$allModules = VeronaFolder::getModuleList();
					$myReturn['editors'] = [];
					$myReturn['players'] = [];
					foreach ($allModules as $module) {
						if ($module->isEditor) {
							$myReturn['editors'][$module->id] = array(
								'label' => $module->label,
								'html' => '',
							);
						} else {
							$myReturn['players'][$module->id] = array(
								'label' => $module->label,
								'html' => '',
							);
						}
					}
				}
			}
		}        
		unset($myDBConnection);

		if ($myErrorCode > 0) {
			http_response_code($myErrorCode);
		} else {
			echo(json_encode($myReturn));
		}
	}
?>
