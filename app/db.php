<?php
require_once __DIR__ . '/config.php';

class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $pdo;
    
    public function __construct() {
        $this->host = DB_HOST;
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }
    
    /**
     * Kết nối database
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die("Database connection failed: " . $e->getMessage());
            } else {
                error_log("Database connection failed: " . $e->getMessage());
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    /**
     * Lấy PDO instance
     */
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Execute raw query with better error handling
     */
    public function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                throw new Exception('Query execution failed: ' . implode(', ', $stmt->errorInfo()));
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Query execution error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Params: " . print_r($params, true));
            throw $e;
        }
    }
    
    /**
     * Check connection status
     */
    public function isConnected() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Select records with conditions
     */
    public function select($table, $columns = '*', $conditions = [], $orderBy = null, $limit = null) {
        try {
            $sql = "SELECT $columns FROM `$table`";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $key => $value) {
                    $whereClause[] = "`$key` = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            if ($orderBy) {
                $sql .= " ORDER BY $orderBy";
            }
            
            if ($limit) {
                $sql .= " LIMIT $limit";
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("Select error in table '$table': " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Find one record by condition
     */
    public function findOne($table, $conditions = []) {
        try {
            $sql = "SELECT * FROM `$table`";
            $params = [];
            
            if (!empty($conditions)) {
                $whereClause = [];
                foreach ($conditions as $key => $value) {
                    $whereClause[] = "`$key` = ?";
                    $params[] = $value;
                }
                $sql .= " WHERE " . implode(' AND ', $whereClause);
            }
            
            $sql .= " LIMIT 1";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("FindOne error in table '$table': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert new record
     */
    public function insert($table, $data) {
        try {
            $columns = array_keys($data);
            $placeholders = array_fill(0, count($columns), '?');
            
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            return $result ? $this->pdo->lastInsertId() : false;
            
        } catch (PDOException $e) {
            error_log("Insert error in table '$table': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $conditions) {
        try {
            $setClause = [];
            $setValues = [];
            
            foreach ($data as $key => $value) {
                $setClause[] = "`$key` = ?";
                $setValues[] = $value;
            }
            
            $whereClause = [];
            $whereValues = [];
            
            foreach ($conditions as $key => $value) {
                $whereClause[] = "`$key` = ?";
                $whereValues[] = $value;
            }
            
            $sql = "UPDATE `$table` SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(array_merge($setValues, $whereValues));
            
        } catch (PDOException $e) {
            error_log("Update error in table '$table': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete records
     */
    public function delete($table, $conditions) {
        try {
            $whereClause = [];
            $whereValues = [];
            
            foreach ($conditions as $key => $value) {
                $whereClause[] = "`$key` = ?";
                $whereValues[] = $value;
            }
            
            $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $whereClause);
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($whereValues);
            
        } catch (PDOException $e) {
            error_log("Delete error in table '$table': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Insert or Update record
     */
    public function insertOrUpdate($table, $data, $condition = []) {
        try {
            // Try to find existing record first
            if (!empty($condition)) {
                $existing = $this->findOne($table, $condition);
                
                if ($existing) {
                    // Update existing record
                    return $this->update($table, $data, $condition);
                }
            }
            
            // Insert new record if not exists
            return $this->insert($table, $data);
            
        } catch (Exception $e) {
            error_log("InsertOrUpdate error in table '$table': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Execute raw SQL query
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * Get table structure
     */
    public function getTableStructure($table) {
        try {
            $sql = "DESCRIBE `$table`";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Get table structure error for '$table': " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            error_log("Table exists check error for '$table': " . $e->getMessage());
            return false;
        }
    }
}

// Initialize database connection
try {
    $db = new Database();
} catch (Exception $e) {
    die("Failed to initialize database: " . $e->getMessage());
}
?>