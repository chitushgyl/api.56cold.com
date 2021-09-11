<?php
namespace App\Http\Api\Tms;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\Tms\TmsCar;

use App\Models\Tms\TmsCarType;


class CarController extends Controller{

    /***    车辆列表      /api/car/carPage
     */
    public function carPage(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        $total_user_id = $user_info->total_user_id;
        $tms_car_possess_type = array_column(config('tms.tms_car_possess_type'),'name','key');
        $tms_control_type     = array_column(config('tms.tms_control_type'),'name','key');
        /**接收数据*/
        $num      = $request->input('num')??10;
        $page     = $request->input('page')??1;
        $listrows = $num;
        $firstrow = ($page-1)*$listrows;

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'total_user_id','value'=>$total_user_id],
        ];

        $where = get_list_where($search);
        $select = ['self_id','car_brand','car_number','car_possess','weight','volam','control','car_type_name','use_flag','contacts','tel','medallion','license'];
        $data['info'] = TmsCar::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->car_possess_show =  $tms_car_possess_type[$v->car_possess] ?? null;
            $v->tms_control_type_show = $tms_control_type[$v->control] ?? null;


        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;

        return $msg;
    }

    /***    新建车辆      /api/car/createCar
     */
    public function createCar(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'car_20210313180835367958101';
        $tms_car_possess_type = array_column(config('tms.tms_car_possess_type'),'name','key');
        $tms_control_type     = array_column(config('tms.tms_control_type'),'name','key');
        $data['tms_car_possess_type'] = config('tms.tms_car_possess_type');
        $data['tms_control_type']     = config('tms.tms_control_type');
        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','car_brand','car_number','car_type_id','group_code','car_possess','weight','volam','control','license','medallion','board_time','contacts','tel'];
        $select1 = ['self_id','parame_name'];
        $data['info'] = TmsCar::with(['tmsCarType'=>function($query)use($select1){
            $query->select($select1);
        }])->where($where)->select($select)->first();
        if ($data['info']){
            $data['info']->car_possess_show =  $tms_car_possess_type[$data['info']->car_possess] ?? null;
            $data['info']->tms_control_type_show = $tms_control_type[$data['info']->control] ?? null;
            $data['info']->license = img_for($data['info']->license,'more');
            $data['info']->medallion = img_for($data['info']->medallion,'more');
            $data['info']->car_type  = $data['info']->tmsCarType->parame_name;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }


    /*
    **    新建车辆数据提交      /api/car/addCar
    */
    public function addCar(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
//        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $self_id       = $request->input('self_id');
        $car_number    = $request->input('car_number');
        $car_possess   = $request->input('car_possess');
        $remark        = $request->input('remark');
        $weight        = $request->input('weight');
        $volam         = $request->input('volam');
        $control       = $request->input('control');
        $board_time       = $request->input('board_time');
        $car_type_id   = $request->input('car_type_id');
        $contacts   = $request->input('contacts');
        $tel   = $request->input('tel');
        $license   = $request->input('license');
        $medallion   = $request->input('medallion');

        /*** 虚拟数据
        //      $input['self_id']           =$self_id='good_202007011336328472133661';
        $input['car_number']  = $car_number  = '沪A45612';
        $input['car_possess'] = $car_possess = 'oneself';
        $input['remark']      = $remark      = 'GIUIUIT';
        $input['weight']      = $weight      = '50000';
        $input['volam']       = $volam       = '12';
        $input['control']     = $control     = 'freeze';
        $input['car_type_id'] = $car_type_id = 'type_202101111722250922770671';
        $input['contacts'] = $contacts = '李白';
        $input['tel'] = $tel = '15648948945';
        $input['license'] = $license = [];
        $input['medallion'] = $medallion = [];
         **/
        $rules = [
            'car_number'=>'required',
            'car_type_id'=>'required',
            'car_possess'=>'required',
            'control'=>'required',
            'contacts'=>'required',
            'tel'=>'required',
        ];
        $message = [
            'car_number.required'=>'车牌号必须填写',
            'car_type_id.required'=>'车型必须选择',
            'car_possess.required'=>'车辆属性必须选择',
            'control.required'=>'温控类型必须选择',
            'contacts.required'=>'请填写司机姓名',
            'tel.required'=>'请填写司机电话',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where_car_type = [
                ['delete_flag','=','Y'],
                ['use_flag','=','Y'],
                ['self_id','=',$car_type_id],
            ];
            $info2 = TmsCarType::where($where_car_type)->select('self_id','parame_name')->first();

            if (empty($info2)) {
                $msg['code'] = 302;
                $msg['msg']  = '车型不存在';
                return $msg;
            }

            if($self_id){
                $name_where = [
                    ['self_id','!=',$self_id],
                    ['car_number','=',$car_number],
                    ['delete_flag','=','Y'],
                    ['total_user_id','=',$total_user_id]
                ];
            }else{
                $name_where = [
                    ['car_number','=',$car_number],
                    ['delete_flag','=','Y'],
                    ['total_user_id','=',$total_user_id]
                ];
            }

            $carnumber = TmsCar::where($name_where)->count();

            if ($carnumber > 0) {
                $msg['code'] = 308;
                $msg['msg']  = '车牌号已存在，请重新填写';
                return $msg;
            }

            $data['car_number']    = $car_number;
            $data['car_possess']   = $car_possess;
            $data['remark']        = $remark;
            $data['weight']        = $weight;
            $data['volam']         = $volam;
            $data['control']       = $control;
            $data['board_time']       = $board_time;
            $data['car_type_id']   = $info2->self_id;
            $data['car_type_name'] = $info2->parame_name;
            $data['contacts'] = $contacts;
            $data['tel'] = $tel;
            $data['license'] = img_for($license,'in');
            $data['medallion'] = img_for($medallion,'in');

            $wheres['self_id'] = $self_id;
            $old_info = TmsCar::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                $id = TmsCar::where($wheres)->update($data);

            }else{
                $data['self_id']          = generate_id('car_');
                $data['total_user_id']    = $total_user_id;
//                $data['create_user_id']   = $total_user_id;
//                $data['create_user_name'] = $token_name;
                $data['create_time']      = $data['update_time'] = $now_time;

                $id = TmsCar::insert($data);

            }

            if($id){
                $msg['code'] = 200;
                $msg['msg']  = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg']  = "操作失败";
                return $msg;
            }
        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            return $msg;
        }

    }

    /*
    **    车辆禁用/启用      /api/car/carUseFlag
    */
    public function carUseFlag(Request $request,Status $status){
        $now_time = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_car';
        $medol_name  = 'TmsCar';
        $self_id     = $request->input('self_id');
        $flag        = 'useFlag';
        // $self_id     = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /*
    **    车辆删除      /api/car/carDelFlag
    */
    public function carDelFlag(Request $request,Status $status){
        $now_time     = date('Y-m-d H:i:s',time());
        $operationing =  $request->get('operationing');//接收中间件产生的参数
        $table_name   = 'tms_car';
        $medol_name   = 'TmsCar';
        $self_id = $request->input('self_id');
        $flag    = 'delFlag';
        // $self_id = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }

    /*
    **    车辆详情     /api/car/details
    */
    public function  details(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_car';
        $select = ['self_id','car_brand','car_number','car_nuclear','car_possess','remark','weight','weight','volam','control','check_time','license','medallion','board_time','car_type_id','car_type_name','contacts','tel'];
        // $self_id = 'car_202101111749191839630920';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $tms_control_type     = array_column(config('tms.tms_control_type'),'name','key');
            $tms_car_possess_type = array_column(config('tms.tms_car_possess_type'),'name','key');
            if ($info->car_possess && !empty($tms_car_possess_type[$info->car_possess])) {
                $info->car_possess = $tms_car_possess_type[$info->car_possess];
            }
            if ($info->control && !empty($tms_control_type[$info->control])) {
                $info->control = $tms_control_type[$info->control];
            }
            $data['info'] = $info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $data;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

    /***    获取当前用户的所有车辆     /api/car/getCar
     */
    public function  getCar(Request $request){
        $user_info    = $request->get('user_info');//获取中间件中的参数
        $tms_control_type     = array_column(config('tms.tms_control_type'),'name','key');
//        $user_info->total_user_id = 'user_202101222136229817204956';
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['total_user_id','=',$user_info->total_user_id]
        ];
        $select=['self_id','car_number','car_type_name','control','contacts','tel'];

        $data['info']=TmsCar::where($where)->select($select)->get();
        foreach ($data['info'] as $key =>$value){
            $data['info'][$key]['temperture'] = $tms_control_type[$value['control']];
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        return $msg;
    }

    /***    拿去车辆类型数据     /api/car/getType
     */
    public function  getType(Request $request){
        $where=[
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
        ];
        $select=['self_id','parame_name','dimensions','allweight','allvolume','img'];
        //dd($where);
        $data['info']=TmsCarType::where($where)->select($select)->get();
        if ($data['info']){
            foreach ($data['info'] as $key =>$value){
                $data['info'][$key]['img'] = img_for($value['img'],'no_json');
                $data['info'][$key]['select_show'] = $value['parame_name'].'*核载：'.($value['allweight']/100).'吨/'.$value['allvolume'].'方';
                $data['info'][$key]['allweight'] = ($value['allweight']/1000).'吨';
                $data['info'][$key]['allvolume'] = $value['allvolume'].'方';
                $data['info'][$key]['dimensions'] = $value['dimensions'].'米';
            }
        }
//        dd($data['info']->toArray());
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;

        //dd($msg);
        return $msg;
    }
}
?>
