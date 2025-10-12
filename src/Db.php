<?php

/**
 * Database connection factory
 * 
 * Provides a singleton PDO instance configured for MySQL/MariaDB
 * with appropriate security and performance settings.
 */
class Db
{
    private static ?PDO $pdo = null;
    
    /**
     * Get PDO instance (singleton)
     * 
     * @throws PDOException if connection fails
     */
    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $database = Env::get('DB_NAME');
        $username = Env::get('DB_USER');
        $password = Env::get('DB_PASS');
        
        if (!$database || !$username) {
            throw new InvalidArgumentException('Database credentials not configured in .env');
        }
        
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        try {
            self::$pdo = new PDO($dsn, $username, $password, $options);
            return self::$pdo;
        } catch (PDOException $e) {
            throw new PDOException('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Execute a prepared statement with parameters
     * 
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Fetch a single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }
    
    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }
}