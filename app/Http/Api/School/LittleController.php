<?php
namespace App\Http\Api\School;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\RedisController as RedisServer;
use App\Models\School\SchoolPathway;
use App\Models\School\SchoolHolidayPerson;
use App\Models\School\SchoolCarriageInventory;
use App\Models\School\SchoolCarriage;
class LittleController extends Controller{
    /**
     * 获取学生点名的名单
     * pathUrl => /little/get_roster
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function getRoster(Request $request,RedisServer $redisServer){
        $user_info          = $request->get('user_info');
        $carriage_id		=$request->input('carriage_id');
        $pathway_id		    =$request->input('pathway_id');

        /**虚拟数据**/
        $carriage_id		='car_path_202009041729266479273648DOWN2020-10-09';
        $pathway_id		    ='pathway_202009011054392633766601';


        $where500['use_flag']='Y';
        $where500['delete_flag']='Y';
        $where500['self_id']=$pathway_id;
        $schoolPathway=SchoolPathway::with(['schoolPath:self_id,path_name,default_care_name'])
            ->select('self_id','path_id','group_name','site_type')
            ->first();
        if($schoolPathway){
            $path_name=$schoolPathway->schoolPath->path_name;
            $default_care_name=$schoolPathway->schoolPath->default_care_name;
            $site_type=self::UpDown($schoolPathway->site_type);
			$data['come_img']= $user_info->token_img;
            if($default_care_name){
                $data['come_name']=$default_care_name;
            }else{
                $data['come_name']= $user_info->token_name??'';
            }
            $data['group_name']=$schoolPathway->group_name;
            $data['path_name']=$site_type.$path_name;

            //获取缓存中运输的信息
            $carriage=$redisServer->get($carriage_id,'carriage');
            if($carriage){
                $carriage_info=json_decode($carriage,true);
                if($carriage_info && $carriage_info['carriage_status'] == 1){
                    $msg['code'] = 301;
                    $msg['msg'] = "线路未发车";
                    $msg['data'] = $data;
                    return $msg;
                }
                $where210['use_flag']='Y';
                $where210['delete_flag']='Y';
                $where210['carriage_id']=$carriage_id;
                $where210['pathway_count_id']=$pathway_id;
                $countqwer=SchoolCarriageInventory::where($where210)->count();
                if($countqwer > 0){
                    $msg['code'] = 301;
                    $msg['msg'] = "该站已点过名";
                    $msg['data'] = $data;
                    return $msg;
                }else{
                    //判断是上午还是下午
                    $data['count']=0;
                    $data['pathway_id']='';
                    $data['pathway_name']='';
                    switch ($carriage_info['site_type']){
                        case 'UP':
                            //判断是否有，排除没有的 如果判断省率则会出错
                            if($carriage_info['school_pathway']){
                                $endPathway_id=$carriage_info['school_pathway'][$carriage_info['end']]['pathway_id'];
                                $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                                $data['pathway_id']=$school_pathway[0]['pathway_id'];
                                $data['pathway_name']=$school_pathway[0]['pathway_name'];
                                //判断是否是最后一站
                                if($endPathway_id == $pathway_id){
                                    $data['type']='fang';
                                    $where['use_flag']='Y';
                                    $where['delete_flag']='Y';
                                    $where['carriage_id']=$carriage_id;
                                    $where['riding_type']='yes_riding';
                                    $schoolCarriageInventory=SchoolCarriageInventory::where($where)->pluck('student_id');
                                    $student_info=[];
                                    foreach ($carriage_info['school_pathway'] as $k=>$v){
                                        //获取孩子的列表
                                        foreach ($v['school_pathway_person'] as $kk=>$vv){
                                            $student['student_id']=$vv['student_id'];
                                            $student['student_name']=$vv['student_name'];
                                            if(in_array($vv['student_id'],$schoolCarriageInventory->toArray())){
                                                $student['riding_type']='yes_riding';
                                                $student['holiday_status']=$vv['holiday_status'];
                                            }else{
                                                if($vv['holiday_status'] == 'Y'){
                                                    $student['riding_type']='yes_holiday';
                                                    $student['holiday_status']=$vv['holiday_status'];
                                                }else{
                                                    $student['riding_type']='not_riding';
                                                    $student['holiday_status']='N';
                                                }
                                            }
                                            $student_info[]=$student;
                                        }
                                    }
                                    $data['count']=count($student_info);
                                    $valies=[];
                                    //每28条为一个数组
                                    foreach(array_chunk($student_info, 4) as $key=>$values){
                                        $valies[$key]['info']=$values;
                                    }
                                    $data['student_info']=$valies;
                                }else{
                                    $data['type']='shang';

                                    $student=[];
                                    foreach ($school_pathway[0]['school_pathway_person'] as $k=>$v){
                                        //获取孩子的列表
                                        $student[$k]['student_id']=$v['student_id'];
                                        $student[$k]['riding_type']='';
                                        $student[$k]['student_name']=$v['student_name'];
                                        $student[$k]['holiday_status']=$v['holiday_status'];
                                    }
                                    $data['count']=count($student);
                                    $valies=[];
                                    //每28条为一个数组
                                    foreach(array_chunk($student, 4) as $key=>$values){
                                        $valies[$key]['info']=$values;
                                    }
                                    $data['student_info']=$valies;
                                }

                            }
                            break;
                        case 'DOWN':
                            //判断是否有，排除没有的 如果判断省率则会出错
                            if($carriage_info['school_pathway']){
                                $startPathway_id=$carriage_info['school_pathway'][$carriage_info['start']]['pathway_id'];
                                $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                                $data['pathway_id']=$school_pathway[0]['pathway_id'];
                                $data['pathway_name']=$school_pathway[0]['pathway_name'];
                                if($startPathway_id == $pathway_id){
                                    $data['type']='shang';
                                    $data['student_info']=[];
                                    //获取线路的所有孩子的名单
                                    $student_info=[];
                                    foreach ($carriage_info['school_pathway'] as $k=>$v){
                                        // $student=[];
                                        if(count($v['school_pathway_person']) > 0){
                                            foreach($v['school_pathway_person'] as $kk=>$vv){
                                                //获取孩子的列表
                                                $student['student_id']=$vv['student_id'];
                                                $student['riding_type']='';
                                                $student['student_name']=$vv['student_name'];
                                                $student['holiday_status']=$vv['holiday_status'];//请假状态 请假Y 未请假N
                                                $student_info[]=$student;
                                            }
                                        }
                                    }
                                    //获取线路的所有孩子的名单
                                    $val=array_values(array_filter($student_info));
                                    $data['count']=count($val);
                                    $valies=[];
                                    //每28条为一个数组
                                    foreach(array_chunk($val, 8) as $key=>$values){
                                        $valies[$key]['info']=$values;
                                    }
                                    $data['student_info']=$valies;
                                }else{
                                    $data['type']='fang';
                                    $where['use_flag']='Y';
                                    $where['delete_flag']='Y';
                                    $where['carriage_id']=$carriage_id;
                                    $where['riding_type']='yes_riding';
                                    $where['pathway_id']=$pathway_id;
                                    $schoolCarriageInventory=SchoolCarriageInventory::where($where)->pluck('student_id');
                                    //获取孩子的列表
                                    $student=[];
                                    foreach ($school_pathway[0]['school_pathway_person'] as $k=>$v){
                                        $student[$k]['student_id']=$v['student_id'];
                                        $student[$k]['student_name']=$v['student_name'];
                                        if(in_array($v['student_id'],$schoolCarriageInventory->toArray())){
                                            $student[$k]['riding_type']='yes_riding';
                                            $student[$k]['holiday_status']=$v['holiday_status'];
                                        }else{
                                            if($v['holiday_status'] == 'Y'){
                                                $student[$k]['riding_type']='yes_holiday';
                                                $student[$k]['holiday_status']=$v['holiday_status'];
                                            }else{
                                                $student[$k]['riding_type']='not_riding';
                                                $student[$k]['holiday_status']='N';
                                            }
                                        }
                                    }
                                    $data['count']=count($student);
                                    $valies=[];
                                    //每28条为一个数组
                                    foreach(array_chunk($student, 4) as $key=>$values){
                                        $valies[$key]['info']=$values;
                                    }
                                    $data['student_info']=$valies;
                                }
                            }
                            break;
                    }

                    $msg['code'] = 200;
                    $msg['msg'] = "成功";
                    $msg['data'] = $data;
                    return $msg;
                }
            }else{
                $msg['code'] = 300;
                $msg['msg'] = "没有运输信息";
                return $msg;
            }
        }else{
            $msg['code'] = 300;
            $msg['msg'] = "此站点不存在";
            return $msg;
        }
    }

    public function setRoster(Request $request,RedisServer $redisServer){
        $user_info          = $request->get('user_info');
        $carriage_id		=$request->input('carriage_id');
        $pathway_id		    =$request->input('pathway_id');
        $student_id		    =$request->input('student_id');
        $input		    =$request->all();

        /**虚拟数据**/
        // $input['carriage_id']=$carriage_id		='car_path_202009041717175186574721DOWN2020-10-09';
        //  $input['pathway_id']=$pathway_id		='pathway_202009011054392633766601';
        //  $input['student_id']=$student_id        =[
//            'info_202008311919107343773742',
        //      'info_202007311538340956450635',
        //      'info_202007311538340957581938'
        //  ];//未请假的

        $rules = [
            'carriage_id' => 'required',
            'pathway_id' => 'required',
            'student_id' => 'required',
        ];
        $message = [
            'carriage_id.required' => '运输线路不能为空',
            'pathway_id.required' => '站点id不能为空',
            'student_id.required' => '孩子id必须',
        ];
        $validator=Validator::make($input, $rules, $message);
        if($validator){
            //获取缓存中运输的信息
            $carriage=$redisServer->get($carriage_id,'carriage');

            //$where100['self_id']=$carriage_id;
            //$carriage=SchoolCarriage::where($where100)->value('text_info');
            if($carriage){
                $student_id=json_decode($student_id,true);
                $carriage_info=json_decode($carriage,true);
                if($carriage_info['carriage_status'] == 1){
                    $msg['code'] = 301;
                    $msg['msg'] = "线路未发车";
                    return $msg;
                }
                //判断是上午还是下午
                switch ($carriage_info['site_type']){
                    case 'UP':
                        $endPathway_id=$carriage_info['school_pathway'][$carriage_info['end']]['pathway_id'];
                        $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                        if($endPathway_id == $pathway_id){
                            $where['use_flag']='Y';
                            $where['delete_flag']='Y';
                            $where['carriage_id']=$carriage_id;
                            $where['riding_type']='yes_riding';
                            $schoolCarriageInventory=SchoolCarriageInventory::where($where)->get();
                            if($schoolCarriageInventory->count()>0){
                                foreach ($schoolCarriageInventory as $k=>$v){
                                    $invent[$k]['self_id']=generate_id('invent_');
                                    $invent[$k]['group_code']=$v->group_code;
                                    $invent[$k]['group_name']=$v->group_name;
                                    $invent[$k]['create_user_id']=$user_info->user_id;
                                    $invent[$k]['create_user_name']=$user_info->true_name;
                                    $invent[$k]['create_time']=date('Y-m-d  H:i:s', time());
                                    $invent[$k]['update_time']=date('Y-m-d  H:i:s', time());
                                    $invent[$k]['carriage_id']=$v->carriage_id;
                                    $invent[$k]['pathway_name']=$v->pathway_name;
                                    $invent[$k]['pathway_id']=$v->pathway_id;
                                    $invent[$k]['pathway_count_id']=$pathway_id;
                                    $invent[$k]['path_name']=$v->path_name;
                                    $invent[$k]['student_id']=$v->student_id;
                                    $invent[$k]['actual_name']=$v->actual_name;
                                    $invent[$k]['english_name']=$v->english_name;
                                    $invent[$k]['grade_name']=$v->grade_name;
                                    $invent[$k]['class_name']=$v->class_name;
                                    if(in_array($v->student_id,$student_id)){
                                        $invent[$k]['riding_type']='up_riding';//已下车
                                    }else{
                                        $invent[$k]['riding_type']='dow_riding';//未下车
                                    }
                                }

                                $where2['use_flag']='Y';
                                $where2['delete_flag']='Y';
                                $where2['carriage_id']=$carriage_id;
                                $where2['riding_type']='yes_riding';
                                $where2['pathway_count_id']=$pathway_id;
                                $exists=SchoolCarriageInventory::where($where2)->exists();
                                if(!$exists){
                                    SchoolCarriageInventory::insert($invent);
                                }
                            }
                        }else{
                            $school_pathway_person=$school_pathway[0]['school_pathway_person'];
                            foreach($school_pathway_person as $k=>$v){
                                $invent['self_id']=generate_id('invent_');
                                $invent['group_code']=$carriage_info['group_code'];
                                $invent['group_name']=$carriage_info['group_name'];
                                $invent['create_user_id']=$user_info->user_id;
                                $invent['create_user_name']=$user_info->true_name;
                                $invent['create_time']=date('Y-m-d  H:i:s', time());
                                $invent['update_time']=date('Y-m-d  H:i:s', time());
                                $invent['carriage_id']=$carriage_id;
                                $invent['pathway_name']=$school_pathway[0]['pathway_name'];
                                $invent['pathway_id']=$school_pathway[0]['pathway_id'];
                                $invent['pathway_count_id']=$pathway_id;
                                $invent['path_name']=$carriage_info['path_name'];
                                $invent['student_id']=$v['student_id'];
                                $invent['actual_name']=$v['student_name'];
                                $invent['grade_name']=$v['grade_name'];
                                $invent['class_name']=$v['class_name'];
                                if(in_array($v['student_id'],$student_id)){
                                    //dump($student_id);
                                    $invent['riding_type']='yes_riding';//已上车
                                }else{
                                    //dump(111);
                                    if($v['holiday_status'] == 'Y'){
                                        $invent['riding_type']='yes_holiday';
                                    }else{
                                        $invent['riding_type']='not_riding';
                                    }
                                }


                                $where2['use_flag']='Y';
                                $where2['delete_flag']='Y';
                                $where2['carriage_id']=$carriage_id;
                                $where2['riding_type']=$invent['riding_type'];
                                $where2['pathway_id']=$school_pathway[0]['pathway_id'];
                                $where2['pathway_count_id']=$pathway_id;
                                $where2['student_id']=$v['student_id'];
                                $exists=SchoolCarriageInventory::where($where2)->exists();
                                if(!$exists){
                                    SchoolCarriageInventory::insert($invent);
                                }
                            }
                        }

//                        if($carriage_info['next'] == $carriage_info['end']){
//                            $where['use_flag']='Y';
//                            $where['delete_flag']='Y';
//                            $where['carriage_id']=$carriage_id;
//                            $where['riding_type']='yes_riding';
//                            $schoolCarriageInventory=SchoolCarriageInventory::where($where)->get();
//                            if($schoolCarriageInventory->count()>0){
//                                foreach ($schoolCarriageInventory as $k=>$v){
//                                    $invent[$k]['self_id']=generate_id('invent_');
//                                    $invent[$k]['group_code']=$v->group_code;
//                                    $invent[$k]['group_name']=$v->group_name;
//                                    $invent[$k]['create_user_id']=$user_info->user_id;
//                                    $invent[$k]['create_user_name']=$user_info->true_name;
//                                    $invent[$k]['create_time']=date('Y-m-d  H:i:s', time());
//                                    $invent[$k]['update_time']=date('Y-m-d  H:i:s', time());
//                                    $invent[$k]['carriage_id']=$v->carriage_id;
//                                    $invent[$k]['pathway_name']=$v->pathway_name;
//                                    $invent[$k]['pathway_id']=$v->pathway_id;
//                                    $invent[$k]['pathway_count_id']=$pathway_id;
//                                    $invent[$k]['path_name']=$v->path_name;
//                                    $invent[$k]['student_id']=$v->student_id;
//                                    $invent[$k]['actual_name']=$v->actual_name;
//                                    $invent[$k]['english_name']=$v->english_name;
//                                    $invent[$k]['grade_name']=$v->grade_name;
//                                    $invent[$k]['class_name']=$v->class_name;
//                                    if(in_array($v->student_id,$student_id)){
//                                        $invent[$k]['riding_type']='up_riding';//已下车
//                                    }else{
//                                        $invent[$k]['riding_type']='dow_riding';//未下车
//                                    }
//                                }
//
//                                $where2['use_flag']='Y';
//                                $where2['delete_flag']='Y';
//                                $where2['carriage_id']=$carriage_id;
//                                $where2['riding_type']='yes_riding';
//                                $where2['pathway_count_id']=$pathway_id;
//                                $exists=SchoolCarriageInventory::where($where2)->exists();
//                                if(!$exists){
//                                    SchoolCarriageInventory::insert($invent);
//                                }
//                            }
//                        }else{
//                            $nextPathway_id=$carriage_info['school_pathway'][$carriage_info['next']]['pathway_id'];
//                            if($nextPathway_id != $pathway_id){
//                                $nextNum=$carriage_info['next']-1;
//                            }else{
//                                $nextNum=$carriage_info['next'];
//                            }
//
//                            $nexts=$carriage_info['school_pathway'][$nextNum];
//                            $school_pathway_person=$nexts['school_pathway_person'];
//                            foreach($school_pathway_person as $k=>$v){
//                                $invent['self_id']=generate_id('invent_');
//                                $invent['group_code']=$carriage_info['group_code'];
//                                $invent['group_name']=$carriage_info['group_name'];
//                                $invent['create_user_id']=$user_info->user_id;
//                                $invent['create_user_name']=$user_info->true_name;
//                                $invent['create_time']=date('Y-m-d  H:i:s', time());
//                                $invent['update_time']=date('Y-m-d  H:i:s', time());
//                                $invent['carriage_id']=$carriage_id;
//                                $invent['pathway_name']=$nexts['pathway_name'];
//                                $invent['pathway_id']=$nexts['pathway_id'];
//                                $invent['pathway_count_id']=$pathway_id;
//                                $invent['path_name']=$carriage_info['path_name'];
//                                $invent['student_id']=$v['student_id'];
//                                $invent['actual_name']=$v['student_name'];
//                                $invent['grade_name']=$v['grade_name'];
//                                $invent['class_name']=$v['class_name'];
//                                if(in_array($v['student_id'],$student_id)){
//                                    //dump($student_id);
//                                    $invent['riding_type']='yes_riding';//已上车
//                                }else{
//                                    //dump(111);
//                                    if($v['holiday_status'] == 'Y'){
//                                        $invent['riding_type']='yes_holiday';
//                                    }else{
//                                        $invent['riding_type']='not_riding';
//                                    }
//                                }
//
//
//                                $where2['use_flag']='Y';
//                                $where2['delete_flag']='Y';
//                                $where2['carriage_id']=$carriage_id;
//                                $where2['riding_type']=$invent['riding_type'];
//                                $where2['pathway_id']=$nexts['pathway_id'];
//                                $where2['pathway_count_id']=$pathway_id;
//                                $exists=SchoolCarriageInventory::where($where2)->exists();
//                                if(!$exists){
//                                    SchoolCarriageInventory::insert($invent);
//                                }
//                            }
//                        }
                        break;
                    case 'DOWN':
                        $startPathway_id=$carriage_info['school_pathway'][$carriage_info['start']]['pathway_id'];
                        $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                        if($startPathway_id != $pathway_id){
                            $where['use_flag']='Y';
                            $where['delete_flag']='Y';
                            $where['carriage_id']=$carriage_id;
                            $where['riding_type']='yes_riding';
                            $where['pathway_id']=$pathway_id;
                            $schoolCarriageInventory=SchoolCarriageInventory::where($where)->get();
                            if($schoolCarriageInventory->count()>0){
                                foreach ($schoolCarriageInventory as $k=>$v){
                                    $invent['self_id']=generate_id('invent_');
                                    $invent['group_code']=$v->group_code;
                                    $invent['group_name']=$v->group_name;
                                    $invent['create_user_id']=$user_info->user_id;
                                    $invent['create_user_name']=$user_info->true_name;
                                    $invent['create_time']=date('Y-m-d  H:i:s', time());
                                    $invent['update_time']=date('Y-m-d  H:i:s', time());
                                    $invent['carriage_id']=$v->carriage_id;
                                    $invent['pathway_name']=$v->pathway_name;
                                    $invent['pathway_id']=$v->pathway_id;
                                    $invent['pathway_count_id']=$pathway_id;
                                    $invent['path_name']=$v->path_name;
                                    $invent['student_id']=$v->student_id;
                                    $invent['actual_name']=$v->actual_name;
                                    $invent['english_name']=$v->english_name;
                                    $invent['grade_name']=$v->grade_name;
                                    $invent['class_name']=$v->class_name;
                                    if(in_array($v->student_id,$student_id)){
                                        $invent['riding_type']='up_riding';//已下车
                                    }else{
                                        $invent['riding_type']='dow_riding';//未下车
                                    }
                                    $where2['use_flag']='Y';
                                    $where2['delete_flag']='Y';
                                    $where2['carriage_id']=$carriage_id;
                                    $where2['riding_type']='yes_riding';
                                    $where2['pathway_id']=$pathway_id;
                                    $where2['student_id']=$v->student_id;
                                    $where2['pathway_count_id']=$pathway_id;
                                    $exists=SchoolCarriageInventory::where($where2)->exists();
                                    if(!$exists){
                                        SchoolCarriageInventory::insert($invent);
                                    }
                                }
                            }
                        }else{
                            foreach($carriage_info['school_pathway'] as $k=>$v){
                                foreach ($v['school_pathway_person'] as $kk=>$vv){
                                    $invent['self_id']=generate_id('invent_');
                                    $invent['group_code']=$carriage_info['group_code'];
                                    $invent['group_name']=$carriage_info['group_name'];
                                    $invent['create_user_id']=$user_info->user_id;
                                    $invent['create_user_name']=$user_info->true_name;
                                    $invent['create_time']=date('Y-m-d  H:i:s', time());
                                    $invent['update_time']=date('Y-m-d  H:i:s', time());
                                    $invent['carriage_id']=$carriage_id;
                                    $invent['pathway_name']=$v['pathway_name'];
                                    $invent['pathway_id']=$v['pathway_id'];
                                    $invent['pathway_count_id']=$pathway_id;
                                    $invent['path_name']=$carriage_info['path_name'];
                                    $invent['student_id']=$vv['student_id'];
                                    $invent['actual_name']=$vv['student_name'];
                                    $invent['grade_name']=$vv['grade_name'];
                                    $invent['class_name']=$vv['class_name'];
                                    if(in_array($vv['student_id'],$student_id)){
                                        $invent['riding_type']='yes_riding';
                                    }else{
                                        if($vv['holiday_status'] == 'Y'){
                                            $invent['riding_type']='yes_holiday';
                                        }else{
                                            $invent['riding_type']='not_riding';
                                        }
                                    }

                                    $where2['use_flag']='Y';
                                    $where2['delete_flag']='Y';
                                    $where2['carriage_id']=$carriage_id;
                                    $where2['pathway_id']=$v['pathway_id'];
                                    $where2['student_id']=$vv['student_id'];
                                    $where2['pathway_count_id']=$pathway_id;
                                    $exists=SchoolCarriageInventory::where($where2)->exists();
                                    if(!$exists){
                                        SchoolCarriageInventory::insert($invent);
                                    }
                                }
                            }
                        }
                        break;
                }
                $msg['code'] = 200;
                $msg['msg'] = "成功";
                return $msg;
            }else{
                $msg['code'] = 301;
                $msg['msg'] = "暂无线路";
                return $msg;
            }
            // $where['use_flag']='Y';
            // $where['delete_flag']='Y';
            // $where['pathway_id']=$pathway_id;
            // //获取已请假和未上车的人的id
            // $schoolPathwayPerson=SchoolPathwayPerson::where($where)->whereNotIn('person_id',$student_id)->pluck('person_id');
            // //判断是否有请假和未上车的人
            // $data=[];
            // if($schoolPathwayPerson->count() > 0){
            //     $where2['use_flag']='Y';
            //     $where2['delete_flag']='Y';
            //     // $where2['holiday_type']=$datetime['status'];
            //     $where2['holiday_type']='UP';
            //     $where2['holiday_date']=$datetime['date'];
            //     //获取已请假人的id
            //     $schoolHolidayPerson=SchoolHolidayPerson::where($where2)->whereIn('person_id',$schoolPathwayPerson)->pluck('person_id');
            //     //如果大于0则有请假的
            //     if($schoolHolidayPerson->count() > 0){
            //         //线路上的请假和为上车的集合 与 请假的集合做差集运算
            //         $intersection = array_diff($schoolPathwayPerson,$schoolHolidayPerson->toArray());
            //         if(count($intersection) > 0){
            //             //既有请假也有为上车的
            //             $data['not_riding']=$intersection;
            //         }
            //         $data['yes_holiday']=$schoolHolidayPerson->toArray();
            //     }else{
            //         $data['not_riding']=$schoolPathwayPerson->toArray();
            //     }
            // }
            // //全部已上车
            // $data['yes_riding']=$student_id;
        }else{
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $msg['msg'] .= $v . "\r\n";
            }
            return $msg;
        }
    }

    public function getRosterCount(Request $request,RedisServer $redisServer){
        $user_info          = $request->get('user_info');
        $carriage_id		=$request->input('carriage_id');
        $pathway_id		    =$request->input('pathway_id');
        // $input		    =$request->all();


        /**虚拟数据**/
        //$input['carriage_id']=$carriage_id		='car_path_202009151618365965850134UP2020-10-10';
        //$input['pathway_id']=$pathway_id		='pathway_202009151704102227606752';

        //获取缓存中运输的信息
        $carriage=$redisServer->get($carriage_id,'carriage');
        // $where100['self_id']=$carriage_id;
        // $carriage=SchoolCarriage::where($where100)->value('text_info');

        if($carriage) {
            $carriage_info = json_decode($carriage, true);
            //上下午状态转化中文
            $site_type=self::UpDown($carriage_info['site_type']);
            $data['come_img']= $user_info->token_img;
            if($carriage_info['default_care_name']){
                $data['come_name']=$carriage_info['default_care_name'];
            }else{
                $data['come_name']= $user_info->token_name??'';
            }
            $data['group_name']=$carriage_info['group_name'];
            $data['path_name']=$site_type.$carriage_info['path_name'];

//            $endPathway_id=$carriage_info['school_pathway'][$carriage_info['end']]['pathway_id'];
//            if($endPathway_id == $pathway_id){
//                $nextNum=$carriage_info['next'];
//            }else{
//                if($carriage_info['next']>0){
//                    $nextNum=$carriage_info['next']-1;
//                }else{
//                    $nextNum=$carriage_info['next'];
//                }
//            }
//            $data['pathway_id']=$carriage_info['school_pathway'][$nextNum]['pathway_id'];
//            $data['pathway_name']=$carriage_info['school_pathway'][$nextNum]['pathway_name'];

//            $data['pathway_id']=$carriage_info['school_pathway'][$carriage_info['next']]['pathway_id'];
//            $data['pathway_name']=$carriage_info['school_pathway'][$carriage_info['next']]['pathway_name'];
            switch($carriage_info['site_type']){
                case 'DOWN':
                    if($carriage_info['school_pathway']){
                        $startPathway_id=$carriage_info['school_pathway'][$carriage_info['start']]['pathway_id'];
                        $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                        $data['pathway_id']=$school_pathway[0]['pathway_id'];
                        $data['pathway_name']=$school_pathway[0]['pathway_name'];
                        if($startPathway_id == $pathway_id){
                            $data['type']='shang';
                            $data['tcount']=$carriage_info['student_count'];
                            $where6['pathway_count_id']=$pathway_id;
                        }else{
                            $data['type']='fang';
                            $data['tcount']=$school_pathway[0]['student_count'];
                            $where2['pathway_id']=$pathway_id;

                            $where3['pathway_id']=$pathway_id;
                            $where3['pathway_count_id']=$pathway_id;

                            $where4['pathway_id']=$pathway_id;
                            $where4['pathway_count_id']=$pathway_id;

                            $where5['pathway_id']=$pathway_id;

                            $where6['pathway_id']=$pathway_id;//下午已请假的拿pathway_id查
                        }
                    }

//                    $data['pathway_id']=$school_pathway[0]['pathway_id'];
//                    $data['pathway_name']=$school_pathway[0]['pathway_name'];

//                    if($nextNum > 0){
//                        $data['tcount']=$carriage_info['school_pathway'][$nextNum]['student_count'];
//                        $where2['pathway_id']=$pathway_id;
//
//                        $where3['pathway_id']=$pathway_id;
//                        $where3['pathway_count_id']=$pathway_id;
//
//                        $where4['pathway_id']=$pathway_id;
//                        $where4['pathway_count_id']=$pathway_id;
//
//                        $where5['pathway_id']=$pathway_id;
//
//                        $where6['pathway_id']=$pathway_id;//下午已请假的拿pathway_id查
//                    }else{
//                        $data['tcount']=$carriage_info['student_count'];
//                        $where6['pathway_count_id']=$pathway_id;
//                    }
                    break;
                case 'UP':
                    //判断是否有，排除没有的 如果判断省率则会出错
                    if($carriage_info['school_pathway']){
                        $endPathway_id=$carriage_info['school_pathway'][$carriage_info['end']]['pathway_id'];
                        $school_pathway =array_values(array_filter($carriage_info['school_pathway'], function($t) use ($pathway_id) { return $t['self_id'] == $pathway_id; }));
                        $data['pathway_id']=$school_pathway[0]['pathway_id'];
                        $data['pathway_name']=$school_pathway[0]['pathway_name'];
                        if($endPathway_id == $pathway_id){
                            $data['type']='fang';
                            $data['tcount']=$carriage_info['student_count'];
                        }else{
                            $data['type']='shang';
                            $data['tcount']=$school_pathway[0]['student_count'];
                            $where6['pathway_count_id']=$pathway_id;
                            $where5['pathway_count_id']=$pathway_id;
                            $where4['pathway_count_id']=$pathway_id;
                            $where3['pathway_count_id']=$pathway_id;
                            $where2['pathway_count_id']=$pathway_id;
                        }
                    }
//                    $endPathway_id=$carriage_info['school_pathway'][$carriage_info['end']]['pathway_id'];
//                    if($endPathway_id == $pathway_id){
//                        $nextNum=$carriage_info['next'];
//                    }else{
//                        if($carriage_info['next']>0){
//                            $nextNum=$carriage_info['next']-1;
//                        }else{
//                            $nextNum=$carriage_info['next'];
//                        }
//                    }

//                    if($nextNum == $carriage_info['end']){
//                        $data['tcount']=$carriage_info['student_count'];
//                    }else{
//                        $data['tcount']=$carriage_info['school_pathway'][$nextNum]['student_count'];
//                        $where6['pathway_count_id']=$pathway_id;
//                        $where5['pathway_count_id']=$pathway_id;
//                        $where4['pathway_count_id']=$pathway_id;
//                        $where3['pathway_count_id']=$pathway_id;
//                        $where2['pathway_count_id']=$pathway_id;
//                    }
                    break;
            }
            //应到
            //$where['use_flag']='Y';
            // $where['delete_flag']='Y';
            // $where['carriage_id']=$carriage_id;
            // $where['pathway_count_id']=$pathway_id;
            // $totalCount=SchoolCarriageInventory::where($where)->count();
            // $data['tcount']=$totalCount;

            //已上车
            $where2['use_flag']='Y';
            $where2['delete_flag']='Y';
            $where2['carriage_id']=$carriage_id;
            $where2['riding_type']='yes_riding';
            $yCount=SchoolCarriageInventory::where($where2)->count();
            $data['ycount']=$yCount;

            //未下车
            $where3['use_flag']='Y';
            $where3['delete_flag']='Y';
            $where3['carriage_id']=$carriage_id;
            $where3['riding_type']='dow_riding';
            $actual_name_dow=SchoolCarriageInventory::where($where3)->pluck('actual_name');
            $dCount=$actual_name_dow->count();
            $data['dcount']=$dCount;
            $data['actual_name_dow']=$actual_name_dow;

            //已下车
            $where4['use_flag']='Y';
            $where4['delete_flag']='Y';
            $where4['carriage_id']=$carriage_id;
            $where4['riding_type']='up_riding';
            $uCount=SchoolCarriageInventory::where($where4)->count();
            $data['ucount']=$uCount;

            //未上车
            $where5['use_flag']='Y';
            $where5['delete_flag']='Y';
            $where5['carriage_id']=$carriage_id;
            $where5['riding_type']='not_riding';
            $actual_name_not=SchoolCarriageInventory::where($where5)->pluck('actual_name');
            $nCount=$actual_name_not->count();
            $data['ncount']=$nCount;
            $data['actual_name_not']=$actual_name_not;

            //请假
            $where6['use_flag']='Y';
            $where6['delete_flag']='Y';
            $where6['carriage_id']=$carriage_id;
            $where6['riding_type']='yes_holiday';
            $actual_name_holiday=SchoolCarriageInventory::where($where6)->pluck('actual_name');
            $hCount=$actual_name_holiday->count();
            $data['hcount']=$hCount;
            $data['actual_name_holiday']=$actual_name_holiday;

            // dump($data);
            // dump($actual_name_holiday);
            //  dd($carriage_info);
            $msg['code'] = 200;
            $msg['msg'] = "成功";
            $msg['data'] = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg'] = "暂无线路";
            return $msg;
        }
    }

    /**
     * 校车上学和放学运行状态中文转换
     * @param $UpDown
     * @return string
     */
    private function UpDown($UpDown){
        switch($UpDown){
            case 'DOWN':
                $status='下午';
                break;
            case 'UP':
                $status='上午';
                break;
        }
        return $status;
    }
}
