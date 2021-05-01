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
        echo(json_encode(VeronaFolder::getModuleHtml($data["m"])));
	}
