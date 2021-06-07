<?php
/**
 * Created by PhpStorm.
 * User: mac
 * Date: 2020/8/30
 * Time: 14:00
 */
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;
use App\Models\School\SchoolPath;
use App\Models\School\SchoolCarriage;
use App\Models\School\SchoolHolidayPerson;
//use Illuminate\Support\Facades\Log;
class MeansController extends Controller{
    /**
     * 获取线路的信息
     * @param $path_id
     * @param $status
     * @return mixed
     */
    public function getPathInfo($path_id){
        /*** 这里需要查询出要推送给的人的集合，比如司机的人，这样可以避免数据库推送的频繁查询***/
        $map3['self_id']=$path_id;
        $map3['delete_flag']='Y';
        $map3['use_flag']='Y';
        $path_info=SchoolPath::with(['schoolPathway'=>function($query){
                $query->where('delete_flag','=','Y');
                //                $query->where('site_type','=',$status);
                $query->select('self_id','self_id as pathway_id','path_id',
                    'pathway_name','pathway_address',
                    'site_type','longitude','dimensionality',
                    'distance','duration','amend_flag','is_compel_arriver');
                $query->orderBy('sort','asc');
                $query->with(['schoolPathwayPerson'=>function($query){
                    $query->where('delete_flag','=','Y');
                    $query->where('use_flag','=','Y');
                    //                    $query->where('pathway_type','=',$status);
                    $query->select('pathway_id',
                        'path_id',
                        'person_id as student_id',
                        'person_name as student_name',
                        'grade_name',
                        'class_name'
                    );
                }]);
            }])
            ->where($map3)
            ->select('self_id','path_name','default_car_id','default_car_brand','default_driver_id','default_driver_name',
                'default_driver_tel','default_care_id','default_care_name','default_care_tel','group_code','group_name','call_up_flag',
                'call_down_flag','start_flag','go_flag','end_flag','go_time','arrive_flag','mini_flag','site_type','pathway_count','sort')
            ->first();
        return $path_info;
    }

    /**
     * 获取是否请假
     * @param $path_info
     * @param $carPathId
     * @param $dispatchCarStatus
     * @param $datetime
     * @return mixed
     */
    public function carriageInfo($path_info,$carPathId,$dispatchCarStatus,$datass,$mac_address)
    {
        $path_info->carriage_status = $dispatchCarStatus;//发车状态
        $path_info->status = $path_info->site_type;//上午下午类型
        //拿到这个线路上的所有的学生以及他们的状态
        $path_info->carriage_id = $carPathId;  //把运输ID传递给到前台1

        $log=[];
        $paichu = [];
        $students = [];
        /** 定义一个总时间，和总时长**/
        $duration 		= null;
        $distance		=null;
        /***循环处理每个站点的数据信息开始 */
        foreach ($path_info->schoolPathway as $k => $v) {
            $v->arrive_push_flag = 'N';            //到站
            $v->go_push_flag 	= 'N';             //到站
            $v->student_count 	= 0;
            $v->distance=$v->distance == 0?null:$v->distance;//判断是否等于0 如果是0则是null
            $v->duration=$v->duration == 0?null:$v->duration;//判断是否等于0 如果是0则是null

            $student = [];
            //判断学生是否有 如果有则执行
            if ($v->schoolPathwayPerson->count() > 0) {
                $studentsId = [];
                foreach ($v->schoolPathwayPerson as $kk => $vv) {
                    $where['holiday_date'] = $datass['date'];
                    $where['holiday_type'] = $path_info->site_type;
                    $where['person_id'] = $vv->student_id;
                    $info = SchoolHolidayPerson::where($where)->select('self_id','use_flag')->first();
                    if ($info && $info->use_flag == 'Y') {
                        $vv->auditor_text = '请假中';
                        $vv->auditor_color = '#000';
                        $vv->holiday_status = 'Y';//请假状态 用于点名时区分
                    } else {
                        $vv->holiday_status = 'N'; //请假状态 用于点名时区分
                        $vv->auditor_text = '正常';
                        $vv->auditor_color = '#fff';
                        $studentsId[] = $vv->student_id;                //用来做什么？？？？发送模板消息，请假中的要发吗？？？
                        $students[] = $vv->student_id;                //用来做什么？？？？发送模板消息，请假中的要发吗？？？这个就是全部的学生，不包含请假的
                    }
                    $vv->grade_flag = 'Y';
                    $vv->auditor_flag = 'Y';
                    $student[] = $vv;
                }
                $v->students = $studentsId;//每个站不包括请假的总人数的信息
                $v->student = $student;//每个站的总人数的信息 包括请假的
                $v->student_count = $v->schoolPathwayPerson->count();//站点的总人数


                /***  is_compel_arriver   如果是强制到站的，则不管这个站点有没有小孩，则到站状态必须是 N*/
                if($v->is_compel_arriver == 'Y'){
                    //没有小孩也必须强制到站
                    $v->pathway_status = 'N';
                    $v->pathway_text = '';
                    //计算总时长及距离
                    $duration+= $v->duration;
                    $distance+=	$v->distance;
                }else{
                    if($studentsId){
                        $v->pathway_status = 'N';
                        $v->pathway_text = '';
                        //计算总时长及距离
                        $duration+= $v->duration;
                        $distance+=	$v->distance;
                    }else{
                        $v->pathway_status = 'Q';
                        $v->pathway_text = '没有孩子，不需要经过';
                        $paichu[] = $k;
                    }
                }
            } else {
                $v->students = [];
                $v->student = [];
                $v->student_count = 0;//站点的总人数

                /***  is_compel_arriver   如果是强制到站的，则不管这个站点有没有小孩，则到站状态必须是 N*/
                if($v->is_compel_arriver == 'Y'){
                    //没有小孩也必须强制到站
                    $v->pathway_status = 'N';
                    $v->pathway_text = '';
                    //计算总时长及距离
                    $duration+= $v->duration;
                    $distance+=	$v->distance;
                }else{
                    $v->pathway_status = 'Q';
                    $v->pathway_text = '没有孩子，不需要经过';
                    $paichu[] = $k;
                }
            }
            $log[$k]['status']          =$v->pathway_status;
            $log[$k]['longitude']       =$v->longitude;
            $log[$k]['dimensionality']  =$v->dimensionality;
        }
        //  dd($path_info->toArray());
        /***循环处理每个站点的数据信息结束 */
        $path_info->duration = $duration;
        $path_info->distance = $distance;
        $path_info->paichu = $paichu;
        $path_info->students = $students;
        $path_info->oldDis=0;
        $path_info->isEmergent='N';
        $path_info->errorcount=0;
        $path_info->real_distance = null;//初始化为null
        $path_info->real_duration = null;//初始化为null
        $path_info->start_time  =date('Y-m-d H:i:s',time());
        $path_info->timss  		=date('Y-m-d H:i:s',time());
        $path_info->student_count = count($students);//学生的总人数
        $path_info->mac_address=$mac_address; //设备号

        //如果是放学，开始的站点必然是学校，如果是上学，终点必然是学校1 0  -  9   [0,1,2,8,9]
        switch ($path_info->site_type){
            case 'UP':
                $num = 0;
                while (in_array($num, $paichu)) {
                    $num++;
                }
                $path_info->start = $num;
                $path_info->next = $num;
                $path_info->end = $path_info->pathway_count - 1;
                break;
            case 'DOWN':
                $path_info->start = 0;
                $path_info->next = 0;
                $num = $path_info->pathway_count - 1;
                while (in_array($num, $paichu)) {
                    $num--;
                }
                $path_info->end = $num;
                break;
        }
        $path_info->log = $log;
        return $path_info;
    }

