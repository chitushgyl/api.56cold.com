<?php
namespace App\Http\Api\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;

class WalletController extends Controller{
    /**
     * 用户钱包，股份，积分流水数据      /user/wallet_page
     */
    public function wallet_page(Request $request){
        $user_info		=$request->get('user_info');
		$listrows		=config('page.listrows')[1];//每次加载的数量
		$first			=$request->input('page')??1;
		$firstrow		=($first-1)*$listrows;
        $capital_type	=$request->input('capital_type')??'wallet';

		//dd($user_info);
		$wallet_where=[
            ['total_user_id','=',$user_info->total_user_id],
            ['capital_type','=',$capital_type],
			['delete_flag','=','Y'],
		];

		$wallet_info=UserWallet::where($wallet_where)
			->offset($firstrow)->limit($listrows)->orderBy('id','desc')
			->select('money','now_money','create_time','produce_cause','produce_type','create_time')
			->get();
		if($capital_type !='share'){
			foreach($wallet_info as $k => $v){
				$v->money=number_format($v->money/100, 2);
				$v->now_money=number_format($v->now_money/100, 2);
			}
		}

		$msg['code']=200;
		$msg['msg']='数据拉取成功！';
		$msg['data']=$wallet_info;


        return $msg;
    }

 	/**
     * 我的提现创建      /user/creat_withdraw
	 * 提现必须是100的整数倍
     */
    public function creat_withdraw(Request $request){
        $user_info		=$request->get('user_info');

        if($user_info->userCapital->money>1000000){
            $user_info->money=number_format($user_info->userCapital->money/1000000, 2).'万';                 //用户余额
        }else{
            $user_info->money=number_format($user_info->userCapital->money/100, 2);                          //用户余额
        }

        dump($user_info->money);






        dd($user_info->toArray());


        $msg['code']=200;
        $msg['msg']='数据拉取成功';
        $msg['data']=$user_info;

        return $msg;


    }

    /**
     * 我的提现进入数据库      /user/add_withdraw
     */
    public function add_withdraw(Request $request){
        $user_info		=$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
	//dd($request->all());
        /** 接收数据*/
        $money         		=$request->input('money');
		$bank_name         	=$request->input('bank_name');
		$card_holder        =$request->input('card_holder');
		$card_number        =$request->input('card_number');
		$d_pay_pwd        =$request->input('pay_pwd');
	//dd($d_pay_pwd);
        /*** 虚拟数据**/
        //$input['money']=$money='100';

        if($money*100>$user_info->userCapital->money){
            //用户余额不够提现
            $msg['code']=301;
            $msg['msg']='用户余额不够提现';
            return $msg;
        }

	$total_wheer=[
          ['self_id','=',$user_info->total_user_id]
	];
        $pay_pwd=DB::table('user_total')->where($total_wheer)->value('pay_pwd');
	//dump($d_pay_pwd);dd($pay_pwd);
        //判断交易密码是否正确
        if($pay_pwd !== $d_pay_pwd){
            //交易密码错误
            $msg['code']=303;
            $msg['msg']='交易密码错误，请重新输入';
            return $msg;
        }




        //判断是不是100的整数倍
        if($money%100 != 0){
            //用户余额不够提现
            $msg['code']=302;
            $msg['msg']='提现必须是100的整数倍';
            return $msg;
        }

        $id=null;
        //可以发起提现
        /** 可以开始执行事务操作了**/
        DB::beginTransaction();
        try{
            $where['total_user_id']=$user_info->total_user_id;
            $where['delete_flag']='Y';

            $capital['money']=$user_info->userCapital->money-$money*100;
            $capital['update_time']=$now_time;

            //DD($capital);
            $id=UserCapital::where($where)->update($capital);
//做一个流水记录1
            $data['self_id']        =generate_id('wallet_');
            $data['total_user_id']  =$user_info->total_user_id;
            $data['capital_type']   ='wallet';
            $data['produce_type']   ='EXTRACT';
            $data['produce_cause']  ='提现';
            $data['create_time']    =$now_time;
            $data['money']          =$money*100;
			$data['bank_name']          =$bank_name;
			$data['card_holder']          =$card_holder;
			$data['card_number']          =$card_number;

            $data['order_sn']       =$user_info->total_user_id;
            $data['now_money']      =$capital['money'];
            $data['now_money_md']   =get_md5($capital['money']);
            $data['ip']             =$request->getClientIp();
            $data['wallet_status']  ='WAIT';
            UserWallet::insert($data);

            DB::commit();
        }catch (\Exception $e) {
            //接收异常处理并回滚
            DB::rollBack();
            $msg['code']=303;
            $msg['msg']="事务打断";
            return $msg;
        }

        if($id){
            $msg['code'] = 200;
            $msg['msg'] = "提现成功";
            return $msg;
        }else{
            $msg['code'] = 302;
            $msg['msg'] = "提现失败";
            return $msg;
        }

    }

