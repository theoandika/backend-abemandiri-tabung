<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class Response {
    public static function created()
    {
        return response('', 201);
    }

    public static function updated()
    {
        return response('', 204);
    }

    public static function deleted()
    {
        return response('', 204);
    }

    public static function success(?string $message = null)
    {
        return response()->json([
            'message' => $message ?? __('message.success')
        ], 200);
    }

    public static function successData(mixed $data, ?string $message = null)
    {
        return response()->json([
            'message' => $message ?? __('message.success'),
            'data' => $data
        ], 200);
    }

    public static function error(string $message, $statusCode = 400)
    {
        return response()->json([
            'message' => $message ?? __('message.fail')
        ], $statusCode);
    }

    public static function errorData(mixed $data, string $message, int $statusCode = 400){
        return response()->json([
            'message' => $message ?? __('message.fail'),
            'data' => $data
        ], $statusCode);
    }

    public static function validation(array $errors)
    {
        $messageJoin = implode(', ', array_map(function($value) {
            return implode(', ', $value);
        }, $errors));
        return response()->json([
            'message' => $messageJoin ?? __('message.validation'),
            'errors' => $errors
        ], 422);
    }

    public static function internalError(?string $message = null)
    {
        Log::channel('single')->error("Internal error: ".$message);
        return response()->json([
            'message' => config('app.debug') ? ($message ?? __('message.internal_error')) : __('message.internal_error'),
        ], 500);
    }

    public static function file(mixed $file)
    {
        return response()->file($file);
    }
}
