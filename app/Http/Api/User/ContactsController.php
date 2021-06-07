<?php
namespace App\Http\Api\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\User\UserAddress;
use App\Models\SysAddressAll;

class ContactsController extends Controller{
    /**
     * 地址列表加载数据，全量拉取，不做分页      /user/contacts_page
     * 前端传递必须参数：    type(owm,order)     owm代表个人中心，order 代表订单过来的
     *前端传递非必须参数：user_token(用户token)
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  地址列表信息
     */
    public function contacts_page(Request $request){
        $user_info		=$request->get('user_info');				//接收中间件产生的参数

        $type			=$request->input('type')??'owm';
		//dump($user_info);
		//用户地址拉取
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

			//这里应该有个判断，就是那些地址是可用的，那些地址是不可用的，但是问题是没有订单号，我不知道商品的配送范围！！！！

			
		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['type']=$type;
		$msg['data']=$address_info;

        //dd($msg);

        return $msg;
    }

    /**
     * 地址的添加和修改     /user/create_contacts
     * 前端传递必须参数：   type(owm,order)     owm代表个人中心，order 代表订单过来的
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function create_contacts(Request $request){
        $user_info		=$request->get('user_info');
        /** 接收数据*/
        $address_id		=$request->input('address_id');
        $type			=$request->input('type')??'owm';

        /** 虚拟数据*/
        //$address_id		='address_202007301102400853415842';

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

		$sheng_info=SysAddressAll::where($address_all_where)->select('self_id','code_name','code_parent')->get();

		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['type']=$type;
		$msg['sheng_info']=$sheng_info;
		$msg['data']=$address_info;
        //dd($msg);

        return $msg;
    }

    /**
     * 地址的添加和修改入库操作     /user/add_contacts
     * 前端传递必须参数：
     *前端传递非必须参数：user_token(用户token)   address_id
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function add_contacts(Request $request){
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());

		//$address_id=$request->get('address_id');
        $input			=$request->all();
		//dd($input);
		 /** 接收数据*/
        $address_id		=		$request->input('address_id');
        $type			=		$request->input('type')??'owm';
        $name			=		$request->input('name');
        $tel			=		$request->input('tel');
        $sheng			=		$request->input('sheng');
        $sheng_name		=		$request->input('sheng_name');
        $shi			=		$request->input('shi');
        $shi_name		=		$request->input('shi_name');
        $qu				=		$request->input('qu');
        $qu_name		=		$request->input('qu_name');
        $remark			=		$request->input('remark');
        $particular		=		$request->input('particular');
        $default_flag	=		$request->input('default_flag');

        /** 虚拟数据
        $name=$input['name']='张三';
        $tel=$input['tel']='12345678910';
        $sheng=$input['sheng']='110000';
        $sheng_name=$input['sheng_name']='北京';
        $shi=$input['shi']='110100';
        $shi_name=$input['shi_name']='北京市';
        $qu=$input['qu']='110101';
        $qu_name=$input['qu_name']='东城区';
        $remark=$input['remark']='home';
        $particular=$input['particular']='什么什么路1589弄8号105';
        $default_flag=$input['default_flag']='Y';*/
//dump($input);
		$rules=[
			'name'=>'required',
			'tel'=>'required',
			'particular'=>'required',
		];
		$message=[
			'name.required'=>'请填写收货人',
			'tel.required'=>'请填写收货人联系方式',
			'particular.required'=>'请填写收货地址',
		];

		$validator=Validator::make($input,$rules,$message);
		if($validator->passes()){
			//先把数据做好
			$data['total_user_id']	=$user_info->total_user_id;
			$data['name']			=$name;
			$data['tel']			=$tel;
			$data['sheng']			=$sheng;
			$data['sheng_name']		=$sheng_name;
			$data['shi']			=$shi;
			$data['shi_name']		=$shi_name;
			$data['qu']				=$qu;
			$data['qu_name']		=$qu_name;
			$data['remark']			=$remark;
			//$domain = strpos($input['shi_name'], $input['sheng_name']);			//看看市名字里面是不是包含省的名称      false代表不包含

//			dd($data);
			if(strpos($shi_name, $sheng_name) !== false){
				$data['address']=$sheng_name.$qu_name.$particular;
			}else{
				$data['address']=$sheng_name.$shi_name.$qu_name.$particular;
			}

			$data['particular']				=$particular;
			$data['ip']						=$request->getClientIp();
			$data['default_flag']			=$default_flag;


//			dd($data);
			//如果过来是个默认地址
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
     * 地址的删除操作     /user/del_contacts
     * 前端传递必须参数：address_id
     *前端传递非必须参数：user_token(用户token)
     * 回调结果：200  数据拉取成功
     *
     *回调数据：
     */
    public function del_contacts(Request $request){
        $user_info		=$request->get('user_info');
        $now_time		=date('Y-m-d H:i:s',time());
        /** 接收数据*/
        $address_id		=$request->input('address_id');
        $type			=$request->input('type')??'owm';

//		$address_id='address_202008150124459352146346';
		if($address_id){
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


		}else{
			$msg['code']=300;
			$msg['type']=$type;
			$msg['data']=$address_id;
			$msg['msg']="没有查询到数据";
            return $msg;
		}

		
//        dd($msg);


    }
}
?>
