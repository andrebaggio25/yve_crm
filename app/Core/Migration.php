<?php

namespace App\Core;

use PDO;
use PDOException;

class Migration
{
    private static ?PDO $db = null;
    private static string $migrationsPath;
    private static string $seedsPath;

    public static function init(): void
    {
        self::$db = Database::getInstance();
        self::$migrationsPath = App::databasePath('migrations');
        self::$seedsPath = App::databasePath('seeds');
        
        self::createMigrationsTable();
    }

    private static function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        self::$db->exec($sql);
    }

    public static function status(): array
    {
        self::init();
        
        $executed = self::getExecutedMigrations();
        $available = self::getAvailableMigrations();
        
        $result = [];
        foreach ($available as $migration) {
            $result[] = [
                'name' => $migration,
                'number' => self::getMigrationNumber($migration),
                'executed' => in_array($migration, $executed),
                'batch' => self::getMigrationBatch($migration),
                'executed_at' => self::getMigrationExecutedAt($migration)
            ];
        }
        
        return $result;
    }

    public static function runAll(): array
    {
        self::init();
        
        $available = self::getAvailableMigrations();
        $executed = self::getExecutedMigrations();
        $pending = array_diff($available, $executed);
        
        if (empty($pending)) {
            return ['executed' => [], 'message' => 'Nenhuma migration pendente'];
        }
        
        $batch = self::getNextBatch();
        $executedNow = [];
        $errors = [];
        
        foreach ($pending as $migration) {
            try {
                self::runMigration($migration, $batch);
                $executedNow[] = $migration;
            } catch (\Exception $e) {
                $errors[] = [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ];
                break;
            }
        }
        
        return [
            'executed' => $executedNow,
            'errors' => $errors,
            'batch' => $batch,
            'message' => count($executedNow) . ' migration(s) executada(s)'
        ];
    }

    public static function runSpecific(string $number): array
    {
        self::init();
        
        $migration = self::findMigrationByNumber($number);
        
        if (!$migration) {
            throw new \Exception("Migration não encontrada: {$number}");
        }
        
        $executed = self::getExecutedMigrations();
        
        if (in_array($migration, $executed)) {
            return ['message' => 'Migration já executada', 'executed' => false];
        }
        
        $batch = self::getNextBatch();
        self::runMigration($migration, $batch);
        
        return [
            'migration' => $migration,
            'batch' => $batch,
            'message' => 'Migration executada com sucesso',
            'executed' => true
        ];
    }

    public static function rollback(int $steps = 1): array
    {
        self::init();
        
        $migrations = self::getMigrationsToRollback($steps);
        
        if (empty($migrations)) {
            return ['rolledBack' => [], 'message' => 'Nenhuma migration para reverter'];
        }
        
        $rolledBack = [];
        $errors = [];
        
        foreach ($migrations as $migration) {
            try {
                self::rollbackMigration($migration);
                $rolledBack[] = $migration['migration'];
            } catch (\Exception $e) {
                $errors[] = [
                    'migration' => $migration['migration'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'rolledBack' => $rolledBack,
            'errors' => $errors,
            'message' => count($rolledBack) . ' migration(s) revertida(s)'
        ];
    }

    public static function rollbackAll(): array
    {
        self::init();
        
        $executed = self::getExecutedMigrations();
        $migrations = array_reverse($executed);
        
        $rolledBack = [];
        $errors = [];
        
        foreach ($migrations as $migration) {
            try {
                $migrationData = self::getMigrationData($migration);
                if ($migrationData) {
                    self::rollbackMigration($migrationData);
                    $rolledBack[] = $migration;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'migration' => $migration,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'rolledBack' => $rolledBack,
            'errors' => $errors,
            'message' => count($rolledBack) . ' migration(s) revertida(s)'
        ];
    }

    public static function runSeeds(): array
    {
        self::init();
        
        $seeds = self::getAvailableSeeds();
        $executed = [];
        $errors = [];
        
        foreach ($seeds as $seed) {
            try {
                $result = self::runSeed($seed);
                $executed[] = [
                    'seed' => $seed,
                    'result' => $result
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'seed' => $seed,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return [
            'executed' => $executed,
            'errors' => $errors,
            'message' => count($executed) . ' seed(s) executado(s)'
        ];
    }

    public static function resetAndSeed(): array
    {
        $rollback = self::rollbackAll();
        $migrations = self::runAll();
        $seeds = self::runSeeds();
        
        return [
            'rollback' => $rollback,
            'migrations' => $migrations,
            'seeds' => $seeds
        ];
    }

    private static function getExecutedMigrations(): array
    {
        if (self::$db === null) {
            return [];
        }
        
        try {
            $stmt = self::$db->query("SELECT migration FROM migrations ORDER BY id");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function getAvailableMigrations(): array
    {
        if (!is_dir(self::$migrationsPath)) {
            return [];
        }
        
        $files = glob(self::$migrationsPath . '/*.php');
        sort($files);
        
        return array_map('basename', $files);
    }

    private static function getAvailableSeeds(): array
    {
        if (!is_dir(self::$seedsPath)) {
            return [];
        }
        
        $files = glob(self::$seedsPath . '/*.php');
        sort($files);
        
        return array_map('basename', $files);
    }

    private static function runMigration(string $migration, int $batch): void
    {
        $file = self::$migrationsPath . '/' . $migration;
        
        if (!file_exists($file)) {
            throw new \Exception("Arquivo não encontrado: {$migration}");
        }
        
        $migrationData = require $file;
        
        if (!is_array($migrationData) || !isset($migrationData['up'])) {
            throw new \Exception("Migration inválida: {$migration}");
        }
        
        try {
            self::$db->beginTransaction();
            
            if (is_callable($migrationData['up'])) {
                $migrationData['up'](self::$db);
            } else {
                self::$db->exec($migrationData['up']);
            }
            
            $stmt = self::$db->prepare("INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)");
            $stmt->execute([':migration' => $migration, ':batch' => $batch]);
            
            self::$db->commit();
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    private static function rollbackMigration(array $migration): void
    {
        $file = self::$migrationsPath . '/' . $migration['migration'];
        
        if (!file_exists($file)) {
            throw new \Exception("Arquivo não encontrado: {$migration['migration']}");
        }
        
        $migrationData = require $file;
        
        if (!isset($migrationData['down'])) {
            throw new \Exception("Rollback não definido para: {$migration['migration']}");
        }
        
        try {
            self::$db->beginTransaction();
            
            if (is_callable($migrationData['down'])) {
                $migrationData['down'](self::$db);
            } else {
                self::$db->exec($migrationData['down']);
            }
            
            $stmt = self::$db->prepare("DELETE FROM migrations WHERE migration = :migration");
            $stmt->execute([':migration' => $migration['migration']]);
            
            self::$db->commit();
        } catch (\Exception $e) {
            self::$db->rollBack();
            throw $e;
        }
    }

    private static function runSeed(string $seed): array
    {
        $file = self::$seedsPath . '/' . $seed;
        
        if (!file_exists($file)) {
            throw new \Exception("Arquivo não encontrado: {$seed}");
        }
        
        $seedData = require $file;
        
        if (!is_array($seedData) || !isset($seedData['run'])) {
            throw new \Exception("Seed inválida: {$seed}");
        }
        
        if (is_callable($seedData['run'])) {
            return $seedData['run'](self::$db);
        }
        
        return ['message' => 'Seed executada'];
    }

    private static function getNextBatch(): int
    {
        $stmt = self::$db->query("SELECT MAX(batch) as max_batch FROM migrations");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_batch'] ?? 0) + 1;
    }

    private static function getMigrationsToRollback(int $steps): array
    {
        $stmt = self::$db->prepare("SELECT * FROM migrations WHERE batch = (SELECT MAX(batch) FROM migrations) ORDER BY id DESC LIMIT :limit");
        $stmt->bindValue(':limit', $steps, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getMigrationData(string $migration): ?array
    {
        $stmt = self::$db->prepare("SELECT * FROM migrations WHERE migration = :migration");
        $stmt->execute([':migration' => $migration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    private static function findMigrationByNumber(string $number): ?string
    {
        $available = self::getAvailableMigrations();
        
        foreach ($available as $migration) {
            if (strpos($migration, $number . '_') === 0) {
                return $migration;
            }
        }
        
        return null;
    }

    private static function getMigrationNumber(string $migration): string
    {
        preg_match('/^(\d+)_/', $migration, $matches);
        return $matches[1] ?? '0';
    }

    private static function getMigrationBatch(string $migration): ?int
    {
        $data = self::getMigrationData($migration);
        return $data ? (int) $data['batch'] : null;
    }

    private static function getMigrationExecutedAt(string $migration): ?string
    {
        $data = self::getMigrationData($migration);
        return $data ? $data['executed_at'] : null;
    }

    public static function getCurrentVersion(): string
    {
        $executed = self::getExecutedMigrations();
        
        if (empty($executed)) {
            return '0.0.0';
        }
        
        $lastMigration = end($executed);
        $number = self::getMigrationNumber($lastMigration);
        
        return '0.' . $number . '.0';
    }

    public static function hasPending(): bool
    {
        try {
            if (self::$db === null) {
                self::init();
            }
            
            $executed = self::getExecutedMigrations();
            $available = self::getAvailableMigrations();
            $pending = array_diff($available, $executed);
            
            return !empty($pending);
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getCount(): array
    {
        $executed = count(self::getExecutedMigrations());
        $available = count(self::getAvailableMigrations());
        
        return [
            'executed' => $executed,
            'available' => $available,
            'pending' => $available - $executed
        ];
    }
}
