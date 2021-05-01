<?php
// www.IQB.hu-berlin.de
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {
    require_once('../vo_code/DBConnectionSuperadmin.php');

    // *****************************************************************

    $return = false;

    $errorCode = 503;

    $myDBConnection = new DBConnectionSuperadmin();
    $data = json_decode(file_get_contents('php://input'), true);
    $sessionToken = $data["t"];
    $configDataAsString = $data["c"];
    $errorCode = 403;
    if ($myDBConnection->isSuperAdmin($sessionToken)) {
        $configFileName = "../config/appConfig.json";
        if (file_put_contents($configFileName, $configDataAsString) == false) {
            $errorCode = 500;
        } else {
            $errorCode = 0;
            $return = true;
        }
    }
    unset($myDBConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}
