<?php
/**
 * Level Model
 */
class Level {
    public static function getById($level_id) {
        return DB::queryOne(
            'SELECT * FROM levels WHERE id = ?',
            [$level_id]
        );
    }

    public static function getAll($language = null, $difficulty = null) {
        $sql = 'SELECT * FROM levels WHERE 1=1';
        $params = [];

        if ($language) {
            $sql .= ' AND language = ?';
            $params[] = $language;
        }

        if ($difficulty) {
            $sql .= ' AND difficulty = ?';
            $params[] = $difficulty;
        }

        $sql .= ' ORDER BY difficulty, order_index';
        return DB::queryAll($sql, $params);
    }

    public static function getByDifficulty($difficulty, $language = null) {
        $sql = 'SELECT * FROM levels WHERE difficulty = ?';
        $params = [$difficulty];

        if ($language) {
            $sql .= ' AND language = ?';
            $params[] = $language;
        }

        $sql .= ' ORDER BY order_index';
        return DB::queryAll($sql, $params);
    }

    public static function getNext($user_id, $language = null) {
        if (!$user_id) {
            $sql = 'SELECT * FROM levels WHERE 1=1';
            $params = [];
            if ($language) { $sql .= ' AND language = ?'; $params[] = $language; }
            $sql .= ' ORDER BY difficulty, order_index LIMIT 1';
            return DB::queryOne($sql, $params);
        }

        // Return first incomplete level, respecting order
        $sql = 'SELECT l.* FROM levels l
                LEFT JOIN user_progress up ON l.id = up.level_id AND up.user_id = ?
                WHERE (up.id IS NULL OR up.status != ?)';
        $params = [$user_id, 'completed'];

        if ($language) {
            $sql .= ' AND l.language = ?';
            $params[] = $language;
        }

        $sql .= ' ORDER BY
            CASE l.difficulty WHEN \'beginner\' THEN 1 WHEN \'intermediate\' THEN 2 WHEN \'advanced\' THEN 3 WHEN \'senior\' THEN 4 END,
            l.order_index LIMIT 1';
        return DB::queryOne($sql, $params);
    }

    public static function isUnlocked($level_id, $user_id) {
        if (!$user_id) return false;
        $level = self::getById($level_id);
        if (!$level) return false;

        // First level of each difficulty/language is always unlocked
        if ($level['order_index'] == 1) return true;

        // Previous level in same difficulty+language must be completed
        $prev = DB::queryOne(
            'SELECT id FROM levels WHERE difficulty = ? AND language = ? AND order_index = ?',
            [$level['difficulty'], $level['language'], $level['order_index'] - 1]
        );
        if (!$prev) return true;

        $progress = DB::queryOne(
            'SELECT status FROM user_progress WHERE user_id = ? AND level_id = ?',
            [$user_id, $prev['id']]
        );
        return $progress && $progress['status'] === 'completed';
    }

    public static function create($data) {
        $sql = 'INSERT INTO levels (title, language, difficulty, order_index, type, description, expected_output, image_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        
        DB::execute($sql, [
            $data['title'],
            $data['language'],
            $data['difficulty'],
            $data['order_index'],
            $data['type'],
            $data['description'],
            $data['expected_output'] ?? null,
            $data['image_path'] ?? null,
        ]);

        return DB::lastInsertId();
    }

    public static function update($level_id, $data) {
        $allowed_fields = ['title', 'description', 'expected_output', 'image_path', 'order_index'];
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed_fields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $level_id;
        $sql = 'UPDATE levels SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
        return DB::execute($sql, $params) > 0;
    }

    public static function delete($level_id) {
        return DB::execute('DELETE FROM levels WHERE id = ?', [$level_id]) > 0;
    }

    public static function getQuestion($level_id) {
        return DB::queryOne(
            'SELECT * FROM questions WHERE level_id = ? ORDER BY id LIMIT 1',
            [$level_id]
        );
    }
}
