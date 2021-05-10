<?php
// www.IQB.hu-berlin.de
// 2018
// license: MIT

class DBConnection
{
    protected $pdoDBhandle = false;

    /**
     * @var string Error message (only used by new (construct))
     */
    public string $errorMsg = '';

    /**
     * @var int number of seconds after which the the session maximum lifetime ends (user token becomes invalid)
     */
    private int $sessionMaxLifetime = 60 * 60;

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
     * @param string $sessionId Unique session identifier
     * @param int $userId Unique user identifier
     * @return string Unique session identifier on success or empty string on failure
     */
    private function createSession(string $sessionId, int $userId): string
    {
        $query = "
            INSERT INTO sessions (token, user_id, valid_until)
                VALUES(:token, :userId, CURRENT_TIMESTAMP + :maxLifetime * interval '1 second')
            ";
        //$sessionLifetime =  "$this->sessionMaxLifetime seconds";
        //error_log("sessionLifetime = $sessionLifetime");
        $params = array(
            ':token' => $sessionId,
            ':userId' => $userId,
            ':maxLifetime' => $this->sessionMaxLifetime
        );

        error_log("PARAMS = " . json_encode($params));
        $statement = $this->pdoDBhandle->prepare($query);

        return $statement->execute($params) ? $sessionId : "";
    }

    /**
     * @param string $sessionId Unique session identifier
     */
    private function deleteSession(string $sessionId): void
    {
        $query = "DELETE FROM sessions WHERE sessions.token = :sessionId";
        $params = [
            ':sessionId' => $sessionId
        ];

        $statement = $this->pdoDBhandle->prepare($query);
        $statement->execute($params);
    }

    /**
     * @param int $userId Unique user identifier
     */
    private function deleteSessionsByUserId(int $userId): void
    {
        $query = "DELETE FROM sessions WHERE sessions.user_id = :userId";
        $params = [
            ':userId' => $userId
        ];

        $statement = $this->pdoDBhandle->prepare($query);
        $statement->execute($params);
    }

    /**
     * @param string $sessionId Unique session identifier
     * @return bool True, if session has not expired, otherwise false
     */
    private function validateSession(string $sessionId): bool
    {
        $query = "
            SELECT now() <= valid_until as isValid, valid_until, now() as now
            FROM sessions
            WHERE token = :sessionId
            ";
        $params = [
            ":sessionId" => $sessionId
        ];
        $stmt = $this->pdoDBhandle->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /**
     * Checks the validity of the session for the passed session id.
     * If session is valid, session lifetime will be updated,
     * otherwise the session will be deleted.
     *
     * @param string $sessionId Unique session identifier
     * @return bool TRUE, is session time can be refreshed, otherwise FALSE
     */
    protected function checkSession(string $sessionId): bool
    {
        if (isset($sessionId) && !empty($sessionId) && $this->validateSession($sessionId)) {
            $query = "
                UPDATE sessions
                SET valid_until = CURRENT_TIMESTAMP + :maxLifetime * interval '1 second'
                WHERE token = :sessionId
            ";
            $params = array(
                ':maxLifetime' => $this->sessionMaxLifetime,
                ':sessionId' => $sessionId
            );

            $stmt = $this->pdoDBhandle->prepare($query);
            $isRefreshed = $stmt->execute($params);

        } else {
            $this->deleteSession($sessionId);
        }

        return $isRefreshed ?? false;
    }

    /**
     * @param string $username Username
     * @param string $password User password
     * @return false|array User data, if username and password match, otherwise false
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
    // encrypts password to introduce a very private way (salt)
    protected function encryptPassword(string $password): string
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
                $this->deleteSessionsByUserId($user['id']);

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
    public function getLoginName(string $token): string
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
                        $this->checkSession($token);
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
                        $this->checkSession($token);
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
                        $this->checkSession($token);
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

    function verifyCredentials(string $sessionToken, string $password, bool $superAdminOnly): bool
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

        $params = [
            ':token' => $sessionToken,
            ':password' => $this->encryptPassword($password),
            ':isSuperAdmin' => $superAdminOnly ? "true" : "false"
        ];

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

    public function setMyPassword(string $token, string $oldPassword, string $newPassword): bool
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
                        $this->checkSession($token);
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
