<?php
namespace App\Http\Api\Tms;
use App\Models\Tms\TmsWarehouse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;


class WarehouseController extends Controller{

    /***    仓库列表      /api/warehouse/warehousePage
     */
    public function warehousePage(Request $request){
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
        $total_user_id = $user_info->total_user_id;
        $tms_warehouse_type = array_column(config('tms.tms_warehouse_type'),'name','key');

        /**接收数据*/
        $num      = $request->input('num')??10;
        $page     = $request->input('page')??1;
        $listrows = $num;
        $firstrow = ($page-1)*$listrows;

        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code],
        ];

        $where = get_list_where($search);
        $select = ['self_id','warehouse_name','pro','city','area','address','all_address','areanumber','price','company_name','contact','tel','create_time','update_time','delete_flag','use_flag',
            'wtype','picture','remark','license','rent_type','store_price','area_price','handle_price','property_price','sorting_price','describe','group_code','group_name'];
        $data['info'] = TmsWarehouse::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->rent_type_show = $tms_warehouse_type[$v->rent_type] ?? null;
            $v->price = number_format($v->price/100,2);
            $v->store_price = number_format($v->store_price/100,2);
            $v->area_price = number_format($v->area_price/100,2);
            $v->handle_price = number_format($v->handle_price/100,2);
            $v->property_price = number_format($v->property_price/100,2);
            $v->sorting_price = number_format($v->sorting_price/100,2);

        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;

        return $msg;
    }

    /***    新建仓库      /api/warehouse/createWarehouse
     */
    public function createWarehouse(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//        $self_id = 'ware_20123156415615151152';
        $tms_warehouse_type     = array_column(config('tms.tms_warehouse_type'),'name','key');
        $data['tms_warehouse_type']     = config('tms.tms_warehouse_type');
        $where = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select = ['self_id','warehouse_name','pro','city','area','address','all_address','areanumber','price','company_name','contact','tel','create_time','update_time','delete_flag','use_flag',
            'wtype','picture','remark','license','rent_type','store_price','area_price','handle_price','property_price','sorting_price','describe','group_code','group_name'];

        $data['info'] = TmsWarehouse::where($where)->select($select)->first();
        if ($data['info']){
            $data['info']->rent_type_show = $tms_warehouse_type[$data['info']->rent_type] ?? null;
            $data['info']->price = $data['info']->price/100;
            $data['info']->store_price = $data['info']->store_price/100;
            $data['info']->area_price = $data['info']->area_price/100;
            $data['info']->handle_price = $data['info']->handle_price/100;
            $data['info']->property_price = $data['info']->property_price/100;
            $data['info']->sorting_price = $data['info']->sorting_price/100;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }


    /*
    **    新建仓库数据提交      /api/warehouse/addWarehouse
    */
    public function addWarehouse(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $total_user_id = $user_info->total_user_id;
//        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();

        /** 接收数据*/
        $self_id           = $request->input('self_id');
        $warehouse_name    = $request->input('warehouse_name');//仓库名称
        $city              = $request->input('city');//市
        $pro               = $request->input('pro');//省
        $area              = $request->input('area');//区
        $address           = $request->input('address');//详细地址
        $all_address       = $request->input('all_address');//完整详细地址
        $describe          = $request->input('describe');//仓库描述
        $contact           = $request->input('contact');//联系人
        $tel               = $request->input('tel');//电话
        $areanumber        = $request->input('areanumber');//面积
        $wtype             = $request->input('wtype');//仓储类型 1仓储型 2中转型
        $remark            = $request->input('remark');//备注
        $price             = $request->input('price');//备注
        $store_price       = $request->input('store_price');//存储费
        $area_price        = $request->input('area_price');//租金
        $handle_price      = $request->input('handle_price');//操作费
        $property_price    = $request->input('property_price');//物业费
        $sorting_price     = $request->input('sorting_price');//分拣费
        $group_code        = $request->input('group_code');//分拣费
        $picture           = $request->input('picture');//仓库图片

        /*** 虚拟数据
        //      $input['self_id']            =$self_id        ='good_202007011336328472133661';
             $input['warehouse_name']        =$warehouse_name ='青浦仓';
             $input['city']                  =$city           ='上海市';
             $input['pro']                   =$pro            ='上海市';
             $input['area']                  =$area           ='青浦区';
             $input['address']               =$address        ='青浦大道';
             $input['all_address']           =$all_address    ='上海市青浦区青浦大道';
             $input['describe']              =$describe       ='123';
             $input['contact']               =$contact        ='张三';
             $input['tel']                   =$tel            ='15656451215';
             $input['areanumber']            =$areanumber     ='2000';
             $input['wtype']                 =$wtype          ='storage';
             $input['store_price']           =$store_price    ='20';
             $input['area_price']            =$area_price     ='2';
             $input['handle_price']          =$handle_price   ='3';
             $input['property_price']        =$property_price ='3';
             $input['sorting_price']         =$sorting_price  ='2';
             $input['group_code']            =$group_code     ='group_';
             $input['picture']               =$picture        ='';

         **/
        $rules = [
            'warehouse_name'=>'required',
            'address'=>'required',
            'areanumber'=>'required',
            'contact'=>'required',
            'tel'=>'required',
            'store_price'=>'required',
        ];
        $message = [
            'warehouse_name.required'=>'请填写仓库名称',
            'address.required'=>'请填写地址',
            'areanumber.required'=>'请填写仓库面积',
            'contact.required'=>'请填写联系人姓名',
            'tel.required'=>'请填写联系人电话',
            'control.required'=>'请填写仓储费',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $data['warehouse_name']    = $warehouse_name;
            $data['city']              = $city;
            $data['pro']               = $pro;
            $data['area']              = $area;
            $data['address']           = $address;
            $data['all_address']       = $all_address;
            $data['describe']          = $describe;
            $data['contact']           = $contact;
            $data['tel']               = $tel;
            $data['areanumber']        = $areanumber;
            $data['wtype']             = $wtype;
            $data['remark']            = $remark;
            $data['price']             = $price;
            $data['store_price']       = $store_price*100;
            $data['area_price']        = $area_price*100;
            $data['handle_price']      = $handle_price*100;
            $data['property_price']    = $property_price*100;
            $data['sorting_price']     = $sorting_price*100;
            $data['group_code']        = $group_code;
            $data['picture']           = $picture;

            $wheres['self_id'] = $self_id;
            $old_info = TmsWarehouse::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                $id = TmsWarehouse::where($wheres)->update($data);

            }else{
                $data['self_id']          = generate_id('ware_');
                $data['group_code']       = $group_code;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsWarehouse::insert($data);

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
    **    仓库禁用/启用      /api/warehouse/warehouseUseFlag
    */
    public function warehouseUseFlag(Request $request,Status $status){
        $now_time = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_warehouse';
        $medol_name  = 'TmsWarehouse';
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
    **    仓库删除      /api/warehouse/warehouseDelFlag
    */
    public function warehouseDelFlag(Request $request,Status $status){
        $now_time     = date('Y-m-d H:i:s',time());
        $operationing =  $request->get('operationing');//接收中间件产生的参数
        $table_name   = 'tms_warehouse';
        $medol_name   = 'TmsWarehouse';
        $self_id = $request->input('self_id');
        $flag    = 'delFlag';
        // $self_id = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }

    /**
     *    车辆详情     /api/warehouse/details
     */
    public function  details(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_car';
        $select = ['self_id','warehouse_name','pro','city','area','address','all_address','areanumber','price','company_name','contact','tel','create_time','update_time','delete_flag','use_flag',
            'wtype','picture','remark','license','rent_type','store_price','area_price','handle_price','property_price','sorting_price','describe','group_code','group_name'];
        // $self_id = 'car_202101111749191839630920';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $info->rent_type_show = $tms_warehouse_type[$info->rent_type] ?? null;
            $info->price = number_format($info->price/100,2);
            $info->store_price = number_format($info->store_price/100,2);
            $info->area_price = number_format($info->area_price/100,2);
            $info->handle_price = number_format($info->handle_price/100,2);
            $info->property_price = number_format($info->property_price/100,2);
            $info->sorting_price = number_format($info->sorting_price/100,2);
            $image_info = [];
            if ($info->picture){
                $image = json_decode($info->picture,true);
                foreach ($image as $key => $value){
                    $image_info[] = img_for($value,'more');
                }
            }
            $info->picture = $image_info;
            $msg['code']  = 200;
            $msg['msg']   = "数据拉取成功";
            $msg['data']  = $info;
            return $msg;
        }else{
            $msg['code'] = 300;
            $msg['msg']  = "没有查询到数据";
            return $msg;
        }
    }

}
?>
