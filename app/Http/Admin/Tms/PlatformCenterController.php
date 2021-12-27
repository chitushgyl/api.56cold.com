<?php
namespace App\Http\Admin\tms;

use App\Http\Controllers\CommonController;
use App\Models\Tms\TmsLine;
use App\Models\Tms\TmsOrder;
use App\Models\User\UserIdentity;
use Illuminate\Http\Request;

/**
 * 数据中台面板
 * */

class PlatformCenterController extends  CommonController{


    public function index(Request $request){
        $user_info      = $request->get('user_info');//接收中间件产生的参数
        $group_info     = $request->get('group_info');
//         dd($user_info,$group_info);
        /** 用户总量统计 ***/
        //货主端用户总数
        $date =  dateTime();
        $user_count = UserIdentity::where(['use_flag'=>'Y','type'=>'user','delete_flag'=>'Y'])->count();

        //承运端用户总数
        $driver_count = UserIdentity::where(['use_flag'=>'Y','type'=>'carriage','delete_flag'=>'Y'])->count();

        //用户总数
        $all_user_count = $user_count + $driver_count;
        /** 当天 本周 当月用户数量***/
        $day_where = [
            ['create_time','>=',$date['start_time']],
            ['create_time','<=',$date['end_time']],
        ];
        $week_where = [
            ['create_time','>=',$date['start_week']],
            ['create_time','<=',$date['end_week']],
        ];
        $month_where = [
            ['create_time','>=',$date['start_month']],
            ['create_time','<=',$date['end_month']],
        ];
        $where = [
            ['use_flag','=','Y'],
            ['delete_flag','=','Y'],
        ];
        //当天
        $user_day_count = UserIdentity::where(['type'=>'user'])->where($where)->where($day_where)->count();
        $driver_day_count = UserIdentity::where(['type'=>'carriage'])->where('type','!=','TML3PL')->where($day_where)->count();
        $company_day_count = UserIdentity::where(['type'=>'TML3PL'])->where($day_where)->count();

        //本周
        $user_week_count = UserIdentity::where(['type'=>'user'])->where($where)->where($week_where)->count();
        $driver_week_count = UserIdentity::where(['type'=>'carriage'])->where($where)->where('type','!=','TML3PL')->where($week_where)->count();
        $company_week_count = UserIdentity::where(['type'=>'TML3PL'])->where($where)->where($week_where)->count();

        //本月
        $user_month_count = UserIdentity::where(['type'=>'user'])->where($where)->where($month_where)->count();
        $driver_month_count = UserIdentity::where(['type'=>'carriage'])->where($where)->where('type','!=','TML3PL')->where($month_where)->count();
        $company_month_count = UserIdentity::where(['type'=>'TML3PL'])->where($where)->where($month_where)->count();

        $user_finance['user_count'] = $user_count;
        $user_finance['driver_count'] = $driver_count;
        $user_finance['all_count'] = $all_user_count;

        $type_finance['day'] = array(
            'user_day_count' => $user_day_count,
            'driver_day_count' => $driver_day_count,
            'company_day_count' => $company_day_count,
        );
        $type_finance['week'] = array(
            'user_week_count' => $user_week_count,
            'driver_week_count' => $driver_week_count,
            'company_week_count' => $company_week_count,
        );
        $type_finance['month'] = array(
            'user_month_count' => $user_month_count,
            'driver_month_count' => $driver_month_count,
            'company_month_count' => $company_month_count,
        );

        $msg['code'] = 200;
        $msg['msg']  = '查询成功！';
        $msg['user_finance'] = $user_finance;
        $msg['type_finance'] = $type_finance;

        return $msg;

    }

    /**
     * 数据中台 线路
     * */

