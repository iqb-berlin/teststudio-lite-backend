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

    $myreturn = false;

    $myerrorcode = 503;

    $myDBConnection = new DBConnectionAuthoring();
    if (!$myDBConnection->isError()) {
        $myerrorcode = 401;

        $data = json_decode(file_get_contents('php://input'), true);
        $myToken = $data["t"];
        $workspaceId = $data["ws"];
        if (isset($myToken)) {
            if ($myDBConnection->canAccessWorkspace($myToken, $workspaceId)) {
                $myerrorcode = 0;
                $unitId = $data["u"];
                $unitKey = $data["k"];
                $unitLabel = $data["l"];
                $unitDescription = $data["d"];
                try {
                    $myreturn = $myDBConnection->changeUnitProps($workspaceId, $unitId, $unitKey, $unitLabel, $unitDescription);
                } catch (Exception $e) {
                    error_log($e->getMessage());
                    $myerrorcode = $e->getCode();
                    // $myreturn = false;
                }
            }
        }
    }
    unset($myDBConnection);

    if ($myerrorcode > 0) {
        error_log('Errorcode = ' . $myerrorcode);
        http_response_code($myerrorcode);
    } else {
        error_log('Return = ' . json_encode($myreturn));
        echo(json_encode($myreturn));
    }
}
?>
