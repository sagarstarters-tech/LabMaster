<?php
/**
 * LabMaster - Database Connection Class
 * High-performance PDO connection handler with transaction support
 */

declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'labmaster_db';
    private const DB_USER = 'root';
    private const DB_PASS = '';
    private const DB_CHARSET = 'utf8mb4';

    /**
     * Get or create PDO singleton instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host = self::DB_HOST;
            $port = self::DB_PORT;
            $name = self::DB_NAME;
            $user = self::DB_USER;
            $pass = self::DB_PASS;
            $charset = self::DB_CHARSET;

            // Optional local / live server configuration override
            $localConfig = __DIR__ . '/database.local.php';
            if (file_exists($localConfig)) {
                $custom = require $localConfig;
                if (is_array($custom)) {
                    $host = $custom['host'] ?? $host;
                    $port = (string)($custom['port'] ?? $port);
                    $name = $custom['name'] ?? $name;
                    $user = $custom['user'] ?? $user;
                    $pass = $custom['pass'] ?? $pass;
                    $charset = $custom['charset'] ?? $charset;
                }
            } elseif (getenv('DB_NAME')) {
                $host = getenv('DB_HOST') ?: $host;
                $port = getenv('DB_PORT') ?: $port;
                $name = getenv('DB_NAME');
                $user = getenv('DB_USER') ?: $user;
                $pass = getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : $pass;
            }

            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $host,
                $port,
                $name,
                $charset
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log('Database Connection Error: ' . $e->getMessage());
                die('Database Connection Failed. Please ensure MariaDB/MySQL is active and credentials are correct.');
            }
        }

        return self::$instance;
    }

    /**
     * Execute parameterized query helper
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch single row helper
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all rows helper
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Execute insert/update/delete and return affected row count
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Fetch single column value helper
     */
    public static function fetchColumn(string $sql, array $params = [], int $columnNumber = 0): mixed
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchColumn($columnNumber);
    }

    /**
     * Execute insert query and return the last inserted ID as integer
     */
    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int)self::lastInsertId();
    }

    /**
     * Get last inserted ID
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }
}
