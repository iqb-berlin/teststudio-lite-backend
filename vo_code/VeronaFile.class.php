<?php

class VeronaFile {
    public static $sizes = array('Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    public $size = 0;
    public $sizeStr = '0';
    public $fileDate = 0;
    public $fileDateStr = 'n/a';
    public $filename;
    public $meta = [];
    public $validationReport = [];
    public $isPlayer = false;
    public $isEditor = false;

    public function __construct($fullFilename) {
        $this->filename = $fullFilename;
        $this->fileDate = filemtime($fullFilename);
        if ($this->fileDate > 0) {
            $this->fileDateStr = date(DATE_ATOM, $this->fileDate);
            setlocale(LC_TIME, "de_DE");
            $this->fileDateStr = strftime('%x', $this->fileDateStr);
        }
        $this->size = filesize($fullFilename);
        if ($this->size > 0) {
            $this->sizeStr = round($this->size/pow(1024, ($i = floor(log($this->size, 1024)))), 2) . ' ' . VeronaFile::$sizes[$i];
        }
        $fileContent = file_get_contents($fullFilename);
        $document = new DOMDocument();
        $document->loadHTML($fileContent, LIBXML_NOERROR);
        $this->meta['title'] = VeronaFile::getMetaTitle($document);
        $meta = VeronaFile::getMetaElement($document);
        if (!$meta) {
            $this->report('warning', 'No meta-information for this player found.');
            return;
        }
        if (!$meta->getAttribute('content')) {
            $this->report('warning', 'Missing `content` attribute in meta-information!');
            return;
        }

        $this->meta['id'] = $meta->getAttribute('content');
        $this->meta['version'] = $meta->getAttribute('data-version');
        $this->meta['type'] = $meta->getAttribute('data-type');
        $this->meta['verona-version'] = $meta->getAttribute('data-api-version');
        $this->meta['repository-url'] = $meta->getAttribute('data-repository-url');

        foreach ($this->meta as $key => $value) {
            if (!$value) {
                unset($this->meta[$key]);
            }
        }
        if ($this->meta['verona-version']) {
            if ($this->meta['type'] == 'verona-editor') {
                $this->isEditor = true;
            } else {
                $this->isPlayer = true;
            }
        }
    }

    public function report(string $level, string $message): void {

        $this->validationReport[] = new ValidationReportEntry($level, $message);
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

