<?php
// www.IQB.hu-berlin.de
// 2018
// license: MIT

class DBConnection
{
    protected $pdoDBhandle = false;
    public string $errorMsg = ''; // only used by new (construct)
    private int $idleTime = 60 * 60; // time the user token gets invalid

    // __________________________
    public function __construct()
    {
        $cData = json_decode(file_get_contents(__DIR__ . '/DataSource.json'));

        if ($cData->type !== 'pgsql') {
            error_log("Connection type is '$cData->type' but has to be 'pgsql'!");

        } else {
            try {
                $dsn = "pgsql:host=$cData->host;port=$cData->port;dbname=$cData->dbname;user=$cData->user;password=$cData->password";
                $this->pdoDBhandle = new PDO($dsn);
                $this->pdoDBhandle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            } catch (PDOException $e) {
                $this->errorMsg = $e->getMessage();
                $this->pdoDBhandle = false;
                error_log($this->errorMsg);
            }
        }

    }

    // __________________________
    public function __destruct()
    {
        if ($this->pdoDBhandle !== false) {
            unset($this->pdoDBhandle);
            $this->pdoDBhandle = false;
        }
    }

    /**
     * @param string $token
     * @param $id
     * @return string
     */
    private function createSession(string $token, $id): string
    {
        $query = "
            INSERT INTO sessions (token, user_id, valid_until)
                VALUES(:token, :user_id, :valid_until)
            ";
        $params = array(
            ':token' => $token,
            ':user_id' => $id,
            ':valid_until' => date('Y-m-d G:i:s', time() + $this->idleTime)
        );

        $statement = $this->pdoDBhandle->prepare($query);

        return $statement->execute($params) ? $token : "";
    }

