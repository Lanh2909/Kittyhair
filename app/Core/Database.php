<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Kết nối CSDL dùng chung theo mẫu Singleton (thay cho db(), db_query(), db_row(), db_all(), db_value()).
 *
 * - Constructor private: bên ngoài không thể "new Database()", chỉ lấy qua Database::getInstance().
 * - Kết nối PDO được mở ở lần truy vấn đầu tiên.
 * - Mọi truy vấn đều là prepared statement -> chống SQL Injection.
 */
final class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Đối tượng PDO gốc (mở kết nối nếu chưa có). */
    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connect();
        }
        return $this->pdo;
    }

    private function connect(): PDO
    {
        try {
            /* CONNECT-BEGIN */
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $pdo->exec("SET time_zone = '+07:00'");
            /* CONNECT-END */
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            http_response_code(500);
            exit('Không thể kết nối cơ sở dữ liệu. Vui lòng kiểm tra file config/config.php.');
        }
        return $pdo;
    }

    /** Chạy truy vấn có tham số và trả về statement. */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Lấy 1 dòng (hoặc null). */
    public function row(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** Lấy tất cả dòng. */
    public function all(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** Lấy 1 giá trị đơn (cột đầu tiên của dòng đầu tiên). */
    public function value(string $sql, array $params = [])
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo()->commit();
    }

    public function rollBack(): void
    {
        $this->pdo()->rollBack();
    }

    public function inTransaction(): bool
    {
        return $this->pdo()->inTransaction();
    }

    /**
     * Chạy $work trong 1 transaction: thành công thì COMMIT, có lỗi thì ROLLBACK rồi ném lại lỗi.
     * @return mixed giá trị $work trả về
     */
    public function transaction(callable $work)
    {
        $this->beginTransaction();
        try {
            $result = $work($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->inTransaction()) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}
