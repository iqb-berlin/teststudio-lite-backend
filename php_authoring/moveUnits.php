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
    $unmovableUnits = array();


    $myDBConnection = new DBConnectionAuthoring();
    if (!$myDBConnection->isError()) {
        $myerrorcode = 401;
        $data = json_decode(file_get_contents('php://input'), true);
        $myToken = $data["t"];
        $sourceWorkspaceId = $data["ws"];
        $targetWorkspaceId = $data["tws"];
        $unitIds = $data["u"];

        if (isset($myToken)) {
            if ($myDBConnection->canAccessWorkspace($myToken, $sourceWorkspaceId)) {
                $myerrorcode = 0;
                $unmovableUnits = $myDBConnection->moveUnits($targetWorkspaceId, $unitIds);
            }
        }
    }
    unset($myDBConnection);


    if ($myerrorcode > 0) {
        error_log('Errorcode = ' . $myerrorcode);
        http_response_code($myerrorcode);
    } else {
        error_log('Unmovable units = ' . json_encode($unmovableUnits));
        echo(json_encode($unmovableUnits));
    }
}
?>
