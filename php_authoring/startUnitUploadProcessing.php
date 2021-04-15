<?php
// www.IQB.hu-berlin.de
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {

    require_once('../vo_code/DBConnectionAuthoring.php');

    $return = false;

    $errorCode = 503;

    $dbConnection = new DBConnectionAuthoring();
    if (!$dbConnection->isError()) {
        $errorCode = 401;

        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data["t"];
        $processId = $data["p"];
        $workspaceId = $data["ws"];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");
        error_log("workspaceId = $workspaceId");
    }

    unset($myDBConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}
?>
