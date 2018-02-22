<?php
$now = 1482163200;//2016-12-20
$tongji_time = $now -86400;
$start_time = strtotime(date('Y-m-d',$tongji_time));
$end_time = $start_time + 86400;
//查询用户数据
$list = M('member m')->distinct('true')
->join('__CURRENCY_USER__ c on c.member_id=m.member_id and c.currency_id=30','LEFT')
->field('m.member_id,m.user_name,m.integrals,m.daily_inc,c.num')->select();
foreach ($list as $v) {
	//充值积分
	$czjf = M('member_log')->where(['member_id'=>$v['member_id'],'addtime'=>['between',$start_time.','.$end_time]])->sum('integrals');
	//释放积分
	$sfjf = M('integrals_log')->where(['member_id'=>$v['member_id'],'title'=>'大盘赠送','addtime'=>['between',$start_time.','.$end_time]])->sum('num');
	//转入积分
	$zrjf = M('integrals_log')->where(['member_id'=>$v['member_id'],'title'=>'积分转入','addtime'=>['between',$start_time.','.$end_time]])->sum('num');
	//前天积分
	$qt = M('data')->where(['member_id'=>$v['member_id'],'datetime'=>$start_time-86400])->find();
	//买币使用积分
	$mb = M('trade')->field('price,num,(price*num) as total')->where(['member_id'=>$v['member_id'],'add_time'=>['between',$start_time.','.$end_time],'type'=>'buy'])->select();
	$mbjf = 0;//买币积分
	$mbgd = '';//买币挂单
	$mbsl = 0;//买币数量
	foreach ($mb as $m) {
		$mbjf += $m['total'];
		$mbsl += $m['num'];
		$mbgd .=$mb['num'].'*'.$mb['price'].'='.$m['total'].'<br>';
	}

	//充值ALPS
	$cz_alps1 = M('member_log')->where(['member_id'=>$v['member_id'],'addtime'=>['between',$start_time.','.$end_time]])->sum('num');
	$cz_alps2 = M('pay')->where(['member_id'=>$v['member_id'],'currency_id'=>30])->sum('money');
	//卖币挂单
	$nb = M('trade')->field('price,num,(price*num) as total')->where(['member_id'=>$v['member_id'],'add_time'=>['between',$start_time.','.$end_time],'type'=>'sell'])->select();
	$nbgd = '';//卖币挂单
	$nbsl = 0;//卖币数量
	$nbje = 0;//卖币金额
	foreach ($nb as $n) {
		$nbje += $n['total'];
		$nbsl += $n['num'];
		$nbgd .= $nb['num'].'*'.$nb['price'].'='.$n['total'].'<br>';
	}
	//提现金额
	$money = M('withdraw')->where(['uid'=>$v['member_id'],'checktime'=>['between',$start_time.','.$end_time],'status'=>2])->sum('all_money');

	$data = [];
	$data['member_id'] = $v['member_id'];
	$data['user_name'] = $v['user_name'];
	$data['sftime'] = $now;
	$data['datetime'] = $start_time;
	$data['czjf'] = $czjf;//充值积分
	$data['sfjf'] = $sfjf;//释放积分
	$data['zrjf'] = $zrjf;//转入积分
	$data['mbjf'] = $mbjf;//买币积分
	//$data['ztjf'] = $qt?($qt['sfjf']+$qt['zrjf']+$qt['czjf']+$qt['ztjf']-$mbjf):0;//昨日积分--昨日（释放积分+转入积分+充值积分+[昨日积分]）-买币使用积分=昨日积分;可注释掉
	//$data['kyjf'] = $data['czjf']+$data['sfjf']+$data['zrjf']+$data['ztjf'];//可用积分--今天释放积分+转入积分+充值积分+昨日积分=可用积分;可注释掉
	$data['mbgd'] = $mbgd;//买币挂单
	//$data['jfye'] = $data['kyjf']-$mbjf;//积分余额——可用积分-实际扣积分=积分余额;可注释掉
	$data['nbgd'] = $nbgd;//卖币挂单

	$data['zr_alps'] = $qt?$qt['ye_alps']:0;//昨日ALPS
	$data['cz_alps'] = $cz_alps1+$cz_alps2;//充值ALPS
	$data['km_alps'] = $data['zr_alps']+$mbsl;//可卖ALPS。
	$data['ye_alps'] = $data['km_alps']-$nbsl;//ALPS余额
	$data['zr_rmb'] = $qt?$qt['kt_rmb']:0;//昨日人民币
	$data['kt_rmb'] = $data['zr_rmb']+$nbje;//可提人民币
	$data['th_rmb'] = $data['kt_rmb']-$money;
	$data['status'] = 1;
	M('data')->add($data);
}