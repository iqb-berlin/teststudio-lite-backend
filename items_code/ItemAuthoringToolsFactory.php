<?php

class ResourceFile {
    private $isXml = false;
    private $size = 0;
    private $filedate;

    public function __construct($filename, $unixtimestamp, $filesize) {
        $this->name = $filename;
        $this->filedate = date(DATE_ATOM, $unixtimestamp);
        $this->size = $filesize;
        $this->isXml = preg_match("/\.(XML|xml|Xml)$/", $filename) == true;
    }

    public function getFileName() {
        return $this->name;
    }

    public function getFileDateTime() {
        if (isset($this->filedate) && (strlen($this->filedate) > 0)) {
            return strtotime ( $this->filedate );
        } else {
            return 0;
        }
    }

    public function getFileDateTimeString() {
        $filedatevalue = $this->getFileDateTime();
        if ($filedatevalue == 0) {
            return 'n/a';
        } else {
            setlocale(LC_TIME, "de_DE");
            return strftime('%x', $filedatevalue);
        }
    }

    public function getFileSize() {
        return $this->size;
    }

    public function getFileSizeString() {
        return FileFactory::filesizeAsString($this->size);
    }

    public function getIsXml() {
        return $this->isXml;
    }
}

// #################################################################################################
class ItemAuthoringToolsFactory {
    private static $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    private static $itemauthoringpath = '../itemauthoringtools';
    private static $itemauthoringMetadataFilename = '../itemauthoringtools/metadata.xml';

    private static function addOrRenameMetadata($id, $name) {
        $myreturn = true;
        $myMetadataXMLFilename = ItemAuthoringToolsFactory::$itemauthoringMetadataFilename;
        if (file_exists($myMetadataXMLFilename)) {
            $xmlfile = simplexml_load_file($myMetadataXMLFilename);
            foreach($xmlfile->children() as $t) { 
                $tid = (string) $t['id'];
                if (isset($tid)) {
                    if ($tid == $id) {
                        unset($t);
                        break;
                    }
                }
            }
            $newElement = $xmlfile->addChild('tool', $name);
            $newElement->addAttribute('id', $id);

            $xmlfile->asXML($myMetadataXMLFilename);
            unset($xmlfile);
        } else {
            $xmlstr = "
            <itemauthoringtools>
                <tool id='" . $id . "'>" . $name . "</tool>
            </itemauthoringtools>";
    
            $dom = new DOMDocument('1.0', 'utf-8');
            $dom->preserveWhiteSpace = FALSE;
            $dom->loadXML($xmlstr);
            $dom->save($myMetadataXMLFilename);
            unset($dom);
        }
    }

    private static function deleteMetadata($id) {
        $myreturn = false;
        $myMetadataXMLFilename = ItemAuthoringToolsFactory::$itemauthoringMetadataFilename;
        if (file_exists($myMetadataXMLFilename)) {
            $xmlfile = simplexml_load_file($myMetadataXMLFilename);
            foreach($xmlfile->children() as $t) { 
                $tid = (string) $t['id'];
                if (isset($tid)) {
                    if ($tid == $id) {
                        $myreturn = true;
                        unset($t);
                        break;
                    }
                }
            }
            if ($myreturn) {
                $xmlfile->asXML($myMetadataXMLFilename);
            }
            unset($xmlfile);
        }
        return $myreturn;
    }

    // __________________________
    static function getItemAuthoringToolsList() {
        $myreturn = [];
        $myfolder = ItemAuthoringToolsFactory::$itemauthoringpath;
        if (file_exists($myfolder)) {
            // read names from metadata-xml
            $nameList = [];
            $myMetadataXMLFilename = ItemAuthoringToolsFactory::$itemauthoringMetadataFilename;
            if (file_exists($myMetadataXMLFilename)) {
                $xmlfile = simplexml_load_file($myMetadataXMLFilename);
                foreach($xmlfile->children() as $t) { 
                    $nameList[(string) $t['id']] = (string) $t;
                }
                unset($xmlfile);
            }
    
            $mydir = opendir($myfolder);
            while (($entry = readdir($mydir)) !== false) {
                $fullname = $myfolder . '/' . $entry;
                if (is_dir($fullname) and ($entry !== '.') and ($entry !== '..')) {
                    // error_log('PHP Notice: ' . $nameList[$entry]);
                    array_push($myreturn, [
                        'id' => $entry,
                        'label' => array_key_exists($entry, $nameList) ? $nameList[$entry] : $entry,
                        'selected' => false]);
                }
            }
        }
        return $myreturn;
    }

    static function addItemAuthoringTool ($id, $name) {
        $myreturn = '';
        $myfolder = ItemAuthoringToolsFactory::$itemauthoringpath;
        if (file_exists($myfolder)) {
            $itemAuthoringToolFolder = $myfolder . '/' . $id;
            if (!file_exists($itemAuthoringToolFolder)) {
                if (mkdir($itemAuthoringToolFolder)) {
                    $myreturn = $id;
                    ItemAuthoringToolsFactory::addOrRenameMetadata($id, $name);
                }
            }
        }
        return $myreturn;
    }

    static function renameItemAuthoringTool ($oldid, $newid, $name) {
        $myreturn = '';
        $myfolder = ItemAuthoringToolsFactory::$itemauthoringpath;
        if (file_exists($myfolder)) {
            $itemAuthoringToolFolder = $myfolder . '/' . $oldid;
            if (file_exists($itemAuthoringToolFolder)) {
                if ($oldid == $newid) {
                    ItemAuthoringToolsFactory::addOrRenameMetadata($oldid, $name);
                } else {
                    $newItemAuthoringToolFolder = $myfolder . '/' . $newid;
                    if (!file_exists($newItemAuthoringToolFolder)) {
                        rename($itemAuthoringToolFolder, $newItemAuthoringToolFolder);
                        ItemAuthoringToolsFactory::deleteMetadata($oldid);
                        ItemAuthoringToolsFactory::addOrRenameMetadata($newid, $name);
                    }
                }
                $myreturn = $newid;
            }
        }
        return $myreturn;
    }

    static function deleteItemAuthoringTool($id) {
        $myreturn = true;
        $myfolder = ItemAuthoringToolsFactory::$itemauthoringpath;
        if (file_exists($myfolder)) {
            $itemAuthoringToolFolder = $myfolder . '/' . $id;
            if (file_exists($itemAuthoringToolFolder)) {
                $files = glob($itemAuthoringToolFolder . '/*', GLOB_MARK);
                foreach ($files as $file) {
                    if (!is_dir($file)) {
                        unlink($file);
                    }
                }
                rmdir($itemAuthoringToolFolder);
                ItemAuthoringToolsFactory::deleteMetadata($id);
            }
        }
        return $myreturn;
    }

    // __________________________
    static function filesizeAsString ( $filesize ) {
        if ($filesize == 0) {
            return '-';
        } else {
            return round($filesize/pow(1024, ($i = floor(log($filesize, 1024)))), 2) . ' ' . FileFactory::$sizes[$i];
        }
    }


}
?>