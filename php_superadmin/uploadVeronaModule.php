<?php
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {

    require_once('../vo_code/DBConnectionSuperadmin.php');

    // *****************************************************************

    $myReturn = '';

    $myErrorCode = 503;

    $myDBConnection = new DBConnectionSuperadmin();
    if (!$myDBConnection->isError()) {
        // Achtung: Wenn Datei zu groß, dann ist $_POST nicht gesetzt
        $myToken = $_POST['t'];

        if (isset($myToken)) {
            $myErrorCode = 401;

            if ($myDBConnection->isSuperAdmin($myToken)) {
                require_once('../vo_code/VeronaFile.class.php');
                require_once('../vo_code/VeronaFolder.class.php');
                $veronaFolder = new VeronaFolder();
                $myErrorCode = 0;

                $myReturn = 'e:Interner Fehler: Dateiname oder Formularelement-Name nicht im Request gefunden.';
                $originalTargetFilename = $_FILES['verona-module']['name'];
                if (isset($originalTargetFilename) and strlen($originalTargetFilename) > 0) {
                    $originalTargetFilename = basename($originalTargetFilename);
                    $tempPrefix = DBConnectionSuperAdmin::getTempFilePath() . '/' . uniqid('at_', true) . '_';
                    $tempFilename = $tempPrefix . $originalTargetFilename;

                    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++
                    // move file from php-server-tmp folder to tmp-folder
                    if (move_uploaded_file($_FILES['verona-module']['tmp_name'], $tempFilename)) {
                        $veronaModule = new VeronaFile($tempFilename);
                        if ($veronaModule->isPlayer || $veronaModule->isEditor) {
                            $targetFilename = VeronaFolder::$location . '/' .
                                $veronaModule->name . '@' . $veronaModule->version . '.html';;
                            if (file_exists($targetFilename)) {
                                if (!unlink($targetFilename)) {
                                    $myReturn = 'e:Interner Fehler: Konnte alte Datei nicht löschen.';
                                    $targetFilename = '';
                                    unlink($tempFilename);
                                }
                            } else {
                                if (!rename($tempFilename, $targetFilename)) {
                                    $myReturn = 'e:Interner Fehler: Konnte Datei nicht in Zielordner verschieben (' . $targetFilename . ').';
                                    unlink($tempFilename);
                                } else {
                                    $myReturn = 'OK';
                                }
                            }
                        } else {
                            $myReturn = 'e:Datei nicht als Verona-Modul erkannt: ' . $veronaModule->errorMessage;
                            unlink($tempFilename);
                        }
                    } else {
                        $myReturn = 'e:Datei abgelehnt (Sicherheitsrisiko?)';
                    }
                    // +++++++++++++++++++++++++++++++++++++++++++++++++++++++
                }
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