    /**
     * 我的余额赠送      /user/give_withdraw
     */
    public function give_withdraw(Request $request){
        $user_info		=$request->get('user_info');
        $now_time       =date('Y-m-d H:i:s',time());
        /** 接收数据*/
        $money         			=$request->input('give_wait');
		$give_total_user_id     =$request->input('give_total_user_id');
        /*** 虚拟数据**/

        if($money*100>$user_info->userCapital->money){
            //用户余额不够提现
            $msg['code']=301;
            $msg['msg']='用户余额不够赠送';
            return $msg;
        }

	$id=null;
	$id2=null;
        //可以发起提现
        /** 可以开始执行事务操作了**/
        DB::beginTransaction();
        try{
            $where['total_user_id']=$user_info->total_user_id;
            $where['delete_flag']='Y';
            $capital['money']=$user_info->userCapital->money-$money*100;
            $capital['update_time']=$now_time;
	    //减去要赠送的玉豆
            $id=UserCapital::where($where)->update($capital);

	    //做一个流水记录
            $data['self_id']        =generate_id('wallet_');
            $data['total_user_id']  =$user_info->total_user_id;
            $data['capital_type']   ='wallet';
            $data['produce_type']   ='CONSUME';
            $data['produce_cause']  ='赠出玉豆';
            $data['create_time']    =$now_time;
            $data['money']          =$money*100;
            $data['order_sn']       =$user_info->total_user_id;
            $data['now_money']      =$capital['money'];
            $data['now_money_md']   =get_md5($capital['money']);
            $data['ip']             =$request->getClientIp();
            $data['wallet_status']  ='FS';
            UserWallet::insert($data);



            //开始赠送玉豆给受让人

            $where2['total_user_id']=$give_total_user_id;
            $where2['delete_flag']='Y';
	    $user_info_money=UserCapital::where($where2)->value('money');


	    //增加要赠送的玉豆
            $capital2['money']=$user_info_money+$money*100;
            $capital2['update_time']=$now_time;

            $id2=UserCapital::where($where2)->update($capital2);


	    //做一个流水记录
			$data2['self_id']        =generate_id('wallet_');
			$data2['total_user_id']  =$give_total_user_id;
			$data2['capital_type']   ='wallet';
			$data2['produce_type']   ='GIVE';
			$data2['produce_cause']  =$user_info->tel.'赠送玉豆';
			$data2['create_time']    =$now_time;
			$data2['money']          =$money*100;
			$data2['order_sn']       =$give_total_user_id;
			$data2['now_money']      =$money*100;
			$data2['now_money_md']   =get_md5($money*100);
			$data2['ip']             =$request->getClientIp();
			$data2['wallet_status']  ='FS';
			UserWallet::insert($data2);


            DB::commit();
        }catch (\Exception $e) {
            //接收异常处理并回滚
            DB::rollBack();
            $msg['code']=303;
            $msg['msg']="事务打断";
            return $msg;
        }

        if($id || $id2){
            $msg['code'] = 200;
            $msg['msg'] = "赠送成功";
           	       return $msg;
       	}else{
		    $msg['code'] = 302;
		    $msg['msg'] = "赠送失败";
		    return $msg;
        }

    }



}
?>
