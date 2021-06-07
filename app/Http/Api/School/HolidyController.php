<?php
namespace App\Http\Api\School;
use Illuminate\Support\Facades\DB;
use App\Models\User\UserReg;
use App\Models\School\SchoolInfo;
use App\Models\School\SchoolHoliday;
use App\Models\School\SchoolHolidayPerson;
use App\Models\School\SchoolPathwayPerson;
//use App\Models\School\SchoolHolidayRead;
use App\Models\WxMessageRead;

use App\Models\School\SchoolPersonRelation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\RedisController as RedisServer;
use App\Http\Controllers\PushController as Push;
use App\Http\Api\School\TempDataController as TempData;

class HolidyController extends Controller{
    /**
     * 请假详情     holidy/holidy_cancel
     * 传递必须参数：user_token，holiday_id
     */
    public function holidy_cancel(Request $request,TempData $tempData,Push $push){
        $user_info          = $request->get('user_info');
        $holiday_person_id  =$request->input('holiday_person_id');
        $now_time=date('Y-m-d H:i:s',time());
        /**虚拟数据11**/
//        $holiday_person_id=json_encode([
//            'h_20200922145928102661698',
//            'h_202009221457080359800337',
//            'h_202009221457080426320127']);

        //前端传过来的数据转换成json数组
        $json_holiday= json_decode($holiday_person_id,true);
        //判断当前的是不是数组并且数组的长度必须大于0
        if(is_array($json_holiday) && count($json_holiday) > 0){
            $holiday_data=[];
            foreach ($json_holiday as $k=>$v){
                $where=[
                    ['self_id','=',$v],
                    ['confirm_cancel_type','=','confirm'],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $holiday_info = SchoolHolidayPerson::where($where)->first();
                if($holiday_info){
					$holiday_data[]=$holiday_info->toArray();
					$holiday_info->use_flag='N';
					$holiday_info->cancel_user_id=$user_info->user_id;
					$holiday_info->cancel_time=$now_time;
					$holiday_info->cancel_user_name=$user_info->token_name;
					$holiday_info->cancel_token_img=$user_info->token_img;
					$holiday_info->save();
                }
            }

            if($holiday_data){
                $where2=[
                    ['person_id','=',$holiday_data[0]['person_id']],
                    ['delete_flag','=','Y']
                ];
                $schoolPathwayPerson=SchoolPathwayPerson::wherehas('schoolLong',function($query){
                        $query->where('delete_flag','=','Y');
                    })
                    ->with(['schoolLong:path_name,self_id'])
                    ->where($where2)
                    ->orderBy('pathway_type','desc')
                    ->select('path_id','pathway_type')
                    ->get();
                $up_path_name=null;
                $down_path_name=null;
                if($schoolPathwayPerson->count()>0){
                    if($schoolPathwayPerson[0]->pathway_type == 'UP'){
                        $up_path_name   ='上学：'.$schoolPathwayPerson[0]->schoolLong->path_name;
                    }
                    if($schoolPathwayPerson[1]['pathway_type'] == 'DOWN'){
                        $down_path_name  ='放学：'.$schoolPathwayPerson[0]->schoolLong->path_name;
                    }
                }

                $holiday_reason='取消请假';
                $holiday					=generate_id('holiday_');
                $data['self_id']            = $holiday;
                $data['person_id']          = $holiday_data[0]['person_id'];
                $data['person_name']        = $holiday_data[0]['person_name'];
                $data['reason']             = $holiday_reason;
                $data['is_go_school']       = 'Y'; //是否去学校
                $data['leave_type']         ='other'; //请假类型
                $data['grade_name']         = $holiday_data[0]['grade_name'];
                $data['class_name']         = $holiday_data[0]['class_name'];
                $data['group_code']         = $holiday_data[0]['group_code'];
                $data['group_name']         = $holiday_data[0]['group_name'];
                $data['create_user_id']     = $user_info->user_id;
                $data['create_user_name']   = $user_info->token_name;//申请人名称
                $data['create_token_img']   = $user_info->token_img;
                $data['create_time']        = $now_time;
                $data['update_time']        = $now_time;
                $data['up_path_name']=$up_path_name;
                $data['down_path_name']=$down_path_name;
                $data['confirm_cancel_type']             = 'cancel';
                SchoolHoliday::insert($data);

                $infos=[];
                foreach($holiday_data as $kk=>$vv){
                    $data_list['self_id']       = generate_id('h_');
                    $data_list['holiday_id']    = $holiday;
                    $data_list['person_id']     = $vv['person_id'];
                    $data_list['person_name']   = $vv['person_name'];
                    $data_list['holiday_date']  = $vv['holiday_date'];
                    $data_list['holiday_type']  = $vv['holiday_type'];
                    $data_list['grade_name']    = $vv['grade_name'];
                    $data_list['class_name']    = $vv['class_name'];
                    $data_list['group_code']    = $vv['group_code'];
                    $data_list['group_name']    = $vv['group_name'];
                    $data_list['create_time']   = $now_time;
                    $data_list['update_time']   = $now_time;
                    $data_list['use_flag']      = 'N';
                    $data_list['cancel_user_id']= $user_info->user_id;
                    $data_list['cancel_time']   = $now_time;
                    $data_list['cancel_user_name']= $user_info->token_name;
                    $data_list['cancel_token_img']= $user_info->token_img;
                    $data_list['confirm_cancel_type']             = 'cancel';
                    $schoolHolidayss= SchoolHolidayPerson::insert($data_list);

                    switch($vv['holiday_type']){
                        case 'DOWN':
                            $infos[]=substr($vv['holiday_date'],-5).'放学';
                            break;
                        case 'UP':
                            $infos[]=substr($vv['holiday_date'],-5).'上学';
                            break;
                    }
                }
                $data['reason']='[其他]'.$holiday_reason;
                $info['data']=$data;
                $info['info']=implode('，',$infos);
                //$tempData->sendCartData($push,$info,'cancel_holiday');
            }
            $msg['code'] = 200;
            $msg['msg'] = "假期取消成功";
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg'] = "请选择取消请假的日期";
            return $msg;
        }
    }

     /**
     * 请假详情
     * pathUrl =>  /holidy/holidy_detail
     * @param Request $request
     * @return mixed
     */
    public function holidy_detail(Request $request){
        $user_info          = $request->get('user_info');
        //拿学生的ID去查需要审核的数据就可以了吧
        $holiday_id         =$request->input('holiday_id');
        $now_time           = date('Y-m-d H:i:s', time());

        //虚拟数据
        //$holiday_id         ='holiday_202010291053451061235605';

        //获取学生是否请假和线路信息
        $where=[
            ['self_id','=',$holiday_id],
            ['delete_flag','=','Y'],
        ];

        //查询请假信息和孩子所称的校车的信息
        $holiday_info = SchoolHoliday::with(['schoolHolidayPerson' => function($query){
            $query->select('self_id','holiday_id','person_id','holiday_date','holiday_type','cancel_user_name','cancel_token_img','cancel_reason','use_flag');
        }])
            ->where($where)
            ->select('self_id','person_id','person_name',
                'reason','grade_name','class_name','group_code','group_name',
                'create_user_name','create_time','send_gather',
                'leave_type','is_go_school','up_path_name','down_path_name'
            )
            ->first();

        //判断学生是否有请假的信息
        if($holiday_info){
            $read					=generate_id('read_');
            $data['self_id']         = $read;
            //$data['holiday_id']         = $holiday_id;
            $data['message_send_id']    = $holiday_id;
            $data['create_user_id']     = $user_info->total_user_id;
            $data['create_user_name']   = $user_info->token_name;
            $data['create_user_img']   = $user_info->token_img;
            $data['create_true_name']   = $user_info->true_name;
            $data['person_type']        = $user_info->person_type;
            $data['create_time']        = $now_time;
            $data['update_time']        =$now_time;
            $data['group_code']         = $holiday_info->group_code;
            $data['group_name']         = $holiday_info->group_name;
            WxMessageRead::insert($data);

            //三元运算是否有值
            $holiday_info->grade_name=$holiday_info->grade_name??'';
            $holiday_info->class_name=$holiday_info->class_name??'';
            $holiday_info->reason=$holiday_info->reason??'';
            $holiday_info->up_path_name=$holiday_info->up_path_name??'';
            $holiday_info->down_path_name=$holiday_info->down_path_name??'';

            //判断请假类型
            switch ($holiday_info->leave_type){
                case 'transfer':
                    $holiday_info->leave_type='家长接送';
                    break;
                case 'disease':
                    $holiday_info->leave_type='病假';
                    break;
                case 'matter':
                    $holiday_info->leave_type='事假';
                    break;
                case 'other':
                    $holiday_info->leave_type='其他';
                    break;
                default:
                    $holiday_info->leave_type='';
                    break;
            }

            //判断是否去学校状态
            switch ($holiday_info->is_go_school){
                case 'Y':
                    $holiday_info->is_go_school='去学校';
                    break;
                case 'N':
                    $holiday_info->is_go_school='不去学校';
                    break;
                default:
                    $holiday_info->is_go_school='';
                    break;
            }

            //查询用户的身份
            $where5=[
                ['total_user_id','=',$user_info->total_user_id],
                ['delete_flag','=','Y'],
            ];
            $holiday_info->relation_type='N';
            $relationType = SchoolPersonRelation::where($where5)->value('relation_type');
            //判断是否为直接身份 默认为间接身份
            if($relationType){
                if($relationType == 'direct'){
                    $holiday_info->relation_type='Y';
                }else{
                    $holiday_info->relation_type='N';
                }
            }else{
                $holiday_info->relation_type='N';
            }

            //循环转换日期和上午，下午的状态
            foreach ($holiday_info->schoolHolidayPerson as $k=>$v){
                $status= $this->UpDown($v->holiday_type);
                $v->holiday_date=date('n.j',strtotime($v->holiday_date)).$status.'午';
            }

            //初始化
            $type=[];
            //判断是否有发送的用户id
            $createUserId=json_decode($holiday_info->send_gather,true);
            if($createUserId){
                //查询收到模板的消息人的信息
//                $where2['delete_flag']='Y';
//                $where2['reg_type']='WEIXIN';
//                $userInfo=UserReg::where($where2)->whereIn('total_user_id',$createUserId)
//                    ->select('true_name','token_img','token_name','total_user_id')
//                    ->get();
                //此查询如果有多个身份total_user_id查询有问题
                $userInfo=SchoolInfo::with(['userReg'=>function($query){
                        $query->where('delete_flag','=','Y');
                        $query->select('true_name','token_img','token_name','total_user_id');
                    }])
                    ->whereIn('total_user_id',$createUserId)
                    ->select('self_id','person_tel','person_type','total_user_id')
                    ->get();
                foreach($userInfo as $kk=>$vv){
                    $type[$kk]['create_user_img']=$vv->userReg->token_img;
                    if($vv->userReg->true_name){
                        $type[$kk]['create_name']=$vv->userReg->true_name;
                    }else{
                        $type[$kk]['create_name']=$vv->userReg->token_name??'';
                    }

                    $type[$kk]['identity_type']='';
                    switch ($vv->person_type){
                        case 'patriarch':
                            $type[$kk]['identity_type']='家长';
                            break;
                        case 'care':
                            $type[$kk]['identity_type']='照管';
                            break;
                        case 'teacher':
                            $type[$kk]['identity_type']='老师';
                            break;
                        case 'driver':
                            $type[$kk]['identity_type']='司机';
                            break;
                    }

                    if($type[$kk]['create_name'] && $type[$kk]['identity_type']){
                        $type[$kk]['create_name']=$type[$kk]['create_name'].'('.$type[$kk]['identity_type'].')';
                    }

                    $where222=[
                        ['message_send_id','=',$holiday_id],
                        ['create_user_id','=',$vv->total_user_id],
                        ['delete_flag','=','Y']
                    ];
                    $schoolHolidayRead=WxMessageRead::where($where222)->first();
                    if($schoolHolidayRead){
                        $type[$kk]['look_up']='已查看';
                    }else{
                        $type[$kk]['look_up']='未查看';
                    }
                }
            }
//            dump($holiday_info->toArray()); 
//            dd($type);
            $msg['code'] = 200;
            $msg['msg'] = "成功";
            $msg['data'] = $holiday_info;
            $msg['look'] = $type;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg'] = "您的学生没有请假";
            return $msg;
        }
    }
	
    /**
     * 学生请假拉取可请假的数据      holidy/patriarch_get_holidy
     * 传递必须参数：user_token，student_id ，student_name ， holidy_type  ，begin_time ，end_time ，reason
     * 非必填参数：reason
     * 回调结果： 200   请假成功
     *            300  参数不能为空
     *            301  没有用户信息
     *            302  线路信息出错，请联系管理员
     *            303  请切换到家长角色
     *            304  假期已存在
     *           500  授权失败，请重新授权
     */

    public function patriarch_get_holidy(Request $request,RedisServer $redisServer){
        $user_info = $request->get('user_info');
       // $pv['user_id']=$user_info->user_id;
      //  $pv['browse_path']=$request->path();
      //  $pv['level']=null;
     //   $pv['table_id']=null;
      //  $pv['ip']=$request->getClientIp();
       // $pv['place']='MINI';
      //  $redisServer ->set_pvuv_info($pv);

        /** 接收数据*/
        $student_id     =$request->input('student_id');
        $date           =$request->input('date')??date('Y-m',time());

        /** 虚拟一下数据来做下操作*/
        //$student_id='info_202008241130197253888506';
        $firstday = date('Y-m-01',strtotime($date));
        $lastday = date('Y-m-d',strtotime("$firstday +1 month -1 day"));
        if($student_id){
            $where=[
                ['person_id','=',$student_id],
                ['confirm_cancel_type','=','confirm'],
                ['use_flag','=','Y'],
                ['delete_flag','=','Y']
            ];
            $holiday_info=SchoolHolidayPerson::where($where)
                ->whereBetween('holiday_date',[$firstday,$lastday])
                ->select('holiday_date as date','holiday_type as status')
                ->get();
//            dd($holiday_info);
            if($holiday_info->count()>0){
                $msg['code'] = 200;
                $msg['msg'] = "数据拉取成功";
                $msg['data'] = $holiday_info;

            }else{
                $msg['code'] = 301;
                $msg['msg'] = "没有请假";
            }
        }else{
            $msg['code'] = 302;
            $msg['msg'] = "参数不能为空";
        }
        return $msg;
    }


    /**
     * 学生请假      /holidy/patriarch_add_holidy
     * 传递必须参数：user_token，student_id ，student_name ， holidy_type  ，begin_time ，end_time ，reason
     * 非必填参数：reason
     * 回调结果： 200   请假成功
     *            300  参数不能为空
     *            301  没有用户信息
     *            302  线路信息出错，请联系管理员
     *            303  请切换到家长角色
     *            304  假期已存在 
     *           500  授权失败，请重新授权
     */
    public function patriarch_add_holidy(Request $request,RedisServer $redisServer,TempData $tempData,Push $push){
        $user_info                  = $request->get('user_info');

       // $pv['user_id']=$user_info->user_id;
       // $pv['browse_path']=$request->path();
      //  $pv['level']=null;
       // $pv['table_id']=null;
       // $pv['ip']=$request->getClientIp();
       // $pv['place']='MINI';
       // $redisServer ->set_pvuv_info($pv);

        $now_time                   = date('Y-m-d  H:i:s', time());
        $date                       =date_time();           //公用方法中拿取数据的地方

        /** 接收数据*/
        $carriage_info              =null;
        $holiday_date               =$request->input('holiday_date');
        $student_id                 =$request->input('student_id');
        $reason                     =$request->input('reason');
        $is_go_school               =$request->input('is_go_school'); //是否去学校
        $leave_type                 =$request->input('leave_type'); //请假类型
        $input					    =$request->all();

        /** 虚拟一下数据来做下操作
        $input['student_id']=$student_id='info_202007311538340958903856';
        $input['holiday_date']=$holiday_date=[
        ['fullDate'=>'2020-09-09','status'=>'UP'],
        ['fullDate'=>'2020-09-09','status'=>'DOWN'],
        ];
        $holiday_date=json_encode($holiday_date);
        $input['reason']=$reason='请假原因';
         */

        $rules = [
            'student_id' => 'required',
            'holiday_date' => 'required',
        ];
        $message = [
            'student_id.required' => '学生id不能为空',
            'holiday_date.required' => '请假时间不能为空',
        ];
        $validator=Validator::make($input, $rules, $message);
        if ($validator->passes()) {
            $holiday_date=json_decode($holiday_date,true);
            //查询这个学生的信息
            $where['self_id']=$student_id;
            $student_info=SchoolInfo::where($where)
                ->select(
                    'self_id as student_id',
                    'actual_name',
                    'english_name',
                    'grade_name',
                    'class_name',
                    'group_code',
                    'group_name'
                )->first();
//dump($student_info->toArray());
            if($student_info){
                $list=[];
                foreach($holiday_date as $k=>$v){
                    $where2['person_id']=$student_info->student_id;
                    $where2['confirm_cancel_type'] = 'confirm';
                    $where2['holiday_date'] = $v['fullDate'];
                    $where2['holiday_type'] = $v['status'];
                    $where2['delete_flag'] = 'Y';
                    $where2['use_flag'] = 'Y';
                    $schoolHolidayPerson=SchoolHolidayPerson::where($where2)->first();
                    if(empty($schoolHolidayPerson)){
                        $list[]=$v;
                    }
                }
                $count=count($list);
                if($count>0){
                    $holiday					=generate_id('holiday_');
                    $data['self_id']            = $holiday;
                    $data['person_id']          = $student_info->student_id;
                    $data['person_name']        = $student_info->actual_name;
                    $data['english_name']       = $student_info->english_name;
                    $data['reason']             = $reason;
                    if($is_go_school){
                        $data['is_go_school']   = $is_go_school; //是否去学校
                    }
                    if($leave_type){
                        $data['leave_type']     =$leave_type; //请假类型
                    }

                    $data['grade_name']         = $student_info->grade_name;
                    $data['class_name']         = $student_info->class_name;
                    $data['group_code']         = $student_info->group_code;
                    $data['group_name']         = $student_info->group_name;
                    $data['create_user_id']     = $user_info->user_id; //申请人id
                    $data['create_user_name']   = $user_info->token_name;//申请人名称
                    $data['create_time']        = $now_time;
                    $data['update_time']        =$now_time;
                    $data['create_token_img']   = $user_info->token_img;

                    $hosi_where=[
                        ['b.person_id','=',$student_id],
                        ['b.delete_flag','=','Y'],
                        ['a.delete_flag','=','Y'],
                    ];
                    $care_info=DB::table('school_path as a')
                        ->join('school_pathway_person as b',function($join){
                            $join->on('a.self_id','=','b.path_id');
                        }, null,null,'left')
                        ->where($hosi_where)
                        ->select('a.default_driver_id','a.default_care_id','a.path_name','a.site_type')
                        ->get()->toArray();
                    $up_path_name=null;
                    $down_path_name=null;
                    foreach($care_info as $k => $v){
                        if($v->site_type =='UP'){
                            $up_path_name   ='上学：'.$v->path_name;
                        }
                        if($v->site_type =='DOWN'){
                            $down_path_name  ='放学：'.$v->path_name;
                        }
                    }
                    $data['up_path_name']=$up_path_name;
                    $data['down_path_name']=$down_path_name;
                    SchoolHoliday::insert($data);


                    $infos=[];

                    //$schoolHoliday=1;
                    foreach($list as $kk=>$vv){
                        $data_list['self_id']       = generate_id('h_');
                        $data_list['holiday_id']    = $holiday;
                        $data_list['person_id']     = $student_info->student_id;
                        $data_list['person_name']   = $student_info->actual_name;
                        $data_list['holiday_date']  = $vv['fullDate'];
                        $data_list['holiday_type']  = $vv['status'];
                        $data_list['grade_name']    = $student_info->grade_name;
                        $data_list['class_name']    = $student_info->class_name;
                        $data_list['group_code']    = $student_info->group_code;
                        $data_list['group_name']    = $student_info->group_name;
                        $data_list['create_time']   = $now_time;
                        $data_list['update_time']   = $now_time;
                        $schoolHoliday= SchoolHolidayPerson::insert($data_list);

                        switch($vv['status']){
                            case 'DOWN':
                                $infos[]=substr($vv['fullDate'],-5).'放学';
                                break;
                            case 'UP':
                                $infos[]=substr($vv['fullDate'],-5).'上学';
                                break;
                        }



                    }

                    $abcc='';
                    switch($leave_type){
                        case 'transfer':
                            $abcc.='[家长接送]';
                            break;
                        case 'disease':
                            $abcc.='[病假]';
                            break;
                        case 'matter':
                            $abcc.='[事假]';
                            break;
                        default:
                            $abcc.='[其他]';
                            break;
                    }


                    if($is_go_school == 'Y'){
                        //去學校的
                        $abcc.= '去学校';
                    }else{
                        $abcc.= '不去学校';
                    }
                    if($reason){
                        $abcc.= '，'.$reason;
                    }


                    $data['reason']             = $abcc;
                    $info['data']=$data;
                    $info['info']=implode('，',$infos);

                    if($schoolHoliday){
                        /*** redis 处理 判断今天1是不是在这个里面，如果在这个里面，看下是不是在运输中，如果在运输中，则把这个运输线路中的数据处理一下**/
                        $af=['fullDate'=>$date['date'],'status'=>$date['status']];
                        if(in_array($af,$holiday_date)){
                            $where_person['person_id']      =$student_id;
                            $where_person['pathway_type']   =$date['status'];
                            $where_person['use_flag']       ='Y';
                            $where_person['delete_flag']    ='Y';
                            $pathId=SchoolPathwayPerson::where($where_person)->value('path_id');
                            if($pathId){
                                $carriageId='car_'.$pathId.$date['dateStatus'];
                                $carriage_info=$redisServer->get($carriageId,'carriage');
                                if($carriage_info){
                                    $carriage_info=json_decode($carriage_info);
                                    $paichu=$carriage_info->paichu;

                                    //请假人
                                    $holidayStudent=[$student_id];
                                    //判断是否是对象 如果是转成数组
                                    if(is_object($carriage_info->students)){
                                        $waibuStudents=json_decode(json_encode($carriage_info->students),true);
                                    }else{
                                        $waibuStudents=$carriage_info->students;
                                    }

                                    $diff2222=array_diff($waibuStudents,$holidayStudent);
                                    //请假如果是数组中的第一个人则获取数组的value值
                                    $carriage_info->students=array_values($diff2222);
                                    foreach ($carriage_info->school_pathway as $k=>$v){
                                        if(is_object($v->students)){
                                            $studentArr=json_decode(json_encode($v->students),true);
                                        }else{
                                            $studentArr=$v->students;
                                        }
                                        if(in_array($student_id,$studentArr)){
                                            $cou=count($studentArr)-1;
                                            if($cou > 0){
                                                //站点还有人
                                                //取交集  【1,2,3】    【1】   --取差集    【2，3】
                                                //原有id
                                                $diff=array_diff($studentArr,$holidayStudent);
                                                //请假如果是数组中的第一个人则获取数组的value值
                                                $v->students=array_values($diff);

                                                foreach ($v->school_pathway_person as $kkk=>$vvv){
                                                    if($vvv->student_id == $student_id){
                                                        $vvv->auditor_text = '请假中';
                                                        $vvv->auditor_color = '#000';
                                                        $vvv->holiday_status = 'Y';//请假状态 用于点名时区分
                                                        $v->student[$kkk]->auditor_text = '请假中';
                                                        $v->student[$kkk]->auditor_color = '#000';
                                                    }
                                                }
                                            }else{
                                                //站点没有人
                                                $v->pathway_status='Q';
                                                $v->pathway_text="没有孩子，不需要经过";
                                                $v->students=[];
                                                //                                //过站的站点  ,需要追加这个$k进入排序中去
                                                foreach ($v->school_pathway_person as $kk => $vv) {
                                                    if($vv->student_id == $student_id){
                                                        $vv->auditor_text = '请假中';
                                                        $vv->auditor_color = '#000';
                                                        $vv->holiday_status = 'Y';//请假状态 用于点名时区分
                                                        $v->student[$kk]->auditor_text = '请假中';
                                                        $v->student[$kk]->auditor_color = '#000';
                                                    }

                                                }
                                                $paichu[]=$k;
                                            }
                                        }
                                    }
                                    $carriage_info->paichu=$paichu;
                                    $redisServer->setex($carriageId,json_encode($carriage_info,JSON_UNESCAPED_UNICODE),'carriage',2592000);

                                }
                            }
                        }
                        /*** redis处理完毕**/

                        /**  现在开始处理请假推送消息1**/
                        //$tempData->sendCartData($push,$info,'holiday');

                        $msg['code'] = 200;
                        $msg['msg'] = '请假成功';
                    }else{
                        $msg['code'] = 301;
                        $msg['msg'] = '请假失败';
                    }
                }else{
                    $msg['code'] = 302;
                    $msg['msg'] = '您选择的请假日期都已经请过假';
                }
//                $this->holidayData('holiday_202008251235312321237255');
                //dd($list);
            }else{
                $msg['code'] = 303;
                $msg['msg'] = '查询不到该学生';
            }
            //做业务逻辑
        }else{
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $msg['msg'] .= $v . "\r\n";
            }
        }
        return $msg;
    }
    /**
     * 校车上学和放学运行状态中文转换
     * @param $UpDown
     * @return string
     */
    private function UpDown($UpDown){
        switch($UpDown){
            case 'DOWN':
                $status='下';
                break;
            case 'UP':
                $status='上';
                break;
        }
        return $status;
    }

}
