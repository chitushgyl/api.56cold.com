<?php
namespace App\Http\Admin\Tms;


use App\Http\Controllers\CommonController;
use App\Http\Controllers\StatusController as Status;
use App\Http\Controllers\DetailsController as Details;
use App\Models\User\UserBank;
use App\Models\User\UserCapital;
use App\Models\User\UserWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WalletController extends CommonController{
    /***    提现头部      /tms/wallet/walletList
     */
    public function  walletList(Request $request){
        $data['page_info']      =config('page.listrows');
        $data['button_info']    =$request->get('anniu');
        $data['user_info']      =$request->get('user_info');

        $money = UserCapital::where('group_code',$data['user_info']->group_code)->select('money')->first();
        $msg['money'] =  number_format($money->money/100,2);
        $msg['group_name'] = $data['user_info']->group_name;
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }

    /**
     *资金流水     /tms/wallet/walletPage
     */
    public function walletPage(Request $request){
        /** 接收中间件参数**/
        $group_info     = $request->get('group_info');//接收中间件产生的参数
        $button_info    = $request->get('anniu');//接收中间件产生的参数

        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $tms_wallet_info   = array_column(config('tms.tms_wallet_info'),'name','key');
        $tms_wallet_image   = array_column(config('tms.tms_wallet_info'),'image','key');
        $tms_wallet_status = array_column(config('tms.tms_wallet_status'),'name','key');
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $state         = $request->input('state');//拉取数据类型 Y 只拉提现数据 N 拉取所有数据
        $group_code    = $request->input('group_code');
        $status        = $request->input('status');//拉取数据类型 Y 只拉提现数据 N 拉取所有数据
        $start_time    = $request->input('start_time');
        $end_time      = $request->input('end_time');
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;
//        $start_time = '2021-02-06';
//        $end_time   = '2021-05-10';
        if ($start_time){
            $start_time = $start_time.' 00:00:00';
        }
        if ($end_time){
            $end_time = $end_time.' 23:59:59';
        }
        $search = [
            ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
            ['type'=>'all','name'=>'wallet_status','value'=>$status],
            ['type'=>'=','name'=>'group_code','value'=>$group_code],
            ['type'=>'>=','name'=>'create_time','value'=>$start_time],
            ['type'=>'<','name'=>'create_time','value'=>$end_time],

        ];
        if ($state=='Y'){
            $search[] = ['type'=>'=','name'=>'produce_type','value'=>'ti'];
        }
        $where  = get_list_where($search);
//        dd($where);
        $select = ['self_id','total_user_id','capital_type','produce_type','money','now_money','wallet_status','wallet_type','group_name','group_code','serial_rate',
            'serial_money','serial_number','serial_bank_name','card_number','card_holder','bank_name','user_bank_id','create_time','update_time','reason'];
        switch ($group_info['group_id']){
            case 'all':
                $data['total'] = UserWallet::where($where)->count();
                $data['items']   = UserWallet::
                    where($where)
                    ->offset($firstrow)
                    ->limit($listrows)
                    ->orderBy('update_time','DESC')
                    ->select($select)
                    ->get();
                break;
            case 'one':
                $where[]=['group_code','=',$group_info['group_code']];
                $data['total'] = UserWallet::where($where)->count();
                $data['items']   = UserWallet::where($where)
                    ->offset($firstrow)
                    ->limit($listrows)
                    ->orderBy('update_time','DESC')
                    ->select($select)
                    ->get();
                break;
            case 'more':
                $data['total'] = UserWallet::where($where)->whereIn('group_code',$group_info['group_code'])->count();
                $data['items']   = UserWallet::where($where)->whereIn('group_code',$group_info['group_code'])
                    ->offset($firstrow)
                    ->limit($listrows)
                    ->orderBy('update_time','DESC')
                    ->select($select)
                    ->get();
                break;
        }
        $button_info1=[];
        $button_info2=[];
        foreach ($button_info as $k => $v){
//            dump($v);
            if($v->id==697){
                $button_info2[]=$v;
            }
            if($v->id==696){
                $button_info2[]=$v;
            }
            if($v->id==695){
                $button_info1[]=$v;
                $button_info2[]=$v;
            }
        }
        foreach ($data['items'] as $k => $v){
            $v->wallet_status_show = $tms_wallet_status[$v->wallet_status] ?? null;
            $v->image = img_for($tms_wallet_image[$v->produce_type],'no_json');
            $v->produce_type_show = $tms_wallet_info[$v->produce_type] ?? null;

            $v->money = number_format($v->money/100,2);
            $v->now_money = number_format($v->now_money/100,2);
            $v->serial_money = number_format($v->serial_money/100,2);
            if ($v->wallet_status == 'WAIT'){
                $v->button_info=$button_info2;
            }elseif($v->wallet_status == 'SU'){
                $v->button_info=$button_info1;
            }else{
                $v->button_info=$button_info1;
            }
        }

        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /**
     * 提现审核 /tms/wallet/walletPass
     * */
    public function walletPass(Request $request){
        $now_time=date('Y-m-d H:i:s',time());
        $operationing = $request->get('operationing');//接收中间件产生的参数
        $user_info          = $request->get('user_info');//接收中间件产生的参数
        $table_name='user_wallet';
        $input              =$request->all();
        $self_id=$request->input('self_id'); //数据ID
        $type = $request->input('type');//操作类别:pass 通过  fail失败
        $reason = $request->input('reason');
        $rules=[
            'self_id'=>'required',
        ];
        $message=[
            'self_id.required'=>'请选择要操作的数据',
        ];
//        $input['self_id'] = $self_id = 'wallet_202105061133562327277462';
//        $input['type'] =  $type = 'fail';
        $validator=Validator::make($input,$rules,$message);
        if($validator->passes()) {
            $select = ['self_id','total_user_id','capital_type','produce_type','money','now_money','wallet_status','wallet_type','group_name','group_code','serial_rate',
                'serial_money','serial_number','serial_bank_name','card_number','card_holder','bank_name','user_bank_id','create_time','update_time','reason'];
            $info = UserWallet::where('self_id',$self_id)->select($select)->first();
            $old_info = [
                'wallet_status'=>$info->state,
                'update_time'=>$now_time
            ];
            switch($type){
                case 'pass':
                    $new_info['update_time'] = $now_time;
                    $new_info['wallet_status'] = 'SU';
                    $id = UserWallet::where('self_id',$self_id)->update($new_info);

                    break;
                case 'fail':
                    $new_info['update_time'] = $now_time;
                    $new_info['wallet_status'] = 'FS';
                    $new_info['reason'] = $reason;
                    $id = UserWallet::where('self_id',$self_id)->update($new_info);
                    /** 审核不通过 添加资金余额***/
                    if ($info->total_user_id){
                        $captial_where = [
                            ['total_user_id','=',$info->total_user_id]
                        ];
                    }else{
                        $captial_where = [
                            ['group_code','=',$info->group_code]
                        ];
                    }
//                    $captial = where($captial_where)->select('money')->first();
                    $update_capital['money'] = $info->money + $info->now_money;
                    $update_capital['update_time'] = $now_time;
                    UserCapital::where($captial_where)->update($update_capital);
                    break;

            }
            $operationing->access_cause='通过/失败';
            $operationing->table=$table_name;
            $operationing->table_id=$self_id;
            $operationing->now_time=$now_time;
            $operationing->old_info=$old_info;
            $operationing->new_info=$new_info;
            if ($id){
                $msg['code']=200;
                $msg['msg']='操作成功！';
                $msg['data']=$new_info;
                return $msg;
            }else{
                $msg['code']=303;
                $msg['msg']='操作失败！';
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


    /**
     *提现  /tms/wallet/withdrawMoney
     * */
    public function withdrawMoney(Request $request){
        $user_info = $request->get('user_info');//接收中间件产生的参数
        $operationing   = $request->get('operationing');//接收中间件产生的参数
        $now_time       =date('Y-m-d H:i:s',time());
        $table_name     ='user_wallet';

        $operationing->access_cause     ='提现';
        $operationing->table            =$table_name;
        $operationing->operation_type   ='create';
        $operationing->now_time         =$now_time;

        /**接收数据*/
        $input         = $request->all();
        $money         = $request->input('money');//申请 提现金额
        $bank_name         = $request->input('bank_name');//开户行/支付宝
        $card_number   = $request->input('card_number');//支付宝/银行卡账号
        $card_holder      = $request->input('card_holder');//支付宝/持卡人姓名
        $bank_id       = $request->input('bank_id');//银行卡self_id
        /**虚拟数据
        $input['money'] = $money =  100;//申请 提现金额
        $input['bank_name'] = $bank_name =  '15100000000';
        $input['card_number'] = $card_number =  '623615156454454154545';
        $input['card_holder'] = $card_holder =  '张三';
        $input['bank_id']     = $bank_id     =  '';
         * **/
        $rules = [
            'money'=>'required',
            'card_number'=>'required',
            'card_holder'=>'required',
        ];
        $message = [
            'money.required'=>'提现金额不能为空',
            'card_number.required'=>'支付宝账号不能为空',
            'card_holder.required'     =>'姓名不能为空',
            'bank_name.required'  => '开户行不能为空',
        ];

        if ($money < 1) {
            $msg['code'] = 303;
            $msg['msg']  = "提现金额最少1元！";
            return $msg;
        }
        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {

            $wallet_where = [
                ['group_code','=',$user_info->group_code],
            ];

            $capital = UserCapital::where($wallet_where)->select(['self_id','money'])->first();

            if (empty($capital)) {
                $msg['code'] = 300;
                $msg['msg']  = "错误，请重试！";
                return $msg;
            }
            $money_100 = $money*100;
            if ($money_100 > $capital->money) {
                $msg['code'] = 301;
                $msg['msg']  = "余额不足！";
                return $msg;
            }

            $nokoru_money = $capital->money - $money_100 - 0;
            $data_wallet = [
                'money' => $nokoru_money,
                'update_time' => $now_time,
            ];

            $data['self_id'] = generate_id('wallet_');

            $data['produce_type']  = 'ti';
            $data['capital_type']  = 'wallet';
            $data['money']         = $money_100;
            $data['create_time']   = $now_time;
            $data['update_time']   = $now_time;
            $data['now_money']     = $nokoru_money;
            $data['now_money_md']  = get_md5($nokoru_money);
            $data['wallet_status'] = 'WAIT';
            $data['bank_name']     = $bank_name;
            $data['card_number']   = $card_number;
            $data['card_holder']   = $card_holder;
            $data['wallet_type']   = $user_info->type;
            $data['group_code']    = $user_info->group_code;

            if (empty($bank_id)){
                $bank['bank_name']     =$bank_name;
                $bank['card_holder']   =$card_holder;
                $bank['card_number']   =$card_number;
                $bank['type']          ='bank'; // 银行卡bank  支付宝Alipay
                $bank['default_flag']  = 'N';
                $bank['group_code']    = $user_info->group_code;
                $bank['group_name']    = $user_info->group_name;
                $bank['self_id']          = generate_id('bank_');
                $bank['create_time']      = $bank['update_time']=$now_time;
                $id = UserBank::insert($bank);
            }

            $id = UserCapital::where($wallet_where)->update($data_wallet);
            UserWallet::insert($data);
            $operationing->old_info=null;
            $operationing->new_info=(object)$data;
            $operationing->table_id = $data['self_id'];
            if($id){
                $msg['code'] = 200;
                $msg['msg']  = "申请提现中，请稍后查询！";
                return $msg;
            }else{
                $msg['code'] = 302;
                $msg['msg'] = "操作失败";
                return $msg;
            }

        }else{
            //前端用户验证没有通过
            $erro = $validator->errors()->all();
            $msg['code'] = 300;
            $msg['msg']  = null;
            foreach ($erro as $k => $v) {
                $kk = $k+1;
                $msg['msg'] .= $kk.'：'.$v.'</br>';
            }
            return $msg;
        }
    }

    /**
     * 提现详情 /tms/wallet/details
     * */
    public function details(Request $request,Details $details){
        $self_id=$request->input('self_id');
        $table_name='user_wallet';
        $tms_wallet_info   = array_column(config('tms.tms_wallet_info'),'name','key');
        $tms_wallet_image   = array_column(config('tms.tms_wallet_info'),'image','key');
        $tms_wallet_status = array_column(config('tms.tms_wallet_status'),'name','key');
        $select=['self_id','total_user_id','capital_type','produce_type','money','now_money','wallet_status','wallet_type','group_name','group_code','serial_rate',
            'serial_money','serial_number','serial_bank_name','card_number','card_holder','bank_name','user_bank_id','create_time','update_time'];
//         $self_id='wallet_202105061655187106480834';
        $info=$details->details($self_id,$table_name,$select);

        if($info){
            /** 如果需要对数据进行处理，请自行在下面对 $info 进行处理工作*/
            $info->wallet_status_show = $tms_wallet_status[$info->wallet_status] ?? null;
            $info->image = img_for($tms_wallet_image[$info->produce_type],'no_json');
            $info->produce_type_show = $tms_wallet_info[$info->produce_type] ?? null;

            $info->money = number_format($info->money/100,2);
            $info->now_money = number_format($info->now_money/100,2);
            $info->serial_money = number_format($info->serial_money/100,2);


            $data['info']=$info;
            $log_flag='Y';
            $data['log_flag']=$log_flag;
            $log_num='10';
            $data['log_num']=$log_num;
            $data['log_data']=null;

            if($log_flag =='Y'){
                $data['log_data']=$details->change($self_id,$log_num);

            }
//             dd($data);

            $msg['code']=200;
            $msg['msg']="数据拉取成功";
            $msg['data']=$data;
            return $msg;
        }else{
            $msg['code']=300;
            $msg['msg']="没有查询到数据";
            return $msg;
        }
    }


    /**
     * 常用银行卡或支付宝账号列表  /api/wallet/accountPage
     * */
    public function accountPage(Request $request){
        $project_type       =$request->get('project_type');
        /** 接收中间件参数**/
        $user_info     = $request->get('user_info');//接收中间件产生的参数
        $tms_account_type        = array_column(config('tms.account_type'),'name','key');
        $tms_account_url        = array_column(config('tms.account_type'),'url','key');

//        $user_info->total_user_id = 'user_202101231735555989206119';
        /**接收数据*/
        $num           = $request->input('num')??10;
        $page          = $request->input('page')??1;
        $listrows      = $num;
        $firstrow      = ($page-1)*$listrows;

        switch ($project_type){
            case 'user':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'use_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];
                break;
            case 'carriage':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'use_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'total_user_id','value'=>$user_info->total_user_id],
                ];
                break;
            case 'TMS3PL':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'use_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code],
                ];
                break;
            case 'company':
                $search = [
                    ['type'=>'=','name'=>'delete_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'use_flag','value'=>'Y'],
                    ['type'=>'=','name'=>'group_code','value'=>$user_info->group_code],
                ];
                break;
        }

        $where  = get_list_where($search);
        $select = ['self_id','group_name','type','bank_name','card_holder','card_number','group_code','default_flag'];
        $data['info'] = UserBank::where($where)
            ->offset($firstrow)
            ->limit($listrows)
            ->orderBy('create_time', 'desc')
            ->select($select)
            ->get();

        foreach ($data['info'] as $k=>$v) {
            $v->account_type_show = $tms_account_type[$v->type]??null;
            $v->account_type_img = $tms_account_url[$v->type]??null;
        }
        $msg['code'] = 200;
        $msg['msg']  = "数据拉取成功";
        $msg['data'] = $data;
        return $msg;
    }

    /***    新建账号    /api/wallet/createAccount
     */
    public function createAccount(Request $request){
        /** 接收数据*/
        $self_id = $request->input('self_id');
//         $self_id = 'bank_202101302000010319404301';
        $where   = [
            ['delete_flag','=','Y'],
            ['self_id','=',$self_id],
        ];
        $select  = ['self_id','group_name','type','bank_name','card_holder','card_number','group_code','default_flag'];
        $data['info'] = UserBank::where($where)->select($select)->first();
        $msg['code']  = 200;
        $msg['msg']   = "数据拉取成功";
        $msg['data']  = $data;
        //dd($msg);
        return $msg;
    }

    /**
     *获取账号  /tms/wallet/getAccount
     * */
    public function getAccount(Request $request){
        $user_info    = $request->get('user_info');//获取中间件中的参数
        $tms_account_type        = array_column(config('tms.account_type'),'name','key');
        $search = [
            ['delete_flag','=','Y'],
            ['use_flag','=','Y'],
            ['group_code','=',$user_info->group_code],
        ];

        $select  = ['self_id','group_name','type','bank_name','card_holder','card_number','group_code','default_flag'];

        $data['info']=UserBank::where($search)->select($select)->get();
        foreach ($data['info'] as $key =>$value){
            $value->account_type_show = $tms_account_type[$value->account_type]??null;
        }
        $msg['code']=200;
        $msg['msg']="数据拉取成功";
        $msg['data']=$data;
        return $msg;
    }


    /**
     * 添加常用支付宝或银行卡账号数据提交 /api/wallet/accountAdd
     * */
    public function accountAdd(Request $request){
        $project_type       =$request->get('project_type');
        $user_info  = $request->get('user_info');//接收中间件产生的参数
        $now_time   = date('Y-m-d H:i:s',time());
        $table_name ='user_bank';
        $input      =$request->all();

        /** 接收数据*/
        $self_id    = $request->input('self_id');
        $bank_name    = $request->input('bank_name');//开户行
        $card_holder = $request->input('card_holder'); //开户行姓名
        $card_number = $request->input('card_number');//开户行账号
        $type = $request->input('type');
        $default_flag = $request->input('default_flag');//是否默认
        /*** 虚拟数据
        $input['self_id']          = $self_id        = 'bank_202101302137376019510383';
        $input['bank_name']        = $bank_name      = '支付宝';
        $input['card_holder']      = $card_holder    = '李四';
        $input['card_number']      = $card_number    = '156456489787';
        $input['type']             = $type           = 'Alipay'; // 银行卡bank  支付宝Alipay
        $input['default_flag']     = $default_flag   = 'Y'; //是否默认 'Y' 是   'N' 否
         ***/

        $rules=[
            'card_number'=>'required',
            'card_holder'=>'required',
        ];
        $message=[
            'card_number.required'=>'请填写账号',
            'card_holder.required'=>'请填写户主名称',
        ];

        $validator = Validator::make($input,$rules,$message);
        if($validator->passes()) {
            if ($default_flag == 'Y'){
                $where = [
                    ['default_flag','=','Y'],
                    ['use_flag','=','Y'],
                    ['delete_flag','=','Y'],
                ];
                $update['default_flag'] = 'N';
                UserBank::where($where)->update($update);
            }
            $data['bank_name']              =$bank_name;
            $data['card_holder']            =$card_holder;
            $data['card_number']            =$card_number;
            $data['type']                   =$type;
            $data['default_flag']           = $default_flag;

            $wheres['self_id'] = $self_id;

            $old_info = UserBank::where($wheres)->first();
            switch ($project_type){
                case 'user':
                    $data['total_user_id']     = $user_info->total_user_id;
                    break;
                case 'carriage':
                    $data['total_user_id']     = $user_info->total_user_id;
                    break;
                case 'TMS3PL':
                    $data['group_code']     = $user_info->userIdentity->group_code;
                    $data['group_name']     =$user_info->userIdentity->group_name;
                    break;
                case 'company':
                    $data['group_code']     = $user_info->userIdentity->group_code;
                    $data['group_name']     =$user_info->userIdentity->group_name;
                    break;
                default:
                    break;
            }

            if($old_info){
                $data['update_time'] = $now_time;
                $id = UserBank::where($wheres)->update($data);
            }else{
                $data['self_id']          = generate_id('bank_');
                $data['create_time']      = $data['update_time']=$now_time;
                $id = UserBank::insert($data);
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

    /***    支付宝/银行卡禁用/启用      /api/wallet/accountUseFlag
     */
    public function accountUseFlag(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'user_bank';
        $medol_name  = 'UserBank';
        $self_id     = $request->input('self_id');
        $flag        = 'useFlag';
        // $self_id  = 'bank_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    /***    支付宝/银行卡账号删除     /api/wallet/accountDelFlag
     */
    public function accountDelFlag(Request $request,Status $status){
        $now_time    = date('Y-m-d H:i:s',time());
        $table_name  = 'user_bank';
        $medol_name  = 'UserBank';
        $self_id     = $request->input('self_id');
        $flag        = 'delFlag';
        // $self_id  = 'bank_202101111755143321983342';
        $status_info = $status->changeFlag($table_name,$medol_name,$self_id,$flag,$now_time);
        $msg['code'] = $status_info['code'];
        $msg['msg']  = $status_info['msg'];
        $msg['data'] = $status_info['new_info'];
        return $msg;
    }

    public function get_function(){

    }
}
