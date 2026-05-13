<?php
/**
 * Database Helper Class
 */
class DB {
    private static $pdo = null;

    public static function getInstance() {
        if (self::$pdo === null) {
            require_once __DIR__ . '/../config/db.php';
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    public static function prepare($sql) {
        return self::getInstance()->prepare($sql);
    }

    public static function query($sql, $params = []) {
        $stmt = self::prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function queryOne($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result->fetch();
    }

    public static function queryAll($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result->fetchAll();
    }

    public static function execute($sql, $params = []) {
        return self::query($sql, $params)->rowCount();
    }

    public static function lastInsertId() {
        return self::getInstance()->lastInsertId();
    }

    public static function transaction($callback) {
        $pdo = self::getInstance();
        try {
            $pdo->beginTransaction();
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
