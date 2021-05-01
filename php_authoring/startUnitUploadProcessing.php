<?php
/**
 * www.IQB.hu-berlin.de
 * license: MIT
 *
 * @param string a json string decoded as associate array '$data'
 * <p>string $data["t"] session token</p>
 * <p>string $data["p"] unique process identifier</p>
 * <p>int $data["ws"] unique workspace id</p>
 * @return string on success JSON string of an array of unit import data, otherwise a HTTP response code:
 * <p> 400, if file upload has failed or a zip file cannot be extracted</p>
 * <p> 401, if session token or process id are invalid or workspace access is not granted</p>
 * <p> 404, if no import metadata files can be found</p>
 * <p> 500, if an upload directory does not exist</p>
 * <p> 503, if no database connection can be established</p>
 */
const UPLOAD_BASE_DIR = '../vo_tmp/';

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();

} else {
    require_once('../vo_code/DBConnectionAuthoring.php');

    $return = array();
    $errorCode = 0;
    $dbConnection = new DBConnectionAuthoring();

    if ($dbConnection->isError()) {
        $errorCode = 503;

    } else {
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data["t"];
        $processId = $data["p"];
        $workspaceId = $data["ws"];

        if (empty($sessionToken) ||
            empty($processId) ||
            !$dbConnection->canAccessWorkspace($sessionToken, $workspaceId)
        ) {
            $errorCode = 401;

        } else {
            $uploadPath = UPLOAD_BASE_DIR . $processId . '/';

            try {
                $importData = $dbConnection->fetchUnitImportData($uploadPath);
                if (empty($importData)) {
                    $errorCode = 404;
                } else {
                    $return = $dbConnection->saveUnitImportData($workspaceId, $importData);
                }

            } catch (Exception $exception) {
                error_log("Upload processing failed: " . $exception->getMessage());
                $errorCode = $exception->getCode();

            } finally {
                $dbConnection->cleanUpImportDirectory($uploadPath)
                    ? error_log("Upload directory '$uploadPath' deleted.")
                    : error_log("Upload directory '$uploadPath' cannot be deleted!");
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
