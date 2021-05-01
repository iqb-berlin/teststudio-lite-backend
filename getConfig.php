<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {
    $myReturn = [];
    $configFileName = "./config/appConfig.json";
    if (file_exists($configFileName)) {
        $myReturn = json_decode(file_get_contents($configFileName), false,512, JSON_UNESCAPED_UNICODE);
    }
    echo(json_encode($myReturn));
}
