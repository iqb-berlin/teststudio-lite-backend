<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license => MIT

require_once('DBConnection.php');

class DBConnectionAuthoring extends DBConnection
{
    private function fetchUnitById($unitId)
    {
        $stmt = $this->pdoDBhandle->prepare(
            'SELECT * FROM units WHERE id = :id'
        );

        $stmt->bindParam(':id', $unitId);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchUnitKeyById($unitId)
    {
        $stmt = $this->pdoDBhandle->prepare(
            'SELECT key FROM units WHERE id = :id'
        );
        $stmt->bindParam(':id', $unitId);
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    private function checkUniqueWorkspaceUnitKey($workspaceId, $unitKey)
    {
        $key = strtolower(trim($unitKey));

        $stmt = $this->pdoDBhandle->prepare(
            'SELECT count(*) FROM units WHERE workspace_id = :ws_id AND LOWER(key) = :key'
        );
        $stmt->execute(
            array(
                ':ws_id' => $workspaceId,
                ':key' => $key
            )
        );

        return $stmt->fetchColumn() == 0;
    }


    /**
     * Creates an upload directory at the passed upload path
     *
     * @param string $uploadPath <p>
     * Server upload directory path
     * </p>
     * @throws ErrorException with code 400, if upload directory already exists
     */
    private function createUploadDir(string $uploadPath): void
    {
        if (!file_exists($uploadPath)) {
            if (!mkdir($uploadPath)) {
                $message = "Upload directory could not be created.";
                error_log($message);
                throw new ErrorException($message, 400);
            }
        }
    }

    /**
     * Reads an upload directory at the passed upload path and returns all files with the passed file type extension
     * it contains.
     *
     * @param string $uploadPath <p>
     * Server upload directory path
     * </p>
     * @param string $metadataFileType <p>
     * File type extension (default file pattern: '*.*')
     * </p>
     * @return array of files of passed file type
     * @throws ErrorException with code 500, if upload directory does not exist or contains no files of passed file type
     */
    private function scanUploadDir(string $uploadPath, string $metadataFileType = "*"): array
    {
        if (!(file_exists($uploadPath) && is_dir($uploadPath))) {
            $message = "Upload directory does not exist!";
            error_log($message);
            throw new ErrorException($message, 500);

        } else {
            $files = glob($uploadPath . "/*." . $metadataFileType);

            if (count($files) == 0) {
                $message = "No unit metadata file found in upload directory!";
                error_log($message);
                throw new ErrorException($message, 500);
            }
            return $files;
        }
    }

    public function getUnitListByWorkspace($wsId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label FROM units
                    WHERE units.workspace_id =:ws
                    ORDER BY units.key');

            if ($sql->execute(array(
                ':ws' => $wsId))) {

                $data = $sql->fetchAll(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns all workspaces for the user associated with the given token
    // returns [] if token not valid or no workspaces 
    public function getWorkspaceList($token)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.id, workspaces.name FROM workspaces
                    INNER JOIN workspace_users ON workspaces.id = workspace_users.workspace_id
                    INNER JOIN users ON workspace_users.user_id = users.id
                    INNER JOIN sessions ON  users.id = sessions.user_id
                    WHERE sessions.token =:token
                    ORDER BY workspaces.name');

            if ($sql->execute(array(
                ':token' => $token))) {

                $data = $sql->fetchAll(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $this->refreshSession($token);
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns the name of the workspace given by id
    // returns '' if not found
    // token is not refreshed
    public function getWorkspaceData($workspace_id)
    {
        $myReturn = '';
        if ($this->pdoDBhandle != false) {

            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.id, workspaces.name as label FROM workspaces
                    WHERE workspaces.id=:w');

            if ($sql->execute(array(
                ':w' => $workspace_id))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myReturn = $data;
                }
            }
        }

        return $myReturn;
    }

    public function addUnit($workspaceId, $unitKey, $unitLabel)
    {
        if (!$this->checkUniqueWorkspaceUnitKey($workspaceId, $unitKey)) {
            throw new Exception("Unit key already exists in workspace (Id: $workspaceId)", 406);
        }

        $sql = $this->pdoDBhandle->prepare(
            'INSERT INTO units (workspace_id, key, label, lastchanged) VALUES (:workspace, :key, :label, :now)'
        );

        $sql->execute(
            array(
                ':workspace' => $workspaceId,
                ':key' => $unitKey,
                ':label' => $unitLabel,
                ':now' => date('Y-m-d G:i:s', time()
                )
            )
        );
        return $this->pdoDBhandle->lastInsertId();
    }

    public function copyUnit($targetWorkspaceId, $targetUnitKey, $targetUnitLabel, $sourceUnitId)
    {
        if (!$this->checkUniqueWorkspaceUnitKey($targetWorkspaceId, $targetUnitKey)) {
            throw new Exception("Unit key already exists in workspace (Id: $targetWorkspaceId)", 406);
        }

        $sourceUnit = $this->fetchUnitById($sourceUnitId);
        if (!$sourceUnit) {
            return false;
        } else {
            $sql = $this->pdoDBhandle->prepare(
                'INSERT INTO units (workspace_id, key, label, lastchanged, description, def, authoringtool_id, player_id, defref) ' .
                'VALUES (:workspace, :key, :label, :now, :description, :def, :atId, :pId, :defref)'
            );

            return $sql->execute(
                array(
                    ':workspace' => $targetWorkspaceId,
                    ':key' => $targetUnitKey,
                    ':label' => $targetUnitLabel,
                    ':now' => date('Y-m-d G:i:s', time()),
                    ':description' => $sourceUnit['description'],
                    ':def' => $sourceUnit['def'],
                    ':atId' => $sourceUnit['authoringtool_id'],
                    ':pId' => $sourceUnit['player_id'],
                    ':defref' => $sourceUnit['defref']
                )
            );
        }
    }

    public function deleteUnits($workspaceId, $unitIds)
    {
        $myreturn = false;
        $sql = $this->pdoDBhandle->prepare(
            'DELETE FROM units
                WHERE units.workspace_id = :ws and units.id in (' . implode(',', $unitIds) . ')');

        if ($sql->execute(array(
            ':ws' => $workspaceId))) {

            $myreturn = true;
        }

        return $myreturn;
    }

    public function moveUnits($targetWorkspaceId, $unitIds)
    {
        $unmovableUnits = array();

        foreach ($unitIds as $unitId) {
            $unitKey = $this->fetchUnitKeyById($unitId);

            if (!empty($unitKey)) {
                if (!$this->checkUniqueWorkspaceUnitKey($targetWorkspaceId, $unitKey)) {
                    array_push($unmovableUnits, $this->fetchUnitById($unitId));

                } else {
                    $sql_update = $this->pdoDBhandle->prepare(
                        'UPDATE units SET workspace_id =:ws WHERE id =:id'
                    );
                    $sql_update->execute(
                        array(
                            ':ws' => $targetWorkspaceId,
                            ':id' => $unitId
                        )
                    );
                }
            }
        }

        return $unmovableUnits;
    }

    // filename will be unit key plus extension '.xml'; but if def is big there will be a second file
    // with extension '.voud'
    public function writeUnitDefToZipFile($wsId, $unitId, $targetZip, $maxDefSize)
    {
        $myreturn = false;
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT * FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $xRootElement = new SimpleXMLElement(
                        '<Unit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation=' .
                        '"https://raw.githubusercontent.com/iqb-berlin/testcenter-backend/9.2.0/definitions/vo_Unit.xsd" />'
                    );
                    $xMetadataElement = $xRootElement->addChild('Metadata');
                    $xMetadataElement->addChild('Id', $data['key']);
                    $xMetadataElement->addChild('Label', $data['label']);
                    setlocale(LC_TIME, "de_DE");
                    $xMetadataElement->addChild('Description', $data['description']);
                    $xMetadataElement->addChild('Lastchange', date(DateTime::RFC3339, strtotime($data['lastchanged'])));
                    $xDefElement = null;
                    if (strlen($data['def']) > $maxDefSize) {
                        $xDefElement = $xRootElement->addChild('DefinitionRef', $data['key'] . '.voud');
                        $targetZip->addFromString($data['key'] . '.voud', $data['def']);
                    } else {
                        $xDefElement = $xRootElement->addChild('Definition', $data['def']);
                    }
                    $xDefElement->addAttribute('player', $data['player_id']);
                    $xDefElement->addAttribute('editor', $data['authoringtool_id']);
                    $xDefElement->addAttribute('type', $data['defref']);

                    $xfile = dom_import_simplexml($xRootElement)->ownerDocument;
                    $xfile->formatOutput = true;
                    $targetZip->addFromString($data['key'] . '.xml', $xfile->saveXML());
                    $myreturn = true;
                }
            }
        }

        return $myreturn;
    }

    public function getUnitMetadata($unitId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.description, 
                    units.lastchanged as lastchanged, units.authoringtool_id as editorid,
                    units.player_id as playerid, units.defref as deftype FROM units
                    WHERE units.id =:u');

            if ($sql->execute(array(
                ':u' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                    try {
                        $pdo_timestamp = $data['lastchanged'];
                        $php_datetime = new DateTimeImmutable($pdo_timestamp);
                        $myreturn['lastchanged'] = $php_datetime->getTimestamp();
                    } catch (Exception $e) {
                        $myreturn['lastchanged'] = 0;
                    }
                }
            }
        }
        return $myreturn;
    }

    // todo delete
    public function getUnitDesignData($wsId, $unitId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.def, units.authoringtool_id, units.player_id FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    public function getUnitDefinition($unitId)
    {
        $myreturn = '';
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.def FROM units WHERE units.id =:u');

            if ($sql->execute(array(
                ':u' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['def'];
                }
            }
        }
        return $myreturn;
    }

    public function getUnitPreviewData($wsId, $unitId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.def, units.player_id FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    public function getUnitAuthoringTool($unitId)
    {
        $myreturn = '';
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.authoringtool_id FROM units
                    WHERE units.id =:id');

            if ($sql->execute(array(
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['authoringtool_id'];
                }
            }
        }
        return $myreturn;
    }

    public function getUnitItemPlayerId($unitId)
    {
        $myreturn = '';
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.player_id FROM units
                    WHERE units.id =:id');

            if ($sql->execute(array(
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['player_id'];
                }
            }
        }
        return $myreturn;
    }

    public function changeUnitProps($workspaceId, $unitId, $unitKey, $unitLabel, $unitDescription,
        $player, $editor, $defType)
    {
        $myreturn = false;
        $newTrimmedKey = trim($unitKey);
        $originUnitKey = $this->fetchUnitKeyById($unitId);
        if (strtolower($originUnitKey) !== strtolower($newTrimmedKey) && !$this->checkUniqueWorkspaceUnitKey($workspaceId, $unitKey)) {
            throw new Exception("Unit key already exists in workspace (Id: $workspaceId)", 406);
        }
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
            SET key=:k,
                label=:l,
                authoringtool_id=:e,
                description=:ds,
                defref=:dt,
                player_id=:p
            WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => strval($unitId),
                ':e' => $editor,
                ':k' => $newTrimmedKey,
                ':l' => $unitLabel,
                ':ds' => $unitDescription,
                ':dt' => $defType,
                ':p' => $player
            ));
        }

        return $myreturn;
    }

    public function setUnitEditor($unitId, $editorId)
    {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET editor_id =:e, lastchanged=:now
                WHERE id =:u');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':u' => $unitId,
                ':e' => $editorId,
                ':now' => date('Y-m-d G:i:s', time())
            ));
        }
        return $myreturn;
    }

