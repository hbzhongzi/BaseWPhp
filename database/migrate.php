<?php
declare(strict_types=1);

use think\App;

require dirname(__DIR__) . '/vendor/autoload.php';

// 初始化 ThinkPHP，使迁移器可以复用 .env 和 config/database.php 的数据库配置。
$app = new App(dirname(__DIR__) . DIRECTORY_SEPARATOR);
$app->initialize();

// 支持三个命令：
//   status 查看迁移状态；up 执行全部未执行迁移；down 回滚最近一次迁移。
$command = strtolower($argv[1] ?? 'status');
$root = __DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR;
// 迁移记录表也遵循 DB_PREFIX，避免与业务表命名冲突。
$migrationTable = ((string) env('DB_PREFIX', '')) . 'sys_migration';
$db = config('database.connections.' . config('database.default', 'mysql'));

if (!is_array($db) || ($db['type'] ?? 'mysql') !== 'mysql') {
    fwrite(STDERR, "当前迁移器仅支持 MySQL。\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $db['hostname'] ?? '127.0.0.1',
    $db['hostport'] ?? '3306',
    $db['database'] ?? '',
    $db['charset'] ?? 'utf8mb4'
);

try {
    // 迁移器只支持 MySQL；PDO 关闭模拟预处理，保留真实数据库错误。
    $pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // 首次运行时创建迁移版本表。该表不属于业务数据，只记录版本和批次。
    $pdo->exec("CREATE TABLE IF NOT EXISTS `{$migrationTable}` (\n"
        . "  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
        . "  `migration` VARCHAR(191) NOT NULL,\n"
        . "  `batch` INT UNSIGNED NOT NULL,\n"
        . "  `applied_at` DATETIME NOT NULL,\n"
        . "  PRIMARY KEY (`id`),\n"
        . "  UNIQUE KEY `uk_migration` (`migration`)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Throwable $e) {
    fwrite(STDERR, "数据库连接失败：{$e->getMessage()}\n");
    exit(1);
}

$migrations = [];
// 每个迁移由同名的 .up.sql 和 .down.sql 组成，例如：
// 202610300001_create_public_tables.up.sql
// 202610300001_create_public_tables.down.sql
foreach (glob($root . '*.up.sql') ?: [] as $file) {
    $name = basename($file, '.up.sql');
    $migrations[$name] = [
        'up'   => $file,
        'down' => $root . $name . '.down.sql',
    ];
}
ksort($migrations);
$appliedRows = $pdo->query("SELECT `migration`, `batch`, `applied_at` FROM `{$migrationTable}` ORDER BY `id`")->fetchAll();
$applied = array_fill_keys(array_column($appliedRows, 'migration'), true);

if ($command === 'status') {
    // status 只读数据库，不执行任何 SQL 迁移文件。
    foreach ($migrations as $name => $files) {
        echo (isset($applied[$name]) ? '[x] ' : '[ ] ') . $name . PHP_EOL;
    }
    exit(0);
}

if (!in_array($command, ['up', 'down'], true)) {
    fwrite(STDERR, "用法：php database/migrate.php [status|up|down]\n");
    exit(1);
}

try {
    if ($command === 'up') {
        // 一个 up 命令使用同一个批次号，便于审计本次发布包含哪些迁移。
        $batch = (int) $pdo->query("SELECT COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `{$migrationTable}`")->fetchColumn();
        foreach ($migrations as $name => $files) {
            if (isset($applied[$name])) {
                continue;
            }
            // MySQL DDL (CREATE/ALTER/DROP TABLE) implicitly commits. Do not wrap
            // schema migrations in a PDO transaction or commit() will fail after DDL.
            runSqlFile($pdo, $files['up'], (string) ($db['prefix'] ?? ''));
            // 只有 SQL 文件成功执行后才写入版本记录。
            $stmt = $pdo->prepare("INSERT INTO `{$migrationTable}` (`migration`, `batch`, `applied_at`) VALUES (?, ?, ?)");
            $stmt->execute([$name, $batch, date('Y-m-d H:i:s')]);
            echo "Applied {$name}\n";
        }
    } else {
        // down 只回滚最近一次已记录的迁移，避免跳过版本造成结构不一致。
        $last = $pdo->query("SELECT `migration` FROM `{$migrationTable}` ORDER BY `id` DESC LIMIT 1")->fetchColumn();
        if (!$last) {
            echo "没有可回滚的迁移。\n";
            exit(0);
        }
        if (!isset($migrations[$last]) || !is_file($migrations[$last]['down'])) {
            throw new RuntimeException("缺少 {$last} 的 down migration");
        }
        runSqlFile($pdo, $migrations[$last]['down'], (string) ($db['prefix'] ?? ''));
        $stmt = $pdo->prepare("DELETE FROM `{$migrationTable}` WHERE `migration` = ?");
        $stmt->execute([$last]);
        echo "Rolled back {$last}\n";
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "迁移失败：{$e->getMessage()}\n");
    exit(1);
}

function runSqlFile(PDO $pdo, string $file, string $prefix): void
{
    // SQL 文件使用 {{prefix}} 占位符，运行时替换为 config/database.php 的 DB_PREFIX。
    if (!is_file($file)) {
        throw new RuntimeException("迁移文件不存在：{$file}");
    }

    $sql = str_replace('{{prefix}}', $prefix, file_get_contents($file));
    // 当前迁移文件是简单 DDL，每条语句以分号结尾；复杂存储过程应使用独立执行方案。
    $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($statements as $statement) {
        // 删除独立的 SQL 单行注释，避免“注释 + 第一条语句”被误判为空语句。
        $statement = preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m', '', $statement) ?? $statement;
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
}
