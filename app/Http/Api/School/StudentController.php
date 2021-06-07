<?php
namespace App\Http\Api\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\RedisController as RedisServer;
use App\Models\School\SchoolHoliday;
class StudentController extends Controller{
    /**
     * 添加学生和自己的关系进入数据库  /student/add_child
     * 前端传递必须参数：
     *  * 回调结果：200    查询成功
     *              500  授权失败，请重新授权
     */
     public function add_child(Request $request,RedisServer $redisServer){
//         $msg['code']=305; 
//         $msg['msg']="缺少必要的参数！";
         

         $user_info     =$request->get('user_info');
         $input			=$request->all();
         $now_time       =date('Y-m-d H:i:s',time());

		 
         
        // $pv['user_id']=$user_info->user_id;
         //$pv['browse_path']=$request->path();
        // $pv['level']=null;
        // $pv['table_id']=null;
        // $pv['ip']=$request->getClientIp();
        // $pv['place']='MINI';
         //$redisServer ->set_pvuv_info($pv);

         /** 接收数据*/
         $identity_card                 =$request->get('identity_card');
         $actual_name                   =$request->get('actual_name');
         $true_name                     =$request->get('true_name');

        /** 虚拟一下数据来做下操作
         $input['identity_card']       =$identity_card                 ='522425201405240410';
         $input['identity_card']       =$identity_card                 ='dbz0024620';
         $input['actual_name']       =$actual_name                   ='冯兴荣';*/

        $rules = [
            'identity_card' => 'required',
            'actual_name' => 'required',
            //'true_name' => 'required',
        ];
        $message = [
            'identity_card.required' => '学生号码不能为空',
            'actual_name.required' => '学生姓名不能为空',
           // 'true_name.required' => '真实姓名不能为空',
        ];

        $validator = Validator::make($input,$rules,$message);

        if($validator->passes()){
            $student_where=[
                ['identity_card','=',$identity_card],
                ['actual_name','=',$actual_name],
                ['delete_flag','=','Y'],
                ['person_type','=','student'],
            ];

            $student_info=DB::table('school_info')->where($student_where)
                ->select('self_id as student_id','actual_name','group_code','group_name','english_name','person_type')->first();

            if(empty($student_info)){
                //取到后面的非0开始的工作作为一个数字，然后把前面的0去掉
                $temp=['1','2','3','4','5','6','7','8','9','0'];
                $hfyu='';
                for ($i=0;$i<strlen($identity_card);$i++){
                    //dump($identity_card[$i]);
                    if(in_array($identity_card[$i],$temp)){
                        //dump(2121);
                        $hfyu.=$identity_card[$i];
                    }
                }
                $hfyu=intval($hfyu);
                $student_where=[
                    ['id','=',$hfyu],
                    ['actual_name','=',$actual_name],
                    ['delete_flag','=','Y'],
                    ['person_type','=','student'],
                ];

                $student_info=DB::table('school_info')->where($student_where)
                    ->select('self_id as student_id','actual_name','group_code','group_name','english_name','person_type')->first();
            }

            /** 以上是验证小孩信息是不是有的地方**/


            if($student_info){
                $person_id=null;

                $where2=[
                    ['person_id','=',$student_info->student_id],
                    ['relation_person_id','=',$user_info->person_id],
                    ['delete_flag','=','Y'],
                    ['relation_type','=','direct'],
                ];

                $relation_type=DB::table('school_person_relation')->where($where2)->value('relation_type');

                if($relation_type){
                    $msg['code']=305;
                    $msg['msg']="学生和您已有关联！";
                    return $msg;
                }

                if($user_info->person_id){
                    $data['relation_person_id']     =$user_info->person_id;
                }else{
                    $data['relation_person_id']     =$person_id =generate_id('info_');
                }
                //建立关系
                $data['relation_person_name']   =$true_name;
                $data['relation_tel']           =$user_info->tel;
                $data['self_id']                =generate_id('relation_');
                $data['person_id']              =$student_info->student_id;
                $data['person_name']            =$student_info->actual_name;
                $data['relation_type']          ='direct';
                $data['group_code']             =$student_info->group_code;
                $data['group_name']             =$student_info->group_name;
                $data['create_time']            =$data['update_time']=$now_time;
                $data['total_user_id']          =$user_info->total_user_id;
                $id=DB::table('school_person_relation')->insert($data);

                    /*** 处理关系结束**/

                if($person_id){
                    //说明是新生成的，需要再school_info  中加一条数据
                    $seoi['self_id']            =$person_id;
                    $seoi['create_time']        =$seoi['update_time']   =$now_time;
                    $seoi['actual_name']        =$data['relation_person_name'];
                    $seoi['person_tel']         =$data['relation_tel'];
                    $seoi['person_type']        ='patriarch';
                    $seoi['group_code']         =$student_info->group_code;
                    $seoi['group_name']         =$student_info->group_name;
                    $seoi['total_user_id']      =$user_info->total_user_id;
                    DB::table('school_info')->insert($seoi);
                }
                $msg['code']=200;
                $msg['msg']="处理成功";
                return $msg;
            }else{
                $msg['code']=304;
                $msg['msg']="未查询到学生！";
                return $msg;
            }


            return $msg;

        }else{
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=null;

            foreach ($erro as $k => $v){
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }


     }

	 
	 /**
     * 拿取用户的小孩信息   /student/get_child
     * 前端传递必须参数：
     *  * 回调结果：200    查询成功
     *              500  授权失败，请重新授权11
     */
    public function get_child(Request $request){
        $user_info=$request->get('user_info');

        //DUMP($user_info);
		$time=time();

        if($user_info->total_user_id){
            $where=[
                ['a.person_type','=','student'],
                ['a.delete_flag','=','Y'],
				['b.total_user_id','=',$user_info->total_user_id],
				['b.delete_flag','=','Y'],
				['b.relation_type','=','direct']
            ];

            $student_info=DB::table('school_info as a')
                ->join('school_person_relation as b',function($join){
                    $join->on('a.self_id','=','b.person_id');
                }, null,null,'left')
                ->where($where)
                ->select(
                    'a.self_id as student_id',
                    'a.actual_name',
                    'a.english_name',
                    'a.grade_name',
                    'a.class_name'
                )
                ->orderBy('a.create_time','desc')
                ->get();

            //说明有线路，则走线路的情况
            $new_time=date('Y-m-d H:i:s',($time-(60*60*24*7)));
            foreach($student_info as $k => $v){
                /**目的 ：抓取请假的数据
                 *
                 **/
                $where2=[
                    ['person_id','=',$v->student_id],
                    ['create_time','>',$new_time],
                    ['delete_flag','=','Y']
                ];
                $v->schoolHoliday=SchoolHoliday::with(['SchoolHolidayPerson'=>function($query){
                    $query->where('delete_flag','=','Y');
                    $query->orderBy('holiday_date','desc');
                    $query->select('holiday_id','holiday_date','holiday_type','group_name');
                }])->where($where2)->limit(3)->orderBy('create_time','desc')->select('person_id','self_id','create_time','group_name')->get();

                if($v->schoolHoliday && $v->schoolHoliday->count() > 0){
                    foreach ($v->schoolHoliday as $kk=>$vv){
                        foreach ($vv->SchoolHolidayPerson as $kkk=>$vvv) {
                            $status= $this->UpDown($vvv->holiday_type);
                            $vvv->holiday_date=date('n.j',strtotime($vvv->holiday_date)).$status.'学';
                        }
                    }
                }else{
                    $v->schoolHoliday='';
                }

            }
//                dump($student_info->toArray());
             //   dd($student_info->toArray());
            $msg['code']=200;
            $msg['msg']="学生数据拉取成功";
            $msg['data']=$student_info;
        }else{
            $msg['code']=300;
            $msg['msg']="没有学生信息";
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
                $status='放';
                break;
            case 'UP':
                $status='上';
                break;
        }
        return $status;
    }

}
?>
