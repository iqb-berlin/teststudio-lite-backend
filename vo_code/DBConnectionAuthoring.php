<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license => MIT

require_once('DBConnection.php');

class DBConnectionAuthoring extends DBConnection
{

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

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // adds new user if no user with the given name exists
    // returns true if ok, false if admin-token not valid or user already exists
    // token is refreshed via isSuperAdmin
    public function addUnit($workspaceId, $key, $label, $sourceUnit)
    {
        $myreturn = false;
        if ($sourceUnit == 0) {
            $sql = $this->pdoDBhandle->prepare(
                'INSERT INTO units (workspace_id, key, label, lastchanged) VALUES (:workspace, :key, :label, :now)');

            if ($sql->execute(array(
                ':workspace' => $workspaceId,
                ':key' => $key,
                ':label' => $label,
                ':now' => date('Y-m-d G:i:s', time())
            ))) {

                $myreturn = true;
            }
        } else {
            // look for unit properties
            $sql = $this->pdoDBhandle->prepare(
                'SELECT * FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');

            if ($sql->execute(array(
                ':ws' => $workspaceId,
                ':id' => $sourceUnit))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $sql = $this->pdoDBhandle->prepare(
                        'INSERT INTO units (workspace_id, key, label, lastchanged, description, def, authoringtool_id, player_id, defref) 
                            VALUES (:workspace, :key, :label, :now, :description, :def, :atId, :pId, :defref)');

                    if ($sql->execute(array(
                        ':workspace' => $workspaceId,
                        ':key' => $key,
                        ':label' => $label,
                        ':now' => date('Y-m-d G:i:s', time()),
                        ':description' => $data['description'],
                        ':def' => $data['def'],
                        ':atId' => $data['authoringtool_id'],
                        ':pId' => $data['player_id'],
                        ':defref' => $data['defref']
                    ))) {

                        $myreturn = true;
                    }
                }
            }

        }

        return $myreturn;
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

    public function moveUnits($workspaceId, $unitIds, $targetWorkspace)
    {
        $update_count = 0;
        foreach ($unitIds as $uid) {
            $sql_update = $this->pdoDBhandle->prepare(
                'UPDATE units
                    SET workspace_id =:ws
                    WHERE id =:id');

            if ($sql_update != false) {
                if ($sql_update->execute(array(
                    ':ws' => $targetWorkspace,
                    ':id' => $uid))) {
                    $update_count += 1;
                }
            }
        }

        return $update_count === count($unitIds);
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

    public function getUnitMetadata($unitId)
    {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.description, 
                    units.lastchanged as lastchanged, units.authoringtool_id as editorid,
                    units.player_id as playerid FROM units
                    WHERE units.id =:u');

            if ($sql->execute(array(
                ':u' => $unitId))) {

                $data = $sql->fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                    $myreturn['ts_pdo'] = $data['lastchange'];
                    try {
                        $myreturn['ts_php'] = new DateTimeImmutable($myreturn['ts_pdo']);
                        $myreturn['ts_unix'] = $myreturn['ts_php']->getTimestamp();
                    } catch (Exception $e) {
                        $myreturn['ts_php'] = $e->getMessage();
                        $myreturn['ts_unix'] = 0;
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

    public function setUnitMetadata($myId, $myKey, $myLabel, $myDescription, $editor, $player)
    {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET key=:k, label=:l, description=:d, lastchanged=:now, authoringtool_id=:e, player_id=:p
                WHERE id =:u');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':u' => $myId,
                ':k' => $myKey,
                ':l' => $myLabel,
                ':d' => $myDescription,
                ':e' => $editor,
                ':p' => $player,
                ':now' => date('Y-m-d G:i:s', time())
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
}

?>
