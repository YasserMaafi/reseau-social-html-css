<?php
/**
 * User Model
 */
class User {
    public static function getById($user_id) {
        return DB::queryOne(
            'SELECT id, username, email, avatar, bio, role, is_banned, total_points, created_at FROM users WHERE id = ?',
            [$user_id]
        );
    }

    public static function getByUsername($username) {
        return DB::queryOne(
            'SELECT id, username, avatar, bio, total_points, created_at FROM users WHERE username = ? AND is_banned = FALSE',
            [$username]
        );
    }

    public static function updateProfile($user_id, $data) {
        $allowed_fields = ['avatar', 'bio', 'username'];
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) return false;

        $params[] = $user_id;
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        return DB::execute($sql, $params) > 0;
    }

    public static function updateTotalPoints($user_id, $points_increment) {
        return DB::execute(
            'UPDATE users SET total_points = total_points + ? WHERE id = ?',
            [$points_increment, $user_id]
        );
    }

    public static function ban($user_id) {
        return DB::execute('UPDATE users SET is_banned = TRUE WHERE id = ?', [$user_id]) > 0;
    }

    public static function unban($user_id) {
        return DB::execute('UPDATE users SET is_banned = FALSE WHERE id = ?', [$user_id]) > 0;
    }

    public static function changeRole($user_id, $role) {
        if (!in_array($role, [ROLE_USER, ROLE_ADMIN])) {
            return false;
        }
        return DB::execute('UPDATE users SET role = ? WHERE id = ?', [$role, $user_id]) > 0;
    }

    public static function getStats($user_id) {
        $user = self::getById($user_id);
        $progress = DB::queryOne(
            'SELECT COUNT(*) as completed FROM user_progress WHERE user_id = ? AND status = ?',
            [$user_id, 'completed']
        );
        $achievements = DB::queryOne(
            'SELECT COUNT(*) as total FROM user_achievements WHERE user_id = ?',
            [$user_id]
        );

        return [
            'user' => $user,
            'levels_completed' => $progress['completed'] ?? 0,
            'achievements_count' => $achievements['total'] ?? 0,
        ];
    }

    public static function getUserProgress($user_id, $level_id) {
        return DB::queryOne(
            'SELECT * FROM user_progress WHERE user_id = ? AND level_id = ?',
            [$user_id, $level_id]
        );
    }
}
