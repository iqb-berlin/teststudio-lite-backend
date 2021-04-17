<?php

class VeronaFile {
    public static $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    public $size = 0;
    public $sizeStr = '0';
    public $fileDate = 0;
    public $fileDateStr = 'n/a';
    public $filename = '';
    public $version = '';
    public $veronaVersion = '';
    public $name = '';
    public $validationReport = [];
    public $isPlayer = false;
    public $isEditor = false;
    public $label = '';
    public $id = '';
    public $errorMessage = '';

    public function __construct($fullFilename) {
        $this->filename = basename($fullFilename);
        $this->fileDate = filemtime($fullFilename);
        if ($this->fileDate > 0) {
            setlocale(LC_TIME, "de_DE");
            $this->fileDateStr = strftime('%x', $this->fileDate);
        }
        $this->size = filesize($fullFilename);
        if ($this->size > 0) {
            $this->sizeStr = round($this->size/pow(1024, ($i = floor(log($this->size, 1024)))), 2) . ' ' . VeronaFile::$sizes[$i];
        }
        $fileContent = file_get_contents($fullFilename);
        $document = new DOMDocument();
        $document->loadHTML($fileContent, LIBXML_NOERROR);
        $meta = [];
        $meta['title'] = VeronaFile::getMetaTitle($document);
        $metaElement = VeronaFile::getMetaElement($document);
        if (!$metaElement) {
            $this->errorMessage = 'No meta-information for this player found.';
            return;
        }
        if (!$metaElement->getAttribute('content')) {
            $this->errorMessage = 'Missing `content` attribute in meta-information!';
            return;
        }

        $meta['name'] = $metaElement->getAttribute('content');
        $meta['version'] = $metaElement->getAttribute('data-version');
        $meta['type'] = $metaElement->getAttribute('data-type');
        $meta['verona-version'] = $metaElement->getAttribute('data-api-version');
        $meta['repository-url'] = $metaElement->getAttribute('data-repository-url');

        foreach ($meta as $key => $value) {
            if (!$value) {
                unset($meta[$key]);
            }
        }
        if ($meta['verona-version'] && $meta['version'] && $meta['name']) {
            $versionMatches = null;
            preg_match_all('/\d+/', $meta['version'], $versionMatches);
            if ($versionMatches && count($versionMatches) > 2) {
                if ($meta['type'] == 'verona-editor') {
                    $this->isEditor = true;
                } else {
                    // players do not carry type attribute up to verona version 3.0
                    $this->isPlayer = true;
                }
                $this->name = strtolower(trim($meta['name']));
                $this->version = strtolower(trim($meta['version']));
                $this->veronaVersion = strtolower(trim($meta['verona-version']));
                $this->id = $this->name . '@' . $versionMatches[0] . '.' . $versionMatches[1];
                $this->label = $meta['title'] . ' v' . $versionMatches[0] . '.' . $versionMatches[1];
            } else {
                $errSublement = implode(' // ', $versionMatches);
                $this->errorMessage = '`data-version` attribute not semver format as expected (' . $meta['version'] . '/' . $errSublement . ').';
            }
        } else {
            $this->errorMessage = 'Missing `data-api-version` and/or `data-version` attribute in meta-information!';
        }
    }

    private static function getMetaElement(DOMDocument $document): ?DOMElement {

        $metaElements = $document->getElementsByTagName('meta');
        foreach ($metaElements as $metaElement) { /* @var $metaElement DOMElement */
            if ($metaElement->getAttribute('name') == 'application-name') {
                return $metaElement;
            }
        }
        return null;
    }
    private static function getMetaTitle(DOMDocument $document): string {

        $titleElements = $document->getElementsByTagName('title');
        if (!count($titleElements)) {
            return '';
        }
        $titleElement = $titleElements[0]; /* @var $titleElement DOMElement */
        return $titleElement->textContent;
    }
}

