<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

if (! function_exists('pf_json_response_ok')) {
    function pf_json_response_ok(mixed $data): JsonResponse
    {
        return response()
            ->json([
                'status' => 'success',
                'error_message' => '',
                'error_code' => null,
                'data' => $data,
            ], options: pf_json_encode_options())
            ->setStatusCode(200);
    }
}

if (! function_exists('pf_json_response_error')) {
    function pf_json_response_error(string $message, int $code = 400): JsonResponse
    {
        return response()
            ->json([
                'status' => 'failed',
                'error_message' => $message,
                'error_code' => $code,
                'data' => null,
            ], options: pf_json_encode_options())
            ->setStatusCode($code);
    }
}

if (!function_exists('pf_response_openai_error')) {
    function pf_response_openai_error(string $message, bool $is_stream, string $type = 'invalid_request_error'): StreamedResponse|JsonResponse
    {
        $error = [
            'error' => [
                'message' => $message,
                'type' => $type,
                'param' => null,
                'code' => null,
            ],
        ];

        if ($is_stream) {
            return response()->stream(function () use ($error) {
                while (@ob_end_flush()){}
                ob_start();

                echo "data: " . json_encode_320($error);
                ob_flush();
                flush();
            }, 200, ['X-Accel-Buffering' => 'no', 'Content-Type' => 'text/event-stream']);
        }

        return response()->json($error, options: pf_json_encode_options())->setStatusCode(400);
    }
}

if (! function_exists('pf_json_encode_options')) {
    function pf_json_encode_options(): int
    {
        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

        if (env('APP_ENV') === 'local') {
            $options |= JSON_PRETTY_PRINT;
        }

        return $options;
    }
}
