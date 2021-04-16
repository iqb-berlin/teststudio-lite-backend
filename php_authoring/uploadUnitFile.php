<?php
/**
 * www.IQB.hu-berlin.de
 * license: MIT
 *
 *
 */
const UPLOAD_BASE_DIR = '../vo_tmp/';
const ALLOWED_FILE_TYPES = array(
    'text/xml' => 'xml',
    'text/plain' => 'voud',
    'application/zip' => 'zip'
);

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
        $sessionToken = $_POST["t"];
        $processId = $_POST["p"];
        $phpUploadMetaData = $_FILES['unit-file'];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");
        error_log(json_encode($_FILES));

        if (empty($sessionToken) || empty($processId)) {
            $errorCode = 401;

        } else {
            $uploadPath = UPLOAD_BASE_DIR . $processId . '/';

            try {
                $dbConnection->handleFileUpload($phpUploadMetaData);
                $mimeType = $dbConnection->verifyMimeType(
                    $phpUploadMetaData['tmp_name'],
                    ALLOWED_FILE_TYPES);

                if ($mimeType == 'application/zip') {
                    $dbConnection->extractZipArchive(
                        $uploadPath,
                        $phpUploadMetaData['tmp_name'],
                        ALLOWED_FILE_TYPES);
                } else {
                    $dbConnection->saveUnitFile($uploadPath, $phpUploadMetaData);
                }

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
