<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {
    require_once('../vo_code/DBConnection.php');

    // *****************************************************************

    $myReturn = '?';

    $myErrorCode = 503;

    $myDBConnection = new DBConnection();
    if (!$myDBConnection->isError()) {
        $myErrorCode = 401;

        $data = json_decode(file_get_contents('php://input'), true);
        $myToken = $data["t"];
        $myFiles = $data["f"];
        if (isset($myToken)) {
            if ($myDBConnection->isSuperAdmin($myToken)) {
                $myErrorCode = 0;
                require_once('../vo_code/VeronaFolder.class.php');
                $fCount = 0;
                foreach ($myFiles as $moduleId) {
                    if (VeronaFolder::deleteModule($moduleId)) {
                        $fCount = $fCount + 1;
                    }
                }
                $myReturn = $fCount .  ' Datei(en) gelöscht.';
            }
        }
    }
    unset($myDBConnection);

    if ($myErrorCode > 0) {
        http_response_code($myErrorCode);
    } else {
        echo(json_encode($myReturn));
    }
}
?>
