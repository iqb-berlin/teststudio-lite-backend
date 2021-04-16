<?php
declare(strict_types=1);

class ValidationReportEntry implements JsonSerializable {

    public string $level = 'info';
    public string $message = '';

    public function __construct(string $level, string $message) {

        $this->level = $level;
        $this->message = $message;
    }

    public function jsonSerialize() {

        $jsonData = [];

        foreach ($this as $key => $value) {

            if (substr($key,0 ,1) != '_') {
                $jsonData[$key] = $value;
            }
        }

        return $jsonData;
    }
}
