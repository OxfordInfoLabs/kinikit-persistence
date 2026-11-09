<?php

namespace Kinikit\Persistence\Database\Connection;

final class QueryContext {

    private static ?int $timeoutMs = null;

    public static function setTimeout(?int $milliseconds): void {
        self::$timeoutMs = $milliseconds;
    }

    public static function getTimeout(): ?int {
        return self::$timeoutMs;
    }

}