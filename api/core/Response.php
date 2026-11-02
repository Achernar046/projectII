<?php
/**
 * api/core/Response.php
 * Standard JSON Response Formatter for RESTful API
 */

class Response
{
    /**
     * Send generic JSON response
     */
    public static function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Send successful response
     */
    public static function success(string $message = 'Success', $data = null, array $meta = [], int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data'    => $data
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send created response (201)
     */
    public static function created(string $message = 'Resource created successfully', $data = null): void
    {
        self::success($message, $data, [], 201);
    }

    /**
     * Send error response
     */
    public static function error(string $message, int $statusCode = 400, $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send 404 Not Found response
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, 404);
    }

    /**
     * Send 401 Unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized access or token expired'): void
    {
        self::error($message, 401);
    }

    /**
     * Send 403 Forbidden response
     */
    public static function forbidden(string $message = 'Access forbidden'): void
    {
        self::error($message, 403);
    }
}
