<?php
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;

//use App\Models\School\SchoolPersonPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\RedisController as RedisServer;
use App\Http\Api\School\MeansController as MeansServer;
use App\Facades\School;
use App\Models\School\SchoolCarriage;
use App\Models\School\SchoolCarriageJson;
use App\Models\School\SchoolCarriageInventory;
class TripsController extends Controller{
    /**
     * 手动发车
     * pathUrl => /trips/trips
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed 
     */
    public function trips(Request $request,RedisServer $redisServer,MeansServer $meansServer){
        $user_info=$request->get('user_info');
		
        //$pv['user_id']=$user_info->user_id;
        //$pv['browse_path']=$request->path();
        //$pv['level']=null;
        //$pv['table_id']=null;
        //$pv['ip']=$request->getClientIp();
        //$pv['place']='MINI';
        //$redisServer ->set_pvuv_info($pv);
//        //通过前端拿一个运输的ID过来 
        $carriage_id=$request->get('carriage_id');
        $carriage_info=json_decode($redisServer->get($carriage_id,'carriage'));
        if($carriage_info){
            $msg=$meansServer->saveInfo($carriage_info,$redisServer,$carriage_info->mac_address,$user_info);
        }else{
            $msg['code']=302;
            $msg['msg']="没有线路";
        }
        return $msg;
    }

    /**
     * 分享后，别人打开的 车辆运行信息,第一次进来渲染页面作用的
     * pathUrl =>  /trips/carriage
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function carriage(Request $request,RedisServer $redisServer){
		$carriage_id=$request->input('carriage_id');					//运输ID
		$flag=$request->input('flag');									//状态	
		$pathway_id=$request->input('pathway_id');						//站点ID
		$user_info=$request->get('user_info');

        //$pv['user_id']=$user_info->user_id;
       // $pv['browse_path']=$request->path();
       // $pv['level']=null;
       // $pv['table_id']=null;
       // $pv['ip']=$request->getClientIp();
       // $pv['place']='MINI';
       // $redisServer ->set_pvuv_info($pv);
		
		/**虚拟数据**/
//        $carriage_id='car_path_202008251012112328613516DOWN2020-08-31';
//        $pathway_id='pathway_202008291707288644993436';
//        $flag='N';

