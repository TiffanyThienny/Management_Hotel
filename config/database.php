<?php
// Database Configuration with Error Handling
class Database {
    private $host = "localhost";
    private $db_name = "hotel_management";
    private $username = "root";
    private $password = "";
    public $conn;
    public $error;

    public function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
            $this->error = null;
        } catch(PDOException $exception) {
            $this->error = "Connection failed: " . $exception->getMessage();
            $this->conn = null;
        }
    }

    public function getConnection() {
        if (!$this->conn) {
            $this->connect();
        }
        return $this->conn;
    }

    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $exception) {
            $this->error = "Query failed: " . $exception->getMessage();
            return false;
        }
    }

    public function getSingle($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        if ($stmt) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function getAll($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        if ($stmt) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return [];
    }

    public function insert($table, $data) {
        $columns = implode(", ", array_keys($data));
        $placeholders = ":" . implode(", :", array_keys($data));
        
        $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $this->executeQuery($query, $data);
        
        if ($stmt) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function update($table, $data, $condition) {
        $set = "";
        foreach ($data as $key => $value) {
            $set .= "$key = :$key, ";
        }
        $set = rtrim($set, ", ");

        // Prepare data for binding
        $queryData = $data;
        
        $query = "UPDATE $table SET $set WHERE $condition";
        $stmt = $this->executeQuery($query, $queryData);
        
        if ($stmt !== false) {
            return true;
        }
        return false;
    }

    public function delete($table, $condition) {
        $query = "DELETE FROM $table WHERE $condition";
        $stmt = $this->executeQuery($query);
        
        if ($stmt !== false) {
            return true;
        }
        return false;
    }

    public function rowCount($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        if ($stmt) {
            return $stmt->rowCount();
        }
        return 0;
    }

    public function getError() {
        return $this->error;
    }
}

// Global database instance
$database = new Database();
$db = $database->getConnection();

// Check if database connection is successful
if (!$db && isset($_SERVER['REQUEST_URI']) && !str_contains($_SERVER['REQUEST_URI'], 'install.php')) {
    die("Database connection failed: " . $database->getError());
}
?>