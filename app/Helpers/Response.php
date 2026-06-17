<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class Response {
    static function created()
    {
        return response('', 201);
    }

    static function updated()
    {
        return response('', 204);
    }

    static function deleted()
    {
        return response('', 204);
    }

    static function success(string $message = null)
    {
        return response()->json([
            'message' => $message ?? __('message.success')
        ], 200);
    }

    static function successData($data, string $message = null)
    {
        return response()->json([
            'data' => $data
        ], 200);
    }

    static function error($message, $statusCode = 400)
    {
        return response()->json([
            'message' => $message ?? __('message.fail')
        ], $statusCode);
    }

    static function errorData($data, string $message, int $statusCode = 400){
        return response()->json([
            'message' => $message ?? __('message.fail'),
            'data' => $data
        ], $statusCode);
    }

    static function validation(array $errors)
    {
        $messageJoin = implode(', ', array_map(function($value) {
            return implode(', ', $value);
        }, $errors));
        return response()->json([
            'message' => $messageJoin ?? __('message.validation'),
            'errors' => $errors
        ], 422);
    }

    static function internalError($message = null)
    {
        Log::channel('single')->error("Internal error: ".$message);
        return response()->json([
            'message' => config('app.debug') ? ($message ?? __('message.internal_error')) : __('message.internal_error'),
        ], 500);
    }

    static function file($file)
    {
        return response()->file($file);
    }
}
