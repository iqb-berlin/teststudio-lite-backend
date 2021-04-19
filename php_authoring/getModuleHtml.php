<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

	// preflight OPTIONS-Request bei CORS
	if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
		exit();
	} else {
		require_once('../vo_code/VeronaFolder.class.php');
        $data = json_decode(file_get_contents('php://input'), true);
        $myreturn = [];
        $myreturn['p'] = VeronaFolder::getModuleHtml($data["m"]);
        echo(json_encode($myreturn));
	}
