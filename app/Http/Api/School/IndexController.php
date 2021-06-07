<?php
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User\UserReg;
use App\Models\School\SchoolHardware;
use App\Models\School\SchoolInfo;
use App\Models\School\SchoolPath;
use App\Models\School\SchoolPathway;
use App\Models\School\SchoolPersonRelation;
use App\Models\School\SchoolCarriage;

use App\Http\Controllers\RedisController as RedisServer;
use App\Http\Api\School\MeansController as MeansServer;
use App\Facades\School;
class IndexController extends Controller{
    private $prefixCar='car_';
    /**
     * 用户鉴别身份的
     * pathUrl =>  /index/index
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request){
        $user_info=$request->get('user_info');
        if(empty($user_info)){
            $user_info['person_type']='no';
            $user_info=(object)$user_info;
        }

        $msg['code']=200;
        $msg['msg']='用户信息抓取成功！';
        $msg['data']=$user_info;
        // dd($msg);
        return $msg;
    }

    /**
     * 照管拉取数据
     * pathUrl =>  /index/care
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function care(Request $request,RedisServer $redisServer,MeansServer $meansServer){
        $user_info      =$request->get('user_info');
        $path_id        =$request->input('path_id');
        $datetime       =date_time();

//        $pv['user_id']=$user_info->user_id;
//        $pv['browse_path']=$request->path();
//        $pv['level']=null;
//        $pv['table_id']=null;
//        $pv['ip']=$request->getClientIp();
//        $pv['place']='MINI';
//        $redisServer ->set_pvuv_info($pv);

        /*****虚拟数据
        //$path_id                    ='path_202009100734291917946750';
        $user_info->person_type     ='driver';
        $user_info->person_id       ='info_202006161543141951584195';
        $datetime['status']='DOWN';****/

        /*** 根据时间段看下应该给用户拿那些数据 如果非发车时间段，不能发车**/
        if($datetime['status'] == 'OUT'){
            $msg['code']=302;
            $msg['msg']='非发车时间段，不能发车';
            return $msg;
        }

        switch ($user_info->person_type){
            case 'care' || 'driver':
                /*** 第一步，判断用户是否可以进入这个页面 ，如果可以的话，根据他人物的属性做条件**/
                $where=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['pathway_count','>',0],
                    ['site_type','=',$datetime['status']],
                ];
                switch ($user_info->person_type){
                    case 'care':
                        $where['default_care_id']=$user_info->person_id;
                        break;
                    case 'driver':
                        $where['default_driver_id']=$user_info->person_id;
                        break;
                }

                /** 第三步，把拉出来的线路根据数据库 去判断他是不是可以发车  这里其实是拉取他的可选项 **/
                $avaf1=null;		//这里是拿取数组中状态等于1的第一条数据
                $avaf2=null;		//这里是拿取数组中状态等于2的第一条数据

                $path_infos=SchoolPath::with(['schoolCarriage' => function($query)use($datetime) {
                    $query->select('self_id','path_id','carriage_status');
                    $query->where('create_date','=',$datetime['date']);
                }])->where($where)->select('self_id','path_name','site_type','sort')->orderBy('sort','asc')->get();
                foreach ($path_infos as $k => $v){
                    if($v->schoolCarriage){
                        switch ($v->schoolCarriage->carriage_status){
                            case  '1':
                                $v->send_cart_status='can_do';
                                $avaf1=$avaf1??$v->self_id;
                                break;
                            case  '2':
                                $v->send_cart_status='doing';
                                $avaf2=$avaf2??$v->self_id;
                                break;
                            case  '3':
                                $v->send_cart_status='did';
                                break;
                        }
                    }else{
                        $v->send_cart_status='can_do';
                        $avaf1=$avaf1??$v->self_id;
                    }
                }
                /** 拉出线路 并  给与状态  结束 **/

