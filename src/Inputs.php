<?php
namespace Restful;

class Inputs
{
    private static bool $booted = false;
    private static array $extendedKeys = [];

    public static function init(): void
    {
        if (self::$booted) return;
        self::$booted = true;
        self::bootstrap();
    }

    public static function setPath(string $mask): void
    {
        $uriPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($uriPath, '/'))));
        $patternSegments = array_values(array_filter(explode('/', trim($mask, '/'))));

        if (count($patternSegments) !== count($segments)) return;

        $params = [];
        foreach ($patternSegments as $i => $pattern) {
            if (preg_match('/^{(.+)}$/', $pattern, $m)) {
                $params[$m[1]] = $segments[$i];
            } elseif ($pattern !== $segments[$i]) {
                return;
            }
        }

        $GLOBALS['_PATH'] = $params;
    }

    public static function extend(string ...$keys): void
    {
        foreach ($keys as $key) {
            if (!in_array($key, self::$extendedKeys, true)) {
                self::$extendedKeys[] = $key;
            }
        }
    }

    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function is(string $type): bool
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if (strtoupper($type) === 'JSON') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            return stripos($contentType, 'application/json') !== false;
        }

        return strtoupper($type) === $method;
    }


    private static function bootstrap(): void
    {
        // Headers
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!$headers) {
            foreach ($_SERVER as $key => $value) {
                if (str_starts_with($key, 'HTTP_')) {
                    $name = str_replace('_', '-', substr($key, 5));
                    $headers[$name] = $value;
                }
            }
        }
        $GLOBALS['_HEADER'] = $headers;

        // Body
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $input = file_get_contents('php://input');
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        $parsed = null;
        if (stripos($contentType, 'application/json') !== false) {
            $parsed = json_decode($input, true);
        } else {
            parse_str($input, $parsed);
        }

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $GLOBALS['_' . $method] = $parsed ?: [];
        }

        $GLOBALS['_JSON'] = (stripos($contentType, 'application/json') !== false)
            ? (json_decode($input, true) ?? [])
            : [];

        $GLOBALS['_RES'] = [
            'status_code' => 200,
            'headers' => ['Content-Type' => 'application/json'],
            'data' => null
        ];

        register_shutdown_function(function () use ($extendedKeys) {
            $res = $GLOBALS['_RES'] ?? null;
            if (!$res) return;

            http_response_code($res['status_code'] ?? 200);

            foreach ($res['headers'] ?? [] as $k => $v) {
                header("$k: $v");
            }

            $response = [
                'data' => $res['data'] ?? null,
                'error' => $res['error'] ?? null
            ];

            foreach (\Restful\Inputs::$extendedKeys as $key) {
                if (isset($res[$key])) {
                    $response[$key] = $res[$key];
                }
            }

            echo json_encode($response);
        });
    }
}