    /**
     * 更改发车状态
     * @param $user_id
     * @param $carriage_id
     * @param $carriage_info
     * @param $redisServer
     * @return mixed
     */
    public function saveInfo($carriage_info,$redisServer,$mac_address,$user_info=null){
        //判断是否发车 如果已发车则不执行
        switch ($carriage_info->carriage_status){
            case '1':
                //判断是否有学生 如果没有则不发车
                if($carriage_info->student_count>0){
                    $where['self_id']=$carriage_info->carriage_id;
                    $where['delete_flag']='Y';
                    //查询数据库是否已有 如果有则不执行发车
                    $schooleCarriage=SchoolCarriage::where($where)->value('self_id');
                    if(empty($schooleCarriage)){
                        /****  这里是不是可以考虑不需要记录进去数据库，因为走定时任务会做相关的处理，统一在定时任务中处理就好了**/
                        $now_time=date('Y-m-d  H:i:s',time());
                        $date=date('Y-m-d',time());
                        $carriage['self_id']=$carriage_info->carriage_id;
                        $carriage['path_id']=$carriage_info->self_id;									//原path_id
                        $carriage['path_name']=$carriage_info->path_name;
                        $carriage['driver_id']=$carriage_info->default_driver_id;
                        $carriage['default_driver_tel']=$carriage_info->default_driver_tel;
                        $carriage['driver_name']=$carriage_info->default_driver_name;
                        $carriage['care_id']=$carriage_info->default_care_id;
                        $carriage['default_care_tel']=$carriage_info->default_care_tel;
                        $carriage['care_name']=$carriage_info->default_care_name;
                        $carriage['group_code']=$carriage_info->group_code;
                        $carriage['group_name']=$carriage_info->group_name;
                        $carriage['create_user_id']=$user_info->user_id??null;
                        $carriage['create_user_name']=$user_info->name??null;
                        $carriage['create_time']=$now_time;
                        $carriage['update_time']=$now_time;
                        $carriage['carriage_type']=$carriage_info->site_type;
                        $carriage['create_date']=$date;
                        $carriage['carriage_status']=2;
                        $carriage['group']=$carriage_info->group_code.$carriage_info->site_type.$date;
                        $carriage['mac_address']=$mac_address;
                        $carriage['default_car_id']=$carriage_info->default_car_id;
                        $carriage['default_car_brand']=$carriage_info->default_car_brand;
                        $carriage['count']=$carriage_info->student_count;
                        SchoolCarriage::insert($carriage);

                        //第二步：将新改的发车状态重新进入redis中
                        $carriage_info->carriage_status=2;
                        $carriage_info->mac_address=$mac_address;
                        $carriage_infos=json_encode($carriage_info,JSON_UNESCAPED_UNICODE);
                        $redisServer->setex($carriage_info->carriage_id,$carriage_infos,'carriage',25920);
                        $redisServer->set($mac_address,$carriage_info->carriage_id,'mac_path');
                        $msg['code']=200;
                        $msg['msg']='发车成功';
                        $msg['data']=$carriage_info;
                    }else{
                        $msg['code']=301;
                        $msg['msg']="该班次已发车过";
                    }
                }else{
                    $msg['code']=303;
                    $msg['msg']="该线路没有孩子，不用发车";
                    $msg['data']=$carriage_info;
                }
                break;
            default :
                $msg['code']=301;
                $msg['msg']="该班次已发车过";
                break;
        }
        return $msg;
    }

}
