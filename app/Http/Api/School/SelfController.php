<?php
namespace App\Http\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Controllers\RedisController as RedisServer;
use App\Http\Api\School\SchoolController as SchoolServer;

use App\Models\School\SchoolCarInfo;
use App\Models\School\SchoolCarriage;
use Illuminate\Http\Request;
class SelfController extends Controller{
    private $prefixCar='car_';
    /**
     * 手动发车
     * pathUrl =>  /school/self
     * @param Request $request
     * @param RedisServer $redisServer
     * @return mixed
     */
    public function self(Request $request,RedisServer $redisServer,SchoolServer $schoolServer){
        $group_code		=$request->input('group_code');
        $now_time=date('Y-m-d  H:i:s',time());
        // $group_code='group_202009071059316859325743';
        $datetime       =$this->date_time();
        if(empty($group_code)){
            $msg['code']=300;
            $msg['msg']='学校code不能为空';
            return response()->json($msg)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        }

        /*** 根据时间段看下应该给用户拿那些数据 ，如果非发车时间段，不能发车**/
        if($datetime['status'] == 'OUT'){
            $msg['code']=302;
            $msg['msg']='非发车时间段，不能发车';
            return response()->json($msg)->setEncodingOptions(JSON_UNESCAPED_UNICODE);
        }
        // dd(13);
        $where=[
//            ['site_type','=',$datetime['status']],
            ['group_code','=',$group_code],
            ['delete_flag','=','Y'],
            ['use_flag','=','Y']
        ];
        //查询出要车辆的相关信息
        $schoolCarInfo=SchoolCarInfo::with(['schoolPathHasOne'=>function($query)use($datetime){
            $query->where('delete_flag','=','Y');
            $query->where('site_type','=',$datetime['status']);
            $query->orderBy('come_time','asc');
            $query->select('self_id','path_name','site_type','come_time','default_driver_id',
                'default_driver_name','default_driver_tel','default_care_id','default_care_name',
                'default_care_tel','group_name','group_code','default_car_id','default_car_brand');
        }])->with(['schoolHardware'=>function($query)use($datetime){
            $query->where('delete_flag','=','Y');
            $query->select('car_id','mac_address');
        }])
            ->where($where)
            ->select('self_id','car_number','remark','group_name')
            ->get();

        foreach($schoolCarInfo as $k => $v){
			if($v->schoolPathHasOne){
				$carriage_id2   =$this->prefixCar.$v->schoolPathHasOne->self_id.$datetime['dateStatus'];
                $aiui=SchoolCarriage::where('self_id','=',$carriage_id2)->value('carriage_status');
                if($aiui){
                    if($aiui == 1){
                        $path_info=$schoolServer->getPathInfo($v->schoolPathHasOne->self_id);
                        if($path_info->count()>0 &&  $path_info->schoolPathway->count()>0){
                            $dispatchCarStatus2=2;         //初始化为1未发车
                            $carriageInfo=$schoolServer->carriageInfo($path_info,$carriage_id2,$dispatchCarStatus2,$datetime,$v->schoolHardware->mac_address);
                            $redisServer->setex($carriage_id2,json_encode($carriageInfo,JSON_UNESCAPED_UNICODE),'carriage',25920);
                            $redisServer->set($v->schoolHardware->mac_address,$carriage_id2,'carriage');
                            $carriage['update_time']=$now_time;
                            $carriage['carriage_status']=2;

                            $where22['self_id']=$carriage_id2;
                            $where22['delete_flag']='Y';
                            SchoolCarriage::where($where22)->update($carriage);
                        }
                    }
                }else{
                    $path_info=$schoolServer->getPathInfo($v->schoolPathHasOne->self_id);
                    if($path_info->count()>0 &&  $path_info->schoolPathway->count()>0){
                        $dispatchCarStatus=1;         //初始化为1未发车
                        $carriageInfo=$schoolServer->carriageInfo($path_info,$carriage_id2,$dispatchCarStatus,$datetime,$v->schoolHardware->mac_address);
                        $redisServer->setex($carriage_id2,json_encode($carriageInfo,JSON_UNESCAPED_UNICODE),'carriage',25920);      ///1
                        /*** 因为这里是mac是空的，所以可以直接执行发车逻辑**/
                        //如果再里面就发车 
                        $schoolServer->saveInfo($carriageInfo,$redisServer,$v->schoolHardware->mac_address);
                    }
                }
			}
        }
        // dd($schoolCarInfo->toArray()); 
        $msg['code']=200;
        $msg['msg']='能发车';
        return response()->json($msg)->setEncodingOptions(JSON_UNESCAPED_UNICODE);

    }
    /**做一个返回时间值**/
    private function date_time(){
        $datetime            =date('Y-m-d H:i:s',time());
        list($date,$time)    =explode(' ',$datetime);

        //上学时间段
        $upStartTime='01:00:00';
        $upEndTime='12:00:00';

        //放学时间段
        $downStartTime='12:01:00';
        $downEndTime='23:59:59';

        if($time>=$upStartTime &&  $time<=$upEndTime){
            $status='UP';
        }else if($time>=$downStartTime &&  $time<=$downEndTime){
            $status='DOWN';
        }else{
            $status='OUT';
        }

        return ['dateStatus'=>$status.$date,'status'=>$status,'date'=>$date];

    }
}
?>
