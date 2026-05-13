<?php
/**
 * Achievement Model and Engine
 */
class Achievement {
    const FIRST_BLOOD = 'First Blood';
    const SPEED_DEMON = 'Speed Demon';
    const PERFECTIONIST = 'Perfectionist';
    const POLYGLOT = 'Polyglot';
    const TOP_3 = 'Top 3';
    const SENIOR_DEV = 'Senior Dev';

    public static function initializeAchievements() {
        $achievements = [
            [
                'name' => self::FIRST_BLOOD,
                'description' => 'Complete your first level',
                'icon' => '🎉',
                'condition_key' => 'first_level_completed',
                'condition_value' => '1',
            ],
            [
                'name' => self::SPEED_DEMON,
                'description' => 'Complete a level in under 60 seconds',
                'icon' => '⚡',
                'condition_key' => 'time_under_60s',
                'condition_value' => '1',
            ],
            [
                'name' => self::PERFECTIONIST,
                'description' => 'Complete a level on the first try with no hints',
                'icon' => '✨',
                'condition_key' => 'first_try_no_hints',
                'condition_value' => '1',
            ],
            [
                'name' => self::POLYGLOT,
                'description' => 'Complete levels in both JS and PHP',
                'icon' => '🌍',
                'condition_key' => 'both_languages',
                'condition_value' => '1',
            ],
            [
                'name' => self::TOP_3,
                'description' => 'Reach the top 3 on the leaderboard',
                'icon' => '🏆',
                'condition_key' => 'leaderboard_rank',
                'condition_value' => '3',
            ],
            [
                'name' => self::SENIOR_DEV,
                'description' => 'Complete all Senior-tier levels',
                'icon' => '👑',
                'condition_key' => 'senior_levels_completed',
                'condition_value' => 'all',
            ],
        ];

        foreach ($achievements as $ach) {
            $existing = DB::queryOne('SELECT id FROM achievements WHERE name = ?', [$ach['name']]);
            if (!$existing) {
                DB::execute(
                    'INSERT INTO achievements (name, description, icon, condition_key, condition_value) VALUES (?, ?, ?, ?, ?)',
                    [$ach['name'], $ach['description'], $ach['icon'], $ach['condition_key'], $ach['condition_value']]
                );
            }
        }
    }

    public static function checkAndUnlock($user_id, $level_id, $time_spent, $tries, $hint_used) {
        $unlocked = [];

        // First Blood
        if (self::checkFirstBlood($user_id)) {
            if ($ach = self::unlock($user_id, self::FIRST_BLOOD)) {
                $unlocked[] = $ach;
            }
        }

        // Speed Demon
        if ($time_spent < 60) {
            if ($ach = self::unlock($user_id, self::SPEED_DEMON)) {
                $unlocked[] = $ach;
            }
        }

        // Perfectionist
        if ($tries == 1 && !$hint_used) {
            if ($ach = self::unlock($user_id, self::PERFECTIONIST)) {
                $unlocked[] = $ach;
            }
        }

        // Polyglot
        if (self::checkPolyglot($user_id)) {
            if ($ach = self::unlock($user_id, self::POLYGLOT)) {
                $unlocked[] = $ach;
            }
        }

        // Top 3
        if (self::checkTopThree($user_id)) {
            if ($ach = self::unlock($user_id, self::TOP_3)) {
                $unlocked[] = $ach;
            }
        }

        // Senior Dev
        if (self::checkSeniorDev($user_id)) {
            if ($ach = self::unlock($user_id, self::SENIOR_DEV)) {
                $unlocked[] = $ach;
            }
        }

        return $unlocked;
    }

    public static function unlock($user_id, $achievement_name) {
        $ach = DB::queryOne('SELECT id FROM achievements WHERE name = ?', [$achievement_name]);
        if (!$ach) return null;

        $existing = DB::queryOne(
            'SELECT id FROM user_achievements WHERE user_id = ? AND achievement_id = ?',
            [$user_id, $ach['id']]
        );

        if ($existing) return null;

        DB::execute(
            'INSERT INTO user_achievements (user_id, achievement_id) VALUES (?, ?)',
            [$user_id, $ach['id']]
        );

        return DB::queryOne('SELECT * FROM achievements WHERE id = ?', [$ach['id']]);
    }

    public static function getUserAchievements($user_id) {
        return DB::queryAll(
            'SELECT a.* FROM achievements a
             INNER JOIN user_achievements ua ON a.id = ua.achievement_id
             WHERE ua.user_id = ?
             ORDER BY ua.unlocked_at DESC',
            [$user_id]
        );
    }

    // Helper functions
    private static function checkFirstBlood($user_id) {
        $count = DB::queryOne(
            'SELECT COUNT(*) as cnt FROM user_progress WHERE user_id = ? AND status = ?',
            [$user_id, 'completed']
        );
        return $count['cnt'] > 0;
    }

    private static function checkPolyglot($user_id) {
        $languages = DB::queryAll(
            'SELECT DISTINCT l.language FROM user_progress up
             INNER JOIN levels l ON up.level_id = l.id
             WHERE up.user_id = ? AND up.status = ?',
            [$user_id, 'completed']
        );
        return count($languages) >= 2;
    }

    private static function checkTopThree($user_id) {
        $rank = DB::queryOne(
            'SELECT rank FROM leaderboard WHERE id = ?',
            [$user_id]
        );
        return $rank && $rank['rank'] <= 3;
    }

    private static function checkSeniorDev($user_id) {
        $seniorLevels = DB::queryOne(
            'SELECT COUNT(*) as total FROM levels WHERE difficulty = ?',
            ['senior']
        );
        $completedSenior = DB::queryOne(
            'SELECT COUNT(*) as completed FROM user_progress up
             INNER JOIN levels l ON up.level_id = l.id
             WHERE up.user_id = ? AND up.status = ? AND l.difficulty = ?',
            [$user_id, 'completed', 'senior']
        );
        return $completedSenior['completed'] == $seniorLevels['total'];
    }
}
