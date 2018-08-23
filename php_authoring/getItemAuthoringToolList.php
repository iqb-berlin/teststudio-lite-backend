<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {

		// *****************************************************************

		$myreturn = [];

		require_once('../itemdb_code/ItemAuthoringToolsFactory.php');
		$myreturn = ItemAuthoringToolsFactory::getItemAuthoringToolsList(true);

		echo(json_encode($myreturn));
	}
?>