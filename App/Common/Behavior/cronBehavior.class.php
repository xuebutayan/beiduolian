<?php
namespace Common\Behavior;
class cronBehavior extends \Think\Behavior{
	public function run(&$param){
		$baodan = M('Baodan');
		//删除状态为0的任务
		$baodan->where(array('status'=>0))->delete();
		$udata = $baodan->field('user_id,nextupdate')->group("`user_id`")->select();

		$today = strtotime(date('Y-m-d',time()));
		//规定每天0:00更新报单积分任务
		if($udata)
		foreach ($udata as $u) {
			//今天与下次执行任务的时间差
			$days1 = ($today-$u['nextupdate'])/86400;
			if($days1<0) continue;
			$days = $days1+1;
			//情况1：剩余赠送积分天数少于当前执行计划任务时间
			$data = array();
			$data['user_id'] = $u['user_id'];
			$data['remain_days']= array('lt',$days);
			$data['nextupdate'] = array('elt',$today);
			$list = $baodan->where($data)->select();
			if($list){
				$jifen = 0;
				foreach ($list as $v) {
					$jiffen += $v['integral']*$v['remain_days']*0.005;
					$baodan->delete($v['oid']);
				}
				M('Member')->where(array('user_id'=>$u['user_id']))->setInc('integrals',$jifen);
			}

			//情况2：剩余赠送天数大于等于当前执行计划任务时间
			$data['remain_days']= array('egt',$days);
			$list = $baodan->where($data)->select();
			if($list){
				$jifen = 0;
				foreach ($list as $v) {
					$jiffen += $v['integral']*$days*0.005;
					if(($v['remain_days']-$days)<=0) $baodan->delete($v['oid']);
					else{
						$new_data = array();
						$new_data['remain_days'] = $v['remain_days']-$days;
						$new_data['nextupdate'] = $today+86400;
						$baodan->where(array('oid'=>$v['oid']))->save($new_data);
					}
				}
				M('Member')->where(array('user_id'=>$u['user_id']))->setInc('integrals',$jifen);
				M('Member')->where(array('user_id'=>$u['user_id']))->setField('daily_inc',$jifen);
			}
		}
	}
}