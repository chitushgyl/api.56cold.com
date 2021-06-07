<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/7/29
 * Time: 15:42
 */
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;
use App\Http\Controllers\RedisController as RedisServer;


use Illuminate\Support\Facades\Redis;
use App\Models\School\SchoolCarriage;
use App\Models\School\SchoolCarriageJson;
use Illuminate\Support\Facades\Log;
class TickController extends Controller{
    protected $prefix='car_';

    public function timerTick(RedisServer $redisServer){
        $now_time=date('Y-m-d  H:i:s',time());
        $date=date('Y-m-d',time());
        $carriage = Redis::connection('carriage');
        //获取缓存中所有的运输的key
        $schoolPath=$carriage->keys($this->prefix.'*'.$date);
        //判断缓存中是否有值
        if(count($schoolPath) > 0){
            foreach ($schoolPath as $k=>$v){
                $jsonRedis=$redisServer->get($v,'carriage');
                $carriage_info=json_decode($jsonRedis);
                //缓存中有key 判断是否有对应的值
                if($carriage_info){
//                    Log::info($v);
//                    if($carriage_info->carriage_status != 3){
                        $where222['self_id']=$v;
                        $where222['delete_flag']='Y';
                        $schoolCarriage=SchoolCarriage::where($where222)->first();
                        if($schoolCarriage){
                            //获取缓存的经纬度存入到数据库 
                            $sss = $v. 'ss';
                            $tempsss = json_decode($redisServer->get($sss,'default'), true);

                            $latLong['self_id']                 = generate_id('self_');
                            $latLong['carriage_id']             = $v;
                            $latLong['json']                    = json_encode($tempsss);
                            $latLong['create_time']             = $now_time;
                            $latLong['update_time']             = $now_time;
                            $latLong['type']                    ='car';
                            SchoolCarriageJson::insert($latLong);

                            $strtotime=strtotime($carriage_info->timss)-strtotime($carriage_info->start_time);
                            $textData['carriage_status']=$carriage_info->carriage_status;
                            $textData['duration']=$strtotime;
                            $textData['distance']=$carriage_info->distance;
                            $textData['text_info']=json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
                            $textData['mac_address']=$carriage_info->mac_address;
                            $textData['update_time']=$now_time;
                            SchoolCarriage::where($where222)->update($textData);
                        }else{
							$textData22['self_id']=$carriage_info->carriage_id;
							$textData22['path_id']=$carriage_info->self_id;
							$textData22['path_name']=$carriage_info->path_name;
							$textData22['driver_id']=$carriage_info->default_driver_id;
							$textData22['default_driver_tel']=$carriage_info->default_driver_tel;
							$textData22['driver_name']=$carriage_info->default_driver_name;
							$textData22['care_id']=$carriage_info->default_care_id;
							$textData22['default_care_tel']=$carriage_info->default_care_tel;
							$textData22['care_name']=$carriage_info->default_care_name;
							$textData22['group_code']=$carriage_info->group_code;
							$textData22['group_name']=$carriage_info->group_name;
							$textData22['create_time']=$now_time;
							$textData22['update_time']=$now_time;
							$textData22['carriage_type']='DOWN';
							$textData22['create_date']='2020-10-19';
							$textData22['carriage_status']=$carriage_info->carriage_status;
							$textData22['group']=$carriage_info->group_code.'DOWN2020-10-19';
							$textData22['text_info']=json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
							$textData22['mac_address']=$carriage_info->mac_address;
							$textData22['default_car_id']=$carriage_info->default_car_id;
							$textData22['default_car_brand']=$carriage_info->default_car_brand;
							$strtotime=strtotime($carriage_info->timss)-strtotime($carriage_info->start_time);
                            $textData22['duration']=$strtotime;
                            $textData22['distance']=$carriage_info->distance;
                            $textData22['count']=$carriage_info->student_count;
                            SchoolCarriage::insert($textData22);
						}
//                        $dd2=$redisServer->del($v,'carriage');
//                        $dd8=$redisServer->del($carriage_info->mac_address,'mac_path');
//                    }
                    $redisServer->del($v,'carriage');
                    $redisServer->del($carriage_info->mac_address,'mac_path');
                }
            }


//            foreach ($schoolPath as $k=>$v){
//                $jsonRedis=$redisServer->get($v,'carriage');
//                $carriage_info=json_decode($jsonRedis);
//                if($carriage_info){
//                    Log::info($v);
//                    if($carriage_info->carriage_status != 3){
//                        $where222['self_id']=$v;
//                        $where222['delete_flag']='Y';
//                        $schoolCarriage=SchoolCarriage::where($where222)->value('text_info');
//                        if(!$schoolCarriage){
//                            $textData['text_info']=json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
//                            $textData['mac_address']=$carriage_info->mac_address;
//                            $textData['update_time']=$now_time;
//                            SchoolCarriage::where($where222)->update($textData);
//                            $dd1=$redisServer->del($v,'carriage');
//                            $dd9=$redisServer->del($carriage_info->mac_address,'mac_path');
//                        }
//                        $dd2=$redisServer->del($v,'carriage'); 
//                        $dd8=$redisServer->del($carriage_info->mac_address,'mac_path');
//                    }
//                    $dd3=$redisServer->del($v,'carriage');
//                    $dd7=$redisServer->del($carriage_info->mac_address,'mac_path');
//                }else{
//                    $dd4=$redisServer->del($v,'carriage');
//                    $dd6=$redisServer->del($carriage_info->mac_address,'mac_path');
//                }
//            }
        }
        die('成功');
    }
	
}
