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

    $return = [];
    $errorCode = 0;
    $dbConnection = new DBConnectionSuperadmin();

    if ($dbConnection->isError()) {
        $errorCode = 503;

    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $workspaces = $dbConnection->getWorkspaces($data["t"]);

        if (is_null($workspaces) || empty($workspaces)) {
            $errorCode = 401;

        } else {
            $return = $workspaces;
        }
    }

    unset($dbConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);

    } else {
        echo(json_encode($return));
    }
}
