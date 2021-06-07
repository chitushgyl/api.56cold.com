<?php
namespace App\Http\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RedisController as RedisServer;
use Illuminate\Http\Request;
class LongLatController extends Controller{
    private $prefixCarReal='real';
    /**
     * 手动发车
     * pathUrl =>  /school/long_lat
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function longLat(Request $request,RedisServer $redisServer){
        $carriage_id		=$request->input('carriage_id');

        /** 虚拟数据 
        $input['latitude'] = $carriage_id='car_path_202009281032011601217216UP2020-10-16';*/

        $longLat=$this->prefixCarReal.':'.$carriage_id;
        $getLongLat = json_decode($redisServer->get($longLat,'lat_long'),true);
		$rate=6500;
		
        return response()->json(['code'=>200,'msg'=>'获取成功','data'=>$getLongLat,'rate'=>$rate])
            ->setEncodingOptions(JSON_UNESCAPED_UNICODE);

    }
}
?>
