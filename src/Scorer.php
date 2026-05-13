<?php
class Scorer {

    public static function normalize($s) {
        $s = (string)$s;
        $s = str_replace(["\r\n", "\r"], "\n", $s);   // normalize line endings
        $s = str_replace("\xc2\xa0", ' ', $s);         // UTF-8 NBSP → space
        $s = preg_replace('/\p{C}/u', '', $s);         // strip all invisible control chars
        $s = trim($s);
        $s = strtolower($s);
        return $s;
    }

    public static function compareOutput($actual, $expected) {
        return self::normalize($actual) === self::normalize($expected);
    }

    public static function calculatePoints($time_spent_seconds, $tries, $hint_used) {
        $time_penalty = floor($time_spent_seconds / TIME_PENALTY_DIVISOR) * TIME_PENALTY_UNIT;
        $try_penalty  = ($tries - 1) * TRY_PENALTY_UNIT;
        $hint_penalty = $hint_used ? HINT_PENALTY : 0;
        return [
            'base'         => BASE_POINTS,
            'time_penalty' => $time_penalty,
            'try_penalty'  => $try_penalty,
            'hint_penalty' => $hint_penalty,
            'final'        => max(MIN_POINTS, BASE_POINTS - $time_penalty - $try_penalty - $hint_penalty),
        ];
    }

    public static function recordSubmission($user_id, $level_id, $code, $output, $expected, $time_spent, $tries, $hint_used = false) {
        $passed = self::compareOutput($output, $expected);

        if ($passed) {
            $progress = DB::queryOne(
                'SELECT id, status FROM user_progress WHERE user_id = ? AND level_id = ?',
                [$user_id, $level_id]
            );
            if (!$progress) {
                $pts = self::calculatePoints($time_spent, $tries, $hint_used);
                DB::execute(
                    'INSERT INTO user_progress (user_id, level_id, status, tries, time_spent_seconds, points_earned, hint_used, completed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
                    [$user_id, $level_id, 'completed', $tries, $time_spent, $pts['final'], $hint_used ? 1 : 0]
                );
                User::updateTotalPoints($user_id, $pts['final']);
            } elseif ($progress['status'] !== 'completed') {
                $pts = self::calculatePoints($time_spent, $tries, $hint_used);
                DB::execute(
                    'UPDATE user_progress SET status=?, tries=?, time_spent_seconds=?, points_earned=?, hint_used=?, completed_at=CURRENT_TIMESTAMP
                     WHERE user_id=? AND level_id=?',
                    ['completed', $tries, $time_spent, $pts['final'], $hint_used ? 1 : 0, $user_id, $level_id]
                );
                User::updateTotalPoints($user_id, $pts['final']);
            }
            // already completed — no points change, but still passed
        } else {
            $progress = DB::queryOne(
                'SELECT id FROM user_progress WHERE user_id = ? AND level_id = ?',
                [$user_id, $level_id]
            );
            if ($progress) {
                DB::execute(
                    'UPDATE user_progress SET tries = tries + 1, time_spent_seconds = ? WHERE user_id = ? AND level_id = ?',
                    [$time_spent, $user_id, $level_id]
                );
            } else {
                DB::execute(
                    'INSERT INTO user_progress (user_id, level_id, status, tries, time_spent_seconds) VALUES (?, ?, ?, ?, ?)',
                    [$user_id, $level_id, 'in_progress', 1, $time_spent]
                );
            }
        }

        DB::execute(
            'INSERT INTO submissions (user_id, level_id, code_submitted, output_produced, expected_output, time_spent_seconds, tries, passed)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$user_id, $level_id, $code, $output, $expected, $time_spent, $tries, $passed ? 1 : 0]
        );

        return ['passed' => $passed];
    }

    public static function getSubmissionHistory($user_id, $level_id, $limit = 50) {
        return DB::queryAll(
            'SELECT * FROM submissions WHERE user_id = ? AND level_id = ? ORDER BY created_at DESC LIMIT ?',
            [$user_id, $level_id, $limit]
        );
    }
}
