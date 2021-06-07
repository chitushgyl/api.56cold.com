<?php
namespace App\Http\Api\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\User\UserAddress;
use App\Models\SysAddressAll;
use App\Models\Tms\TmsAddress;
use App\Models\SysAddress;

class AddressController extends Controller{
    /**
     * 地址列表加载数据，全量拉取，不做分页      /user/address_page
     * 前端传递必须参数：    type(owm,order)     owm代表个人中心，order 代表订单过来的
     *回调数据：  地址列表信息
     */
    public function address_page(Request $request){
        $project_type        =$request->get('project_type');
        $user_info			=$request->get('user_info');				//接收中间件产生的参数

        $type			=$request->input('type')??'owm';

//        dump($user_info->toArray());
//        $user_info->group_code='group_202012251449437824125582';
//        $project_type='1212';
        switch ($project_type){
			case 'shop':
                $where=[
                    ['total_user_id','=',$user_info->total_user_id],
                    ['delete_flag','=','Y'],
                ];
                $select=['self_id as address_id','name','tel','address','particular','remark','default_flag'];

                $address_info=UserAddress::where($where)->orderBy('create_time','desc')->select($select)->get();

                foreach($address_info as $k => $v){
                    switch ($v->remark){
                        case 'home':
                            $v->remark='家';
                            break;
                        case 'company':
                            $v->remark='公司';
                            break;
                        default:
                            $v->remark=null;
                            break;
                    }

                }
				break;
			default:
                $where=[
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                    ['group_code','=',$user_info->group_code],
                ];

                $select=['self_id','sheng_name','shi_name','qu_name','address','company_name'];

                $address_info=TmsAddress::where($where)->orderBy('create_time', 'desc')->select($select)->get();
				break;

		}
//		dump($address_info->toArray());
		//用户地址拉取


			//这里应该有个判断，就是那些地址是可用的，那些地址是不可用的，但是问题是没有订单号，我不知道商品的配送范围！！！！

			
		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['type']=$type;
		$msg['data']=$address_info;

        //dd($msg);

        return $msg;
    }

    /**
     * 地址的添加和修改     /user/create_address
     * 前端传递必须参数：   type(owm,order)     owm代表个人中心，order 代表订单过来的
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function create_address(Request $request){
        $project_type       =$request->get('project_type');
        $user_info			=$request->get('user_info');
        /** 接收数据*/
        $address_id		    =$request->input('address_id');
        $type				=$request->input('type')??'owm';

        /** 虚拟数据*/
        //$address_id		='address_202007301102400853415842';
//        $project_type='1212';

		switch ($project_type){
            case 'shop':
                $where=[
                    ['self_id','=',$address_id],
                    ['total_user_id','=',$user_info->total_user_id],
                ];
                $select=['self_id as address_id','name','tel','sheng','sheng_name','shi','shi_name','qu','qu_name','particular','remark','default_flag'];
                $address_info=UserAddress::where($where)->select($select)->first();
                //抓取一下要做3级联动的地址
                $address_all_where=[
                    ['delete_flag','=','Y'],
                    ['type_flag','=','express'],
                ];

                $info=SysAddressAll::where($address_all_where)->select('self_id','code_name','code_parent')->get();

                break;
            default:
                $where=[
                    ['self_id','=',$address_id],
                    ['group_code','=',$user_info->group_code],
                ];

                $select=['self_id','sheng_name','shi_name','qu_name','address','company_name'];

                $address_info=TmsAddress::where($where)->orderBy('create_time', 'desc')->select($select)->get();

                $info=SysAddress::get();

                break;
		}





		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['type']=$type;
		$msg['info']=$info;
		$msg['data']=$address_info;
        //dd($msg);