		$carriage_info=json_decode($redisServer->get($carriage_id,'carriage'));
		if($carriage_info){
			$date_time=date('Y-m-d H:i:s',time());
			/*//第一步，先查询这个人和这个线路有没有关系，如果有关系，关系是什么
			$where['user_id']=$user_info->token_user_id;
			$where['path_id']=$carriage_info->self_id;
            $relation_type=SchoolPersonPath::where($where)->value('relation_type');
			if($relation_type){
				//如果是间接关系的，可以考虑把他做为直接关系,满足这3个条件则做成直接关系
				if($relation_type == 'indirect' && $flag == 'Y'){
					$person['relation_type']='direct';
					$person['update_time']=$date_time;
                    SchoolPersonPath::where($where)->update($person);
				}
			}else{
                //如果没有数据，则建立关系，根据flag来，照管过来的是Y，其他过来的是N
                $person['self_id']=generate_id('relation_');
                $person['user_id']=$user_info->user_id;
                $person['path_id']=$carriage_info->self_id;
                $person['group_code']=$carriage_info->group_code;
                $person['group_name']=$carriage_info->group_name;
                $person['union_id']=$user_info->union_id;
                $person['create_time']=$person['update_time']=$date_time;
                if($flag == 'Y' ){
                    $person['relation_type']='direct';
                }else{
                    $person['relation_type']='indirect';
                }
                SchoolPersonPath::insert($person);
			}*/

			//处理用户前端页面显示的内容
			$kkk=$carriage_info->carriage_id.'ss';
            $abc=json_decode($redisServer->get($kkk,'default'),true);
			if($abc){
				$ke=count($abc);
				//获取最后一次的经纬度
				$carriage_info->site=$abc[$ke-1];
			}else{
				//当redis中没有存储当前路线的经纬度时，取当前路线的第一个站点的经纬度
				$data['longitude']=$carriage_info->school_pathway[0]->longitude;
                $data['latitude']=$carriage_info->school_pathway[0]->dimensionality;
                $data['distance']=$carriage_info->school_pathway[0]->distance;
                $data['duration']=$carriage_info->school_pathway[0]->duration;
                $data['time']=$date_time;
                $data['status']=2;
				$carriage_info->site=$data;
			}

            //做一个分享的数据出来
			$catenate=[$carriage_info->group_code,$carriage_info->group_name];
			switch($carriage_info->carriage_status){
				case '1':
						$msg['code']=203;
                        $msg['msg']='运输未开始！';
                        $msg['data']=$carriage_info;
						$msg['data_share']=School::defaultShare(...$catenate);
				    break;
				case '2':
						//家长分享端的小图标
						//这个部分估计要从数据库中捞取出来
						//做一个分享的数据出来
						$msg['code']=200;
                        $msg['msg']='运输中！';
						$msg['data']=$carriage_info;
						$msg['data_share']=School::defaultShare(...$catenate);
						if($pathway_id){
							$msg['pathway_id']=$pathway_id;
						}else{
							$msg['pathway_id']=null;
						}
						//频率
						$interval['path']=2;
						$interval['walk']=3;
						$msg['interval']=$interval; //线路轮循的频率
				    break;
				case '3':
						$msg['code']=201;
                        $msg['msg']='运输已经结束！';
                        $msg['data']=$carriage_info;
                        $msg['data_share']=School::defaultShare(...$catenate);
				    break;
			}
		}else{
			$msg['code']=301;
			$msg['msg']='未查到运输信息';
		}
        return $msg;
    }


    /**
     * 手动到站
     * pathUrl => /trips/ride_status
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function ride_status(Request $request,RedisServer $redisServer){
		$user_info=$request->get('user_info');
		
        //$pv['user_id']=$user_info->user_id;
       // $pv['browse_path']=$request->path();
       // $pv['level']=null;
       // $pv['table_id']=null;
       // $pv['ip']=$request->getClientIp();
       // $pv['place']='MINI';
       // $redisServer ->set_pvuv_info($pv);		
				
        $carriage_id=$request->input('carriage_id');//运输ID
        $pathway_id=$request->input('pathway_id');//站点ID
		$date_time=date('Y-m-d H:i:s',time());
        $carriage=$redisServer->get($carriage_id,'carriage');

		/*****虚拟数据
        $carriage_id='car_path_202008241539526469382776DOWN2020-09-01';
        $pathway_id='pathway_202008291707288644993436';****/

        if($carriage){
            $carriage_info=json_decode($carriage);
            switch ($carriage_info->carriage_status){
                case '2':
                    if($pathway_id == $carriage_info->school_pathway[$carriage_info->next]->pathway_id){
                        $carriage_info->school_pathway[$carriage_info->next]->pathway_status='Y';
                        $carriage_info->school_pathway[$carriage_info->next]->arrive_time=$date_time;
						$carriage_info->school_pathway[$carriage_info->next]->real_distance=0;
                        $carriage_info->school_pathway[$carriage_info->next]->real_duration=0;

                        if($carriage_info->next == $carriage_info->end){
							$carriage_info->carriage_status=3;         //全部到站完成，线路结束 
							$carriage_info->timss=$date_time;         //全部到站完成，线路结束 
							
							$strtotime=strtotime($date_time)-strtotime($carriage_info->start_time);
							$dass['carriage_status'] = 3;
							$dass['duration'] = $strtotime;	
							$dass['distance'] = $carriage_info->distance;
							$dass['text_info'] = json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
							$dass['mac_address']=$carriage_info->mac_address;
							$dass['update_time'] = $date_time;
							$where['self_id']=$carriage_info->carriage_id;
							SchoolCarriage::where($where)->update($dass);
							
							/**将redis中的运输数据存储到数据库中 **/
							$redisServerDefault=$redisServer->get($carriage_id,'default');
							$sss = $carriage_id.'ss';
							$longitudeEnd=$carriage_info->school_pathway[$carriage_info->end]->longitude;
							$latitudeEnd=$carriage_info->school_pathway[$carriage_info->end]->dimensionality;
                            $carInfo=['longitude'=>$longitudeEnd,'latitude'=>$latitudeEnd,'altitude'=>0,'direction'=>0,'speed'=>0,'gun'=>0,'gnss'=>0];
							$tempsss = json_decode($redisServer->get($sss,'default'), true);
							if($tempsss){
								array_push($tempsss,$carInfo);
								$da['self_id'] = generate_id('self_');
								$da['carriage_id'] = $carriage_id;
								$da['json'] = json_encode($tempsss);
								$da['create_time'] = $date_time;
								$da['update_time'] = $date_time;
								$da['type'] ='car';
								SchoolCarriageJson::insert($da);
							}

                            if($carriage_info->end_flag == 'Y'){
								$push_flag='end';
                                $this->addQueue($push_flag,$carriage_info,$date_time);
                                $carriage_info->end_flag = 'N';
                            }
							$this->rollCall($carriage_info,$date_time);
                            $msg['code']=200;
                            $msg['msg']="修改成功";
                            $msg['data']=$carriage_info;
                            $redisServer->setex($carriage_id,json_encode($carriage_info,JSON_UNESCAPED_UNICODE),'carriage',2592000);
                            return  $msg;
                        }else{
							if($carriage_info->arrive_flag == 'Y'){
							    //模版消息的推送 
								$push_flag='arrive';
                                $this->addQueue($push_flag,$carriage_info,$date_time);
							}
							$this->rollCall($carriage_info,$date_time);
							
                            do {
                                $carriage_info->next ++ ;
                            } while (in_array($carriage_info->next,$carriage_info->paichu));
							
                            $msg['code']=200;
                            $msg['msg']="修改成功";
                            $msg['data']=$carriage_info;
                            $redisServer->setex($carriage_id,json_encode($carriage_info,JSON_UNESCAPED_UNICODE),'carriage',2592000);
                            return  $msg;
                        }
					}else{
						$msg['code'] = 301;
						$msg['msg'] = "请按顺序到站";
                        return  $msg;
					}
                    break;
                default:
                    $msg['code'] = 300;
                    $msg['msg'] = "非运行时间，无法修改";
                    return  $msg;
                    break;
            }
        }else{
            $msg['code'] = 300;
            $msg['msg'] = "非运行时间，无法修改";
            return  $msg;
        }

    }

    /**
     * 家长端实时获取线路的经纬度
     * pathUrl => /trips/path_loglat
     * @param Request $request 
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function path_loglat(Request $request,RedisServer $redisServer){
        $user_info		=$request->get('user_info');	
		
        //$pv['user_id']=$user_info?$user_info->user_id:null;
        //$pv['browse_path']=$request->path();
        //$pv['level']=null;
       // $pv['table_id']=null;
       // $pv['ip']=$request->getClientIp();
       // $pv['place']='MINI';
       // $redisServer ->set_pvuv_info($pv);		
		
        $carriage_id	=$request->input('carriage_id');
        $longitude		=$request->input('longitude');				//经度
        $latitude		=$request->input('latitude');					//纬度
		$carriage_info=json_decode($redisServer->get($carriage_id,'carriage'));
		if($carriage_info){
			//说明运输结束了1
			if($carriage_info->carriage_status==3){
				//运输结束
				$msg['code']=201;
				$msg['msg']='运输已经结束！';
				$msg['data']=$carriage_info;
			}else if($carriage_info->carriage_status==2){
                //说明是在运行中，则需要存储用户的数据
                $date_time=date('Y-m-d H:i:s',time());
                $site['longitude'] = $longitude;
                $site['latitude'] = $latitude;
                $site['carriage_id'] = $carriage_id;
                $site['time'] = $date_time;
                $site['status'] = $carriage_info->status;//当前运输线路的状态（上学的还是放学的）
                $site['total_user_id'] = $user_info->token_user_id;//当前用户user_id

                $walkId=$user_info->total_user_id.$carriage_id.'walk';
                $abc = json_decode($redisServer->get($walkId,'default'), true);
                if($abc){
                    //说明已经有数据了，所以要追加在后面1
                    $ke = count($abc);
                    if ($ke >= 50) {
                        //如果redis中超过50条，则要把数据储存进入数据库
                        $da['self_id'] = generate_id('self_');
                        $da['carriage_id'] =$carriage_id;
                        $da['json'] = json_encode($abc);
                        $da['create_time'] = $da['update_time'] = $date_time;
                        $da['type'] ='man';
                        $da['total_user_id'] =$user_info->token_user_id;
                        SchoolCarriageJson::insert($da);

                        $aewiu[0] = $site;
                        $redisServer->set($walkId,$aewiu,'default');
                    }else {
                        $abc[$ke] = $site;//数组的长度为key值 赋值
                        $redisServer->set($walkId,$abc,'default');
                    }
                }else{
                    //说明是第一条数据
                    $data[0] = $site;
                    //存实时经纬度进redis
                    $redisServer->set($walkId,$data,'default');
                }

				$msg['code']=200;
				$msg['msg']='运输中！';
				$msg['data']=$carriage_info;
			}else{
				$msg['code']=203;
				$msg['msg']='运输未开始！';
				$msg['data']=$carriage_info;
			}
		}else{
			$msg['code']=301;
			$msg['msg']='未查到运输信息';
			$msg['data']=null;
		}
        return $msg;
    }
	
	public function rollCall($carriage_info,$date_time){
        //判断是否为Y
        if($carriage_info->call_up_flag == 'Y' && $carriage_info->call_down_flag == 'Y'){
            $where['use_flag']='Y';
            $where['delete_flag']='Y';
            $where['carriage_id']=$carriage_info->carriage_id;
            $where['pathway_count_id']=$carriage_info->school_pathway[$carriage_info->next]->pathway_id;
            $exists=SchoolCarriageInventory::where($where)->exists();
            if(!$exists){
                //判断是否是最后一战
                switch ($carriage_info->site_type){
                    case 'UP':
						Log::info(11);
                        //if($carriage_info->next == $carriage_info->end){
                         //   $tempData->sendCartData($push,$carriage_info,'rollCall');
                        //}else{
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                       // }
                        break;
                    case 'DOWN':
					Log::info(22);
                        //if($carriage_info->next == $carriage_info->start){
                        //    $tempData->sendCartData($push,$carriage_info,'rollCall');
                        //}else{
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                        //}
                        break;
                }
            }
        }else if($carriage_info->call_up_flag == 'Y' && $carriage_info->call_down_flag == 'N'){
            //判断是否是最后一战
            $where['use_flag']='Y';
            $where['delete_flag']='Y';
            $where['carriage_id']=$carriage_info->carriage_id;
            $where['pathway_count_id']=$carriage_info->school_pathway[$carriage_info->next]->pathway_id;
            $exists=SchoolCarriageInventory::where($where)->exists();
            if(!$exists){
                switch ($carriage_info->site_type){
                    case 'UP':
                        if($carriage_info->next < $carriage_info->end){
							Log::info(333);
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                        }
                        break;
                    case 'DOWN':
                        if($carriage_info->next == $carriage_info->start){
							Log::info(4444);
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                        }
                        break;
                }
            }
        }else if($carriage_info->call_up_flag == 'N' && $carriage_info->call_down_flag == 'Y'){
            //判断最后一战 
            $where['use_flag']='Y';
            $where['delete_flag']='Y';
            $where['carriage_id']=$carriage_info->carriage_id;
            $where['pathway_count_id']=$carriage_info->school_pathway[$carriage_info->next]->pathway_id;
            $exists=SchoolCarriageInventory::where($where)->exists();
            if(!$exists){
                switch ($carriage_info->site_type){
                    case 'UP':
                        if($carriage_info->next == $carriage_info->end){
							Log::info(555);
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                        }
                        break;
                    case 'DOWN':
                        if($carriage_info->next > $carriage_info->start){
							Log::info(666);
                            //$tempData->sendCartData($push,$carriage_info,'rollCall');
                        }
                        break;
                }
            }
        }
    }

	/**
     *	推送进入数据库
     * @param $push_flag
     * @param $carriage_info
     * @param $now_time
     */
    private function addQueue($push_flag,$carriage_info,$now_time,$gather=null){
        /**写入数据库，定时任务执行推送**/
        $fat['push_type']=$push_flag;
        $fat['carriage_id']=$carriage_info->carriage_id;
        $fat['push_status']='N';
        $fat['push_json']=json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
        if($gather){
            $fat['push_gather']=json_encode($gather,JSON_UNESCAPED_UNICODE);
        }
        $fat['create_time']=$now_time;
        $fat['update_time']=$now_time;
		
		Log::info(99999);
		
        //DB::table('school_working_message')->insert($fat);
    }

	private function httpGet($url) {
	    $curl = curl_init();
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
	    curl_setopt($curl, CURLOPT_URL, $url);
	    $res = curl_exec($curl);
	    curl_close($curl);
    	return $res;
  	}
}
?>
