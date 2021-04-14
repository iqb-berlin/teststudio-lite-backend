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

    $return = false;

    $errorCode = 503;

    $dbConnection = new DBConnectionAuthoring();
    if (!$dbConnection->isError()) {
        $errorCode = 401;

        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data["t"];
        $workspace = $data["ws"];
        if (isset($sessionToken)) {
            if ($dbConnection->canAccessWorkspace($sessionToken, $workspace)) {
                $errorCode = 0;
                $unitId = $data["u"];
                $unitPlayerId = $data["pl"];

                $return = $dbConnection->setUnitPlayer($unitId, $unitPlayerId);
            }
        }
    }

    unset($dbConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}
?>
