<?php
require_once '../app/config.php';
require_once '../app/db.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/migrations/create_email_tables.sql');
    
    // Split by delimiter to handle stored procedures
    $statements = array_filter(
        array_map('trim', 
        preg_split('/DELIMITER\s+\$\$|\$\$\s+DELIMITER\s+;|;(?![^(]*\))/', $sql))
    );
    
    $success = 0;
    $errors = [];
    
    foreach ($statements as $statement) {
        if (!empty($statement) && $statement !== 'DELIMITER') {
            try {
                $pdo->exec($statement);
                $success++;
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false &&
                    strpos($e->getMessage(), 'Duplicate') === false) {
                    $errors[] = substr($statement, 0, 50) . '... : ' . $e->getMessage();
                }
            }
        }
    }
    
    echo "Migration completed!\n";
    echo "Successful statements: $success\n";
    
    if (!empty($errors)) {
        echo "\nErrors encountered:\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }
    
    echo "\nDatabase is ready for email functionality!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}