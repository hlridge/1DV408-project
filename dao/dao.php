<?php

namespace BoostMyAllowanceApp\Model\Dao;

use PDO;
use BoostMyAllowanceApp\Model\User;
use BoostMyAllowanceApp\Model\Task;
use BoostMyAllowanceApp\Model\Transaction;
use BoostMyAllowanceApp\Model\AdminUserEntity;

require_once("database-config.php");

class Dao {

    private $connection;
    private $config;

    private $userId;
    private $mappedUserIds;

    public function __construct() {
        $this->config = new DatabaseConfig();

        try {
            $this->connection = new PDO(
                'mysql:host=' . $this->config->host . ';dbname=' . $this->config->database,
                $this->config->username,
                $this->config->password
            );
        } catch (\PDOException $e) {
            throw new \Exception("Kunde inte ansluta till databasen.");
        }
    }
    public function doesUserExist($username) {
        $statement = $this->connection->prepare('
                        SELECT 1
                        FROM user
                        WHERE username = :username');
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return ($row ? true : false);
    }

    public function isUserAdmin($username) {
        $fieldIsAdmin = "is_admin";
        $isAdmin = false;

        $statement = $this->connection->prepare("
                          SELECT $fieldIsAdmin
                          FROM user
                          WHERE username = :username");
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row) {
           $isAdmin = ($row[$fieldIsAdmin]);
        }
        return $isAdmin;
    }

    public function doesCookiePasswordMatch($username, $encryptedCookiePassword) {
        $fieldCookiePassword = "cookie_password";

        $statement = $this->connection->prepare("
                          SELECT $fieldCookiePassword
                          FROM user
                          WHERE username = :username");
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $match = false;
        if ($row) {
            $dbCookiePassword = isset($row[$fieldCookiePassword]) ? $row[$fieldCookiePassword] : false;
            if ($dbCookiePassword) {
                if ($dbCookiePassword == $encryptedCookiePassword) {
                    $match = true;
                }
            }
        }
        return $match;
    }

    public function isCookieExpirationValid($username) {
        $fieldCookieExpiration = "cookie_expiration";

        $statement = $this->connection->prepare("
                          SELECT *
                          FROM user
                          WHERE username = :username");
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $isValid = false;
        if ($row) {
            $dbCookieExpiration = isset($row[$fieldCookieExpiration]) ? $row[$fieldCookieExpiration] : false; //isset really needed?
            if ($dbCookieExpiration) {
                if (strtotime($dbCookieExpiration) > time()) {
                    $isValid = true;
                }
            }
        }

        return $isValid;
    }

    public function doesPasswordMatch($username, $password) {
        //get users salt, get users salted_password
        $fieldSalt = "salt";
        $fieldSaltedPassword = "salted_password";
        $tableUser = "user";
        $fieldUsername = "username";

        $statement = $this->connection->prepare("
                          SELECT $fieldSalt, $fieldSaltedPassword
                          FROM $tableUser
                          WHERE $fieldUsername = :username");
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $isMatch = false;
        if ($row) {
            if ($this->encryptPassword($password, $row[$fieldSalt]) == $row[$fieldSaltedPassword]) {
                $isMatch = true;
            }
        }

        return $isMatch;
    }

    private function encryptPassword($password, $salt) {
        $encryptedPassword = md5($salt.$password); //should probably use a better encryption algorithm
        return $encryptedPassword;
    }

    public function getUserByUsername($username) {
        $tableUser = "user";
        $tableUserToUser = "user_to_user";
        $fieldId = "id";
        $fieldUsername = "username";
        $fieldIsAdmin = "is_admin";
        $fieldName = "name";
        $fieldParentUserId = "parent_user_id";
        $fieldChildUserId = "child_user_id";
        //$fieldSecretToken = "secret_token"; might be needed later

        $statement = $this->connection->prepare("
                        SELECT $fieldId, $fieldIsAdmin, $fieldName
                        FROM $tableUser
                        WHERE $fieldUsername = :username");
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row[$fieldIsAdmin]) {
            $statement = $this->connection->prepare("
                            SELECT $fieldChildUserId
                            FROM $tableUserToUser
                            WHERE $fieldParentUserId = $row[$fieldId]");
        } else {
            $statement = $this->connection->prepare("
                            SELECT $fieldParentUserId
                            FROM $tableUserToUser
                            WHERE $fieldChildUserId = $row[$fieldId]");
        }
        $statement->execute();

        $mappedUsersIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        $user = new User($username, $row[$fieldId], $row[$fieldIsAdmin], $row[$fieldName], $mappedUsersIds);

        return $user;
    }

    public function registerNewUser($username, $password, $name, $isAdmin) {
        $tableUser = "user";
        $fieldUsername = "username";
        $fieldIsAdmin = "is_admin";
        $fieldName = "name";
        $fieldSecretToken = "secret_token";
        $fieldSaltedPassword = "salted_password";
        $fieldSalt = "salt";

        $secretToken = $this->generateSecretToken(); //empty for now...
        $salt = $username; //username as salt
        $saltedPassword = $this->encryptPassword($password, $salt);

        $statement = $this->connection->prepare("
                        INSERT INTO $tableUser ($fieldIsAdmin, $fieldName, $fieldUsername, $fieldSecretToken, $fieldSaltedPassword, $fieldSalt)
                        VALUES (:is_admin, :name, :username, :secret_token, :salted_password, :salt)
                        ");
        $statement->bindParam(':is_admin', $isAdmin, PDO::PARAM_BOOL);
        $statement->bindParam(':name', $name, PDO::PARAM_STR);
        $statement->bindParam(':username', $username, PDO::PARAM_STR);
        $statement->bindParam(':secret_token', $secretToken, PDO::PARAM_STR);
        $statement->bindParam(':salted_password', $saltedPassword, PDO::PARAM_STR);
        $statement->bindParam(':salt', $salt, PDO::PARAM_STR);

        $isSuccess = $statement->execute();

        return $isSuccess;
    }

    /**
     * A secret token would be needed to map another user to ones account
     * This function generates and returns a five digit number.
     * @return int|string
     */
    private function generateSecretToken() {
        //doesn't need to be particularly secure. More important to be easy to type in.
        $token = rand(1,9);
        for ($i = 0; $i < 4; $i++) {
            $token .= rand(0,10);
        }
        return $token;
    }

    public function getTasksByUserId($userId) {
        $tableTask = "task";
        $fieldUserToUserId = "user_to_user_id";

        $fieldId = "id";
        $fieldUnitId = "unit_id";
        $fieldValidFrom = "valid_from";
        $fieldValidTo = "valid_to";
        $fieldRewardValue = "reward_value";
        $fieldPenaltyValue = "penalty_value";
        $fieldTimeOfRequest = "time_of_request";
        $fieldTimeOfResponse = "time_of_response";
        $fieldIsConfirmed = "is_confirmed";
        $fieldIsDenied = "is_denied";
        $fieldTitle = "title";
        $fieldDescription = "description";

        $userToUserIds = $this->getUserToUserIds($userId);

        $tasks = array();

        foreach($userToUserIds as $userToUserId) {
            $statement = $this->connection->prepare("
                        SELECT *
                        FROM $tableTask
                        WHERE $fieldUserToUserId = :user_to_user_id");
            $statement->bindParam(':user_to_user_id', $userToUserId, PDO::PARAM_STR);
            $statement->execute();

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row[$fieldId], $row[$fieldUserToUserId], $row[$fieldUnitId], strtotime($row[$fieldValidFrom]),
                    strtotime($row[$fieldValidTo]), $row[$fieldRewardValue], $row[$fieldPenaltyValue], strtotime($row[$fieldTimeOfRequest]),
                    strtotime($row[$fieldTimeOfResponse]), $row[$fieldIsConfirmed], $row[$fieldIsDenied], $row[$fieldTitle], $row[$fieldDescription]);

                array_push($tasks, $task);
            }
        }
        return $tasks;
    }

    private function getUserToUserIds($userId) {
        $tableUserToUser = "user_to_user";
        $fieldId = "id";
        $fieldChildUserId = "child_user_id";
        $fieldParentUserId = "parent_user_id";

        $statement = $this->connection->prepare("
                        SELECT $fieldId
                        FROM $tableUserToUser
                        WHERE $fieldChildUserId = :child_user_id OR $fieldParentUserId = :parent_user_id");
        $statement->bindParam(':child_user_id', $userId, PDO::PARAM_STR);
        $statement->bindParam(':parent_user_id', $userId, PDO::PARAM_STR);
        $statement->execute();

        $userToUserIds = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $userToUserIds;
    }

    public function getAdminUserEntitiesByUserId($userId) {
        $tableUserToUser = "user_to_user";
        $fieldChildUserId = "child_user_id";
        $fieldParentUserId = "parent_user_id";
        $tableUser = "user";
        $fieldName = "name";
        $fieldId = "id";
        $aliasChildsName = "childs_name";
        $aliasParentsName = "parents_name";
        $aliasTableChild = "table_child";
        $aliasTableParent = "table_parent";
        $aliasChildId = "child_id";
        $aliasParentId = "parent_id";

        $userToUserIds = $this->getUserToUserIds($userId);

        $adminToUserEntities = array();

        foreach ($userToUserIds as $userToUserId) {
            $statement = $this->connection->prepare("
                SELECT $tableUserToUser.$fieldChildUserId AS $aliasChildId,
                  $tableUserToUser.$fieldParentUserId AS $aliasParentId,
                  $aliasTableChild.$fieldName AS $aliasChildsName,
                  $aliasTableParent.$fieldName AS $aliasParentsName
                FROM $tableUser $aliasTableChild
                INNER JOIN $tableUserToUser ON $aliasTableChild.$fieldId = $tableUserToUser.$fieldChildUserId
                INNER JOIN $tableUser $aliasTableParent ON $aliasTableParent.$fieldId = $tableUserToUser.$fieldParentUserId
                WHERE $tableUserToUser.$fieldId = $userToUserId");
            $statement->execute();
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $adminToUserEntity = new AdminUserEntity($userToUserId, $row[$aliasChildId], $row[$aliasParentId], $row[$aliasChildsName], $row[$aliasParentsName]);

            array_push($adminToUserEntities, $adminToUserEntity);
        }

        return $adminToUserEntities;

    }

    public function getTransactionsByUserId($userId) {
        $tableTransaction = "transaction";
        $fieldUserToUserId = "user_to_user_id";

        $fieldId = "id";
        $fieldUnitId = "unit_id";
        $fieldTransactionValue = "transaction_value";
        $fieldTimeOfRequest = "time_of_request";
        $fieldTimeOfResponse = "time_of_response";
        $fieldIsConfirmed = "is_confirmed";
        $fieldIsDenied = "is_denied";
        $fieldDescription = "description";

        $userToUserIds = $this->getUserToUserIds($userId);

        $transactions = array();

        foreach($userToUserIds as $userToUserId) {
            $statement = $this->connection->prepare("
                        SELECT *
                        FROM $tableTransaction
                        WHERE $fieldUserToUserId = :user_to_user_id");
            $statement->bindParam(':user_to_user_id', $userToUserId, PDO::PARAM_STR);
            $statement->execute();

            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $transaction = new Transaction($row[$fieldId], $row[$fieldUserToUserId], $row[$fieldUnitId], strtotime($row[$fieldTimeOfRequest]),
                    strtotime($row[$fieldTimeOfResponse]), $row[$fieldIsConfirmed], $row[$fieldIsDenied], $row[$fieldDescription], $row[$fieldTransactionValue]);

                array_push($transactions, $transaction);
            }
        }
        return $transactions;
    }

    public function getSecretTokenByUserId($userId) {
        $fieldSecretToken = "secret_token";
        $tableUser = "user";
        $fieldId = "id";

        $statement = $this->connection->prepare("
                        SELECT $fieldSecretToken
                        FROM $tableUser
                        WHERE $fieldId = :id");
        $statement->bindParam(':id', $userId, PDO::PARAM_STR);
        $statement->execute();

        $secretToken = $statement->fetchAll(PDO::FETCH_COLUMN)[0];

        return (isset($secretToken) ? $secretToken : "");
    }

    public function connectAccounts($ownUserId, $isOwnUserAdmin, $foreignUsername, $secretToken) {
        $fieldSecretToken = "secret_token";
        $tableUser = "user";
        $fieldUsername = "username";
        $tableUserToUser = "user_to_user";
        $fieldChildUserId = "child_user_id";
        $fieldParentUserId = "parent_user_id";

        $statement = $this->connection->prepare("
                        SELECT $fieldSecretToken
                        FROM $tableUser
                        WHERE $fieldUsername = :username");
        $statement->bindParam(':username', $foreignUsername, PDO::PARAM_STR);
        $statement->execute();
        $savedSecretToken = $statement->fetchAll(PDO::FETCH_COLUMN)[0];
        $otherUser = $this->getUserByUsername($foreignUsername);
        $otherUserId = $otherUser->getId();
        $isSuccess = false;

        //Check if secret tokens match and that admin/parent is not connecting to admin/parent
        //and user/child is not connecting to user/child. This also prevent connecting to oneself.
        if ($savedSecretToken == $secretToken && $isOwnUserAdmin != $otherUser->getIsAdmin()) {
            $statement = $this->connection->prepare("
                        INSERT INTO $tableUserToUser ($fieldChildUserId, $fieldParentUserId)
                        VALUES (:child_user_id, :parent_user_id)
                        ");
            if ($isOwnUserAdmin) {
                $statement->bindParam(':child_user_id', $otherUserId, PDO::PARAM_STR);
                $statement->bindParam(':parent_user_id', $ownUserId, PDO::PARAM_STR);
            } else {
                $statement->bindParam(':child_user_id', $ownUserId, PDO::PARAM_STR);
                $statement->bindParam(':parent_user_id', $otherUserId, PDO::PARAM_STR);
            }
            $isSuccess = $statement->execute();
        }

        return $isSuccess;
    }

    public function storeCookieInfoByUsername($username, $cookieExpirationTime, $cookiePassword) {
        $tableUser = "user";
        $fieldUsername = "username";
        $fieldCookiePassword = "cookie_password";
        $fieldCookieExpiration = "cookie_expiration";
        $cookieExpirationTimeForMySql = date('Y-m-d H:i:s', $cookieExpirationTime);

        $statement = $this->connection->prepare("
                    UPDATE $tableUser
                    SET $fieldCookiePassword = :cookie_password, $fieldCookieExpiration = :cookie_expiration
                    WHERE $fieldUsername = :user_name
                    ");

        $statement->bindParam(':cookie_password', $cookiePassword, PDO::PARAM_STR);
        $statement->bindParam(':cookie_expiration', $cookieExpirationTimeForMySql, PDO::PARAM_STR);
        $statement->bindParam(':user_name', $username, PDO::PARAM_STR);

        return $statement->execute();
    }

    public function insertNewTransaction($adminUserIdentityId, $description, $value, $isConfirmed) {
        $tableTransaction = "transaction";
        $fieldUserToUserId = "user_to_user_id";
        $fieldUnitId = "unit_id";
        $fieldTimeOfRequest = "time_of_request";
        $fieldTimeOfResponse = "time_of_response";
        $fieldIsConfirmed = "is_confirmed";
        $fieldIsDenied = "is_denied";
        $fieldTransactionValue = "transaction_value";
        $fieldDescription = "description";

        $unitId = 1; //hard coded for now...
        $timeOfRequestForMySql = date('Y-m-d H:i:s', time());
        $timeOfResponseForMySql = $isConfirmed ? $timeOfRequestForMySql : null;
        $isDenied = false; //can't be denied upon creation

        $statement = $this->connection->prepare("
                        INSERT INTO $tableTransaction ($fieldUserToUserId, $fieldUnitId, $fieldTimeOfRequest,
                        $fieldTimeOfResponse, $fieldIsConfirmed, $fieldIsDenied, $fieldTransactionValue, $fieldDescription)
                        VALUES (:user_to_user_id, :unit_id, :time_of_request, :time_of_response, :is_confirmed,
                        :is_denied, :transaction_value, :description)
                        ");
        $statement->bindParam(':user_to_user_id', $adminUserIdentityId, PDO::PARAM_INT);
        $statement->bindParam(':unit_id', $unitId, PDO::PARAM_INT);
        $statement->bindParam(':time_of_request', $timeOfRequestForMySql, PDO::PARAM_STR);
        $statement->bindParam(':time_of_response', $timeOfResponseForMySql, PDO::PARAM_STR);
        $statement->bindParam(':is_confirmed', $isConfirmed, PDO::PARAM_BOOL);
        $statement->bindParam(':is_denied', $isDenied, PDO::PARAM_BOOL);
        $statement->bindParam(':transaction_value', $value, PDO::PARAM_INT);
        $statement->bindParam(':description', $description, PDO::PARAM_STR);

        $isSuccess = $statement->execute();

        return $isSuccess;
    }


    public function insertNewTask($adminUserIdentityId, $title, $description, $rewardValue, $penaltyValue, $validFrom, $validTo, $repeatNumberOfWeeks) {
        $tableTask = "task";
        $fieldUserToUserId = "user_to_user_id";
        $fieldTitle = "title";
        $fieldDescription = "description";
        $fieldUnitId = "unit_id";
        $fieldRewardValue = "reward_value";
        $fieldPenaltyValue = "penalty_value";
        $fieldValidFrom = "valid_from";
        $fieldValidTo = "valid_to";
        $fieldIsConfirmed = "is_confirmed";
        $fieldIsDenied = "is_denied";

        $unitId = 1; //hard coded for now...
        $isConfirmed = 0;
        $isDenied = 0;

        $secondsInAWeek = 7 * 24 * 60 * 60;
        $countSuccess = 0;

        for ($i = 0; $i < $repeatNumberOfWeeks; $i++) {

            $validFromForMySql = date('Y-m-d H:i:s', $validFrom);
            $validToForMySql = date('Y-m-d H:i:s', $validTo);

            $statement = $this->connection->prepare("
                            INSERT INTO $tableTask ($fieldUserToUserId, $fieldUnitId, $fieldIsConfirmed, $fieldIsDenied,
                            $fieldRewardValue, $fieldPenaltyValue, $fieldTitle, $fieldDescription, $fieldValidFrom,
                            $fieldValidTo)
                            VALUES (:user_to_user_id, :unit_id, :is_confirmed, :is_denied, :reward_value,
                            :penalty_value, :title, :description, :valid_from, :valid_to)
                            ");
            $statement->bindParam(':user_to_user_id', $adminUserIdentityId, PDO::PARAM_INT);
            $statement->bindParam(':unit_id', $unitId, PDO::PARAM_INT);
            $statement->bindParam(':is_confirmed', $isConfirmed, PDO::PARAM_BOOL);
            $statement->bindParam(':is_denied', $isDenied, PDO::PARAM_BOOL);
            $statement->bindParam(':reward_value', $rewardValue, PDO::PARAM_INT);
            $statement->bindParam(':penalty_value', $penaltyValue, PDO::PARAM_INT);
            $statement->bindParam(':title', $title, PDO::PARAM_STR);
            $statement->bindParam(':description', $description, PDO::PARAM_STR);
            $statement->bindParam(':valid_from', $validFromForMySql, PDO::PARAM_STR);
            $statement->bindParam(':valid_to', $validToForMySql, PDO::PARAM_STR);

            $isSuccess = $statement->execute();
            if ($isSuccess) {
                $countSuccess++;
            }
            var_dump($statement->errorInfo());
            echo PHP_EOL;
            $validFrom = $validFrom  + $secondsInAWeek;
            $validTo = $validTo  + $secondsInAWeek;
        }

        return $countSuccess;
    }
}
