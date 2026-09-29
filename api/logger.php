<?php
/**
 * Lightweight centralized logger for the EyeSense PHP project.
 *
 * Configuration is read from environment variables:
 * EYESENSE_LOG_LEVEL=DEBUG|INFO|WARNING|ERROR|CRITICAL (default INFO)
 * EYESENSE_LOG_DEDUP_WINDOW=seconds (default 60)
 * EYESENSE_LOG_DEDUP_SUMMARY_THRESHOLD=count (default 10)
 * EYESENSE_LOG_MAX_SIZE=bytes (default 5242880)
 * EYESENSE_LOG_MAX_BACKUPS=count (default 5)
 * EYESENSE_LOG_DIR=absolute or project-relative directory
 * EYESENSE_LOG_TIMEZONE=timezone (default Asia/Kolkata)
 */
class EyeSenseLogger
{
    private const LEVELS = [
        'DEBUG' => 10,
        'INFO' => 20,
        'WARNING' => 30,
        'ERROR' => 40,
        'CRITICAL' => 50,
    ];

    private static ?self $instance = null;
    private string $logDir;
    private string $logFile;
    private string $stateFile;
    private string $lockFile;
    private string $stateLockFile;
    private int $minimumLevel;
    private int $dedupWindow;
    private int $summaryThreshold;
    private int $maxSize;
    private int $maxBackups;

    private function __construct()
    {
        $timezone = $this->env('EYESENSE_LOG_TIMEZONE', 'Asia/Kolkata');
        if (in_array($timezone, timezone_identifiers_list(), true)) {
            date_default_timezone_set($timezone);
        }

        $configuredDir = trim($this->env('EYESENSE_LOG_DIR', ''));
        $this->logDir = $configuredDir === ''
            ? __DIR__ . '/logs'
            : (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $configuredDir)
                ? $configuredDir
                : __DIR__ . '/' . ltrim($configuredDir, '/\\'));
        $this->logDir = rtrim(str_replace('\\', '/', $this->logDir), '/');
        $this->logFile = $this->logDir . '/eyesense.log';
        $this->stateFile = $this->logDir . '/.dedup_state.json';
        $this->lockFile = $this->logDir . '/.logger.lock';
        $this->stateLockFile = $this->logDir . '/.dedup_state.lock';
        $this->minimumLevel = self::LEVELS[$this->normalizedLevel($this->env('EYESENSE_LOG_LEVEL', 'INFO'))];
        $this->dedupWindow = max(1, $this->positiveInt('EYESENSE_LOG_DEDUP_WINDOW', 60));
        $this->summaryThreshold = max(1, $this->positiveInt('EYESENSE_LOG_DEDUP_SUMMARY_THRESHOLD', 10));
        $this->maxSize = max(1024, $this->positiveInt('EYESENSE_LOG_MAX_SIZE', 5 * 1024 * 1024));
        $this->maxBackups = max(1, $this->positiveInt('EYESENSE_LOG_MAX_BACKUPS', 5));
        $this->ensureLogDirectory();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function env(string $name, string $default): string
    {
        $value = getenv($name);
        return $value === false ? $default : (string)$value;
    }

    private function positiveInt(string $name, int $default): int
    {
        $value = filter_var($this->env($name, (string)$default), FILTER_VALIDATE_INT);
        return $value === false ? $default : (int)$value;
    }

    private function normalizedLevel(string $level): string
    {
        $level = strtoupper(trim($level));
        return isset(self::LEVELS[$level]) ? $level : 'INFO';
    }

    private function ensureLogDirectory(): void
    {
        if (!is_dir($this->logDir) && !@mkdir($this->logDir, 0775, true) && !is_dir($this->logDir)) {
            throw new RuntimeException('Unable to create log directory: ' . $this->logDir);
        }

        if (!file_exists($this->logFile) && @touch($this->logFile) === false) {
            throw new RuntimeException('Unable to create log file: ' . $this->logFile);
        }
    }

