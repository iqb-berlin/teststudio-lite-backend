<?php
// www.IQB.hu-berlin.de
// license: MIT

const UPLOAD_BASE_DIR = '../vo_tmp/';
const ALLOWED_FILE_TYPES = [
    'text/xml' => 'xml',
    'text/plain' => 'voud',
    'application/json' => 'json',
    'application/zip' => 'zip'
];

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {

    require_once('../vo_code/DBConnectionAuthoring.php');

    $return = false;
    $errorCode = 0;

    $dbConnection = new DBConnectionAuthoring();

    if (!$dbConnection->isError()) {
        $sessionToken = $_POST["t"];
        $processId = $_POST["p"];
        $fileUploadMetaData = $_FILES['unit-file'];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");
        error_log(json_encode($_FILES));

        if (!empty($sessionToken) && !empty($processId)) {
            $uploadDir = UPLOAD_BASE_DIR . $processId . '/';
            file_exists($uploadDir) ? $uploadDir : mkdir($uploadDir);

            try {
                $dbConnection->handleFileUpload($fileUploadMetaData);
                $mimeType = $dbConnection->verifyMimeType($fileUploadMetaData['tmp_name'], ALLOWED_FILE_TYPES);

            } catch (Exception $exception) {
                error_log($exception->getMessage());
                $errorCode = $exception->getCode();
            }

        } else {
            $errorCode = 401;
        }

    } else {
        $errorCode = 503;
    }

    unset($myDBConnection);

    if ($errorCode > 0) {
        http_response_code($errorCode);
    } else {
        echo(json_encode($return));
    }
}
?>
