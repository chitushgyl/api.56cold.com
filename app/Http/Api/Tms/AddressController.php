<?php
namespace App\Http\Api\Tms;
use App\Models\Tms\TmsAddressContact;
use App\Models\Tms\TmsCity;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;

use App\Models\SysAddress;
class AddressController extends Controller{

    /***    地址列表      /api/address/addressPage
     */
    public function addressPage(Request $request){
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $project_type       =$request->get('project_type');
//        $total_user_id = $user_info->total_user_id;
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;
        $search = [];
        switch ($project_type){
            case 'user':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];
                break;
            case 'carriage':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];
                break;
            case 'customer':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'company_id','value'=>$user_info->company_id],
                ];
                break;
            case 'carriers':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'company_id','value'=>$user_info->userIdentity->company_id],
                ];
                break;
            default:
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];
                break;

        }

        $where=get_list_where($search);

        $select=['self_id','sheng','shi','qu','sheng_name','shi_name','qu_name','address','particular','create_time','use_flag','contacts','tel'];
        $data['info'] = TmsAddressContact::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();
        $data['total'] = TmsAddressContact::where($where)->count();
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /***    新建地址    /api/address/createAddress
     */
    public function createAddress(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
        // $self_id = 'address_202101111755143321983342';
        $where   = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select  = ['self_id','sheng','sheng_name','shi','shi_name','qu','qu_name','address','contacts','tel','longitude','dimensionality','create_time'];
        $data['info'] = TmsAddressContact::where($where)->select($select)->first();
        $msg['code']  = 200;
        $msg['msg']   = "数据拉取成功";
        $msg['data']  = $data;
        //dd($msg);
        return $msg;
    }

    /*
    **    地址数据提交      /api/address/addAddress
    */
    public function addAddress(Request $request){
        $project_type       =$request->get('project_type');
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name ='tms_address_contact';
        $input      =$request->all();

        /** 接收数据*/
        $self_id    = $request->input('self_id');
        $address    = $request->input('address');//详细地址
        $pro = $request->input('pro');
        $qu = $request->input('qu');
        $city = $request->input('city');
        $area = $request->input('area');
        $contacts = $request->input('contacts');
        $tel = $request->input('tel');
        /*** 虚拟数据
        $input['self_id'] = $self_id = null;
        $input['qu']      = $qu      = '160';
        $input['address'] = $address = '爱特路';
        $input['pro']      = $pro      = '上海市';
        $input['city']      = $city      = '上海市';
        $input['area']      = $area      = '嘉定区';
        $input['contacts']      = $contacts      = '嘉定区';
        $input['tel']      = $tel      = '13215615154';
        ***/
        $rules=[
            'address'=>'required',
        ];
        $message=[
            'address.required'=>'请填写详细地址',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $where_address=[
                ['id','=',$qu],
            ];
            $selectMenu=['id','name','parent_id'];
            $address_info=SysAddress::with(['sysAddress' => function($query)use($selectMenu) {
                $query->select($selectMenu);
                $query->with(['sysAddress' => function($query)use($selectMenu) {
                    $query->select($selectMenu);
                }]);
            }])->where($where_address)->select($selectMenu)->first();

            $data['sheng']              =$address_info->sysAddress->sysAddress->id;
            $data['sheng_name']         =$address_info->sysAddress->sysAddress->name;
            $data['shi']                =$address_info->sysAddress->id;
            $data['shi_name']           =$address_info->sysAddress->name;
            $data['qu']                 =$address_info->id;
            $data['qu_name']            =$address_info->name;
            $data['address']            =$address;
            $data['contacts']            =$contacts;
            $data['tel']            =$tel;

            $wheres['self_id'] = $self_id;

            $location = bd_location(2,$data['sheng_name'],$data['shi_name'],$data['qu_name'],$data['address']);

            $data['longitude']      = $location ? $location['lng'] : '';
            $data['dimensionality'] = $location ? $location['lat'] : '';

            $old_info = TmsAddressContact::where($wheres)->first();

            switch ($project_type){
                case 'user':
                    $data['total_user_id']    = $user_info->total_user_id;
                    break;
                case 'customer':
                    $data['company_id']     = $user_info->userIdentity->company_id;
                    $data['company_name']     =$user_info->userIdentity->company_id;
//                    $data['group_code']     = $user_info->userIdentity->group_code;
//                    $data['group_name']     =$user_info->userIdentity->group_name;
                    break;
                case 'carriage':
                    $data['total_user_id']    = $user_info->total_user_id;
                    break;
                default:
                    break;
            }
            if($old_info){
                $data['update_time'] = $now_time;
                $id = TmsAddressContact::where($wheres)->update($data);
            }else{
                $data['self_id']          = generate_id('address_');

//                $data['create_user_id']   = $user_info->total_user_id;
//                $data['create_user_name'] = $user_info->token_name;
                $data['create_time']      = $data['update_time']=$now_time;
                $id = TmsAddressContact::insert($data);
            }

            if($id){
                $msg['code'] = 200;
                $msg['msg'] = "操作成功";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }

        }else{
            //前端用户验证没有通过
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

    /***    地址禁用/启用      /api/address/addressUseFlag
     */
    public function addressUseFlag(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_address_contact';
        $medol_name  = 'TmsAddressContact';
        $self_id     = $request->input('self_id');
        $flag        = 'useFlag';
        // $self_id  = 'address_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /***    地址删除     /api/address/addressDelFlag
     */
    public function addressDelFlag(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'tms_address_contact';
        $medol_name  = 'TmsAddressContact';
        $self_id     = $request->input('self_id');
        $flag        = 'delFlag';
        // $self_id  = 'address_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /***    拿地址信息     /address/getAddress
     */
    public function  getAddress(Request $request){
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = [];
        //dd($msg);
        return $msg;
    }

    /***    地址详情     /api/address/details
     */
    public function  details(Request $request,Details $details){
        $self_id    = $request->input('self_id');
        $table_name = 'tms_address_contact';
        $select     = ['self_id','sheng_name','shi_name','qu_name','address',
            'create_time','group_name','company_name','create_user_name','contacts','tel'
        ];
        // $self_id='address_202101111755143321983342';
        $info = $details->details($self_id,$table_name,$select);

        if($info) {
            /** 如果需要对数据进行处理，请自行在下面对 $$info 进行处理工作*/
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

    /*
     * 获取所有省市区并按照首拼字母排序
     * */
    public function get_city(Request $request){
        $type   = $request->input('type');//
        if ($type){
            $data['info'] = SysAddress::orderBy('first_word','ASC')->get();
        }else{
            $data['info'] = SysAddress::get();
        }

//        dd($data['info']->toArray());
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        //dd($msg);
        return $msg;
    }

    /**
     * 获取市内整车已开放城市
     * */
    public function get_address(Request $request){
        $list = TmsCity::where('delete_flag','Y')->select(['self_id','city','c_city'])->get();
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $list;
        return $msg;
    }

    /**
     * 获取市内整车已开放城市
     * */
    public function get_address(Request $request){
        $list = TmsCity::where('delete_flag','Y')->select(['self_id','city','c_city'])->get();
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $list;
        return $msg;
    }

    /**
     * 根据市区名称获取市ID，区ID  /api/address/get_address_id
     * */
    public function get_address_id(Request $request){
        $pro    = $request->input('pro');
        $city   = $request->input('city');//市名称
        $area   = $request->input('area');//区名称
        $where = [
            ['city'=>$city],

        ];
        /** 虚拟数据
        $pro = $input['pro'] = '贵州';
        $city = $input['city'] = '毕节';
        $area = $input['area'] = '威宁';
         ***/
        $pro_info = SysAddress::where('name','like','%'.$pro.'%')->first();
        $city_info = SysAddress::where('name','like','%'.$city.'%')->where('parent_id',$pro_info->id)->first();
        $area_info = SysAddress::where('name','like','%'.$area.'%')->where('parent_id',$city_info->id)->first();

        $info = [
            'pro'=>$pro_info->id,
            'city'=>$city_info->id,
            'area'=>$area_info->id,
        ];
        $msg['data'] = $info;
        $msg['code'] = 200;
        $msg['msg']  = '数据拉取成功';
        return $msg;
    }

}
?>
