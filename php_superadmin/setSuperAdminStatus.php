<?php
// www.IQB.hu-berlin.de
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {
    require_once('../vo_code/DBConnectionSuperadmin.php');

    // *****************************************************************

    $return = false;

    $errorCode = 503;

    $myDBConnection = new DBConnectionSuperadmin();

    if (!$myDBConnection->isError()) {
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionToken = $data["t"];
        $userId = $data["u"];
        $superAdminPw = $data["p"];
        $isSuperAdmin = $data["s"];

        if (isset($sessionToken) && isset($userId) && isset($superAdminPw) && isset($isSuperAdmin)) {
            if ($myDBConnection->setSuperAdminFlag($sessionToken, $userId, $superAdminPw, $isSuperAdmin)) {
                $errorCode = 0;
                $return = true;
            } else {
                $errorCode = 401;
            }
        } else {
            $errorCode = 400;
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