    private function rotateIfNeeded(): void
    {
        clearstatcache(true, $this->logFile);
        $size = @filesize($this->logFile);
        if ($size === false || $size < $this->maxSize) return;

        $oldest = $this->logFile . '.' . $this->maxBackups;
        if (file_exists($oldest)) @unlink($oldest);

        for ($index = $this->maxBackups - 1; $index >= 1; $index--) {
            $oldFile = $this->logFile . '.' . $index;
            $newFile = $this->logFile . '.' . ($index + 1);
            if (file_exists($oldFile)) @rename($oldFile, $newFile);
        }

        @rename($this->logFile, $this->logFile . '.1');
        if (!file_exists($this->logFile)) @touch($this->logFile);
    }

    private static function sanitizeValue(mixed $value): string
    {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_scalar($value)) {
            $text = self::redactString((string)$value);
            return strlen($text) > 200 ? substr($text, 0, 197) . '...' : $text;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $keyText = (string)$key;
                $result[$keyText] = self::isSensitiveKey($keyText) ? '[REDACTED]' : self::sanitizeValue($item);
            }
            return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[array]';
        }
        if (is_object($value)) return get_class($value);
        return gettype($value);
    }

    private static function redactString(string $value): string
    {
        return preg_replace(
            '/((?:password|passwd|token|secret|api[_-]?key|authorization|cookie)\s*[=:]\s*)[^\s,|]+/i',
            '$1[REDACTED]',
            $value
        ) ?? '[REDACTED]';
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/password|passwd|token|secret|api[_-]?key|authorization|cookie|private[_-]?key|session/i', $key);
    }

    private static function sanitizeContext(array $context): array
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $keyText = (string)$key;
            $safe[$keyText] = self::isSensitiveKey($keyText) ? '[REDACTED]' : self::sanitizeValue($value);
        }
        ksort($safe);
        return $safe;
    }

    private function sourceKey(string $level, string $message, array $context, ?string $file, ?int $line): string
    {
        return hash('sha256', json_encode([
            'level' => $level,
            'source' => $file === null ? '' : basename($file),
            'line' => $line,
            'exception_type' => $context['exception_type'] ?? '',
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function formatLine(string $level, string $message, array $context, ?string $file, ?int $line): string
    {
        $detail = $file === null ? '' : ' [' . basename($file) . ($line !== null ? ':' . $line : '') . ']';
        $parts = [];
        foreach ($context as $key => $value) $parts[] = $key . '=' . $value;
        return '[' . date('Y-m-d H:i:s') . '] [' . $level . ']' . $detail . ' ' . $message
            . ($parts ? ' | ' . implode(' | ', $parts) : '') . PHP_EOL;
    }

    private function readState(): array
    {
        if (!is_file($this->stateFile)) return [];
        $decoded = json_decode((string)@file_get_contents($this->stateFile), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeState(array $state): void
    {
        $json = json_encode($state, JSON_UNESCAPED_SLASHES);
        if ($json !== false) @file_put_contents($this->stateFile, $json, LOCK_EX);
    }

    private function prepareLines(string $level, string $message, array $context, ?string $file, ?int $line): ?array
    {
        $stateLock = @fopen($this->stateLockFile, 'c+b');
        if ($stateLock === false || !@flock($stateLock, LOCK_EX)) {
            if (is_resource($stateLock)) fclose($stateLock);
            throw new RuntimeException('Unable to lock deduplication state');
        }

        try {
            $key = $this->sourceKey($level, $message, $context, $file, $line);
            $now = time();
            $state = $this->readState();
            $state = array_filter($state, static function ($record) use ($now): bool {
                return is_array($record) && ($now - (int)($record['last'] ?? 0)) < 604800;
            });
            $record = $state[$key] ?? ['last' => 0, 'suppressed' => 0];
            $duplicate = ($now - (int)$record['last']) < $this->dedupWindow;
            $lines = [];

            if ($duplicate) {
                $record['suppressed'] = (int)$record['suppressed'] + 1;
                if ($record['suppressed'] < $this->summaryThreshold) {
                    $record['last'] = $now;
                    $state[$key] = $record;
                    $this->writeState($state);
                    return null;
                }
                $lines[] = $this->formatLine($level, 'Previous event repeated ' . $record['suppressed'] . ' times', [
                    'count' => $record['suppressed'], 'window_seconds' => $this->dedupWindow,
                ], $file, $line);
                $record['suppressed'] = 0;
            } elseif ((int)$record['suppressed'] > 0) {
                $lines[] = $this->formatLine($level, 'Previous event repeated ' . $record['suppressed'] . ' times', [
                    'window_seconds' => $this->dedupWindow,
                ], $file, $line);
                $record['suppressed'] = 0;
            }

            $lines[] = $this->formatLine($level, $message, $context, $file, $line);
            $record['last'] = $now;
            $state[$key] = $record;
            $this->writeState($state);
            return $lines;
        } finally {
            @flock($stateLock, LOCK_UN);
            fclose($stateLock);
        }
    }

    private function appendLines(array $lines): void
    {
        $handle = @fopen($this->lockFile, 'c+b');
        if ($handle === false || !@flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('Unable to lock logger coordination file');
        }

        try {
            $this->rotateIfNeeded();
            foreach ($lines as $entry) {
                if (@file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX) === false) {
                    throw new RuntimeException('Unable to append to log file: ' . $this->logFile);
                }
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public static function log(string $level, string $message, array $context = [], ?string $file = null, ?int $line = null): void
    {
        try {
            $instance = self::getInstance();
            $level = $instance->normalizedLevel($level);
            if (self::LEVELS[$level] < $instance->minimumLevel) return;

            $context = self::sanitizeContext($context);
            $lines = $instance->prepareLines($level, $message, $context, $file, $line);
            if ($lines === null) return;
            $instance->appendLines($lines);
        } catch (Throwable $exception) {
            @error_log('[' . date('Y-m-d H:i:s') . '] [CRITICAL] [logger.php] Logging failed: ' . $exception->getMessage() . PHP_EOL);
        }
    }

    public static function debug(string $message, array $context = [], ?string $file = null, ?int $line = null): void { self::log('DEBUG', $message, $context, $file, $line); }
    public static function info(string $message, array $context = [], ?string $file = null, ?int $line = null): void { self::log('INFO', $message, $context, $file, $line); }
    public static function warning(string $message, array $context = [], ?string $file = null, ?int $line = null): void { self::log('WARNING', $message, $context, $file, $line); }
    public static function error(string $message, array $context = [], ?string $file = null, ?int $line = null): void { self::log('ERROR', $message, $context, $file, $line); }
    public static function critical(string $message, array $context = [], ?string $file = null, ?int $line = null): void { self::log('CRITICAL', $message, $context, $file, $line); }

    public static function exception(string $message, Throwable $exception, array $context = []): void
    {
        $context['exception_type'] = get_class($exception);
        $context['exception'] = $exception->getMessage();
        self::error($message, $context, $exception->getFile(), $exception->getLine());
    }

    public static function registerPhpErrorHandler(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) return false;
            if (in_array($severity, [E_WARNING, E_USER_WARNING, E_RECOVERABLE_ERROR], true)) {
                $level = 'WARNING';
            } elseif ($severity === E_USER_ERROR) {
                $level = 'ERROR';
            } else {
                $level = 'DEBUG';
            }
            self::log($level, $message, ['php_error_type' => $severity], $file, $line);
            return false;
        });

        register_shutdown_function(static function (): void {
            $last = error_get_last();
            if ($last !== null && in_array($last['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::critical($last['message'], ['php_error_type' => $last['type']], $last['file'], $last['line']);
            }
        });
    }
}

function logger_debug(string $message, array $context = [], ?string $file = null, ?int $line = null): void { EyeSenseLogger::debug($message, $context, $file, $line); }
function logger_info(string $message, array $context = [], ?string $file = null, ?int $line = null): void { EyeSenseLogger::info($message, $context, $file, $line); }
function logger_warning(string $message, array $context = [], ?string $file = null, ?int $line = null): void { EyeSenseLogger::warning($message, $context, $file, $line); }
function logger_error(string $message, array $context = [], ?string $file = null, ?int $line = null): void { EyeSenseLogger::error($message, $context, $file, $line); }
function logger_critical(string $message, array $context = [], ?string $file = null, ?int $line = null): void { EyeSenseLogger::critical($message, $context, $file, $line); }
function logger_exception(string $message, Throwable $exception, array $context = []): void { EyeSenseLogger::exception($message, $exception, $context); }

EyeSenseLogger::registerPhpErrorHandler();
