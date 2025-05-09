<?php
namespace Restful;

class Inputs
{
    private static $trigger = self::bootstrap();

    public static function setPath(string $mask): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        $cleanPath = str_replace($baseDir, '', $requestUri);
        $cleanPath = strtok($cleanPath, '?');
        $segments = array_values(array_filter(explode('/', trim($cleanPath, '/'))));
        $GLOBALS['_PATH'] = $segments;

        $patternSegments = explode('/', trim($mask, '/'));
        if (count($patternSegments) !== count($segments)) return;

        $params = [];
        foreach ($patternSegments as $i => $seg) {
            if (preg_match('/^{(.+)}$/', $seg, $m)) {
                $params[$m[1]] = $segments[$i];
            } elseif ($seg !== $segments[$i]) {
                return;
            }
        }

        $GLOBALS['_PATH'] = $params;
    }

    private static function bootstrap(): bool
    {
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

        register_shutdown_function(function () {
            if (!isset($GLOBALS['_RES'])) return;

            $res = $GLOBALS['_RES'];
            http_response_code($res['status_code'] ?? 200);
            foreach ($res['headers'] ?? [] as $k => $v) {
                header("$k: $v");
            }
            if ($res['data'] !== null) {
                echo json_encode($res['data']);
            }
        });

        return true;
    }
}
