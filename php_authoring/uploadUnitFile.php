<?php
// www.IQB.hu-berlin.de
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {

    require_once('../vo_code/DBConnectionAuthoring.php');

    $return = false;

    error_log(json_encode($_FILES));

    $allowed_mime_types = [
        'text/xml' => 'xml',
        'application/octet-stream' => 'voud',
        'application/json' => 'json',
        'application/zip' => 'zip'
    ];

    $dbConnection = new DBConnectionAuthoring();
    if (!$dbConnection->isError()) {
        $sessionToken = $_POST["t"];
        $processId = $_POST["p"];
        $uploadedFile = $_FILES['unit-file'];

        error_log("sessionToken = $sessionToken");
        error_log("processId = $processId");

        if (isset($sessionToken) && isset($processId)) {

            if (isset($uploadedFile) && $uploadedFile['error'] == UPLOAD_ERR_OK && isset($allowed_mime_types[mime_content_type($uploadedFile['tmp_name'])])) {
                $filename = $uploadedFile['name'];
                $fileType = $uploadedFile['type'];
                $fileTmpName = $uploadedFile['tmp_name'];
                $fileError = $uploadedFile['error'];
                $fileSize = $uploadedFile['size'];

                $mimeType = mime_content_type($fileTmpName);

                error_log("filename = $filename");
                error_log("fileType = $fileType");
                error_log("fileTmpName = $fileTmpName");
                error_log("fileError = $fileError");
                error_log("fileSize = $fileSize");
                error_log("mimeType = $mimeType");

            } else {
                $errorMessage = 'File Upload failed.';
                $errorCode = 400;

                if (isset($uploadedFile) && !isset($allowed_mime_types[mime_content_type($uploadedFile['tmp_name'])])) {
                    $errorMessage = 'File upload failed due to unallowed mime type: ' . mime_content_type($uploadedFile['tmp_name']);
                    $errorCode = 415;
                } else {
                    switch ($uploadedFile['error']) {
                        case UPLOAD_ERR_OK:
                            $errorMessage = '';
                            $errorCode = 0;
                            break;

                        case UPLOAD_ERR_INI_SIZE:
                        case UPLOAD_ERR_FORM_SIZE:
                            $errorMessage = 'The file exceeds the maximum permitted size.';
                            $errorCode = 413;
                            break;

                        case UPLOAD_ERR_NO_FILE:
                            $errorMessage = 'No file selected for upload.';
                            break;

                        default:
                            break;
                    }
                }

                error_log($errorMessage);
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
