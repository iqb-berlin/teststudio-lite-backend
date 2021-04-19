<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

// preflight OPTIONS-Request bei CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit();
} else {
    $myReturn = [
        "app_title" => "IQB-Teststudio",
        "intro_html" => "<p>Diese Web-Anwendung dient der Aufgabenentwicklung zum Einsatz in computerbasierten Leistungstests oder Befragungen. Der Zugang ist nur möglich, wenn Sie Zugangsdaten erhalten haben. Es sind keine weiteren Seiten öffentlich zugänglich.</p><p>Die Programmierungen erfolgten durch das <a href=\"https://www.iqb.hu-berlin.de\" target=\"_blank\">Institut zur Qualitätsentwicklung im Bildungswesen</a>.</p>",
        "impressum_html" => "<p>Diese Installation wurde noch nicht konfiguriert.</p>"
    ];
    $configFileName = "./config/appConfig.json";
    if (file_exists($configFileName)) {
        $myReturn = json_decode(file_get_contents($configFileName), false,512, JSON_UNESCAPED_UNICODE);
    }
    echo(json_encode($myReturn));
}
