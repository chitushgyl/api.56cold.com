<?php
namespace App\Http\Api\Login;
use App\Models\User\UserIdentity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Group\SystemGroup;
use App\Models\User\UserReg;
use App\Models\User\UserTotal;
use App\Models\User\UserTelCheck;
use App\Models\Log\LogLogin;
use App\Models\User\UserCapital;



class LoginController extends Controller{
    //获取微信用户基本信息
    function getDataWithCurl($url){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res,true);
    }


    /**
     * 用户授权登陆微信      /login/wx_login
     *
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息
     */
    public function wx_login(Request $request){
		$hosturl		=$request->get('hosturl');
		$group_code		=$request->get('group_code');
        $group_info		=null;
//        $group_code='1234';
		if($group_code){
			$where['group_code']=$group_code;
			$group_info=SystemGroup::with(['SystemGroup' => function($query) {
                $query->select('group_code','wx_pay_info');
            }])->where($where)->select('father_group_code','group_code')->first();
		}

		if($hosturl){
            $where['front_name']=$hosturl;
			$group_info=SystemGroup::with(['SystemGroup' => function($query) {
                $query->select('group_code','wx_pay_info');
            }])->where($where)->select('father_group_code','group_code')->first();

		}

        if($group_info){
			if($group_info->SystemGroup->wx_pay_info){
				$group_info2=json_decode($group_info->SystemGroup->wx_pay_info,true);
			}else{
				$group_info2['app_id']=config('page.platform.app_id');
				$group_info2['secret']=config('page.platform.secret');
			}

		}else{
			$group_info2['app_id']=config('page.platform.app_id');
			$group_info2['secret']=config('page.platform.secret');
		}


        //dump($group_info2);
		//dd($group_info2);


		if(is_wechat_client()){
			$get = $request->all();
			$url="http://{$_SERVER['HTTP_HOST']}/login/wxcallback";
			//dump($get);
			if($get){
				unset($get['s']);
				//判断redirect  将这个里面的？前面的拿出来，然后把这个链接中的？替换成&
				if(	strpos($get['redirect'], '?') !== false	){
					//说明包含？
					//把？替换成&11
					$get['redirect']=str_replace("?","&",$get['redirect']);
				}

				$url.='?path='.$get['redirect'];

				unset($get['redirect']);
				//unset($get['group_code']);
				//$get['group_code']=$group_code;
				//unset($get['redirect']);
				foreach($get as $k=>$v){
					$url.='&'.$k.'='.$v;
				}
			}

			//dd($url);
			$redirect_uri = urlencode($url);
			$config['app_id']=$group_info2['app_id'];
			$config['secret']=$group_info2['secret'];

			//dd($config);
			$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$config['app_id']."&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect";
			//dd($url);
			$str = '<script>window.location.href="'.$url.'";</script>';
			echo $str;die;

		}


    }

    /**
     * 用户授权登陆微信      /login/wxcallback
     *
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息11
     */

	public function wxcallback(Request $request) {
		$code           = $request->get('code');
		$group_code     = $request->get('group_code')??'1234';
//dd($code);
		//dump($group_code);
        $where['group_code']=$group_code;

        $group_info=SystemGroup::with(['SystemGroup' => function($query) {
            $query->select('group_code','wx_pay_info');
        }])->where($where)->select('father_group_code','group_code')->first();

//dump($group_info->toArray());
        $group_infoss=json_decode($group_info->SystemGroup->wx_pay_info,true);
        if(empty($group_info)){
            $group_infoss['app_id']=config('page.platform.app_id');
            $group_infoss['secret']=config('page.platform.secret');
        }

		$group_info->front_name=$group_info->front_name??config('page.platform.front_name');

		$config['app_id']=$group_infoss['app_id'];
		$config['secret']=$group_infoss['secret'];
//dd($config);
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$config['app_id'].'&secret='.$config['secret'].'&code='.$code.'&grant_type=authorization_code';
        $res = $this->getDataWithCurl($url);




		$userInfouri = "https://api.weixin.qq.com/sns/userinfo?access_token={$res['access_token']}&openid={$res['openid']}&lang=zh_CN";
        $userInfo =  $this->getDataWithCurl($userInfouri);

        /*** 拿到用户的信息后，先去数据库查询，看看有没有这个用户，如果有，则执行登录，然后没有，则执行注册后再执行登录**/
        $where_reg['token_id']  = $userInfo['openid'];
        $user_id                = UserReg::where($where_reg)->value('self_id');
        $now_time               =date('Y-m-d H:i:s',time());
        $reg_place                   ='CT_H5';

        if($user_id){
            $user_token_re         =$this->addToken($user_id,$reg_place,$now_time);
            $ftoken=$user_token_re['ftoken'];
            $dtoken=$user_token_re['dtoken'];
        }else{
            $info['ip']         = $request->getClientIp();
            $info['app_id']     =$config['app_id'];
            $info['promo_code'] =$request->get('promo_code');
            $reg_type           ='WEIXIN';
            $userInfo['tel']    = null;
            $userInfo['true_name']= null;
            $self_id            =$this->addUser($userInfo,$info,$reg_type,$reg_place,$now_time);
            $user_token_re         =$this->addToken($self_id,$reg_place,$now_time);
            $ftoken=$user_token_re['ftoken'];
            $dtoken=$user_token_re['dtoken'];
        }




		$get = $request->all();

		$url='http://'.$get['hosturl'].'/login/login/';


//dump($url);
		//dump($get);
		if($get){
			unset($get['s']);
			unset($get['code']);
			unset($get['state']);
			$path=$get['hosturl'].$get['path'];
			unset($get['hosturl']);
			unset($get['path']);
			//unset($get['group_code']);
			foreach($get as $k=>$v){
				$path.='*'.$k.'='.$v;
			}

			$url.='?path='.$path.'&user_token='.$ftoken.'&dtoken='.$dtoken;
		}


		//dd($url);

			$str = '<script>window.location.href="'.$url.'";</script>';
			echo $str;die;


	}



    /**
     * 用户授权登陆小程序      /login/mini_login
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息
     */
    public function mini_login(Request $request){
        //第一步，拿到前端传递过来的code
        $code                   =$request->input('code');
        $raw_data               =$request->input('raw_data');
        $iv                     =$request->input('iv');
        $encrypted_data         =$request->input('encrypted_data');

        if($code){
			$config                 =config('page.smallRoutine');
            $appId                  = $config['appId'];
            $appSecret              = $config['appSecret'];
            //第二步，通过code去换取用户的信息
            $url        = 'https://api.weixin.qq.com/sns/jscode2session?appid='.$appId.'&secret='.$appSecret.'&js_code='.$code.'&grant_type=authorization_code';

            $html       = file_get_contents($url);
            $response   = json_decode($html);

            $userInfo['openid'] =$response->openid;
            if($raw_data){
                $infos = json_decode($raw_data);
                $userInfo['headimgurl']     =$infos->avatarUrl;
                $userInfo['nickname']       =$infos->nickName;
            }

            /**   拿取用户的unionId  */
            $session_key        =$response->session_key;
            $aesKey             =base64_decode($session_key);
            $aesIV              =base64_decode($iv);
            $aesCipher          =base64_decode($encrypted_data);
            if($aesKey && $aesIV && $aesCipher){
                $result=openssl_decrypt( $aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
                $dataObj=json_decode( $result,true );
                $userInfo['unionid']=$dataObj['unionId'];
            }else{
                $userInfo['unionid']=null;
            }

            /*** 拿到用户的信息后，先去数据库查询，看看有没有这个用户，如果有，则执行登录，然后没有，则执行注册后再执行登录**/
            $where_reg['token_id']      = $userInfo['openid'];
            $user_id                = UserReg::where($where_reg)->value('self_id');
            $now_time               =date('Y-m-d H:i:s',time());
            $reg_type               ='MINI';

            if($user_id){
                $user_token         =$this->addToken($user_id,$reg_type,$now_time);
            }else{
                $info['ip']         = $request->getClientIp();
                $info['app_id']     = $appId;
                $info['promo_code'] = null;
                $userInfo['tel']    = null;
                $reg_place           ='MINI';
                $userInfo['true_name']= null;
                $self_id            =$this->addUser($userInfo,$info,$reg_type,$reg_place,$now_time);
                $user_token         =$this->addToken($self_id,$reg_type,$now_time);
            }

            $msg['code']=200;
            $msg['msg']='注册成功！';
            $msg['data']=$user_token;

            return  $msg;

        }else{
            $msg['code']=300;
            $msg['msg']='缺少必要的参数！';
            $msg['data']=null;
            return $msg;
        }
        //dd($msg);

    }

    /**
     * 手机号码授权登录      /login/tel_login
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息
     */
    public function tel_login(Request $request){
        /** 需要做一个开关，是TEL完成注册的时候需要不需要合并账号，Y合并，N不合并，如果需要合并账号，则修改数据完成合并，合并需要修改很多东西**/
        $flag='Y';
        $user_token     =$request->header('ftoken');
        $mini_token     =$request->get('ftoken');
        $user_token     =$user_token??$mini_token;
        $input			=$request->all();
        $now_time       =date('Y-m-d H:i:s',time());
        /** 接收数据*/
        $tel                           =$request->post('tel');
        $code                          =$request->post('code');
        $type                          =$request->input('type');
        $id_type                          =$request->input('id_type');
//        $father_tel                    =$request->get('father_tel');
        $reg_place                     =$request->get('reg_type')??'MINI';
        $true_name                     =$request->get('true_name');

		//DUMP($father_tel);

        /** 虚拟一下数据来做下操作*/
//        $input['tel']       =$tel   ='18538712250';
//        $input['code']      =$code  ='1234';
        //$user_token         ='82d47920dee6d7a5b22c647cbf3c03d9';
        //$true_name          ='221';
		//dump($tel);dd($code);
        $rules = [
            'tel' => 'required',
            'code' => 'required',
        ];
        $message = [
            'tel.required' => '手机号码不能为空',
            'code.required' => '手机验证码不能为空',
        ];

        $validator = Validator::make($input,$rules,$message);

        if($validator->passes()){
//            $father_where['tel']=$father_tel;
//			$father_where['delete_flag']='Y';
//            $select=['father_user_id1','father_user_id2','father_user_id3','father_user_id4','father_user_id5','father_user_id6','father_user_id7','self_id'];
//            $father=UserTotal::where($father_where)->select($select)->first();
//
//			//dump($father);
//            if(empty($father)){
//                $msg['code']=306;
//                $msg['msg']="推广员手机号码不存在，请核实";
//                return $msg;
//            }
            if ($type == 'APP'){
                $user_token = null;
            }
            $tins=date("Y-m-d H:i:s",strtotime("-5 minute"));
//
            $where=[
                ['tel','=',$tel],
                ['send_type','=','verify'],
                ['check_flag','=','Q'],
                ['create_time','>=',$tins],
            ];

            $message=UserTelCheck::where($where)->orderBy('create_time','desc')->value('message');

            if($message){
                if($message != $code){
                    $msg['code']=301;
                    $msg['msg']="验证码不正确，请重新输入";
                    return $msg;
                }
            }else{
                $msg['code']=302;
                $msg['msg']="手机或验证码无效，请重新检查";
                return $msg;
            }

            /** 把用户的这个验证码的姿态修改成已验证*/
            $data_check['check_flag']='Y';
            $data_check['update_time']=$now_time;
            UserTelCheck::where($where)->orderBy('create_time','desc')->update($data_check);

            /***如果这个用户是登录状态的情况下，则需要拿去到这个用户当前登录的账号total_user_id
            连表查询出，链接REG**/
            $token_where=[
                ['user_token','=',$user_token],
                ['delete_flag','=','Y'],
                ['type','!=','after'],
            ];
            $token_info=LogLogin::with(['userReg' => function($query) {
                $query->select('self_id','total_user_id');
            }])->where($token_where)->select('user_id','user_token','type')->first();
            //DUMP($token_info);
            /**    手机号码和验证码通过了**/

            $reg_type                   ='TEL';
            $where_reg=[
                ['tel','=',$tel],
                ['reg_type','=',$reg_type],
            ];
            $self_id = $total_id=UserReg::where($where_reg)->value('total_user_id');
            /** 这里是要判断到底做什么的地方
             *  先判断是不是登录状态，如果是登录状态，则比较复杂，如果非登录状态则再判断这个手机号码有还是没有
             *
             *
             *  有几种情况，1，如果不合并$flag='N' ，那么不管怎么样，都需要完成数据追加
             *              2，如果是合并，又是TEL已有数据，则需要将数据合并到TEL中来
             *              3，如果合并，TEL，没有数据，则追加进去数据即可
             **/
            if ($self_id){
                $where_identity = [
                    ['total_user_id','=',$self_id],
                    ['type','=',$id_type],
                    ['delete_flag','=','Y'],
                    ['use_flag','=','Y']
                ];
                $count_identity = UserIdentity::where($where_identity)->first();
                if (!$count_identity){
                    if($id_type == 'user'){
                        $identity_type = '赤途承运端';
                    }else{
                        $identity_type = '赤途冷链';
                    }
                    $msg['code'] = 305;
                    $msg['msg']  = '您已注册'.$identity_type.'同一账号只能注册一个身份';
                    return $msg;
                }

            }
            if($token_info){
                if($flag== 'N'){
                    //登录状态，如果合并账号为N，说明不需要合并，那么就可以提醒用户无需登录
                    $msg['code']=301;
                    $msg['msg']='无需登录';
                    return $msg;

                }else{
                    //这里是最复杂的登录状态，又要和合并账号，这个时候触发的逻辑就是手机号码有没有完成注册了！！！

                        if($self_id){
                            //把登录的信息合并到手机号码中去，修改当前登录状态的total_user_id 为$total_id
                            $reg_up['total_user_id']    =$total_id;
                            $reg_up['tel']              =$tel;
                            $reg_up['update_time']      =$now_time;

                            $where_reg_up['total_user_id']=$token_info->userReg->total_user_id;
                            $id=UserReg::where($where_reg_up)->update($reg_up);
							//DD(222);


                            //还要把原来的那个reg表total_user_id 去掉中的账号在UserTotal 表中做个删除操作，不然后台会显示一个空数据
                            $total_up['delete_flag']      ='N';
                            $total_up['true_name']        =$true_name;
                            $total_up['update_time']      =$now_time;
                            $total_up['password']         =get_md5('123456');
                            $where_total_up2['self_id']=$token_info->userReg->total_user_id;
                            UserTotal::where($where_total_up2)->update($total_up);

							//DD(1111);
                        }else{
                            //手机号码需要完成注册
                            $info['ip']             = $request->getClientIp();
                            $info['app_id']         = null;
                            $info['promo_code']     = null;
                            $userInfo['openid']     =null;
                            $userInfo['headimgurl'] =null;
                            $userInfo['nickname']   =null;
                            $userInfo['unionid']    =null;
                            $userInfo['tel']        =$tel;
                            $total_id=$token_info->userReg->total_user_id;
                            $self_id  =$this->addUser($userInfo,$info,$reg_type,$reg_place,$now_time,$total_id,$flag);
                            //手机号码没有注册，则添加手机号码进入当前登录账号，且添加tel进去total表

                            //写REG，total   把手机号码写进去
                            $reg_up['tel']              =$tel;
                            $reg_up['update_time']      =$now_time;
                            $where_reg_up['total_user_id']=$token_info->userReg->total_user_id;
                            $id=UserReg::where($where_reg_up)->update($reg_up);

                            $where_total_up['self_id']      =$token_info->userReg->total_user_id;
                            $reg_up['true_name']            =$true_name;
                            $reg_up['password']         =get_md5('123456');
//                            for($i=2;$i<9;$i++){
//                                $fwfhiwf='father_user_id'.$i;
//                                $j=$i-1;
//                                $fewfew="father_user_id".$j;
//                                $reg_up[$fwfhiwf]=$father->$fewfew;
//                            }
//                            //dd($father);
//                            $reg_up['father_user_id1']    =$father->self_id;

                            UserTotal::where($where_total_up)->update($reg_up);
                        }

                    if($id){
                        $msg['code']=201;
                        $msg['msg']='绑定成功！';
                        return $msg;
                    }else{
                        $msg['code']=202;
                        $msg['msg']='手机号码注册成功！';
                        return $msg;
                    }

                }
            }else{
                //这里是非登录状态，非登录状态，非登录状态不需要合并账号
                //完成登录
                if($self_id){
                    //用手机号码登录
                    $user_token_re         =$this->addToken($self_id,$reg_place,$now_time,$id_type);
                    $msg['code']=200;
                    $msg['msg']='登录成功！';
                    $msg['ftoken']=$user_token_re['ftoken'];
                    $msg['dtoken']=$user_token_re['dtoken'];
                    return $msg;

                }else{
                    $info['ip']             = $request->getClientIp();
                    $info['app_id']         = null;
                    $info['promo_code']     = null;
                    $userInfo['openid']     =null;
                    $userInfo['headimgurl'] =null;
                    $userInfo['nickname']   =null;
                    $userInfo['unionid']    =null;
                    $userInfo['true_name']  =$true_name;
                    $userInfo['tel']        =$tel;
                    $self_id  =$this->addUser($userInfo,$info,$reg_type,$reg_place,$now_time,$total_id,$flag);

                    $user_token_re         =$this->addToken($self_id,$reg_place,$now_time,$id_type);
                    $msg['code']=200;
                    $msg['msg']='注册成功！';
                    $msg['ftoken']=$user_token_re['ftoken'];
                    $msg['dtoken']=$user_token_re['dtoken'];
                    return $msg;
                }
            }

        }else{
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=$erro[0];
            return $msg;
        }
    }

    /**账号密码登陆
     * /login/account_login
     **/
    public function account_login(Request $request){
        $user_token     =$request->header('ftoken');
        $mini_token     =$request->get('ftoken');
        $user_token     =$user_token??$mini_token;
        $input			=$request->all();
        $now_time       =date('Y-m-d H:i:s',time());
        /** 接收数据*/
        $tel                           =$request->input('tel');
        $password                          =$request->input('password');
        $type                         = $request->input('id_type');
        //DUMP($father_tel);

        /** 虚拟一下数据来做下操作
        $input['tel']       = $tel   ='15966351512';
        $input['password']  = $password  ='123456';
        $input['id_type']   = $type = 'user';
         */
        $rules = [
            'tel' => 'required',
            'password' => 'required',
        ];
        $message = [
            'tel.required' => '手机号码不能为空',
            'password.required' => '密码不能为空',
        ];

        $validator = Validator::make($input,$rules,$message);

        if($validator->passes()){

            /**判断是否注册**/
            $where_reg=[
                ['tel','=',$tel],
                ['delete_flag','=','Y'],
            ];
            $user_id=UserTotal::where($where_reg)->select('self_id','password','tel','delete_flag')->first();

            if ($user_id){
                $user_identity_where = [
                    ['total_user_id','=',$user_id->self_id],
                    ['type','=',$type],
                    ['delete_flag','=','Y'],
                    ['use_flag','=','Y']
                ];
                $count_identity = UserIdentity::where($user_identity_where)->first();
                if (!$count_identity){
                    if($type == 'user'){
                        $identity_type = '赤途承运端';
                    }else{
                        $identity_type = '赤途冷链';
                    }
                    $msg['code'] = 305;
                    $msg['msg']  = '您已注册'.$identity_type.',同一账号只能注册一个身份';
                    return $msg;
                }
            }

            if ($user_id){
                if ($user_id->password){
                    if ($user_id->password != get_md5($password)){
                        $msg['code']=301;
                        $msg['msg']='请输入正确的密码！';
                        return $msg;
                    }
                }else{
                    $update['password'] = get_md5($password);
                    $update['update_time'] = $now_time;
                    UserTotal::where($where_reg)->update($update);
                }

                //用手机号码登录
                $user_token_re         =$this->addToken($user_id->self_id,'',$now_time,$type);
                $msg['code']=200;
                $msg['msg']='登录成功！';
                $msg['project_type'] = $user_token_re['project_type'];
                $msg['ftoken']=$user_token_re['ftoken'];
                $msg['dtoken']=$user_token_re['dtoken'];
                return $msg;
            }else{
                $info['ip']             = $request->getClientIp();

                $data['self_id']  = $data['total_user_id']      = generate_id('user_');
                $data['reg_type']       = 'TEL';
                $data['tel']            = $tel;
                $data['reg_place']      = 'CT_H5';
                $data['ip']             = $info['ip'];
                $data['token_appid']    = null;
                $data['token_id']       = null;
                $data['token_img']      = null;
                $data['token_name']     = null;
                $data['create_time']    = $data['update_time']       =$now_time;

                UserReg::insert($data);									//写入用户表

                $data_total['self_id']      = $data['total_user_id'];
                $data_total['tel']          = $tel;
                $data_total['password']     = get_md5($password);
                $data_total['promo_code']   = md5($data_total['self_id'].$now_time);
                $data_total['true_name']    =null;
                $data_total['create_time']  =$data_total['update_time']=$now_time;

                UserTotal::insert($data_total);							//写入用户主表

                $capital_data['self_id']        = generate_id('capital_');
                $capital_data['total_user_id']  = $data['total_user_id'];
                $capital_data['update_time']    =$now_time;
                UserCapital::insert($capital_data);						//写入用户资金表

                $user_token_re         =$this->addToken($data['total_user_id'],'',$now_time,$type);
                $msg['code']=200;
                $msg['msg']='注册成功！';
                $msg['project_type'] = $user_token_re['project_type'];
                $msg['ftoken']=$user_token_re['ftoken'];
                $msg['dtoken']=$user_token_re['dtoken'];
                return $msg;
            }


        }else{
            $erro=$validator->errors()->all();
            $msg['code']=300;
            $msg['msg']=$erro[0];
            return $msg;
        }
    }

    /**
     * 小程序审核处理      /login/anniu_show
     * 回调结果：200  注册成功
     *          300  注册失败,数据库没有写入
     *          100  缺少必要的参数
     *
     *回调数据：  用户信息
     */

    public function anniu_show(Request $request){
        //传递一个user_token   手机号码，验证码，true_name过来

        $msg['code'] = 200;
        $abc['quan']='Y';  //首页授权按钮
        $abc['use']='N';  //首页立即使用按钮

        $abc['tel']='Y';    //欢迎页用户注册按钮
        $abc['invite']='Y'; //欢迎页邀请码绑定按钮
        $abc['child']='Y';  //欢迎页绑定孩子按钮

        $msg['data'] = $abc;

        return $msg;

    }


    /***将用户信息写入数据库***/
    public function addUser($userInfo,$info,$reg_type,$reg_place,$now_time,$total_id=null,$flag=null){
        /** 添加用户进入数据库，这个时候，需要做的是判断UserTotal  要不要进的问题
         *  当用户有unionid 的时候，在UserTotal  有数据，则不需要进入数据库中
         * 当用户使用手机号码来完成注册的时候，什么时候需要写入UserTotal  表中
         **/
        //$flag='Y';
         if($flag == 'Y'){

         }else{
             $total_id=null;            //保证了如果是不合并账号的情况下 total_user_id  和   self_id  两个一致
         }

        $data['self_id']        = generate_id('user_');
        if($total_id){
            $data['total_user_id']=$total_id;
        }else{
            $data['total_user_id']=$data['self_id'];
        }

        $data['reg_type']       = $reg_type;
        $data['tel']            = $userInfo['tel'];
        $data['reg_place']      = $reg_place;
        $data['ip']             = $info['ip'];
        $data['token_appid']    = $info['app_id'];
        $data['token_id']       = $userInfo['openid'];
        $data['token_img']      = $userInfo['headimgurl'];
        $data['token_name']     = $userInfo['nickname'];
        $data['create_time']    = $data['update_time']       =$now_time;

        UserReg::insert($data);									//写入用户表

        /** 什么情况下需要写入UserTotal  和资金表，这2个应该是一起的，有一个必然有另外一个
         *  $falg='N'  说明不合并账号，那么就肯定是要写入，如果是Y的话，那么什么情况下需要写呢
         */
        if($flag == 'N'){
            $total_flag = 'Y';
        }else{
            //当合并账号的开关是Y的时候，这个时候，如果有传递过来的$total_id  ， 则说明资金啥的都有了不需要合并，如果没有这个值，说明要新加进去才可以
            if($total_id){
                $total_flag = 'N';
            }else{
                $total_flag = 'Y';
            }
        }
        /*** 以上定义一个是不是要写入UserTotal的开关**/

        if($total_flag == 'Y'){
            /** 做一个上下级的关系*/
            if($info['promo_code']){
                $where2['promo_code']       =$info['promo_code'];
                $select=['self_id','father_user_id1','father_user_id2','father_user_id3','father_user_id4','father_user_id5','father_user_id6','father_user_id7'];
                $father_info                =UserTotal::where($where2)->select($select)->first();
                $data_total['father_user_id1']    =$father_info->self_id;
                for($i=2;$i<9;$i++){
                    $fwfhiwf='father_user_id'.$i;
                    $j=$i-1;
                    $fewfew="father_user_id".$j;
                    $data_total[$fwfhiwf]=$father_info->$fewfew;
                }
            }
            /** 做一个上下级的关系结束*/

            $data_total['self_id']      = $data['total_user_id'];
            $data_total['tel']          = $userInfo['tel'];
            $data_total['password']     = get_md5('123456');
            $data_total['promo_code']   = md5($data_total['self_id'].$now_time);
            $data_total['true_name']    =$userInfo['true_name'];
            $data_total['create_time']  =$data_total['update_time']=$now_time;

            UserTotal::insert($data_total);							//写入用户主表

            $capital_data['self_id']        = generate_id('capital_');
            $capital_data['total_user_id']  = $data['total_user_id'];
            $capital_data['update_time']    =$now_time;
            UserCapital::insert($capital_data);						//写入用户资金表
        }

        //完成所有的操作后，我应该还给前面一个什么值？？如果是登录状态的话，我应该还一个true就可以了，如果是未登录状态，前面接口需要一个self_id 来做登录
            return $data['self_id'];
    }

    /***完成登录***/
    public function addToken($user_id,$reg_place,$now_time,$type){
		$ftoken                 = md5($user_id.$now_time);
        $dtoken                 = null;

        $data['self_id']        = generate_id('self_');
        $data['user_id']        = $user_id;
        $data['create_time']    = $now_time;
        $data['user_token']     = $ftoken;
        $data['type']           = $reg_place;
        LogLogin::insert($data);
        /** 以上是前端用户的登录**/

        $where = [
            ['self_id','=',$user_id],
        ];

        $where2 = [
            ['default_flag','=','Y']
        ];
        $where3 = [
           ['delete_flag','=','Y']
        ];
        $info=UserReg::with(['userTotal' => function($query) use($where2,$where3){
            $query->select('self_id');
            $query->with(['userIdentity' => function($query)use($where2,$where3) {
                $query->where($where2);
                $query->select('self_id','total_user_id','admin_login','type');
                $query->with(['systemAdmin' => function($query)use($where3) {
                    $query->where($where3);
                    $query->select('self_id','login','name','group_code','group_name');
                }]);
            }]);
        }])->where($where)->select('total_user_id')->first();
//        dd($info);
        if($info->userTotal && $info->userTotal->userIdentity && ($info->userTotal->userIdentity->type == 'TMS3PL' || $info->userTotal->userIdentity->type == 'company' ||$info->userTotal->userIdentity->type == 'dispatcher' ||$info->userTotal->userIdentity->type == 'business')){
            /** 后台完成登录*/
            $dtoken                      =md5($info->userTotal->userIdentity->systemAdmin->self_id.$now_time);
            $token_data['self_id']       = generate_id('login_');
            $token_data["user_token"]    =$dtoken;
            $token_data["user_id"]       =$info->userTotal->userIdentity->systemAdmin->self_id;
            $token_data["user_name"]     =$info->userTotal->userIdentity->systemAdmin->name;
            $token_data["group_code"]    =$info->userTotal->userIdentity->systemAdmin->group_code;
            $token_data["group_name"]    =$info->userTotal->userIdentity->systemAdmin->group_name;
            $token_data["login_status"]  ='SU';
            $token_data["login"]         =$info->userTotal->userIdentity->systemAdmin->login;
            $token_data['type']          = 'after';
            $token_data['create_time']   =$token_data['update_time']=$now_time;
            LogLogin::insert($token_data);
//dump($token_data);

        }
        if ($info->userTotal->userIdentity){
            $msg['project_type'] = $info->userTotal->userIdentity->type;
        }else{
            $msg['project_type'] = $type;
        }

        $msg['ftoken']=$ftoken;
        $msg['dtoken']=$dtoken;

        return $msg;


    }

}
?>
