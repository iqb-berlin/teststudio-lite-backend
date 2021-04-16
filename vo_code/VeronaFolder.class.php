<?php
class VeronaFolder
{
    public static string $location = '../vo_verona';

    static function getModuleList(): array {
        $myReturn = [];
        $myFolder = VeronaFolder::$location;
        if (file_exists($myFolder)) {
            $myDir = opendir($myFolder);
            while (($entry = readdir($myDir)) !== false) {
                $fullFilename = $myFolder . '/' . $entry;
                if (is_file($fullFilename)) {
                    $veronaFile = new VeronaFile($fullFilename);
                    array_push($myReturn, [
                        'id' => $veronaFile->meta['id'],
                        'name' => $veronaFile->meta['title'],
                        'verona-version' => $veronaFile->meta['verona-version'],
                        'filename' => $veronaFile->filename,
                        'filesize' => $veronaFile->size,
                        'filesizeStr' => $veronaFile->sizeStr,
                        'fileDatetime' => $veronaFile->fileDate,
                        'fileDatetimeStr' => $veronaFile->fileDateStr,
                        'isPlayer' => $veronaFile->isPlayer,
                        'isEditor' => $veronaFile->isEditor
                    ]);
                }
            }
        }
        return $myReturn;
    }
}