                /***   现在根据情况弄一个线路ID出来，  如果照管自己选择了一个线路，就可以出他选择的下路的数据
                 *
                 * 如果  照管没有选择线路，我应该给他一个默认的线路，
                 * 注意，默认给的线路，必须是未发车或已发车的，已发车的优先权大于未发车的
                 *
                 */
                if(empty($path_id)){
                    $path_id=$avaf2??$avaf1;
                }
                if($path_id){
                    /** 处理缓存，保证缓存中有数据的**/
                    $carPathId=$this->prefixCar.$path_id.$datetime['status'].$datetime['date'];
                    $carriage=$redisServer->get($carPathId,'carriage');
                    if($carriage){
                        //已经有运输信息了，则把运输信息拿出来
                        $carriage_info=json_decode($carriage);
                    }else{
                        $where60=[
                            ['delete_flag','=','Y'],
                            ['self_id','=',$carPathId],
                        ];
                        $textInfo=SchoolCarriage::where($where60)->value('text_info');
                        //判断数据库是否有已有的车
                        if($textInfo){
                            //写入缓存
                            $carriage_info=json_decode($textInfo);
                            $redisServer->setex($carPathId,json_encode($carriage_info,JSON_UNESCAPED_UNICODE),'carriage',25920);
                            $redisServer->set($carriage_info->mac_address,$carriage_info->carriage_id,'mac_path');
                        }else{
                            $dispatchCarStatus=1;
                            $path_info=$meansServer->getPathInfo($path_id);
                            if($path_info && $path_info->count()>0 && $path_info->pathway_count>0){
                                $where50=[
                                    ['delete_flag','=','Y'],
                                    ['car_id','=',$path_info->default_car_id],
                                ];
                                $mac_address=SchoolHardware::where($where50)->value('mac_address');
                                $carriage_info=$meansServer->carriageInfo($path_info,$carPathId,$dispatchCarStatus,$datetime,$mac_address);
                                $redisServer->setex($carPathId,json_encode($carriage_info,JSON_UNESCAPED_UNICODE),'carriage',25920);
                            }else{
                                $msg['code']=302;
                                $msg['msg']='该线路没有站点';
                                return $msg;
                            }
                        }
                    }
                    /** 处理缓存，保证缓存中有数据的  结束**/

                    /**  这里才是真正输出数据的地方！！！！**/
                    if($carriage_info){
                        /** 以下是做分享功能的地方**/
                        $catenate=[$carriage_info->group_code,$carriage_info->group_name];
                        $msg['data_share']=School::defaultShare(...$catenate);
                        /** 以下是做分享功能的地方结束**/
                        $msg['code']=200;
                        $msg['msg']='拉取运输信息成功';
                        $msg['path_info']=$path_infos;
                        $msg['data']=$carriage_info;
                        return $msg;
                    }else{
                        $msg['code']=302;
                        $msg['msg']='暂无运输信息';
                        return $msg;
                    }
                    /**  这里才是真正输出数据的地方 结束！！！！**/
                }else{
                    //没有任何线路，
                    $msg['code']=301;
                    $msg['msg']='照管员暂未分配无线路！';
                    return $msg;
                }
            default:
                $msg['code']=301;
                $msg['msg']='你无权访问这个页面';
                return $msg;
                break;
        }
    }

    /**
     * 家长拉取数据
     * pathUrl =>  /index/patriarch
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function patriarch(Request $request,RedisServer $redisServer){
        $interval['path']=3;
        $interval['walk']=3;
        $msg['interval']=$interval; 		//线路轮循的频率

        $datetime=date_time();
        $user_info=$request->get('user_info');

        // $pv['user_id']=$user_info->user_id;
        // $pv['browse_path']=$request->path();
        // $pv['level']=null;
        //  $pv['table_id']=null;
        // $pv['ip']=$request->getClientIp();
        // $pv['place']='MINI';
        // $redisServer ->set_pvuv_info($pv);

        switch($datetime['status']){
            case 'OUT':
                $msg['code']=300;
                $msg['msg']='此时间段不能发车！';
                return $msg;
                break;
            default:

                $where=[
                    ['a.person_type','=','student'],
                    ['a.delete_flag','=','Y'],
                    ['b.delete_flag','=','Y'],
                    ['c.delete_flag','=','Y'],
                    ['c.pathway_type','=',$datetime['status']],
                    ['b.relation_type','=','direct'],
                    //['b.union_id','=',$user_info->union_id],
                    ['b.total_user_id','=',$user_info->total_user_id],
                ];

                //$carriage=DB::table('school_info as a')
                //    ->join('school_person_relation as b',function($join){
                //        $join->on('a.self_id','=','b.person_id');
                //    }, null,null,'left')
                //    ->join('school_pathway_person as c',function($join){
                //        $join->on('a.self_id','=','c.person_id');
                //    }, null,null,'left')
                //    ->where($where)
                //   ->select('c.path_id','c.pathway_type')->get()->toArray();
                $carriage=DB::table('school_info as a')
                    ->join('school_person_relation as b',function($join){
                        $join->on('a.self_id','=','b.person_id');
                    }, null,null,'left')
                    ->join('school_pathway_person as c',function($join){
                        $join->on('a.self_id','=','c.person_id');
                    }, null,null,'left')
                    ->where($where)
                    ->select('c.path_id','c.pathway_type')->first();

                if($carriage){
                    $carriageId=$this->prefixCar.$carriage->path_id.$carriage->pathway_type.$datetime['date'];
                    $carriage_info=$redisServer->get($carriageId,'carriage');
                    $carriage_info=json_decode($carriage_info);
                    if($carriage_info){;
                        switch($carriage_info->carriage_status){
                            case '1':
                                $msg['code']=203;
                                $msg['msg']='运输未开始！';
                                $msg['data']=$carriage_info;
                                $catenate=[$carriage_info->group_code,$carriage_info->group_name];
                                $msg['data_share']=School::defaultShare(...$catenate);
                                return $msg;
                                break;
                            case '2':
                                //做一个分享的数据出来
                                $msg['code']=200;
                                $msg['msg']='运输中！';
                                $msg['data']=$carriage_info;
                                $catenate=[$carriage_info->group_code,$carriage_info->group_name];
                                $msg['data_share']=School::defaultShare(...$catenate);
                                return $msg;
                                break;

                            case '3':
                                $msg['code']=201;
                                $msg['msg']='运输已经结束！';
                                $msg['data']=$carriage_info;
                                $catenate=[$carriage_info->group_code,$carriage_info->group_name];
                                $msg['data_share']=School::defaultShare(...$catenate);
                                return $msg;
                                break;
                        }
                    }else{
                        $msg['code']=301;
                        $msg['msg']='未查到运输信息';
                        return $msg;
                    }
                }else{
                    $msg['code']=301;
                    $msg['msg']='未查到运输信息';
                    return $msg;
                }
                break;
        }
//        $pv['user_id']=$user_info->user_id;
//        $pv['browse_path']=$request->path();
//        $pv['level']=null;
//        $pv['table_id']=null;
//        $pv['ip']=$request->getClientIp();
//        $pv['place']='MINI';
//        $this->redis ->set_pvuv_info($pv);
    }

    /**
     * 老师拉取数据
     * pathUrl =>  /index/teacher
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function teacher(Request $request,RedisServer $redisServer){
        $user_info=$request->get('user_info');

        //$pv['user_id']=$user_info->user_id;
        //$pv['browse_path']=$request->path();
        //$pv['level']=null;
        // $pv['table_id']=null;
        // $pv['ip']=$request->getClientIp();
        //$pv['place']='MINI';
        //$redisServer ->set_pvuv_info($pv);

        $where=[
            ['a.person_type','=','student'],
            ['a.delete_flag','=','Y'],
            ['b.self_id','=',$user_info->person_id],
        ];
        $now_time=date('Y-m-d', time());
        $student_info=DB::table('school_info as a')
            ->join('school_info as b',function($join){
                $join->on('a.grade_name','=','b.grade_name');
                $join->on('a.class_name','=','b.class_name');
                $join->on('a.group_code','=','b.group_code');
            }, null,null,'left')
            ->join('school_holiday_person as c',function($join)use($now_time){
                $join->on('a.self_id','=','c.person_id')
                    ->where('c.holiday_date','=',$now_time)
                    ->where('c.holiday_type','=','UP');
            }, null,null,'left')
            ->join('school_holiday_person as d',function($join)use($now_time){
                $join->on('a.self_id','=','d.person_id')
                    ->where('d.holiday_date','=',$now_time)
                    ->where('d.holiday_type','=','DOWN');
            }, null,null,'left')
            ->select(
                'a.self_id as student_id',
                'a.actual_name',
                'a.english_name',
                'a.grade_name',
                'a.class_name',
                'c.holiday_date as up_holiday_date',
                'd.holiday_date as down_holiday_date'
            )
            ->where($where)
            ->orderBy('a.create_time','desc')
            ->get()->toArray();

        if($student_info){
            $data1=[];      //待审核的
            $data2=[];      //审核通过的或者不通过的
            $data3=[];      //正常的
            foreach ($student_info as $k => $v){
                if($v->up_holiday_date){
                    $v->up_status=2;
                }else{
                    $v->up_status=4;
                }

                if($v->down_holiday_date){
                    $v->down_status=2;
                }else{
                    $v->down_status=4;
                }

                if($v->up_status == '1' || $v->down_status == '1'){
                    $v->bg_color='#ccc';
                    $data1[]=$v;
                }else{
                    if($v->up_status == '4' && $v->down_status == '4'){
                        $v->bg_color='#fff';
                        $data3[]=$v;
                    }else{
                        $v->bg_color='#eee';
                        $data2[]=$v;
                    }
                }
            }
            $data= array_merge($data1,$data2,$data3);

            $catenate=[$user_info->group_code,$user_info->group_name];
            $msg['small']=School::defaultShare(...$catenate);
            $msg['code']=200;
            $msg['msg']="学生数据拉取成功";
            $msg['data']=$data;
            $msg['now_time']=$now_time;
            return $msg;
        }else{
            $msg['code']=301;
            $msg['msg']='暂无数据！';
            return $msg;
        }
    }


//    /**
//     * 照管员身份绑定（邀请码）
//     * pathUrl =>  /index/invite
//     * @param Request $request
//     * @return mixed
//     */
//    public function invite(Request $request){
//        $user_info=$request->get('user_info');
//        $inviteId=$request->get('inviteId');
//
//        $where['id']=$inviteId;
//        $where['delete_flag']='Y';
//        $schoolInfo=SchoolInfo::where($where)->select('id','self_id','actual_name','person_type','person_tel','user_id')->first();
//        if($schoolInfo){
//            //dd($schoolInfo);
//            if($schoolInfo->user_id){
//                $msg['code']=300;
//                $msg['msg']="该识别码已绑定身份！";
//                return $msg;
//            }else{
//                $now_time=date('Y-m-d H:i:s',time());
//                $seoi['user_id']=$user_info->user_id;
//                $seoi['union_id']=$user_info->union_id;
//                $seoi['update_time']=$now_time;
//                SchoolInfo::where($where)->update($seoi);
//
//                //更新关联关系表中的union_id
//                $where_relation['relation_person_id'] = $schoolInfo->self_id;
//                $relation['update_time'] = $now_time;
//                $relation['union_id']=$user_info->union_id;
//                SchoolPersonRelation::where($where_relation)->update($relation);
//
//                //数据开始进入use_reg表
//                $dataReg['tel']=$schoolInfo->person_tel;
//                $dataReg['true_name']=$schoolInfo->actual_name;
//                $dataReg['person_id']=$schoolInfo->self_id;
//                $dataReg['person_type']=$schoolInfo->person_type;
//                $dataReg['update_time']=$now_time;
//                $where_reg['self_id'] =$user_info->user_id;
//                $id=UserReg::where($where_reg)->update($dataReg);
//                //去缓存中处理功能
////                $update_flag='Y';
////                $path_info_qiao=$this->redis -> set_user_info($user_info->user_id,$update_flag);
//                $msg['status']=200;
//                $msg['msg']="绑定身份成功！";
//                return $msg;
//            }
//        }else{
//            $msg['code']=300;
//            $msg['msg']="没有查到职员信息！";
//            return $msg;
//        }
////        $this->redis = new RedisController;
////        $pv['user_id']=$user_info->user_id;
////        $pv['browse_path']=$request->path();
////        $pv['level']=null;
////        $pv['table_id']=null;
////        $pv['ip']=$request->getClientIp();
////        $pv['place']='MINI';
////        $this->redis ->set_pvuv_info($pv);
//    }
//
//    /**
//     * 小程序抓点修正线路数据
//     * pathUrl =>	/index/capture
//     * @param Request $request
//     * @return mixed
//     */
//    public function capture(Request $request,RedisServer $redisServer){
//        $user_info			=$request->get('user_info');
//
//        $pv['user_id']=$user_info->user_id;
//        $pv['browse_path']=$request->path();
//        $pv['level']=null;
//        $pv['table_id']=null;
//        $pv['ip']=$request->getClientIp();
//        $pv['place']='MINI';
//        $redisServer ->set_pvuv_info($pv);
//
//        $now_time       	=date('Y-m-d H:i:s',time());
//        /**接收数据*/
//        $pathway_id			=$request->input('pathway_id');
//        $longitude			=$request->input('longitude');
//        $dimensionality		=$request->input('dimensionality');
//
//        /**接收数据
//        $pathway_id='pathway_202008241548591448871671';
//        $longitude='121.352789';
//        $dimensionality='31.184789';*/
//
//        $where=[
//            ['self_id','=',$pathway_id],
//            ['delete_flag','=','Y'],
//            ['use_flag','=','Y'],
//        ];
//
//        $path_info=SchoolPathway::with(['schoolPath' => function($query) {
//            $query->select('self_id','site_type');
//        }])->where($where)->select('path_id')->first();
//        //这里说明要从数据库中去拿数据才是争取的111
//        $where_path=[
//            ['path_id','=',$path_info->path_id],
//            ['delete_flag','=','Y'],
//            ['use_flag','=','Y'],
//        ];
//        $json=SchoolPathway::where($where_path)->select('self_id','distance','duration','longitude','dimensionality','sort')->orderBy('sort','asc')->get();
//
//        $weizhi=null;
//        foreach($json as $k=>$v){
//            if($pathway_id == $v->self_id){
//                $weizhi=$k;
//            }
//        }
//
//        //执行业务代码11   0      $json->count()				$weizhi-1    $weizhi+1
//        /*** 以下是计算这个点到上一个点的距离和位置**/
//        if($weizhi>0){
//            $origin=$json[$weizhi-1]->longitude.','.$json[$weizhi-1]->dimensionality;
//            $destination=$longitude.','.$dimensionality;               //这个是当前的位置经纬度
//
//            $queryUrl='https://restapi.amap.com/v3/direction/driving?origin='.$origin.'&destination='.$destination.'&extensions=base&output=json&key=4e481c099d1871be2e8989497ab26e46&strategy=10';
//            $json1 = $this->httpGet($queryUrl);
//            $back1=json_decode($json1,true);
//            if($back1['info'] == 'OK'){
//                $distance=$back1['route']['paths'][0]['distance'];
//                $duration=$back1['route']['paths'][0]['duration'];
//
//                $data['distance']			= $distance;
//                $data['duration']			= $duration;
//                $data['longitude']			= $longitude;
//                $data['dimensionality']		= $dimensionality;
//                $data['update_time']		= $now_time;
//                //写入数据库
//                $id=SchoolPathway::where($where)->update($data);
//            }
//        }else{
//            //如果第一个站点打点，所以只要修改经纬度，距离和时间都是0
//            $data['longitude']			= $longitude;
//            $data['dimensionality']		= $dimensionality;
//            $data['update_time']		= $now_time;
//            //写入数据库
//            $id=SchoolPathway::where($where)->update($data);
//        }
//
//        /*** 以下是计算这个点到下一个点的距离和位置**/
//        if($weizhi+1 < $json->count()){
//            $origin=$longitude.','.$dimensionality;  //这个是当前的位置经纬度
//            $destination=$json[$weizhi+1]->longitude.','.$json[$weizhi+1]->dimensionality;
//
//            $queryUrl='https://restapi.amap.com/v3/direction/driving?origin='.$origin.'&destination='.$destination.'&extensions=base&output=json&key=4e481c099d1871be2e8989497ab26e46&strategy=10';
//            $json2 = $this->httpGet($queryUrl);
//            $back2=json_decode($json2,true);
//            if($back2['info'] == 'OK'){
//                $distance=$back2['route']['paths'][0]['distance'];
//                $duration=$back2['route']['paths'][0]['duration'];
//
//                $data2['distance']			= $distance;
//                $data2['duration']			= $duration;
//                $data2['update_time']		= $now_time;
//                //写入数据库
//                $where2['self_id']=$json[$weizhi+1]->self_id;
//                $id=SchoolPathway::where($where2)->update($data2);
//            }
//        }
//
//        if($id){
//            $msg['code']=200;
//            $msg['msg']="修正成功";
//            return $msg;
//        }else{
//            $msg['code']=301;
//            $msg['msg']="修正失败";
//            return $msg;
//        }
////        $this->redis = new RedisController;
////        $pv['user_id']=$user_info->user_id;
////        $pv['browse_path']=$request->path();
////        $pv['level']=null;
////        $pv['table_id']=null;
////        $pv['ip']=$request->getClientIp();
////        $pv['place']='MINI';
////        $this->redis ->set_pvuv_info($pv);
//    }

    /**
     * GET请求远程链接
     * @param $url
     * @return mixed
     */
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