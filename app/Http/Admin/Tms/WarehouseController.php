<?php
namespace App\Http\Admin\Tms;
use App\Http\Controllers\CommonController;
use App\Models\SysAddress;
use App\Models\Tms\TmsAddressContact;
use App\Models\Tms\TmsGroup;
use App\Models\Tms\TmsWarehouse;
use App\Tools\Import;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use Maatwebsite\Excel\Facades\Excel;


class WarehouseController extends CommonController {
    /**
     *仓库列表头部      /tms/warehouse/warehouseList
     * */
    public function  warehouseList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $abc='仓库';
        $data['import_info']    =[
            'import_text'=>'下载'.$abc.'导入示例文件',
            'import_color'=>'#FC5854',
            'import_url'=>config('aliyun.oss.url').'execl/2020-07-02/TMS仓库导入文件范本.xlsx',
        ];
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    仓库列表      /tms/warehouse/warehousePage
     */
    public function warehousePage(Request $request){
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数
        $tms_warehouse_type = array_column(config('tms.tms_warehouse_type'),'name','key');

        /**接收数据*/
        $num      = $request->input('num')??10;
        $page     = $request->input('page')??1;
        $wtype    = $request->input('wtype');
        $pro      = $request->input('pro');
        $city     = $request->input('city');
        $area     = $request->input('area');
        $group_code     = $request->input('group_code');
        $area_price     = $request->input('area_price');
        $warehouse_name     = $request->input('warehouse_name');
        $use_flag     = $request->input('use_flag');
        $listrows = $num;
        $firstrow = ($page-1)*$listrows;
        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'=','name'=>'wtype','value'=>$wtype],
            ['type'=>'=','name'=>'warehouse_name','value'=>$warehouse_name],
            ['type'=>'=','name'=>'use_flag','value'=>$use_flag],
        ];
        if ($pro){
            $search[] = ['type'=>'like','name'=>'pro','value'=>$pro];
        }
        if ($city) {
            $search[] = ['type'=>'like','name'=>'city','value'=>$city];
        }
        if ($area) {
            $search[] = ['type'=>'like','name'=>'area','value'=>$area];
        }
        if ($warehouse_name) {
            $search[] = ['type'=>'like','name'=>'warehouse_name','value'=>$warehouse_name];
        }
        if ($area) {
            $search[] = ['type'=>'=','name'=>'use_flag','value'=>$use_flag];
        }
        $where = get_list_where($search);
        $select = ['self_id','warehouse_name','pro','city','area','address','all_address','areanumber','price','company_name','contact','tel','create_time','update_time','delete_flag','use_flag',
            'wtype','picture','remark','license','rent_type','store_price','area_price','handle_price','property_price','sorting_price','describe','group_code','group_name'];
        $select2 = ['self_id','group_name'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total']=TmsWarehouse::where($where)->count(); //总的数据量
                $data['info'] = TmsWarehouse::with(['SystemGroup' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->where($where)
                    ->offset($firstrow)
                    ->limit($listrows);

                if ($area_price == 1){
                    $data['info'] = $data['info']->orderBy('area_price','asc');
                }else{
                    $data['info'] = $data['info']->orderBy('area_price','desc');
                }
                $data['info'] =$data['info']
                    ->orderBy('create_time', 'desc')
                    ->select($select)
                    ->get();
                $data['group_show']='Y';
                break;

            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total']=TmsWarehouse::where($where)->count(); //总的数据量
                $data['info'] = TmsWarehouse::with(['SystemGroup' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->where($where)
                    ->offset($firstrow)
                    ->limit($listrows);

                if ($area_price == 1){
                    $data['info'] = $data['info']->orderBy('area_price','asc');
                }else{
                    $data['info'] = $data['info']->orderBy('area_price','desc');
                }
                $data['info'] =$data['info']
                    ->orderBy('create_time', 'desc')
                    ->select($select)
                    ->get();
                $data['group_show']='N';
                break;

            case 'more':
                $data['total']=TmsWarehouse::where($where)->whereIn('group_code',$group_info['group_code'])->count(); //总的数据量
                $data['info'] = TmsWarehouse::with(['SystemGroup' => function($query) use($select2){
                    $query->select($select2);
                }])
                    ->where($where)
                    ->offset($firstrow)
                    ->limit($listrows);
                if ($area_price == 1){
                    $data['info'] = $data['info']->orderBy('area_price','asc');
                }else{
                    $data['info'] = $data['info']->orderBy('area_price','desc');
                }
                $data['info'] =$data['info']
                    ->orderBy('create_time', 'desc')
                    ->select($select)
                    ->get();
                $data['group_show']='Y';
                break;
        }
        foreach ($data['info'] as $k=>$v) {
            $v->button_info=$button_info;
            $v->rent_type_show = $tms_warehouse_type[$v->wtype] ?? null;
            $v->price = number_format($v->price/100,2);
            $v->store_price = number_format($v->store_price/100,2);
            $v->area_price = number_format($v->area_price/100,2);
            $v->handle_price = number_format($v->handle_price/100,2);
            $v->property_price = number_format($v->property_price/100,2);
            $v->sorting_price = number_format($v->sorting_price/100,2);
            $v->picture_show = img_for($v->picture,'more');
            if ($v->SystemGroup){
                $v->group_name_show = $v->SystemGroup->group_name;
            }
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;

        return $msg;
    }

    /***    新建仓库      /tms/warehouse/createWarehouse
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
            $data['info']->rent_type_show = $tms_warehouse_type[$data['info']->wtype] ?? null;
            $data['info']->price = $data['info']->price/100;
            $data['info']->store_price = $data['info']->store_price/100;
            $data['info']->area_price = $data['info']->area_price/100;
            $data['info']->handle_price = $data['info']->handle_price/100;
            $data['info']->property_price = $data['info']->property_price/100;
            $data['info']->sorting_price = $data['info']->sorting_price/100;
            $data['info']->picture_show = img_for($data['info']->picture,'more');
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
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $table_name     ='tms_warehouse';
//        $token_name    = $user_info->token_name;
        $now_time      = date('Y-m-d H:i:s',time());
        $input         = $request->all();
        $operationing->access_cause     ='创建/修改仓库';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='add';
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
        $wtype             = $request->input('wtype');//仓储类型 1仓储型 storage 2中转型 transfer
        $remark            = $request->input('remark');//备注
        $price             = $request->input('price');//备注
        $store_price       = $request->input('store_price');//存储费
        $area_price        = $request->input('area_price');//平方价 租金
        $handle_price      = $request->input('handle_price');//操作费
        $property_price    = $request->input('property_price');//物业费
        $sorting_price     = $request->input('sorting_price');//分拣费
        $other_price       = $request->input('other_price');//其他价格
        $group_code        = $request->input('group_code');//
        $picture           = $request->input('picture');//仓库图片
        $rent_type         = $request->input('rent_type');//出租方式 1整租 2分租 3托管 4其他

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
             $input['other_price']           =$other_price    ='';
             $input['rent_type']             =$rent_type      ='';

         **/
        $rules = [
            'warehouse_name'=>'required',
            'address'=>'required',
            'areanumber'=>'required',
            'contact'=>'required',
            'tel'=>'required',
//            'store_price'=>'required',
        ];
        $message = [
            'warehouse_name.required'=>'请填写仓库名称',
            'address.required'=>'请填写地址',
            'areanumber.required'=>'请填写仓库面积',
            'contact.required'=>'请填写联系人姓名',
            'tel.required'=>'请填写联系人电话',
//            'control.required'=>'请填写仓储费',
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
            $data['other_price']       = $other_price*100;
            $data['group_code']        = $user_info->group_code;
            $data['picture']           = img_for($picture,'in');
            $data['rent_type']         = $rent_type;

            $wheres['self_id'] = $self_id;
            $old_info = TmsWarehouse::where($wheres)->first();

            if($old_info){
                $data['update_time'] = $now_time;
                $id = TmsWarehouse::where($wheres)->update($data);
                $operationing->access_cause='修改仓库';
                $operationing->operation_type='update';
            }else{
                $data['self_id']          = generate_id('ware_');
                $data['group_code']       = $group_code;
                $data['create_time']      = $data['update_time'] = $now_time;
                $id = TmsWarehouse::insert($data);
                $operationing->access_cause='新建仓库';
                $operationing->operation_type='create';

            }

            $operationing->table_id=$old_info?$self_id:$data['self_id'];
            $operationing->old_info=$old_info;
            $operationing->new_info=$data;

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
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $table_name  = 'tms_warehouse';
        $medol_name  = 'TmsWarehouse';
        $self_id     = $request->input('self_id');
        $flag        = 'useFlag';
        // $self_id     = 'car_202101111723422044395481';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);

        $operationing->access_cause='启用/禁用';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

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

        $operationing->access_cause='删除';
        $operationing->table=$table_name;
        $operationing->table_id=$self_id;
        $operationing->now_time=$now_time;
        $operationing->old_info=$status_info['old_info'];
        $operationing->new_info=$status_info['new_info'];
        $operationing->operation_type=$flag;

        $msg['code']=$status_info['code'];
        $msg['msg']=$status_info['msg'];
        $msg['data']=$status_info['new_info'];
        return $msg;
    }

    /**
     *    仓库详情     /api/warehouse/details
     */
    public function  details(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_warehouse';
        $select = ['self_id','warehouse_name','pro','city','area','address','all_address','areanumber','price','company_name','contact','tel','create_time','update_time','delete_flag','use_flag',
            'wtype','picture','remark','license','rent_type','store_price','area_price','handle_price','property_price','sorting_price','describe','group_code','group_name'];
//         $self_id = 'ware_20210820131302105995787';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
            $tms_warehouse_type     = array_column(config('tms.tms_warehouse_type'),'name','key');
            $info->rent_type_show = $tms_warehouse_type[$info->wtype] ?? null;
            $info->price = number_format($info->price/100,2);
            $info->store_price = number_format($info->store_price/100,2);
            $info->area_price = number_format($info->area_price/100,2);
            $info->handle_price = number_format($info->handle_price/100,2);
            $info->property_price = number_format($info->property_price/100,2);
            $info->sorting_price = number_format($info->sorting_price/100,2);
            $info->picture_show = img_for($info->picture,'more');
            $warehouse_list = [];
            $warehouse_info1['name'] = '出租面积';
            $warehouse_info1['value'] = $info->areanumber.'㎡';
            $warehouse_info2['name'] = '仓库类型' ;
            $warehouse_info2['value'] = $info->rent_type_show;
            $warehouse_info3['name'] = '物业费';
            $warehouse_info3['value'] = $info->property_price.'元/吨';
            $warehouse_info4['name'] = '存储费' ;
            $warehouse_info4['value'] = $info->store_price.'元/吨';
            $warehouse_info5['name'] = '操作费';
            $warehouse_info5['value'] = $info->handle_price.'元/㎡';
            $warehouse_info6['name'] = '分拣费';
            $warehouse_info6['value'] = $info->sorting_price.'元/件';

            $warehouse_list[] = $warehouse_info1;
            $warehouse_list[] = $warehouse_info2;
            if ($info->property_price > 0){
                $warehouse_list[] = $warehouse_info3;
            }
            if ($info->store_price >0){
                $warehouse_list[] = $warehouse_info4;
            }
            if ($info->handle_price>0){
                $warehouse_list[] = $warehouse_info5;
            }
            if ($info->sorting_price>0){
                $warehouse_list[] = $warehouse_info6;
            }
            $info->warehouse_info_show = $warehouse_list;
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

    /**
     * 导入仓库 /tms/warehouse/import
     * */
    public function import(Request $request){
        $table_name         ='tms_warehouse';
        $now_time           = date('Y-m-d H:i:s', time());

        $operationing       = $request->get('operationing');//接收中间件产生的参数
        $operationing->access_cause     ='导入创建仓库';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;
        $operationing->type             ='import';

        $user_info          = $request->get('user_info');//接收中间件产生的参数


        /** 接收数据*/
        $input              =$request->all();
        $importurl          =$request->input('importurl');
        $company_id         =$request->input('company_id');
        $file_id            =$request->input('file_id');
        //
        /****虚拟数据
        $input['importurl']     =$importurl="uploads/import/TMS仓库导入文件范本.xlsx";
        $input['company_id']       =$company_id='group_202012291153523141320375';
         ***/
        $rules = [
            'company_id' => 'required',
            'importurl' => 'required',
        ];
        $message = [
            'company_id.required' => '请选择业务公司',
            'importurl.required' => '请上传文件',
        ];
        $validator = Validator::make($input, $rules, $message);
        if ($validator->passes()) {

            /**发起二次效验，1效验文件是不是存在， 2效验文件中是不是有数据 3,本身数据是不是重复！！！* */
            if (!file_exists($importurl)) {
                $msg['code'] = 301;
                $msg['msg'] = '文件不存在';
                return $msg;
            }

            $res = Excel::toArray((new Import),$importurl);
            //dump($res);
            $info_check=[];
            if(array_key_exists('0', $res)){
                $info_check=$res[0];
            }

            //dump($info_check);

            /**  定义一个数组，需要的数据和必须填写的项目
            键 是EXECL顶部文字，
             * 第一个位置是不是必填项目    Y为必填，N为不必须，
             * 第二个位置是不是允许重复，  Y为允许重复，N为不允许重复
             * 第三个位置为长度判断
             * 第四个位置为数据库的对应字段
             */
            $shuzu=[
                '仓库名称' =>['Y','Y','64','warehouse_name'],
                '省份' =>['Y','Y','64','pro'],
                '城市' =>['Y','Y','64','city'],
                '区县' =>['Y','Y','64','area'],
                '详细地址' =>['Y','Y','64','address'],
                '联系人' =>['Y','Y','64','contact'],
                '联系电话' =>['Y','Y','64','tel'],
                '面积' =>['Y','Y','64','areanumber'],
                '仓库类型' =>['Y','Y','64','wtype'],
                '存储费' =>['N','Y','64','store_price'],
                '租金' =>['N','Y','64','area_price'],
                '操作费' =>['N','Y','64','handle_price'],
                '物业费' =>['N','Y','64','property_price'],
                '分拣费' =>['N','Y','64','sorting_price'],
                '仓库描述' =>['N','Y','64','describe'],
                '备注' =>['N','Y','64','remark'],
            ];
            $ret=arr_check($shuzu,$info_check);


            // dump($ret);
            if($ret['cando'] == 'N'){
                $msg['code'] = 304;
                $msg['msg'] = $ret['msg'];
                return $msg;
            }

            $info_wait=$ret['new_array'];
            $where_check=[
                ['delete_flag','=','Y'],
                ['self_id','=',$company_id],
            ];

            $info= TmsGroup::where($where_check)->select('self_id','company_name','group_code','group_name')->first();
            // dd($info->toArray());
            if(empty($info)){
                $msg['code'] = 305;
                $msg['msg'] = '业务公司不存在';
                return $msg;
            }

//            dd($info);
            /** 二次效验结束**/

            $datalist=[];       //初始化数组为空
            $cando='Y';         //错误数据的标记
            $strs='';           //错误提示的信息拼接  当有错误信息的时候，将$cando设定为N，就是不允许执行数据库操作
            $abcd=0;            //初始化为0     当有错误则加1，页面显示的错误条数不能超过$errorNum 防止页面显示不全1
            $errorNum=50;       //控制错误数据的条数
            $a=2;

            // dump($info_wait);
            /** 现在开始处理$car***/
            foreach($info_wait as $k => $v){

//                $where_address=[
//                    ['name','=',$v['qu_name']],
//                    ['level','=',3],
//                ];
//
//                $where_address2=[
//                    ['name','=',$v['shi_name']],
//                    ['level','=',2],
//                ];
//                $where_address3=[
//                    ['name','=',$v['sheng_name']],
//                    ['level','=',1],
//                ];
//
//                $selectMenu=['id','name','parent_id'];
//                $address_info=SysAddress::with(['sysAddress' => function($query)use($selectMenu,$where_address2,$where_address3) {
//                    $query->where($where_address2);
//                    $query->select($selectMenu);
//                    $query->with(['sysAddress' => function($query)use($selectMenu,$where_address3) {
//                        $query->where($where_address3);
//                        $query->select($selectMenu);
//                    }]);
//                }])->where($where_address)->select($selectMenu)->first();
//
//                if(empty($address_info)){
//                    if($abcd<$errorNum){
//                        $strs .= '数据中的第'.$a."行区不存在".'</br>';
//                        $cando='N';
//                        $abcd++;
//                    }
//                }else{
//                    if(empty($address_info->sysAddress)){
//                        if($abcd<$errorNum){
//                            $strs .= '数据中的第'.$a."行市不存在".'</br>';
//                            $cando='N';
//                            $abcd++;
//                        }
//                    }else{
//                        if(empty($address_info->sysAddress->sysAddress)){
//                            if($abcd<$errorNum){
//                                $strs .= '数据中的第'.$a."行省不存在".'</br>';
//                                $cando='N';
//                                $abcd++;
//                            }
//                        }
//                    }
//                }
//                $location = bd_location(2,$v['sheng_name'],$v['shi_name'],$v['qu_name'],$v['address']);
                // dump($cando);
                $list=[];
                if($cando =='Y'){
                    $list['self_id']            =generate_id('warehouse_');
                    $list['pro']                = $v['pro'];
                    $list['city']               = $v['city'];
                    $list['area']               = $v['area'];
                    $list['address']            = $v['address'];
                    $list['all_address']        = $v['pro'].$v['city'].$v['area'].$v['address'];
                    $list['contact']            = $v['contact'];
                    $list['tel']                = $v['tel'];
                    $list['group_code']         = $info->group_code;
                    $list['group_name']         = $info->group_name;
                    $list['create_time']        = $list['update_time']=$now_time;
                    $list['areanumber']         = $v['areanumber'];
                    $list['wtype']              = $v['wtype'];
                    $list['store_price']        = $v['store_price'];
                    $list['area_price']         = $v['area_price'];
                    $list['handle_price']       = $v['handle_price'];
                    $list['property_price']     = $v['property_price'];
                    $list['sorting_price']      = $v['sorting_price'];
                    $list['describe']           = $v['describe'];
                    $list['remark']             = $v['remark'];
                    $datalist[]=$list;
                }
                $a++;

            }

            $operationing->new_info=$datalist;

            //dump($operationing);
            // dd($datalist);

            if($cando == 'N'){
                $msg['code'] = 306;
                $msg['msg'] = $strs;
                return $msg;
            }
            $count=count($datalist);
            $id= TmsWarehouse::insert($datalist);

            if($id){
                $msg['code']=200;
                /** 告诉用户，你一共导入了多少条数据，其中比如插入了多少条，修改了多少条！！！*/
                $msg['msg']='操作成功，您一共导入'.$count.'条数据';

                return $msg;
            }else{
                $msg['code']=307;
                $msg['msg']='操作失败';
                return $msg;
            }
        }else{
            $erro = $validator->errors()->all();
            $msg['msg'] = null;
            foreach ($erro as $k => $v) {
                $kk=$k+1;
                $msg['msg'].=$kk.'：'.$v.'</br>';
            }
            $msg['code'] = 300;
            return $msg;
        }
    }

}
?>