    public function setUnitDefinition($unitId, $myUnitdef)
    {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET def =:ud, lastchanged=:now
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $unitId,
                ':ud' => $myUnitdef,
                ':now' => date('Y-m-d G:i:s', time())
            ));
        }
        return $myreturn;
    }

    public function setUnitPlayer($unitId, $playerId): bool
    {
        $stmt = $this->pdoDBhandle->prepare('UPDATE units SET player_id= :pl, lastchanged= :now WHERE id = :id');
        $params = array(
            ':id' => $unitId,
            ':pl' => $playerId,
            ':now' => date('Y-m-d G:i:s', time()));

        return $stmt->execute($params);
    }

    /**
     * @param array $fileUploadMetaData <p>
     * Array of file upload metadata
     * </p>
     * @throws ErrorException if file upload has failed
     */
    public function handleFileUpload(array $fileUploadMetaData)
    {
        if (empty($fileUploadMetaData) || $fileUploadMetaData['error'] != UPLOAD_ERR_OK) {
            $fileUploadError = $fileUploadMetaData['error'];
            error_log("fileError = $fileUploadError");

            $errorMessage = 'File Upload failed.';
            $errorCode = 400;

            switch ($fileUploadError) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'The file exceeds the maximum permitted size.';
                    $errorCode = 413;
                    break;

                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = 'No file selected for upload.';
                    break;

                default:
                    break;
            }

            throw new ErrorException($errorMessage, $errorCode);
        }

        error_log("filename = " . $fileUploadMetaData['name']);
        error_log("fileType = " . $fileUploadMetaData['type']);
        error_log("fileTmpName = " . $fileUploadMetaData['tmp_name']);
        error_log("fileSize = " . $fileUploadMetaData['size']);
    }

    /**
     * @param string $filename <p>
     * Path to the tested file.
     * </p>
     * @param array $allowedMimeTypes <p>
     * Array of allowed key value pairs of MIME content type (key) and file type (value)
     * </p>
     * @return false|string $mimeType the allowed content type in MIME format, like
     * text/plain or application/octet-stream.
     * @throws ErrorException with code 415, if the detected MIME content type is not allowed.
     */
    public function verifyMimeType(string $filename, array $allowedMimeTypes)
    {
        $mimeType = mime_content_type($filename);
        error_log("mimeType = $mimeType");

        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new ErrorException('File upload failed due to not allowed mime type: ' . $mimeType, 415);
        }

        return $mimeType;
    }

    /**
     * @param string $uploadPath <p>
     * Server upload directory path
     * </p>
     * @param string $uploadFileTmpName <p>
     * Upload file tmp name
     * </p>
     * @param array $allowedMimeTypes <p>
     * Array of allowed key value pairs of MIME content type (key) and file type (value)
     * </p>
     * @throws ErrorException <p>
     * with code 400, if the zip file is corrupt or cannot be opened
     * </p><p>
     * with code 415, if the detected file extension of an archive entry is not allowed.
     * </p>
     */
    function extractZipArchive(string $uploadPath, string $uploadFileTmpName, array $allowedMimeTypes)
    {
        $zip = new ZipArchive;
        if ($zip->open($uploadFileTmpName) === true) {

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $archiveEntryName = $zip->getNameIndex($i);
                $fileInfo = pathinfo($archiveEntryName);

                error_log("archiveEntryName = " . $archiveEntryName);
                error_log("filename = " . $fileInfo['filename']);
                error_log("dirname = " . $fileInfo['dirname']);
                error_log("basename = " . $fileInfo['basename']);
                error_log("extension = " . $fileInfo['extension']);

                $sourcePath = "zip://" . $uploadFileTmpName . "#" . $archiveEntryName;
                $targetPath = $uploadPath . $fileInfo['basename'];

                error_log("sourcePath = $sourcePath");
                error_log("targetPath = $targetPath");

                $allowedFileTypes = array_values($allowedMimeTypes);
                error_log("Allowed file types:\n" . print_r($allowedFileTypes, true));

                if (!in_array($fileInfo['extension'], $allowedFileTypes)) {
                    throw new ErrorException(
                        'File upload failed due to not allowed file type: ' . $fileInfo['extension'], 415);
                }
                if ($fileInfo['extension'] == 'zip') {
                    error_log("zip in zip");
                    //$this->extractZipArchive($uploadPath, $sourcePath, $allowedMimeTypes);
                }
                //else {
                $this->createUploadDir($uploadPath);

                copy($sourcePath, $targetPath);
                //}
            }

            $zip->close();
        } else {
            throw new ErrorException('Could not extract Zip-File', 400);
        }

        unlink($uploadFileTmpName);
    }


    /**
     * @param string $uploadPath <p>
     * Server upload directory path
     * </p>
     * @param array $phpUploadMetaData <p>
     * Array of file upload metadata
     * </p>
     * @throws ErrorException with code 409 if file already exists
     */
    function saveUnitFile(string $uploadPath, array $phpUploadMetaData)
    {
        $unitFilename = $uploadPath . $phpUploadMetaData['name'];

        error_log("Save unit at '$unitFilename' ...");
        error_log("uploadDir = $uploadPath");
        error_log("filename = " . $phpUploadMetaData['name']);
        error_log("fileType = " . $phpUploadMetaData['type']);
        error_log("fileTmpName = " . $phpUploadMetaData['tmp_name']);
        error_log("fileSize = " . $phpUploadMetaData['size']);

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath);
        }

        if (!file_exists($unitFilename)) {
            move_uploaded_file($phpUploadMetaData['tmp_name'], $unitFilename)
                ? error_log("Unit saved!")
                : error_log("Save failed!");
        } else {
            throw new ErrorException(
                "Uploaded unit file '" . $phpUploadMetaData['name'] . "' already exists!",
                409);
        }
    }

    /**
     * @param string $uploadPath
     * @return array
     * @throws ErrorException
     */
    public function fetchUnitImportData(string $uploadPath): array
    {
        $importData = array();
        $metadataFiles = $this->scanUploadDir($uploadPath, "xml");

        foreach ($metadataFiles as $metadataFile) {
            array_push($importData, $this->parseUnitMetadataFile($metadataFile, $uploadPath));
        }
        return $importData;
    }

    /**
     * @param $metadataFile
     * @param string $uploadPath
     * @return array
     * @throws ErrorException
     */
    public function parseUnitMetadataFile($metadataFile, string $uploadPath): array
    {
        $xml = simplexml_load_file($metadataFile);
        $idNodes = $xml->xpath('/Unit/Metadata/Id');
        $labelNodes = $xml->xpath('/Unit/Metadata/Label');
        $descriptionNodes = $xml->xpath('/Unit/Metadata/Description');
        $lastChangeNodes = $xml->xpath('/Unit/Metadata/Lastchange');
        $definitionRefNodes = $xml->xpath('/Unit/DefinitionRef');
        $definitionNodes = $xml->xpath('/Unit/Definition');

        if (
            count($idNodes) != 1 ||         // mandatory node
            count($labelNodes) > 1 ||       // optional node
            count($descriptionNodes) > 1 || // optional node
            count($lastChangeNodes) > 1 ||  // optional node
            (count($definitionNodes) != 1 && count($definitionRefNodes) != 1)   // mandatory optional nodes
        ) {
            $message = "Unit Metadata file $metadataFile is invalid!";
            error_log($message);
            throw new ErrorException($message, 400);

        } else {
            $unit = array(
                "key" => (string)$idNodes[0],
                "label" => (string)$labelNodes[0],
                "description" => (string)$descriptionNodes[0],
                "lastChange" => (string)$lastChangeNodes[0]
            );
            if (count($definitionRefNodes) != 1) {
                $unit['editor'] = (string)$definitionNodes[0]['editor'];
                $unit['player'] = (string)$definitionNodes[0]['player'];
                $unit['defType'] = (string)$definitionNodes[0]['type'];
                $unit['def'] = (string)$definitionNodes[0];
            } else {
                $unit['editor'] = (string)$definitionRefNodes[0]['editor'];
                $unit['player'] = (string)$definitionRefNodes[0]['player'];
                $unit['defType'] = (string)$definitionRefNodes[0]['type'];
                $unitDefinitionFile = $uploadPath . '/' . $definitionRefNodes[0];
                $unit['def'] = $this->loadUnitDefinitionFile($unitDefinitionFile);
            }
       }

       return $unit;
    }

    /**
     * @param string $unitDefinitionFile
     * @return int
     * @throws ErrorException
     */
    public function loadUnitDefinitionFile(string $unitDefinitionFile): string
    {
        if (!file_exists($unitDefinitionFile)) {
            $message = "Unit definition file does not exist.";
            error_log($message);
            throw new ErrorException($message, 400);

        } else {
            $definition = file_get_contents($unitDefinitionFile);

            if (!$definition) {
                $message = "Could not read unit definition file $unitDefinitionFile!";
                error_log($message);
                throw new ErrorException($message, 400);
            }

            error_log("definition = " . $definition);
        }

        return $definition;
    }

    /**
     * @param string $workspaceId
     * @param array $importData
     * @throws Exception
     */
    public function saveUnitImportData(string $workspaceId, array $importData): void
    {
        foreach ($importData as $import) {
            $newUnitId = $this->addUnit($workspaceId, $import["key"], $import["label"]);
            $this->changeUnitProps($workspaceId, $newUnitId, $import["key"], $import["label"],
                $import["description"], $import["player"], $import["editor"], $import["defType"]);
            $this->setUnitDefinition($newUnitId, $import["def"]);
        }
    }

}

?>