        return $msg;
    }

    /**
     * 地址的添加和修改入库操作     /user/add_address
     * 前端传递必须参数：
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function add_address(Request $request){
        $project_type       =$request->get('project_type');
        $user_info		    =$request->get('user_info');
        $now_time		    =date('Y-m-d H:i:s',time());
        $project_type='1212';
		//$address_id=$request->get('address_id');
        $input			=$request->all();
		//dd($input);
		 /** 接收数据*/
        $address_id		=		$request->input('address_id');
        $type			=		$request->input('type')??'owm';
        $name			=		$request->input('name');
        $tel			=		$request->input('tel');
        $qu				=		$request->input('qu');
        $remark			=		$request->input('remark');
        $address	    =		$request->input('address');
        $default_flag	=		$request->input('default_flag');

        /** 虚拟数据
        $name               =$input['name']='张三';
        $tel                =$input['tel']='12345678910';
        $qu                 =$input['qu']='110101';
        $remark             =$input['remark']='home';
        $address            =$input['address']='什么什么路1589弄8号105';
        $default_flag       =$input['default_flag']='Y';
         */
        switch ($project_type){
            case 'shop':
                $rules=[
                    'name'=>'required',
                    'tel'=>'required',
                    'qu'=>'required',
                    'address'=>'required',
                ];
                $message=[
                    'name.required'=>'请填写收货人',
                    'tel.required'=>'请填写收货人联系方式',
                    'qu.required'=>'请选择区域',
                    'address.required'=>'请填写地址',
                ];
                break;
            default:
                $rules=[
                    'qu'=>'required',
                    'address'=>'required',
                ];
                $message=[
                    'qu.required'=>'请选择区域',
                    'address.required'=>'请填写地址',
                ];
                break;

        }


		$validator=Validator::make($input,$rules,$message);
		if($validator->passes()){
            switch ($project_type){
                case 'shop':

                    $address_all_where=[
                        ['delete_flag','=','Y'],
                        ['self_id','=',$qu],
                    ];
                    $select=['self_id','code_name','code_parent'];

                    $info=SysAddressAll::with(['sysAddressAll' => function($query)use($select) {
                        $query->select($select);
                        $query->with(['sysAddressAll' => function($query)use($select) {
                            $query->select($select);
                        }]);
                    }])->where($address_all_where)->select($select)->first();

                    $data['total_user_id']	=$user_info->total_user_id;
                    $data['name']			=$name;
                    $data['tel']			=$tel;
                    $data['sheng']			=$info->sysAddressAll->sysAddressAll->self_id;
                    $data['sheng_name']		=$info->sysAddressAll->sysAddressAll->code_name;
                    $data['shi']			=$info->sysAddressAll->self_id;
                    $data['shi_name']		=$info->sysAddressAll->code_name;
                    $data['qu']				=$info->self_id;
                    $data['qu_name']		=$info->code_name;;
                    $data['remark']			=$remark;
                    $data['address']		=$address;
                    $data['default_flag']	=$default_flag;

                    if($default_flag=="Y"){
                        //把之前的默认地址改为N
                        $gehieg['total_user_id'] 	=$user_info->total_user_id;
                        $gehieg['default_flag']		="Y";
                        $att['update_time']			=$now_time;
                        $att['default_flag']		='N';
                        UserAddress::where($gehieg)->update($att);
                    }

//开始入库操作
                    if($address_id){ //是修改
                        $where2['self_id']		=$address_id;
                        $data['update_time']	=$now_time;
                        $id=UserAddress::where($where2)->update($data);
                        $data['address_id']		=$address_id;

                    }else{  //是新增1
                        $data['create_time']	=$data['update_time']	=$now_time;
                        $data['self_id']		=generate_id('address_');
                        $id=UserAddress::insert($data);
                        $data['address_id']		=$data['self_id'];
                    }


                    if($id){
                        $msg['code']=200;
                        $msg['msg']="操作成功";
                        $msg['type']=$type;
                        $msg['data']=$data;
                        return $msg;
                    }else{
                        $msg['code']=301;
                        $msg['msg']="操作失败";
                        return $msg;
                    }


                    break;
                default:
//                    $qu='956';
                    $address_all_where=[
                        ['id','=',$qu],
                    ];

                    $select=['id','name','parent_id'];
                    $info=SysAddress::with(['sysAddress' => function($query)use($select) {
                        $query->select($select);
                        $query->with(['sysAddress' => function($query)use($select) {
                            $query->select($select);
                        }]);
                    }])->where($address_all_where)->select($select)->first();

                    $data['sheng']              =$info->sysAddress->sysAddress->id;
                    $data['sheng_name']         =$info->sysAddress->sysAddress->name;
                    $data['shi']                =$info->sysAddress->id;
                    $data['shi_name']           =$info->sysAddress->name;
                    $data['qu']                 =$info->id;
                    $data['qu_name']            =$info->name;
                    $data['address']            =$address;


                    if($address_id){ //是修改
                        $where2['self_id']		=$address_id;
                        $data['update_time']	=$now_time;
                        $id=TmsAddress::where($where2)->update($data);
                        $data['address_id']		=$address_id;

                    }else{  //是新增1
                        $data['create_time']	=$data['update_time']	=$now_time;
                        $data['self_id']		=generate_id('address_');
                        $data['group_code']     =$user_info->group_code;
                        $data['group_name']     =$user_info->group_name;
                        $id=TmsAddress::insert($data);
                        $data['address_id']		=$data['self_id'];
                    }

                    if($id){
                        $msg['code']=200;
                        $msg['msg']="操作成功";
                        $msg['type']=$type;
                        $msg['data']=$data;
                        return $msg;
                    }else{
                        $msg['code']=301;
                        $msg['msg']="操作失败";
                        return $msg;
                    }
                    break;
            }



		}else{
			$erro=$validator->errors()->all();
			$msg['code']=300;
			$msg['type']=$type;
			$msg['msg']=$erro[0];
            return $msg;
		}

//dd($msg);


    }
    /**
     * 地址的删除操作     /user/del_address
     * 前端传递必须参数：address_id
     */
    public function del_address(Request $request){
        $project_type       =$request->get('project_type');
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());
        /** 接收数据*/
        $address_id		=$request->input('address_id');
        $type			=$request->input('type')??'owm';

//		$address_id='address_202008150124459352146346';
        switch ($project_type){
            case 'shop':
                $where=[
                    ['self_id','=',$address_id],
                    ['total_user_id','=',$user_info->total_user_id],
                ];
                $data['delete_flag']='N';
                $data['delete_time']=$now_time;
                $id=UserAddress::where($where)->update($data);

                if($id){
                    $msg['code']=200;
                    $msg['type']=$type;
                    $msg['data']=$address_id;
                    $msg['msg']="删除成功";
                    return $msg;

                }else{
                    $msg['code']=301;
                    $msg['type']=$type;
                    $msg['data']=$address_id;
                    $msg['msg']="删除失败";
                    return $msg;
                }


                break;
            default:
                $where=[
                    ['self_id','=',$address_id],
                ];

                $data['delete_flag']='N';
                $data['delete_time']=$now_time;
                $id=TmsAddress::where($where)->update($data);

                if($id){
                    $msg['code']=200;
                    $msg['type']=$type;
                    $msg['data']=$address_id;
                    $msg['msg']="删除成功";
                    return $msg;

                }else{
                    $msg['code']=301;
                    $msg['type']=$type;
                    $msg['data']=$address_id;
                    $msg['msg']="删除失败";
                    return $msg;
                }

                break;


        }

    }
}
?>
