<?php namespace App\Http\Controllers\Dtgraph;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiController extends Controller {


    public function sensor($sensor = null) {
        //hack:
        if (isset($sensor)) {
            echo 'a';
        } else {
            echo json_encode(['a', 'b', 'c']);
        }
    }


    public function reading(Request $request, $sensor) {
        echo $request->input('a');

    }
}
