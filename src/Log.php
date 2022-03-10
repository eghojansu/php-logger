<?php

namespace Ekok\Logger;

class Log
{
    const LEVEL_EMERGENCY = 'emergency';
    const LEVEL_ALERT = 'alert';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    /** @var array */
    const LOG_LEVELS = array(
        self::LEVEL_EMERGENCY => 0,
        self::LEVEL_ALERT     => 1,
        self::LEVEL_CRITICAL  => 2,
        self::LEVEL_ERROR     => 3,
        self::LEVEL_WARNING   => 4,
        self::LEVEL_NOTICE    => 5,
        self::LEVEL_INFO      => 6,
        self::LEVEL_DEBUG     => 7,
    );

    /** @var array */
    private $options = array (
        'extension'      => 'txt',
        'dateFormat'     => 'Y-m-d G:i:s.u',
        'filename'       => false,
        'enabled'        => false,
        'flushFrequency' => false,
        'prefix'         => 'log_',
        'logFormat'      => false,
        'appendContext'  => true,
        'permission'     => 0777,
        'directory'      => null,
        'threshold'      => self::LEVEL_DEBUG,
    );

    /** @var int */
    private $lineCount = 0;

    /** @var string */
    private $lastLine;

    /** @var string */
    private $filePath;

    /** @var string */
    private $fileMode;

    /** @var resource */
    private $fileHandle;

    public function __construct(array $options = null)
    {
        $this->setOptions($options ?? array());
    }

    public function __destruct()
    {
        if ($this->fileHandle) {
            fclose($this->fileHandle);
        }
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): static
    {
        array_walk($options, function ($value, $name) {
            $this->options[$name] = $value;
        });

        return $this->setLogFilePath($this->options['directory']);
    }

    public function getLevelThreshold(): string
    {
        return $this->options['threshold'];
    }

    public function setLevelThreshold(string $threshold): static
    {
        $this->options['threshold'] = $threshold;

        return $this;
    }

    public function getLastLine(): string|null
    {
        return $this->lastLine;
    }

    public function getLineCount(): int
    {
        return $this->lineCount;
    }

    public function getLogFilePath(): string
    {
        return $this->filePath;
    }

    public function setLogFilePath(string|null $directory): static
    {
        if ($directory && 0 === strpos($directory, 'php://')) {
            $this->fileMode = 'w+';
            $this->filePath = $directory;
        } else {
            $this->fileMode = 'a';
            $this->filePath = $this->resolveDirectory($directory) . '/' . $this->resolveFileName();
        }

        return $this;
    }

    public function getFileHandleMode(): string
    {
        return $this->fileMode;
    }

    public function setFileHandleMode(string $mode): static
    {
        $this->fileMode = $mode;

        if ($this->fileHandle) {
            fclose($this->fileHandle);

            $this->fileHandle = null;
        }

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->options['enabled'];
    }

    public function isDisabled(): bool
    {
        return !$this->options['enabled'];
    }

    public function disable(): static
    {
        $this->options['enabled'] = false;

        return $this;
    }

    public function enable(): static
    {
        $this->options['enabled'] = true;

        return $this;
    }

    public function log(string $level, string $message, array $context = null): static
    {
        if ($this->options['enabled']) {
            $given = self::LOG_LEVELS[$level] ?? self::LOG_LEVELS[strtolower($level)] ?? 99;
            $threshold = self::LOG_LEVELS[$this->options['threshold']];

            if ($given <= $threshold) {
                $this->write($this->formatMessage($given, $message, $context));
            }
        }

        return $this;
    }

    public function resolveDirectory(string|null $directory): string
    {
        if ($directory) {
            is_dir($directory) || mkdir($directory, $this->options['permission'], true);

            return rtrim($directory, '/\\');
        }

        return sys_get_temp_dir();
    }

    public function resolveFileName(): string
    {
        $extension = '.' . $this->options['extension'];

        if ($this->options['filename']) {
            if (str_ends_with($this->options['filename'], $extension)) {
                return $this->options['filename'];
            }

            return $this->options['filename'] . $extension;
        }

        return $this->options['prefix'] . date('Y-m-d') . $extension;
    }

    public function formatMessage(int $priority, string $message, array $context = null): string
    {
        $eol = "\n";
        $level = array_search($priority, self::LOG_LEVELS);
        $timestamp = $this->timestamp($this->options['dateFormat']);

        if (false === $level) {
            $level = 'unknown';
        }

        if ($this->options['logFormat']) {
            $formatted = strtr($this->options['logFormat'], array(
                '{date}'          => $timestamp,
                '{level}'         => strtoupper($level),
                '{level-padding}' => str_repeat(' ', 9 - strlen($level)),
                '{priority}'      => $priority,
                '{message}'       => $message,
                '{context}'       => json_encode($context),
            ));
        } else {
            $formatted = "[{$timestamp}] [{$level}] {$message}";
        }

        if ($this->options['appendContext'] && $context) {
            $formatted .= $eol . $this->indent($this->contextToString($context));
        }

        return $formatted . $eol;
    }

    public function timestamp(string $format): string
    {
        $originalTime = microtime(true);
        $micro = sprintf("%06d", ($originalTime - floor($originalTime)) * 1000000);
        $date = new \DateTime(date('Y-m-d H:i:s.' . $micro, $originalTime));

        return $date->format($format);
    }

    public function contextToString(array $context): string
    {
        $export = '';

        foreach ($context as $key => $value) {
            $export .= "{$key}: " . preg_replace(array(
                '/=>\s+([a-zA-Z])/im',
                '/array\(\s+\)/im',
                '/^  |\G  /m'
            ), array(
                '=> $1',
                'array()',
                '    '
            ), str_replace('array (', 'array(', var_export($value, true))) . "\n";
        }

        return str_replace(array('\\\\', '\\\''), array('\\', '\''), rtrim($export));
    }

    public function indent(string $string, int $size = 4): string
    {
        $indent = str_repeat(' ', $size);

        return $indent . str_replace("\n", "\n" . $indent, $string);
    }

    public function write(string $message): static
    {
        if (null === $this->fileHandle) {
            $this->fileHandle = fopen($this->filePath, $this->fileMode);
        }

        if (fwrite($this->fileHandle, $message) === false) {
            throw new \RuntimeException('The file could not be written to. Check that appropriate permissions have been set.');
        }

        $this->lastLine = trim($message);
        $this->lineCount++;

        if ($this->options['flushFrequency'] && $this->lineCount % $this->options['flushFrequency'] === 0) {
            fflush($this->fileHandle);
        }

        return $this;
    }
}
