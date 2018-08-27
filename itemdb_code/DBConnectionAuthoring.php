<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license => MIT

require_once('DBConnection.php');

class DBConnectionAuthoring extends DBConnection {
    
    public function getUnitListByWorkspace($wsId) {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label FROM units
                    WHERE units.workspace_id =:ws
                    ORDER BY units.key');
        
            if ($sql -> execute(array(
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
    public function getWorkspaceList($token) {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.id, workspaces.name FROM workspaces
                    INNER JOIN workspace_users ON workspaces.id = workspace_users.workspace_id
                    INNER JOIN users ON workspace_users.user_id = users.id
                    INNER JOIN sessions ON  users.id = sessions.user_id
                    WHERE sessions.token =:token
                    ORDER BY workspaces.name');
        
            if ($sql -> execute(array(
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
    // adds new user if no user with the given name exists
    // returns true if ok, false if admin-token not valid or user already exists
    // token is refreshed via isSuperAdmin
    public function addUnit($workspaceId, $key, $label) {
        $myreturn = false;
        $sql = $this->pdoDBhandle->prepare(
            'INSERT INTO units (workspace_id, key, label) VALUES (:workspace, :key, :label)');
            
        if ($sql -> execute(array(
            ':workspace' => $workspaceId,
            ':key' => $key,
            ':label' => $label))) {
                
            $myreturn = true;
        }
            
        return $myreturn;
    }

    public function deleteUnits($workspaceId, $unitIds) {
        $myreturn = false;
        $sql = $this->pdoDBhandle->prepare(
            'DELETE FROM units
                WHERE units.workspace_id = :ws and units.id in (' . implode(',', $unitIds) . ')');
            
        if ($sql -> execute(array(
            ':ws' => $workspaceId))) {
                
            $myreturn = true;
        }
            
        return $myreturn;
    }

    public function getUnitProperties($wsId, $unitId) {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.description, 
                    units.lastchanged, units.authoringtool_id as authoringtoolId FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');
        
            if ($sql -> execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                    setlocale(LC_TIME, "de_DE");
                    $myreturn['lastchangedStr'] = strftime("%d.%m.%Y %H:%M",strtotime($data['lastchanged']));
                    // $myreturn['lastchangedStr'] = strftime('%x', $data['lastchanged']);
                }
            }
        }
        return $myreturn;
    }

    public function getUnitDesignData($wsId, $unitId) {
        $myreturn = [];
        if (($this->pdoDBhandle != false) and ($wsId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.id, units.key, units.label, units.def, units.authoringtool_id, units.player_id FROM units
                    WHERE units.id =:id and units.workspace_id=:ws');
        
            if ($sql -> execute(array(
                ':ws' => $wsId,
                ':id' => $unitId))) {

                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    public function getUnitAuthoringTool($unitId) {
        $myreturn = '';
        if (($this->pdoDBhandle != false) and ($unitId > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT units.authoringtool_id FROM units
                    WHERE units.id =:id');
        
            if ($sql -> execute(array(
                ':id' => $unitId))) {

                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['authoringtool_id'];
                }
            }
        }
        return $myreturn;
    }

    public function changeUnitProps($myId, $myKey, $myLabel, $myDescription) {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET key =:key, label=:label, description=:description
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $myId,
                ':key' => $myKey,
                ':label'=> $myLabel,
                ':description'=>$myDescription
            ));
        }
        return $myreturn;
    }

    public function setUnitAuthoringTool($myId, $myTool) {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET authoringtool_id =:at
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $myId,
                ':at' => $myTool
            ));
        }
        return $myreturn;
    }

    public function setUnitDefinition($myId, $myUnitdef, $playerId) {
        $myreturn = false;
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE units
                SET def =:ud, player_id=:pl
                WHERE id =:id');

        if ($sql_update != false) {
            $myreturn = $sql_update->execute(array(
                ':id' => $myId,
                ':pl' => $playerId,
                ':ud' => $myUnitdef
            ));
        }
        return $myreturn;
    }
    /*
    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns the name of the workspace given by id
    // returns '' if not found
    // token is not refreshed
    public function getWorkspaceName($workspace_id) [
        $myreturn = '';
        if ($this->pdoDBhandle != false) [

            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.name FROM workspaces
                    WHERE workspaces.id=:workspace_id');
                
            if ($sql -> execute(array(
                ':workspace_id' => $workspace_id))) [
                    
                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) [
                    $myreturn = $data['name'];
                ]
            ]
        ]
            
        return $myreturn;
    ] */
}
?>