<?php
// www.IQB.hu-berlin.de
// Bărbulescu, Stroescu, Mechtel
// 2018
// license: MIT

class DBConnection {
    protected $pdoDBhandle = false;
    public $errorMsg = ''; // only used by new (construct)
    private $idletime = 60 * 60; // time the usertoken gets invalid

    // __________________________
    public function __construct() {
        try {
            $cData = json_decode(file_get_contents(__DIR__ . '/DBConnectionData.json'));
            if ($cData->type === 'mysql') {
                $this->pdoDBhandle = new PDO("mysql:host=" . $cData->host . ";port=" . $cData->port . ";dbname=" . $cData->dbname, $cData->user, $cData->password);
            } elseif ($cData->type === 'pgsql') {
                $this->pdoDBhandle = new PDO("pgsql:host=" . $cData->host . ";port=" . $cData->port . ";dbname=" . $cData->dbname . ";user=" . $cData->user . ";password=" . $cData->password);
            }

            $this->pdoDBhandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        } catch(PDOException $e) {
            $this->errorMsg = $e->getMessage();
            $this->pdoDBhandle = false;
        }
    }

    // __________________________
    public function __destruct() {
        if ($this->pdoDBhandle !== false) {
            unset($this->pdoDBhandle);
            $this->pdoDBhandle = false;
        }
    }

    // __________________________
    public function isError() {
        return $this->pdoDBhandle == false;
    }

    // + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + +
    // sets the valid_until of the token to now + idle
    protected function refreshSession($token) {
        $sql_update = $this->pdoDBhandle->prepare(
            'UPDATE sessions
                SET valid_until =:value
                WHERE token =:token');

        if ($sql_update != false) {
            $sql_update->execute(array(
                ':value' => date('Y/m/d h:i:s a', time() + $this->idletime),
                ':token'=> $token));
        }
    }

    // + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + +
    // encrypts password to introduce a very private way (salt)
    protected function encryptPassword($password) {
        return sha1('t' . $password);
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // deletes all tokens of this user if any and creates new token
    public function login($username, $password) {
        $myreturn = '';

        if (($this->pdoDBhandle != false) and (strlen($username) > 0) and (strlen($username) < 50) 
                        and (strlen($password) > 0) and (strlen($password) < 50)) {
            $sql_select = $this->pdoDBhandle->prepare(
                'SELECT * FROM users
                    WHERE users.name = :name AND users.password = :password');
                
            if ($sql_select->execute(array(
                ':name' => $username, 
                ':password' => $this->encryptPassword($password)))) {

                $selector = $sql_select->fetch(PDO::FETCH_ASSOC);
                if ($selector != false) {
                    // first: delete all sessions of this user if any
                    $sql_delete = $this->pdoDBhandle->prepare(
                        'DELETE FROM sessions 
                            WHERE sessions.user_id = :id');

                    if ($sql_delete != false) {
                        $sql_delete -> execute(array(
                            ':id' => $selector['id']
                        ));
                    }

                    // create new token
                    $myToken = uniqid('t', true);
                    
                    $sql_insert = $this->pdoDBhandle->prepare(
                        'INSERT INTO sessions (token, user_id, valid_until) 
                            VALUES(:token, :user_id, :valid_until)');

                    if ($sql_insert != false) {
                        if ($sql_insert->execute(array(
                            ':token' => $myToken,
                            ':user_id' => $selector['id'],
                            ':valid_until' => date('Y-m-d G:i:s', time() + $this->idletime)))) {

                                $myreturn = $myToken;
                        }
                    }
                }
            }
        }
        return $myreturn;
    }
    
    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // deletes all tokens of this user
    public function logout($token) {
        $myreturn = false;
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'DELETE FROM sessions 
                    WHERE sessions.token=:token');
            if ($sql != false) {
                if ($sql -> execute(array(
                    ':token'=> $token))) {
                        $myreturn = true;
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns the name of the user with given (valid) token
    // returns '' if token not found or not valid
    // refreshes token
    public function getLoginName($token) {
        $myreturn = '';
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.name FROM users
                    INNER JOIN sessions ON users.id =sessions.user_id
                    WHERE sessions.token=:token');
    
            if ($sql != false) {
                if ($sql->execute(array(
                    ':token' => $token))) {

                    $first = $sql -> fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $myreturn = $first['name'];
                    }
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns true if the user with given (valid) token is superadmin
    // refreshes token
    public function isSuperAdmin($token) {
        $myreturn = false;
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.is_superadmin FROM users
                    INNER JOIN sessions ON users.id = sessions.user_id
                    WHERE sessions.token=:token');
    
            if ($sql != false) {
                if ($sql -> execute(array(
                    ':token' => $token))) {

                    $first = $sql -> fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $myreturn = $first['is_superadmin'] == 'true';
                    }
                }
            }
        }
        return $myreturn;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns true if the user with given (valid) token is superadmin
    // refreshes token
    public function canAccessWorkspace($token, $workspaceId) {
        $myreturn = false;
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sqlUserId = $this->pdoDBhandle->prepare(
                'SELECT users.id FROM users
                    INNER JOIN sessions ON users.id = sessions.user_id
                    WHERE sessions.token=:token');
    
            if ($sqlUserId != false) {
                if ($sqlUserId -> execute(array(
                    ':token' => $token))) {

                    $first = $sqlUserId -> fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $sqlWorkspace = $this->pdoDBhandle->prepare(
                            'SELECT workspace_users.workspace_id FROM workspace_users
                                WHERE workspace_users.workspace_id=:wsId and workspace_users.user_id=:userId');
            
                        if ($sqlWorkspace -> execute(array(
                            ':wsId' => $workspaceId, ':userId' => $first['id']))) {

                            $first = $sqlWorkspace -> fetch(PDO::FETCH_ASSOC);
                            $myreturn = $first != false;
                        }
                    }
                }
            }
        }
        return $myreturn;
    }
}
?>