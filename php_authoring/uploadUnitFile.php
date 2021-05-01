<?php
/**
 * www.IQB.hu-berlin.de
 * license: MIT
 *
 * @param string $_POST ["t"] session token
 * @param string $_POST ["p"] process id
 * @param array $_FILES ['unit-file'] array of php file upload metadata
 * @return string on success JSON string of a boolean, otherwise a HTTP response code:
 * <p> 400, if file upload has failed or a zip file cannot be extracted</p>
 * <p> 401, if session token or process id are invalid</p>
 * <p> 409, if an upload file already exists</p>
 * <p> 413, if file upload exceeds the maximum permitted size</p>
 * <p> 500, if an upload file cannot be saved</p>
 * <p> 503, if no database connection can be established</p>
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
        $sessionToken = $_POST["t"];
        $processId = $_POST["p"];
        $phpUploadMetaData = $_FILES['unit-file'];

        if (empty($sessionToken) || empty($processId)) {
            $errorCode = 401;

        } else {
            $uploadPath = UPLOAD_BASE_DIR . $processId . '/';

            try {
                $dbConnection->handleFileUpload($phpUploadMetaData);
                $mimeType = mime_content_type($phpUploadMetaData['tmp_name']);

                if (empty($mimeType)) {
                    error_log("File '" . $phpUploadMetaData['tmp_name'] . "': Mime content type could not be detected!");

                } elseif ($mimeType == 'application/zip') {
                    $dbConnection->extractZipArchive($uploadPath, $phpUploadMetaData['tmp_name']);

                } else {
                    $dbConnection->saveUnitFile($uploadPath, $phpUploadMetaData);
                }

                $return = true;

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