    /**
     * @param $userId
     */
    private function deleteSession($userId): void
    {
        $query = "DELETE FROM sessions WHERE sessions.user_id = :userId";
        $params = [
            ':userId' => $userId
        ];

        $statement = $this->pdoDBhandle->prepare($query);

        if ($statement != false) {
            $statement->execute($params);
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @return false|array User data, if username and password match otherwise false
     */
    private function getUser(string $username, string $password): array
    {
        $query = "
                SELECT *
                FROM users
                WHERE users.name = :name
                    AND users.password = :password
                ";
        $params = [
            ':name' => $username,
            ':password' => $this->encryptPassword($password)
        ];

        $sql_select = $this->pdoDBhandle->prepare($query);
        $sql_select->execute($params);

        return $sql_select->fetch(PDO::FETCH_ASSOC);
    }

    // __________________________
    public function isError(): bool
    {
        return $this->pdoDBhandle == false;
    }

    // + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + +
    // sets the valid_until of the token to now + idle
    protected function refreshSession(string $token): void
    {
        $query = "
            UPDATE sessions
            SET valid_until =:value
            WHERE token =:token
        ";
        $params = array(
            ":value" => date('Y/m/d h:i:s a', time() + $this->idleTime),
            ":token" => $token
        );

        $stmt = $this->pdoDBhandle->prepare($query);
        $stmt->execute($params);
    }

    // + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + + +
    // encrypts password to introduce a very private way (salt)
    protected function encryptPassword($password): string
    {
        return sha1('t' . $password);
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // deletes all tokens of this user if any and creates new token
    public function login(string $username, string $password): ?array
    {
        $user = false;

        if (strlen($username) > 0 and strlen($username) < 50 and
            strlen($password) > 0 and strlen($password) < 50) {

            $user = $this->getUser($username, $password);

            if ($user) {
                // first: delete all sessions of this user if any
                $this->deleteSession($user['id']);

                // create new token
                $sessionToken = $this->createSession(uniqid('t', true), $user['id']);
            }
        }

        return isset($sessionToken) && !empty($sessionToken)
            ? ["user" => $user, "sessionToken" => $sessionToken]
            : null;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // deletes all tokens of this user
    public function logout(string $sessionToken): bool
    {
        if ($this->pdoDBhandle != false and !empty($sessionToken)) {
            $query = "
                DELETE FROM sessions
                WHERE sessions.token=:token
                ";
            $params = array(':token' => $sessionToken);

            $statement = $this->pdoDBhandle->prepare($query);
            $result = $statement->execute($params);
        }

        return $result ?? false;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns the name of the user with given (valid) token
    // returns '' if token not found or not valid
    // refreshes token
    public function getLoginName($token)
    {
        $return = '';
        if ($this->pdoDBhandle != false and !empty($token)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.name FROM users
                    INNER JOIN sessions ON users.id =sessions.user_id
                    WHERE sessions.token=:token');

            if ($sql != false) {
                if ($sql->execute(array(
                    ':token' => $token))) {

                    $first = $sql->fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $return = $first['name'];
                    }
                }
            }
        }
        return $return;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns true if the user with given (valid) token is super admin
    // refreshes token
    public function isSuperAdmin($token): bool
    {
        $return = false;
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sql = $this->pdoDBhandle->prepare(
                'SELECT users.is_superadmin FROM users
                    INNER JOIN sessions ON users.id = sessions.user_id
                    WHERE sessions.token=:token');

            if ($sql != false) {
                if ($sql->execute(array(
                    ':token' => $token))) {

                    $first = $sql->fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $return = $first['is_superadmin'] == 'true';
                    }
                }
            }
        }
        return $return;
    }

    // / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / / /
    // returns true if the user with given (valid) token is super admin
    // refreshes token
    public function canAccessWorkspace($token, $workspaceId): bool
    {
        $return = false;
        if (($this->pdoDBhandle != false) and (strlen($token) > 0)) {
            $sqlUserId = $this->pdoDBhandle->prepare(
                'SELECT users.id FROM users
                    INNER JOIN sessions ON users.id = sessions.user_id
                    WHERE sessions.token=:token');

            if ($sqlUserId != false) {
                if ($sqlUserId->execute(array(
                    ':token' => $token))) {

                    $first = $sqlUserId->fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $sqlWorkspace = $this->pdoDBhandle->prepare(
                            'SELECT workspace_users.workspace_id FROM workspace_users
                                WHERE workspace_users.workspace_id=:wsId and workspace_users.user_id=:userId');

                        if ($sqlWorkspace->execute(array(
                            ':wsId' => $workspaceId, ':userId' => $first['id']))) {

                            $first = $sqlWorkspace->fetch(PDO::FETCH_ASSOC);
                            $return = $first != false;
                        }
                    }
                }
            }
        }
        return $return;
    }

    function verifyCredentials($sessionToken, $password, $superAdminOnly): bool
    {
        $result = false;

        $query = "
                SELECT count(*)
                FROM users, sessions
                WHERE sessions.token = :token
                    AND sessions.user_id = users.id
                    AND users.password = :password
                    AND users.is_superadmin = :isSuperAdmin
                    ";

        $params = array(
            ':token' => $sessionToken,
            ':password' => $this->encryptPassword($password),
            ':isSuperAdmin' => $superAdminOnly ? "true" : "false"
        );

        $stmt = $this->pdoDBhandle->prepare($query);
        if ($stmt->execute($params)) {
            $queryResultCount = $stmt->fetchColumn();
            if ($queryResultCount === 1) {
                $result = true;
            } else {
                error_log('Super Admin Verification failed ...');
                if ($queryResultCount === 0) {
                    error_log('Super Admin could not be verified in this session!');
                } else {
                    error_log('More than one Super Admin verified in this session!');
                }
            }
        }

        return $result;
    }

    public function setMyPassword($token, $oldPassword, $newPassword): bool
    {
        $return = false;
        if ($this->verifyCredentials($token, $oldPassword, false)) {
            $sqlUserId = $this->pdoDBhandle->prepare(
                'SELECT users.id FROM users
                    INNER JOIN sessions ON users.id = sessions.user_id
                    WHERE sessions.token=:token');

            if ($sqlUserId != false) {
                if ($sqlUserId->execute(array(
                    ':token' => $token))) {

                    $first = $sqlUserId->fetch(PDO::FETCH_ASSOC);

                    if ($first != false) {
                        $this->refreshSession($token);
                        $sql = $this->pdoDBhandle->prepare(
                            'UPDATE users SET password = :password WHERE id = :user_id');
                        if ($sql->execute(array(
                            ':user_id' => $first['id'],
                            ':password' => $this->encryptPassword($newPassword)))) {
                            $return = true;
                        }
                    }
                }
            }
        }
        return $return;
    }

}
