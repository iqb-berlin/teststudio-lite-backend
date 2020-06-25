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
}
