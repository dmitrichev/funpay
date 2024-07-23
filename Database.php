<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private static string $skipToken = '__SKIP__';

    public function __construct(private readonly mysqli $mysqli)
    {
    }

    /**
     * @throws Exception
     */
    public function buildQuery(string $query, array $args = []): string
    {
        $i = 0;
        $query = preg_replace_callback('/\?#|\?a|\?d|\?f|\?/', function ($matches) use ($args, &$i) {
            $match = $matches[0];
            if (!isset($args[$i])) {
                throw new Exception("Missing parameter for placeholder idx: $i placeholder: $match");
            }

            $param = $args[$i++];
            if ($param === $this->skip()) {
                return $param;
            }

            return match ($match) {
                '?d' => is_null($param) ? 'NULL' : intval($param),
                '?f' => is_null($param) ? 'NULL' : floatval($param),
                '?a' => $this->formatArray($param),
                '?#' => $this->formatIdentifiers($param),
                default => $this->formatValue($param),
            };
        }, $query);

        $query = preg_replace("/(\{(.+)?{$this->skip()}(.+)?})/", '', $query);
        $query = str_replace(['{', '}'], '', $query);
        return trim($query);
    }

    public function skip(): string
    {
        return self::$skipToken;
    }

    /**
     * @throws Exception
     */
    private function formatArray(array $arr): string
    {
        if (empty($arr)) {
            throw new Exception('Array cannot be empty');
        }

        if (!array_is_list($arr)) {
            $result = [];
            foreach ($arr as $key => $value) {
                $result[] = "`{$this->mysqli->real_escape_string($key)}` = {$this->formatValue($value)}";
            }
            return implode(', ', $result);
        }

        return implode(', ', array_map([$this, 'formatValue'], $arr));
    }

    private function formatIdentifiers(array|string $identifiers): string
    {
        if (is_array($identifiers)) {
            return implode(', ', array_map(function ($identifier) {
                return "`{$this->mysqli->real_escape_string($identifier)}`";
            }, $identifiers));
        }

        return "`{$this->mysqli->real_escape_string($identifiers)}`";
    }

    /**
     * @throws Exception
     */
    private function formatValue($value): string
    {
        return match (gettype($value)) {
            'NULL' => 'NULL',
            'boolean' => $value ? '1' : '0',
            'string' => "'{$this->mysqli->real_escape_string($value)}'",
            'integer', 'double' => $value,
            default => throw new Exception('Unsupported parameter type'),
        };
    }
}
