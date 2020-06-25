#!/usr/bin/php
<?php
/**
 * CLi script to initialize app
 *
 * creates a super user
 */


if (php_sapi_name() !== 'cli') {

    header('HTTP/1.0 403 Forbidden');
    echo "This is only for usage from command line.";
    exit(1);
}

require_once "../vo_code/DBConnection.php";
require_once "DBConnectionStarter.class.php";

$retries = 5;

while ($retries--) {

    try {

        error_log("Database Connection attempt");
        $myDBConnection = new DBConnectionStarter();

        if ($myDBConnection->isError()) {
            throw new Exception("DB Connection error: " . $myDBConnection->errorMsg);
        }

        error_log("Database Connection successful");
        break;

    } catch (Exception $t) {

        error_log("Database Connection failed! Retry: $retries attempts left.");
        usleep(20 * 1000000); // give database container time to come up
    }
}



if ($myDBConnection->isError()) {

    echo 'Fehler beim Herstellen der Datenbankverbindung: ' . $myDBConnection->errorMsg;
    echo file_get_contents("../vo_code/DBConnectionData.json");
    exit(1);
}


$arguments = getopt("", [
    'user_name:',
    'user_password:'
]);

if (isset($arguments['user_name']) && isset($arguments['user_password'])) {

    echo $myDBConnection->addSuperuser($arguments['user_name'], $arguments['user_password']);
    exit(0);

} else {

    echo "Unvollständige Parameter. Aufruf:\n";
    echo 'php init.cli.php --user_name=test --user_password=user123';
    exit(1);
}

