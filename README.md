# Restful Inputs for PHP

Automatically sets useful REST globals in plain PHP:

- `$_HEADER`: request headers
- `$_PUT`, `$_PATCH`, `$_DELETE`: request body data (if `isset($_PUT)` it means to was sent PUT)
- `$_JSON`: parsed JSON body (if `isset($_JSON)` it means to was sent JSON)
- `$_PATH`: parsed path parameters (EG: a input mask of `Inputs::setPath('api/someItem/{uid}')` will make `$_PATH['uid']` available)
- `$_RES`: response wrapper sent automatically on shutdown - use ['data'], ['status_code'] and ['headers']

## TLDR; Auto Behaviour Summary

| Global     | When Available                          |
| ---------- | --------------------------------------- |
| `$_HEADER` | Always — all request headers            |
| `$_JSON`   | Always — if body is JSON                |
| `$_PUT`    | Only on PUT request                     |
| `$_PATCH`  | Only on PATCH request                   |
| `$_DELETE` | Only on DELETE request                  |
| `$_PATH`   | After `Inputs::setPath()` or fallback   |
| `$_RES`    | Always — sent automatically at shutdown |

## `$_RES` WARNING

If you don't call `exit;` and the script continues running (e.g., later logic or another `$_RES['data']` overwrite), the final values of `$_RES` at the end of execution are what gets sent.

You control exactly when to respond by calling `exit;` after setting `$_RES`. That is the right and recommended usage pattern for this design.

## Install via Composer

```bash
composer require restful-inputs
```

## $\_PATH

`$_PATH` if you've inserted and `id` or perhaps `uid` in the url you can get it but you need to provide the input mask. More complicated set ups are not supported at the moment - terminal params only on `index.php`

```php
use Restful\Inputs;

Inputs::setPath('api/something/{uid}'); //only needed if you actually have a path with params - and it assumes you're in an index.php

$uid = $_PATH['uid'] ?? null;
$name = $_JSON['name'] ?? 'guest';

$_RES['data'] = ['uid' => $uid, 'name' => $name];
$_RES['status_code'] = 200;

```

## $\_HEADER

Access all request headers:

```php
$auth = $_HEADER['Authorization'] ?? null;
$userAgent = $_HEADER['User-Agent'] ?? null;

if (!$auth) {
    $_RES['status_code'] = 401;
    $_RES['data'] = ['error' => 'Missing auth token'];
    exit;
}
```

## $\_PUT, $\_PATCH, $\_DELETE

These are automatically available when the request method matches:
Example `PUT` `/api/profile` with body:

```php
{
  "username": "jdoe",
  "email": "jdoe@example.com"
}
```

```php
if (isset($_PUT)) {
    $username = $_PUT['username'] ?? null;
    $email = $_PUT['email'] ?? null;

    $_RES['data'] = ['updated' => compact('username', 'email')];
}
```

it is safe to test/bail on error like this `isset($_PUT)`

```php
if (!isset($_PUT)) {
    $_RES['status_code'] = 401;
    $_RES['data'] = ['error' => 'not _PUT'];
    exit;
}
```

## $\_JSON

Always available if request has `Content-Type: application/json.`

```php
$name = $_JSON['name'] ?? 'guest';

if (!$name) {
    $_RES['status_code'] = 400;
    $_RES['data'] = ['error' => 'Name is required'];
}
```

Even for POST, PATCH, etc., $\_JSON will work as long as the input is JSON.

it is safe to test `isset($_JSON)`

## $\_RES (Auto-Sent Response)

`$_RES` is automatically sent when the script finishes — no need to manually `echo` or `http_response_code()`.

```php
$_RES['status_code'] = 200;
$_RES['headers']['X-Custom-Header'] = 'Foobar';
$_RES['data'] = ['success' => true];
exit;
```

```php
$_RES['status_code'] = 404;
$_RES['data'] = ['error' => 'Not found'];
exit;
```

```php
$_RES['status_code'] = 204;
$_RES['data'] = null; // no body
exit;
```

## License

MIT
