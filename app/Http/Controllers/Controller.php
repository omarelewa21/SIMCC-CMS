<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Arr;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private function filterCollection ($collection,$filter,$filterVal,$nested=0,$filterBy='id') {
        $collection = is_array($collection) ? collect($collection) : $collection;

        try {
            $filtered = $collection->filter(function ($fvalue, $fkey) use ($filter, $filterVal, $nested, $filterBy) {
                return $nested ? collect($fvalue[$filter])->contains($filterBy, $filterVal) : (collect($fvalue)->get($filter) == $filterVal);
            });


            return $filtered;
        }
        catch(\Exception $e){
            // do task when error11
            return response()->json([
                "status" => 500,
                "message" => "Filter unsuccessful"
            ]);
        }
    }

    public function filterCollectionList ($collection,$filters,$filterBy="id") {

        try {
            foreach ($filters as $filter => $val) {
                $temp = explode(",",$filter);
                $nested = $temp[0];
                $filter = $temp[1];

                if($val) {
                    $val = explode(",",$val);
                    foreach($val as $row) {
                        $collection = $this->filterCollection ($collection,$filter,$row,$nested,$filterBy);
                    }
                }
            }
            return $collection;
        }
        catch(\Exception $e){
            // do task when error
            return response()->json([
                "status" => 500,
                "message" => "Filter unsuccessful"
            ]);
        }
    }

}
