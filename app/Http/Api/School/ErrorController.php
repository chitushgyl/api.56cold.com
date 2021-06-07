<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/8/30
 * Time: 14:00
 */
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;

use App\Models\School\SchoolCarriage;
use App\Http\Controllers\PushController as Push;
use App\Http\Api\School\TempDataController as TempData;

use App\Facades\MacAddress;
use Illuminate\Support\Facades\Redis;
class ErrorController extends Controller{

    /**
     * 定时触发异常线路发送模板信息 
     * pathUrl =>  /error
     * @param TempDataController $tempData
     * @param Push $push 
     */
    public function getErrorInfo(TempData $tempData,Push $push){
		
		$carriage_id='car_path_202009100734291917946750UP2020-10-22';
        $longitude     ='121.312337';
        $latitude      ='31.194791';
        $now_time                   ='2020-09-17 11:10:00';
		
		$redisServer = Redis::connection('carriage');
        $jsonInfo=$redisServer->get($carriage_id);
        $carriage_info=json_decode($jsonInfo);
        dd(MacAddress::calculate($carriage_info,$longitude,$latitude,$now_time));
		
		
		
        $datetime       =date_time();
        //获取线路的信息
        $where=[
            ['carriage_status','!=',3],
            ['push_flag','=','N'],
            ['delete_flag','=','Y'],
            ['carriage_type','=',$datetime['status']],
            ['create_date','=',$datetime['date']],
        ];
        $schooleCarriage=SchoolCarriage::where($where)->pluck('text_info');
        if($schooleCarriage->count()>0){
            $jsonCarriage=json_decode(json_encode($schooleCarriage,JSON_UNESCAPED_UNICODE),true);
            $arrayFilter = array_values(array_filter($jsonCarriage,function($value){
                return !empty($value);
            }));
            if(count($arrayFilter)>0){
                foreach($arrayFilter as $k=>$v){
                    $jsonValue=json_decode($v);
					if($jsonValue && $jsonValue->real_longitude && $jsonValue->real_latitude){
                        $tempData->sendCartData($push,$jsonValue,'abnormal');
                    }
                    $where2=[
                        ['carriage_status','!=',3],
                        ['push_flag','=','N'],
                        ['delete_flag','=','Y'],
                        ['self_id','=',$jsonValue->carriage_id],
                    ];
                    $data['update_time']=date('Y-m-d  H:i:s',time());
                    $data['push_flag']='Y';
                    SchoolCarriage::where($where2)->update($data);
                }
                die('异常信息发送成功');
            }
            die('去除异常线路信息为空的');
        }
        die('没有异常线路信息');
    }
}
