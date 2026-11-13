<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Paginator;
use App\Core\QueryFilter;

/**
 * Bảng admins (tài khoản quản trị: Quản trị viên / Lễ tân).
 */
final class AdminModel extends Model
{
    public function find(int $adminId): ?array
    {
        return $this->db->row('SELECT * FROM admins WHERE admin_id = ?', [$adminId]);
    }

    /** Chỉ tài khoản đang hoạt động (is_active = 1). */
    public function findActive(int $adminId): ?array
    {
        return $this->db->row('SELECT * FROM admins WHERE admin_id = ? AND is_active = 1', [$adminId]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->row('SELECT * FROM admins WHERE username = ?', [$username]);
    }

    public function usernameExists(string $username): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM admins WHERE username = ?', [$username]) > 0;
    }

    public function emailTaken(string $email, int $exceptId): bool
    {
        return $this->db->value('SELECT COUNT(*) FROM admins WHERE email = ? AND admin_id <> ?', [$email, $exceptId]) > 0;
    }

    /** Số Quản trị viên đang hoạt động, không tính tài khoản $exceptId. */
    public function countOtherActiveAdmins(int $exceptId): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM admins WHERE role = 'admin' AND is_active = 1 AND admin_id <> ?", [$exceptId]);
    }

    public function touchLastLogin(int $adminId): void
    {
        $this->db->query('UPDATE admins SET last_login_at = NOW() WHERE admin_id = ?', [$adminId]);
    }

    public function create(string $username, string $fullName, string $email, string $passwordHash, string $role, string $stamp): void
    {
        $this->db->query(
            'INSERT INTO admins (username, full_name, email, password_hash, role, must_change_password, security_stamp) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$username, $fullName, $email, $passwordHash, $role, $stamp]
        );
    }

    public function updateAccount(int $adminId, string $fullName, string $email, string $role, string $stamp): void
    {
        $this->db->query(
            'UPDATE admins SET full_name = ?, email = ?, role = ?, security_stamp = ? WHERE admin_id = ?',
            [$fullName, $email, $role, $stamp, $adminId]
        );
    }

    public function updateProfile(int $adminId, string $fullName, string $email): void
    {
        $this->db->query('UPDATE admins SET full_name = ?, email = ? WHERE admin_id = ?', [$fullName, $email, $adminId]);
    }

    public function updateAvatar(int $adminId, ?string $avatar): void
    {
        $this->db->query('UPDATE admins SET avatar = ? WHERE admin_id = ?', [$avatar, $adminId]);
    }

    /** Đổi mật khẩu + security stamp; $mustChange = true khi người khác đặt lại giúp. */
    public function updatePassword(int $adminId, string $passwordHash, string $stamp, bool $mustChange): void
    {
        $this->db->query(
            'UPDATE admins SET password_hash = ?, must_change_password = ?, security_stamp = ? WHERE admin_id = ?',
            [$passwordHash, $mustChange ? 1 : 0, $stamp, $adminId]
        );
    }

    public function setActive(int $adminId, bool $active, string $stamp): void
    {
        $this->db->query('UPDATE admins SET is_active = ?, security_stamp = ? WHERE admin_id = ?', [$active ? 1 : 0, $stamp, $adminId]);
    }

    public function delete(int $adminId): void
    {
        $this->db->query('DELETE FROM admins WHERE admin_id = ?', [$adminId]);
    }

    public function count(QueryFilter $filter): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM admins WHERE ' . $filter->sql(), $filter->params());
    }

    public function page(QueryFilter $filter, Paginator $pager): array
    {
        return $this->db->all('SELECT * FROM admins WHERE ' . $filter->sql() . ' ORDER BY created_at, admin_id ' . $pager->limitSql(), $filter->params());
    }
}
