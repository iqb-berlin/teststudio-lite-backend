<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

require_once('DBConnection.php');

class DBConnectionSuperAdmin extends DBConnection {

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns all workspaces if the user associated with the given token is superadmin
    // returns [] if token not valid or no workspaces 
    // token is refreshed via isSuperAdmin
    public function getWorkspaces($token) {
        $myreturn = [];
        if ($this->isSuperAdmin($token)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.id, workspaces.name FROM workspaces ORDER BY workspaces.name');
        
            if ($sql -> execute()) {

                $data = $sql->fetchAll(PDO::FETCH_ASSOC);
                if ($data != false) {
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
    public function getWorkspaceName($workspace_id) {
        $myreturn = '';
        if ($this->pdoDBhandle != false) {

            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspaces.name FROM workspaces
                    WHERE workspaces.id=:workspace_id');
                
            if ($sql -> execute(array(
                ':workspace_id' => $workspace_id))) {
                    
                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data['name'];
                }
            }
        }
            
        return $myreturn;
    }


    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns all users if the user associated with the given token is superadmin
    // returns [] if token not valid or no users 
    // token is refreshed via isSuperAdmin
    public function getUsers($token) {
        $myreturn = [];
        if ($this->isSuperAdmin($token)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.name FROM users ORDER BY users.name');
        
            if ($sql -> execute()) {
                $data = $sql->fetchAll(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $myreturn = $data;
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns all workspaces with a flag whether the given user has access to it
    // returns [] if token not valid or user not found
    // token is refreshed via isSuperAdmin
    public function getWorkspacesByUser($token, $username) {
        $myreturn = [];
        if ($this->isSuperAdmin($token)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT workspace_users.workspace_id as id FROM workspace_users
                    INNER JOIN users ON users.id = workspace_users.user_id
                    WHERE users.name=:user_name');
        
            if ($sql -> execute(array(
                ':user_name' => $username))) {

                $userworkspaces = $sql->fetchAll(PDO::FETCH_ASSOC);
                $workspaceIdList = [];
                if ($userworkspaces != false) {
                    foreach ($userworkspaces as $userworkspace) {
                        array_push($workspaceIdList, $userworkspace['id']);
                    }
                }

                $sql = $this->pdoDBhandle->prepare(
                    'SELECT workspaces.id, workspaces.name FROM workspaces ORDER BY workspaces.name');
            
                if ($sql -> execute()) {
                    $allworkspaces = $sql->fetchAll(PDO::FETCH_ASSOC);
                    if ($allworkspaces != false) {
                        foreach ($allworkspaces as $workspace) {
                            array_push($myreturn, [
                                'id' => $workspace['id'],
                                'label' => $workspace['name'],
                                'selected' => in_array($workspace['id'], $workspaceIdList)]);
                        }
                    }
                }
            }
        }
        return $myreturn;
    }


    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // sets workspaces to the given user to give access to it
    // returns false if token not valid or user not found
    // token is refreshed via isSuperAdmin
    public function setWorkspacesByUser($token, $username, $workspaces) {
        $myreturn = false;
        if ($this->isSuperAdmin($token)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.id FROM users
                    WHERE users.name=:user_name');
            if ($sql -> execute(array(
                ':user_name' => $username))) {
                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data != false) {
                    $userid = $data['id'];
                    $sql = $this->pdoDBhandle->prepare(
                        'DELETE FROM workspace_users
                            WHERE workspace_users.user_id=:user_id');
                
                    if ($sql -> execute(array(
                        ':user_id' => $userid))) {

                        $sql_insert = $this->pdoDBhandle->prepare(
                            'INSERT INTO workspace_users (workspace_id, user_id) 
                                VALUES(:workspaceId, :userId)');
                        foreach ($workspaces as $userworkspace) {
                            if ($userworkspace['selected']) {
                                $sql_insert->execute(array(
                                        ':workspaceId' => $userworkspace['id'],
                                        ':userId' => $userid));
                            }
                        }
                        $myreturn = true;
                    }
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // adds new user if no user with the given name exists
    // returns true if ok, false if admin-token not valid or user already exists
    // token is refreshed via isSuperAdmin
    public function addUser($token, $username, $password) {
        $myreturn = false;
        if ($this->isSuperAdmin($token)) {

            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.name FROM users
                    WHERE users.name=:user_name');
                
            if ($sql -> execute(array(
                ':user_name' => $username))) {
                    
                $data = $sql -> fetch(PDO::FETCH_ASSOC);
                if ($data == false) {
                    $passwort_sha = sha1('t' . $password);
                    $sql = $this->pdoDBhandle->prepare(
                        'INSERT INTO users (name, password) VALUES (:user_name, :user_password)');
                        
                    if ($sql -> execute(array(
                        ':user_name' => $username,
                        ':user_password' => $passwort_sha))) {
                            
                        $myreturn = true;
                    }
                }
            }
        }
            
        return $myreturn;
    }
}
?>