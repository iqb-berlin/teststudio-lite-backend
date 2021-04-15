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

        $sessionToken = $_POST["t"];
        $processId = $_POST["p"];
        $originalTargetFilename = $_FILES['unit-file']['name'];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");
        error_log("originalTargetFilename = $originalTargetFilename");
    }

    unset($myDBConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}
?>
