<?php
/*
status说明：0待审核,-1审核未通过,1审核通过,2已贷款,3已还款
 */
namespace Home\Controller;
use Common\Controller\CommonController;
use Common\Model\BorrowModel;
class BorrowController extends CommonController{
	function index(){
		$borrow = M('Borrow');
		//当前用户状态
		$member_id = intval($this->member['member_id']);
		$info = $borrow->where(['member_id'=>$member_id])->find();
		$this->assign('borrow_info',$info);
		//贷款申请列表
		$list1 = M('Borrow b')
			->field('b.*,m.user_name')
			->join('__MEMBER__ m on b.member_id=m.member_id','LEFT')
			->where(['b.status'=>0])->limit(20)->order('b.applydate desc')->select();
		//已贷款列表
		$list2 = M('Borrow b')
			->field('b.*,m.user_name')
			->join('__MEMBER__ m on b.member_id=m.member_id','LEFT')
			->where(['b.status'=>2])->limit(20)->order('b.allowdate desc')->select();
		//单字符输出
		$bstr1 = (string)round($this->config['borrow_success']);
        for($i=0;$i<strlen($bstr1);$i++){
            $barr1[strlen($bstr1)-1-$i] = $bstr1[$i];
        }
        $this->assign('barr1',$barr1);
        $bstr2 = (string)round($this->config['invest_money']);
        for($i=0;$i<strlen($bstr2);$i++){
            $barr2[strlen($bstr2)-1-$i] = $bstr2[$i];
        }
        $this->assign('barr2',$barr2);
		$this->assign('list1',$list1);
		$this->assign('list2',$list2);
		$this->display();
	}
	function doBorrow(){
		$post = I('post.','','trim,htmlspecialchars');
		$borrow = D('Borrow');
		$post['member_id'] = $this->member['member_id'];
		$post['user_id'] = $this->member['user_id'];
		$post['applydate'] = time();
		//$this->ajaxReturn(['status'=>0,'info'=>'贷款接口关闭！']);
		if(empty($post['money'])) $this->ajaxReturn(['status'=>0,'info'=>'贷款金额不能为0！']);

		//统计待审核总金额
		$total_borrow = $borrow->where(['status'=>['in','0,1']])->sum('money');
		if($post['money']>$this->config['invest_money']-$total_borrow) $this->ajaxReturn(['status'=>0,'info'=>'贷款失败，资金池余额不足。']);
		//是否超过30天代理时间
		if(time()>($this->member['reg_time']+180*86400)) $this->ajaxReturn(['status'=>0,'info'=>'超过贷款时间180天限制']);

		$re = $borrow->where(['member_id'=>$this->member['member_id']])->find();
		if($re) $this->ajaxReturn(['status'=>0,'info'=>'已存在申请记录。']);
		//代理级别判断
		if($post['money']>$this->agent_type[$this->member['user_levels']][1]) $this->ajaxReturn(['status'=>0,'info'=>'贷款金额超过级别额度。']);
		if($this->member['pwdtrade']!=md5($post['password'])) $this->ajaxReturn(['status'=>0,'info'=>'交易密码不正确！']);
		if(!$borrow->create($post)){
			$this->ajaxReturn(['status'=>0,'info'=>$borrow->getError()]);
		}else{
			$re = $borrow->add($post);
			if($re){
				$msg['status'] = 1;
				$msg['info'] = '操作成功！';
				$this->ajaxReturn($msg);
			}else $this->ajaxReturn(['status'=>0,'info'=>'操作失败'.$this->error()]);
		}
	}
	function cancel(){
		$id = intval($_POST['bid']);
		$re = M('Borrow')->delete($id);
		if($re) $this->ajaxReturn(['status'=>1,'info'=>'撤销成功！']);
		else $this->ajaxReturn(['status'=>0,'info'=>'撤销失败！']);
	}
	function deal(){
		if(empty($this->config['invest_money'])) $this->ajaxReturn(['status'=>1,'data'=>0]);
		$data = [];
		$list = M('Borrow b')
			->field('b.*,m.user_name')
			->join('__MEMBER__ m on b.member_id=m.member_id')
			->where(['b.status'=>1])->order('b.allowdate desc')->select();
		foreach ($list as $v) {
			//资金池的钱是否满足此次借款
			if($v['money']>$this->config['invest_money']) continue;
			M()->startTrans();
			$r = $this->setDeal($v);
			if (in_array(false, $r)) {
	            M()->rollback();
	            //dump($r);exit;
	        } else {
	            M()->commit();
	            $d = [
		            'bid'=>$v['bid'],'allowdate'=>date('Y-m-d H:i:s',$v['allowdate']),
		            'user_name'=>utf8Substr($v['user_name'],0,2).'***'.utf8Substr($v['user_name'],4,15),
		            'money'=>$v['money'],'yinghuan'=>$v['money']*1.2,'paymoney'=>$v['paymoney'],'leaf'=>($v['money']*1.2-$v['paymoney'])
	            ];
	            $data[] = $d;
	        }
		}
		$this->ajaxReturn(['status'=>1,'data'=>$data,'invest_money'=>$this->config['invest_money'],'borrow_success'=>$this->config['borrow_success']]);

	}
	function setDeal($v){
		$r[] = M('Config')->where(['key'=>'borrow_success'])->save(['value'=>['exp','value+'.$v['money']]]);
		$r[] = M('Config')->where(['key'=>'invest_money'])->save(['value'=>['exp','value-'.$v['money']]]);
		$r[] = M('Borrow')->where(['bid'=>$v['bid']])->setField('status',2);
		//$r[] = M('Member')->where(['member_id'=>$v['member_id']])->setInc('rmb',$v['money']);
		return $r;
	}
	//转入代理中心
	function zhuan(){
		$time = time();
		$url = C('borrow_url');
		if(($time-F('zhuan_time'))<60) $this->ajaxReturn(['status'=>0,'info'=>'60秒内不要重复提交']);
		F('zhuan_time',$time);
		//$url = 'http://chaobi.9dufz.com/Home/Borrow/test';
		if(!$this->member) $this->ajaxReturn(['status'=>0,'info'=>'未登录！']);
		//if(!$this->member['is_active']) $this->ajaxReturn(['status'=>0,'info'=>'尚未在代理中心激活']);
		$borrow = M('Borrow');
		$re = $borrow->where(['member_id'=>$this->member['member_id'],'status'=>2])->find();
		if(!$re) $this->ajaxReturn(['status'=>0,'info'=>'未找到贷款记录!']);

		M()->startTrans();
		$r1 = $borrow->where(['member_id'=>$this->member['member_id']])->setField('istransfer',1);
		//远程请求

		$key = 'GDSL28GSJGJ2G5YH6JSGS03S';
		$md5key = md5($time.md5($key));
		$post = ['uid'=>$this->member['user_id'],'money'=>$re['money'],'times'=>$time,'md5key'=>$md5key];
		$data = curlPost($url,$post);
		$r2 = json_decode($data,true);
		if($r1 && $r2['status']){
			M()->commit();
			$this->ajaxReturn(['status'=>1,'info'=>'转入成功！']);
		}else{
			M()->rollback();
			$this->ajaxReturn(['status'=>0,'info'=>'转入失败！']);
		}
	}
	function test(){
		$this->ajaxReturn($_POST);
	}
}