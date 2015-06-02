<?php
namespace Mathielen\ImportEngineBundle;

class Utils
{

    /**
     * @return array
     */
    public static function parseSourceId($sourceId)
    {
        if (is_file($sourceId)) {
            return $sourceId;
        } elseif (preg_match('/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)+/', $sourceId)) {
            $parsedSourceId = parse_url($sourceId);
            if (isset($parsedSourceId['query'])) {
                parse_str($parsedSourceId['query'], $parsedSourceId['query']);
            }
            $pathTokens = explode('.', $parsedSourceId['path']);
            $method = array_pop($pathTokens);
            $service = join('.', $pathTokens);

            return array(
                'service' => $service,
                'method' => $method,
                'arguments' => isset($parsedSourceId['query'])?$parsedSourceId['query']:null
            );
        }

        return $sourceId;
    }

    /**
     * @return bool
     */
    public static function isWindows()
    {
        return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }

    /**
     * @return bool
     */
    public static function isCli()
    {
        return php_sapi_name() == "cli";
    }

    /**
     * @return string
     */
    public static function whoAmI()
    {
        if (self::isWindows()) {
            $user = getenv("username");
        } else {
            $processUser = posix_getpwuid(posix_geteuid());
            $user = $processUser['name'];
        }

        return $user;
    }

    /**
     * @return array
     */
    public static function numbersToRangeText(array $numbers)
    {
        if (empty($numbers)) {
            return [];
        }

        $ranges = [];
        sort($numbers);

        $currentRange = [];
        foreach ($numbers as $number) {
            if (!empty($currentRange) && current($currentRange) !== $number-1) {
                self::addRangeText($ranges, $currentRange);

                $currentRange = [];
            }

            $currentRange[] = $number;
            end($currentRange);
        }

        self::addRangeText($ranges, $currentRange);

        return $ranges;
    }

    private static function addRangeText(array &$ranges, array $currentRange)
    {
        $lastItem = current($currentRange);

        if (count($currentRange) === 1) {
            $ranges[] = $lastItem;
        } else {
            $firstItem = reset($currentRange);
            $ranges[] = $firstItem . '-' . $lastItem;
        }
    }

}
