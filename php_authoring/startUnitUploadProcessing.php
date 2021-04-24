<?php
/**
 * www.IQB.hu-berlin.de
 * license: MIT
 *
 *
 */
const UPLOAD_BASE_DIR = '../vo_tmp/';

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();

} else {
    require_once('../vo_code/DBConnectionAuthoring.php');

    $return = false;
    $errorCode = 0;
    $dbConnection = new DBConnectionAuthoring();

    if ($dbConnection->isError()) {
        $errorCode = 503;

    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data["t"];
        $processId = $data["p"];
        $workspaceId = $data["ws"];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");
        error_log("workspaceId = $workspaceId");

        if (empty($sessionToken) ||
            empty($processId) ||
            !$dbConnection->canAccessWorkspace($sessionToken, $workspaceId)
        ) {
            $errorCode = 401;

        } else {
            $uploadPath = UPLOAD_BASE_DIR . $processId . '/';

            try {
                $importData = $dbConnection->fetchUnitImportData($uploadPath, "xml");
                $dbConnection->saveUnitImportData($workspaceId, $importData);

            } catch (Exception $exception) {
                error_log($exception->getMessage());
                $errorCode = $exception->getCode();
            }
        }
    }

    unset($myDBConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}

?>
