<?php
require_once('VeronaFile.class.php');

class VeronaFolder
{
    public static $location = '../verona-modules';

    static function getModuleList(): array {
        $myReturn = [];
        $myFolder = VeronaFolder::$location;
        if (file_exists($myFolder)) {
            $myDir = opendir($myFolder);
            while (($entry = readdir($myDir)) !== false) {
                $fullFilename = $myFolder . '/' . $entry;
                if (is_file($fullFilename)) {
                    $veronaFile = new VeronaFile($fullFilename);
                    if ($veronaFile->isEditor || $veronaFile->isPlayer) {
                        array_push($myReturn, [
                            'id' => $veronaFile->id,
                            'name' => $veronaFile->name,
                            'label' => $veronaFile->label,
                            'version' => $veronaFile->version,
                            'veronaVersion' => $veronaFile->veronaVersion,
                            'filename' => $veronaFile->filename,
                            'filelink' => basename(VeronaFolder::$location) . '/' . $veronaFile->filename,
                            'filesize' => $veronaFile->size,
                            'filesizeStr' => $veronaFile->sizeStr,
                            'fileDatetime' => $veronaFile->fileDate,
                            'fileDatetimeStr' => $veronaFile->fileDateStr,
                            'isPlayer' => $veronaFile->isPlayer,
                            'isEditor' => $veronaFile->isEditor
                        ]);
                    } else {
                        array_push($myReturn, [
                            'filename' => $fullFilename,
                            'error' => $veronaFile->errorMessage
                        ]);
                    }
                }
            }
        }
        return $myReturn;
    }

    static function deleteModule(string $id): bool {
        $myReturn = true;
        $myFolder = VeronaFolder::$location;
        if (file_exists($myFolder)) {
            $myDir = opendir($myFolder);
            while (($entry = readdir($myDir)) !== false) {
                $fullFilename = $myFolder . '/' . $entry;
                if (is_file($fullFilename)) {
                    $veronaFile = new VeronaFile($fullFilename);
                    if ($veronaFile->id == $id) {
                        if (!unlink($fullFilename)) {
                            $myReturn = false;
                        }
                    }
                }
            }
        }
        return $myReturn;
    }

    static function getModuleHtml(string $id): string {
        $myFolder = VeronaFolder::$location;
        if (file_exists($myFolder)) {
            $myDir = opendir($myFolder);
            while (($entry = readdir($myDir)) !== false) {
                $fullFilename = $myFolder . '/' . $entry;
                if (is_file($fullFilename)) {
                    $veronaFile = new VeronaFile($fullFilename);
                    if ($veronaFile->id == $id) {
                        return file_get_contents($fullFilename);
                    }
                }
            }
        }
        return '';
    }
}
