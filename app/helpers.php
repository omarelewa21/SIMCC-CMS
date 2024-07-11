<?php

if(!function_exists('encompass')) {
    /**
     * Encompass logic inside a try-catch block
     *
     * @param callable $callback
     */
    function encompass(callable $callback) {
        try {
            return $callback();
        } catch (\Exception $e) {
            return response()->json([
                'status'    => 500,
                'message'   => $e->getMessage(),
                'error'     => strval($e)
            ], 500);
        }
    }
}

if(!function_exists('encompassWithDBTransaction')) {
    /**
     * Encompass logic inside a try-catch block and a database transaction
     *
     * @param callable $callback
     */
    function encompassWithDBTransaction(callable $callback, $return = [
        'status'    => 200,
        'message'   => 'Success'
    ]) {
        \Illuminate\Support\Facades\DB::beginTransaction();

        try {
            $callback();
            \Illuminate\Support\Facades\DB::commit();
            return response()->json($return, 200);
        }

        catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            report($e);
        }
    }
}

if(!function_exists('defaultLimit')) {
    /**
     * @return int
     */
    function defaultLimit()
    {
        return 10;
    }
}