    public function order_count(Request $request){
        $user_info      = $request->get('user_info');//接收中间件产生的参数
        $group_info     = $request->get('group_info');
        $date = dateTime();
        /** 计算各个类型订单数量**/
//        当天
        $day_where = [
            ['create_time','>=',$date['start_time']],
            ['create_time','<=',$date['end_time']],
        ];
        $week_where = [
            ['create_time','>=',$date['start_week']],
            ['create_time','<=',$date['end_week']],
        ];
        $month_where = [
            ['create_time','>=',$date['start_month']],
            ['create_time','<=',$date['end_month']],
        ];

        $where = [
            ['order_status','in',[2,3,4,5,6]],
            ['delete_flag','=','Y']
        ];
        $vehical_day_count = TmsOrder::where(['order_type'=>'vehicle'])->where($where)->where($day_where)->count();
        $line_day_count = TmsOrder::where(['order_type'=>'line'])->where($where)->where($day_where)->count();
        $lift_day_count = TmsOrder::where(['order_type'=>'lift'])->where($where)->where($day_where)->count();
//         本周
        $vehical_week_count = TmsOrder::where(['order_type'=>'vehicle'])->where($where)->where($week_where)->count();
        $line_week_count = TmsOrder::where(['order_type'=>'line'])->where($where)->where($week_where)->count();
        $lift_week_count = TmsOrder::where(['order_type'=>'lift'])->where($where)->where($week_where)->count();

//        本月
        $vehical_month_count = TmsOrder::where(['order_type'=>'vehicle'])->where($where)->where($month_where)->count();
        $line_month_count = TmsOrder::where(['order_type'=>'line'])->where($where)->where($month_where)->count();
        $lift_month_count = TmsOrder::where(['order_type'=>'lift'])->where($where)->where($month_where)->count();

        $order_finance['day'] = array(
            'vehical_day_count' => $vehical_day_count,
            'line_day_count' => $line_day_count,
            'lift_day_count' => $lift_day_count,
        );
        $order_finance['week'] = array(
            'vehical_week_count' => $vehical_week_count,
            'line_week_count' => $line_week_count,
            'lift_week_count' => $lift_week_count,
        );
        $order_finance['month'] = array(
            'vehical_month_count' => $vehical_month_count,
            'line_month_count' => $line_month_count,
            'lift_month_count' => $lift_month_count,
        );

        $msg['code'] = 200;
        $msg['msg']  = '查询成功！';
        $msg['order_finance'] = $order_finance;
        return $msg;
    }

    /**
     * 数据中台 线路统计
     * */
    public function line_count(Request $request){
        $group_info = $request->get('group_info');
        $user_info = $request->get('user_info');
        $date = dateTime();
        /** 线路数量***/
        $line_count = TmsLine::where(['use_flag'=>'Y','delete_flag'=>'Y'])->count();
        $start_city = TmsLine::where(['use_flag'=>'Y','delete_flag'=>'Y'])->pluck('send_shi_name')->toArray();
        $start_city_count = count(array_unique($start_city));
        $end_city = TmsLine::where(['use_flag'=>'Y','delete_flag'=>'Y'])->pluck('gather_shi_name')->toArray();
        $end_city_count = count(array_unique($end_city));

        // 线路排名
        $day_where = [
            ['create_time','>=',$date['start_time']],
            ['create_time','<=',$date['end_time']],
        ];
        $week_where = [
            ['create_time','>=',$date['start_week']],
            ['create_time','<=',$date['end_week']],
        ];
        $month_where = [
            ['create_time','>=',$date['start_month']],
            ['create_time','<=',$date['end_month']],
        ];
        $line_day_count = $this->count_line($day_where);
        $line_week_count = $this->count_line($week_where);
        $line_month_count = $this->count_line($month_where);

        $city_count['start_city'] = $start_city_count;
        $city_count['end_city'] = $end_city_count;

        $msg['code'] = 200;
        $msg['msg']  = '查询成功！';
        $msg['city_count'] = $city_count;
        $msg['day'] = $line_day_count;
        $msg['week'] = $line_week_count;
        $msg['month'] = $line_month_count;
        return $msg;
    }

    public function count_line($where){
        $line_count = TmsLine::with(['tmsOrder' => function($query){
            $query->select('line_id','self_id');
            $query->count('line_id');
        }])
            ->select('send_shi_name','gather_shi_name','self_id','group_code','group_name')
            ->limit(10)
            ->where($where)
            ->get();
        if ($line_count){
            foreach ($line_count as $key =>$value){
                $line_count[$key]['count'] = count($value->tmsOrder);
            }
            $line_count2 = $line_count->toArray();

            $line_count1 = array_column($line_count2,'count');
            array_multisort($line_count1,SORT_DESC,$line_count2);
            $line_count = $line_count2;
        }else{
            $line_count = [];
        }
        return $line_count;
    }

    /**
     *司机数量
     * */
    public function  driver_count(Request $request){
        $group_info = $request->get('group_info');
        $user_info = $request->get('user_info');

    }
























































}


