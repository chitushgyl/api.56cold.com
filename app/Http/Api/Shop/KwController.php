<?php
namespace App\Http\Api\Shop;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User\UserKw;
use App\Models\Shop\ShopKw;


class KwController extends Controller{
    /**
     * 搜索显示     /shop/serch
     * 前端传递必须参数：group_code
     *前端传递非必须参数：user_token(用户token)
     *
     * 回调结果：200  数据拉取成功
     *
     *回调数据：  搜索关键字信息
     */
    public function serch(Request $request){
        $user_info      =$request->get('user_info');
        $group_info     =$request->get('group_info');
        $group_code     =$group_info->group_code??config('page.platform.group_code');
		/** 虚拟数据*/
        //$user_id='user_202001192116520483548783';
        //dump($user_info->toArray());
		//dd($user_id);
        //个人的搜索历史
        if($user_info){
            $timnmes    =date('Y-m-d H:i:s',strtotime('-7 day'));
            $user_kw_where=[
                ['total_user_id','=',$user_info->total_user_id],
                ['use_flag','=','Y'],
                ['delete_flag','=','Y'],
                ['create_time','>',$timnmes],
            ];
			
            $user_kw_info=UserKw::where($user_kw_where)->orderBy('create_time','desc')->pluck('keyword_name');
//			dd($user_kw_info->toArray());

        }else{
            $user_kw_info=null;
        }

		//配置的热搜选项
		$index_kw_where=[
            ['group_code','=',$group_code],
            ['kw_type','=','head'],
        ];
		$keyword_head_info=ShopKw::where($index_kw_where)->value('keyword_name');

//        dd($keyword_head_info);
		$kw_where=[
            ['group_code','=',$group_code],
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
            ['kw_type','=','classifys'],
        ];


        $keyword_info=ShopKw::with(['shopKw' => function($query) {
            $query->select('self_id as keyword_id','kw_classifys_id','keyword_name','img','emphasis_flag');
        }])->where($kw_where)->orderBy('sort','asc')
            ->select('self_id','self_id as kw_classifys_id','kw_classifys_name','img','emphasis_flag')
            ->get();





        foreach($keyword_info as $k => $v){
            $v->img=img_for($v->img,'more');

            foreach($v->shopKw as $kk => $vv){
                $vv->img=img_for($vv->img,'more');
            }
        }

        //dd($keyword_info->toArray());

        $pv['user_id']=$user_info?$user_info->total_user_id:null;
		$pv['browse_path']=$request->path();
		$pv['level']=null;
		$pv['table_id']=null;
		$pv['ip']=$request->getClientIp();
		$pv['place']='CT_H5';
//		pvuv($pv);
			
			
        //dd($keyword_info);
        $msg['code']=200;
        $msg['msg']='数据拉取成功！';
        $msg['user_data']=$user_kw_info;
        $msg['data']=$keyword_info;
		$msg['keyword_head_info']=$keyword_head_info;
		//dd($msg);
        return $msg;
    }

    /**
     * 搜索结果记录用户搜索数据      /shop/result
     * 前端传递必须参数：serach（搜索内容）
     *前端传递非必须参数：user_token(用户token)    kw_id（系统的关键字ID）
     *
     * 回调结果：200  记录成功
     *
     *回调数据：
     */
    public function result(Request $request){
        //用户信息抓取
        //搜索结果页面进入用户搜索历史数据
        $user_info      =$request->get('user_info');
        $group_info     =$request->get('group_info');
        $group_code     =$group_info->group_code??config('page.platform.group_code');
        $now_time       =date('Y-m-d H:i:s',time());

		$kw_id          =$request->input('keyword_id');
		$serach         =$request->input('keyword_name');
		//dd($request->all());
		/** 虚拟数据*/
        //$kw_id='keyword201912301401543284372346';
        //$serach='1234544545';
		//dd($kw_id);
        if($serach){
            $data['self_id']        =generate_id('keyword_');
            $data['total_user_id']  =$user_info?$user_info->total_user_id:null;
            $data['keyword_name']   =$serach;
			$data['group_code']     =$group_code;
            if($kw_id){
                $where_fre['self_id']=$kw_id;
                $qiao=ShopKw::where($where_fre)->select('kw_classifys_id','kw_classifys_name')->first();
				if($qiao){
					$data['kw_classifys_id']        =$qiao->kw_classifys_id;
					$data['kw_classifys_name']      =$qiao->kw_classifys_name;
				}

                $data['create_time']    =$data['update_time']   =$now_time;
            }
			//dd($data);
            $id=ShopKw::insert($data);
			if($id){
                $msg['code']=200;
                $msg['msg']='用户搜索记录成功';
                //dd($msg);
                return $msg;
			}else{
                $msg['code']=301;
                $msg['msg']='用户搜索记录失败';
                //dd($msg);
                return $msg;
			}

        }else{
            $msg['code']=302;
            $msg['msg']='没有搜索条件';
            //dd($msg);
            return $msg;

		}



    }

    /**
     * 用户搜索历史删除      /shop/result_del
     * 前端传递必须参数：serach（搜索内容）
     *前端传递非必须参数：user_token(用户token)    kw_id（系统的关键字ID）
     *
     * 回调结果：200  记录成功
     *
     *回调数据：
     */
    public function result_del(Request $request){
        $user_info      =$request->get('user_info');
		$keyword_id     =$request->input('keyword_id');
        $now_time       =date('Y-m-d H:i:s',time());
		/** 虚拟数据*/
        //$user_id='user_202001192116520483548783';
        //$keyword_id='keyword_202001272328034992336167';

		if($keyword_id){
			$kw_delete_where=[
				['self_id','=',$keyword_id],
				['total_user_id','=',$user_info->total_user_id],
				['use_flag','=','Y'],
				['delete_flag','=','Y'],
			];
		}else{
			$kw_delete_where=[
				['total_user_id','=',$user_info->total_user_id],
				['use_flag','=','Y'],
				['delete_flag','=','Y'],
			];
		}

		$dat['delete_flag']='N';
		$dat['delete_time']=$now_time;
		$id=ShopKw::where($kw_delete_where)->update($dat);
		if($id){
			$msg['code']=200;
			$msg['msg']='删除成功';
            return $msg;
		}else{
			$msg['code']=301;
			$msg['msg']='删除失败';
            return $msg;
		}



    }
}
?>
