<?php
//返回每天释放总数
function check_jifen($user_id){
	if(!$user_id) return 0;
	$jifen = M('baodan')->where(['user_id'=>$user_id,'remain_days'=>['neq',0]])->sum('integral');
	return $jifen/200;
}
//提现额度
function getMoney($member_id,$levels){
	//查用户是不是贷款
    $bor = M('Borrow')->where($wheres)->find();
	if (count($bor)) {
        //用户代过款
        $levels = $user_obj['user_levels'] - 1;
    } else {
        //用户没代款
        $levels = $user_obj['user_levels'];
    }
	switch ($levels) {
        case 1:
            $top_vales = 2000;
            break;
        case 2:
            $top_vales = 10000;
            break;
        case 3:
            $top_vales = 20000;
            break;
        case 4:
            $top_vales = 60000;
            break;
        case 5:
            $top_vales = 100000;
            break;
        default:
            $top_vales = -1;
    }
    //提现总额
    $s_money = M('Withdraw')->where(['uid'=>$member_id,'status'=>['egt',2]])->sum('all_money');
    if($top_vales==-1) return '无'.$s_money;
    else return $s_money-$top_vales;
}
