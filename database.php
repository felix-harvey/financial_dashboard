<?php
class Database {
    private $host = "localhost";
    private $db_name = "financial_dashboard";
    private $username = "root";
    private $password = "";
    private ?PDO $conn = null;

    public function getConnection(): PDO {
        if ($this->conn instanceof PDO) {
            return $this->conn;
        }
        $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            return $this->conn;
        } catch (PDOException $e) {
            // In production, prefer logging rather than echoing the exception
            throw new RuntimeException("Database connection failed.");
        }
    }
}
