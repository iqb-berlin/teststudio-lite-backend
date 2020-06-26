<?php

class DBConnectionStarter extends DBConnection {
    public function addSuperuser($username, $userpassword) {
        $myreturn = '?';
        $sql = $this->pdoDBhandle->prepare(
            'SELECT users.name FROM users');

        if ($sql -> execute()) {

            $data = $sql -> fetchAll(PDO::FETCH_ASSOC);
            if (($data == false) || (count($data) === 0)) {

                $sql = $this->pdoDBhandle->prepare(
                    'INSERT INTO users (name, password, is_superadmin) VALUES (:user_name, :user_password, True)');

                if ($sql -> execute(array(
                    ':user_name' => $username,
                    ':user_password' => $this->encryptPassword($userpassword)))) {

                    $myreturn = 'Superuser "' . $username . '" angelegt.';
                } else {
                    $myreturn = 'Anlegen des Superusers fehlgeschlagen (execute insert).';
                }
            } else {
                $myreturn = 'Ausführung nur möglich, wenn keine anderen User in der Datenbank vorhanden sind.';
            }
        } else {
            $myreturn = 'Anlegen des Superusers fehlgeschlagen (execute select).';
        }
        return $myreturn;
    }

    public function addWorkspace($workspaceName) {

        $sql = $this->pdoDBhandle->prepare('SELECT workspaces.id FROM workspaces WHERE workspaces.name=:ws_name');

        if ($sql->execute([':ws_name' => $workspaceName])) {

            $data = $sql->fetch(PDO::FETCH_ASSOC);

            if ($data == false) {

                $sql = $this->pdoDBhandle->prepare('INSERT INTO workspaces (name) VALUES (:ws_name)');

                if ($sql->execute([':ws_name' => $workspaceName])) {

                    return "Neuer workspace '$workspaceName' angelegt.";
                }

            } else {

                return "Workspace '$workspaceName' bereits vorhanden.";
            }
        }

        return false;
    }

    public function setWorkspaceRights($workspaceName, $userName) {

        $sql = $this->pdoDBhandle->prepare('SELECT workspaces.id FROM workspaces WHERE workspaces.name=:ws_name');
        $sql->execute([':ws_name' => $workspaceName]);
        $workspace = $sql->fetch(PDO::FETCH_ASSOC);

        if ($workspace === false) {
            error_log("workspace $workspaceName not found");
            return false;
        }

        $sql = $this->pdoDBhandle->prepare('SELECT users.id FROM users WHERE users.name=:user_name');
        $sql->execute([':user_name' => $userName]);
        $user = $sql->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            error_log("user $userName not found");
            return false;
        }

        $sql = $this->pdoDBhandle->prepare('DELETE FROM workspace_users WHERE workspace_users.workspace_id=:ws_id');
        $sql->execute([':ws_id' => $workspace['id']]);

        $sql_insert = $this->pdoDBhandle->prepare('INSERT INTO workspace_users (workspace_id, user_id) VALUES(:workspace_id, :user_id)');
        return $sql_insert->execute([
            ':workspace_id' => $workspace['id'],
            ':user_id' => $user['id']
        ]);
    }
}
