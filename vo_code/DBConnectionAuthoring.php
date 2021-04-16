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
    public function getWorkspaceName($workspace_id)
    {
        $myreturn = '';
        if ($this->pdoDBhandle != false) {

            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.name FROM workspaces
                    WHERE workspaces.id=:workspace_id');

            if ($sql->execute(array(
                ':workspace_id' => $workspace_id))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['name'];
                }
            }
        }

        return $myreturn;
    }

    public function addUnit($workspaceId, $unitKey, $unitLabel)
    {
        if (!$this->checkUniqueWorkspaceUnitKey($workspaceId, $unitKey)) {
            throw new Exception("Unit key already exists in workspace (Id: $workspaceId)", 406);
        }

        $sql = $this->pdoDBhandle->prepare(
            'INSERT INTO units (workspace_id, key, label, lastchanged) VALUES (:workspace, :key, :label, :now)'
        );

        return $sql->execute(
            array(
                ':workspace' => $workspaceId,
                ':key' => $unitKey,
                ':label' => $unitLabel,
                ':now' => date('Y-m-d G:i:s', time()
                )
            )
        );
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
    public function writeUnitDefToZipFile($wsId, $unitId, $targetZip, $wsName)
    {
        $myreturn = false;
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.key, units.label, units.def, units.player_id, units.lastchanged FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $xRootElement = new SimpleXMLElement(
                        '<Unit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation=' .
                        '"https://raw.githubusercontent.com/iqb-berlin/testcenter-backend/9.1.2/definitions/vo_Unit.xsd" />'
                    );
                    $xMetadataElement = $xRootElement->addChild('Metadata');
                    $xMetadataElement->addChild('Id', $data['key']);
                    $xMetadataElement->addChild('Label', $data['label']);
                    setlocale(LC_TIME, "de_DE");

                    if (strlen($data['def']) > 1000) {
                        $xDefElement = $xRootElement->addChild('DefinitionRef', $data['key'] . '.voud');
                        $xDefElement->addAttribute('player', $data['player_id']);
                        $targetZip->addFromString($data['key'] . '.voud', $data['def']);
                    } else {
                        $xDefElement = $xRootElement->addChild('Definition', $data['def']);
                        $xDefElement->addAttribute('player', $data['player_id']);
                    }

                    $xfile = dom_import_simplexml($xRootElement)->ownerDocument;
                    $xfile->formatOutput = true;
                    $targetZip->addFromString($data['key'] . '.xml', $xfile->saveXML());
                    $myreturn = true;
                }
            }
        }

        return $myreturn;
    }

    public function getUnitProperties($wsId, $unitId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.description, 
                    units.lastchanged, units.authoringtool_id as authoringtoolId,
                    units.player_id as playerId FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                    setlocale(LC_TIME, "de_DE");
                    $myreturn['lastchangedStr'] = strftime("%d.%m.%Y %H:%M", strtotime($data['lastchanged']));
                    // $myreturn['lastchangedStr'] = strftime('%x', $data['lastchanged']);
                }
            }
        }
        return $myreturn;
    }

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

    public function changeUnitProps($workspaceId, $unitId, $unitKey, $unitLabel, $unitDescription)
    {
        $myreturn = false;
        $newTrimmedKey = trim($unitKey);
        $originUnitKey = $this->fetchUnitKeyById($unitId);

        if (strtolower($originUnitKey) !== strtolower($newTrimmedKey) && !$this->checkUniqueWorkspaceUnitKey($workspaceId, $unitKey)) {
            throw new Exception("Unit key already exists in workspace (Id: $workspaceId)", 406);
        }

        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
            SET key =:key, label=:label, description=:description, lastchanged=:now
            WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $unitId,
                ':key' => $newTrimmedKey,
                ':label' => $unitLabel,
                ':description' => $unitDescription,
                ':now' => date('Y-m-d G:i:s', time())
            ));
        }

        return $myreturn;
    }

    public function setUnitAuthoringTool($myId, $myTool)
    {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET authoringtool_id =:at, player_id=:pl, lastchanged=:now
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $myId,
                ':at' => $myTool,
                ':pl' => '',
                ':now' => date('Y-m-d G:i:s', time())
            ));
        }
        return $myreturn;
    }

    public function setUnitDefinition($myId, $myUnitdef, $playerId)
    {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET def =:ud, player_id=:pl, lastchanged=:now
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $myId,
                ':pl' => $playerId,
                ':ud' => $myUnitdef,
                ':now' => date('Y-m-d G:i:s', time())
            ));
        }
        return $myreturn;
    }

    public function setUnitPlayer($unitId, $playerId)
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
            throw new ErrorException('File upload failed due to unallowed mime type: ' . $mimeType, 415);
        }

        return $mimeType;
    }

}

?>
