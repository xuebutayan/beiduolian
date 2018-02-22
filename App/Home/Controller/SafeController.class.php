<?php
namespace Home\Controller;
use Common\Controller\CommonController;
class SafeController extends HomeController {
 	public function _initialize(){
 		parent::_initialize();
 	}
	//空操作
	public function _empty(){
		header("HTTP/1.0 404 Not Found");
		$this->display('Public:404');
	}
	public function index(){
        $u_info = M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->find();
        //大盘数据
        $pan = ['total'=>0,'send'=>0];
        $baodan = M('Baodan');
        if($u_info['user_id']){
        	$total = 0;
        	$send = 0;
        	$list = $baodan->field('integral,remain_days')->where('user_id='.$u_info['user_id'])->select();
        	if($list){
        		foreach ($list as $v) {
        			$total += $v['integral'];
        			$send += $v['integral']*(200-$v['remain_days'])*0.005;
        		}
        		$pan = ['total'=>$total,'send'=>$send];
        	}
        }
        //alps币
        $alps = M('currency_user')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'currency_id'=>30])->find();
        //B仓帐号
        $code = M('hugemt4')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->getField('huge_user',true);
        //外汇出币统计
        //$dollar = M('alps_log')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'platform'=>'waihui','status'=>1,'type'=>1])->sum('money');
        $this->assign('chubi',$this->auth['waihui']);
        $this->assign('code',$code);
        $this->assign('alps_info',$alps);
        $this->assign('pan',$pan);
        $this->assign('u_info',$u_info);
		$this->assign('empty','暂无数据');
        $this->display();
     }
	 public function mobilebind(){

		$this->assign('empty','暂无数据');
        $this->display();
     }
     function generate_code(){
        $alps_code = M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->getField('alps_code');
        if($alps_code) $this->error('已存在编号');
        do{
            $alps_code = 'AL'.rand(100000,999999);
            $member_id = M('Member')->where(['alps_code'=>$alps_code])->getField('member_id');

        }while($member_id);
        M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->save(['alps_code'=>$alps_code]);
        $this->success('生成成功！');
     }
     //主从状态1主2从
     function account(){
        $u_info = M('Member')->where("member_id = {$_SESSION['USER_KEY_ID']}")->find();
        //收到的从帐号邀请
        $receive = M('master_req')->where(['child_id'=>$_SESSION['USER_KEY_ID'],'status'=>0])->select();

        //当前已绑定从账户
        $slaves = M('member')->where(['master'=>$_SESSION['USER_KEY_ID']])->select();
        //待反馈从账户
        $waits = M('master_req')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'status'=>0,'type'=>1])->select();
        //查询可用alps币
        $num = M('currency_user')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->getField('num');
        //兑出比例
        $currency_info = M('currency')->field('price_up,price_down')->where(['currency_id'=>30])->find();
        $radio = ($currency_info['price_up']+$currency_info['price_down'])/2;

        //转入转出记录
        $log = M('master_log')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->order('addtime desc')->select();
        $this->assign('log',$log);
        $this->assign('radio',$radio);
        $this->assign('num',$num);
        $this->assign('mt4_num',$this->auth['alps_mt4']);
        $this->assign('receive',$receive);
        $this->assign('waits',$waits);
        $this->assign('slaves',$slaves);
        $this->assign('u_info',$u_info);
        $this->display();
     }
     //撤销添加从帐号
     function cancelSlave(){
        $id = I('get.id',0,'intval');
        $re = M('master_req')->where(['member_id'=>$_SESSION['USER_KEY_ID'],'status'=>0,'type'=>1,'child_id'=>$id])->delete();
        if($re){
            $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
        }else $this->ajaxReturn(['status'=>0,'info'=>'操作失败！']);
     }
     //从帐号解绑
     function delBang(){
        $id = I('get.id',0,'intval');
        $re = M('member')->where(['member_id'=>$id,'master'=>$_SESSION['USER_KEY_ID']])->setField('master',0);
        if($re){
            $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
        }else $this->ajaxReturn(['status'=>0,'info'=>'操作失败！']);
     }
     //当前帐号绑定或解绑
     function setBang(){
        $id = I('request.id',0,'intval');
        $post = I('post.');
        if($post['bang']){
            if(empty($_POST['user_name'])) $this->ajaxReturn(['status'=>0,'info'=>'用户名不能为空！']);
            //检测是否有从账户
            $count = M('member')->where(['master'=>$_SESSION['USER_KEY_ID']])->count();
            if($count) $this->ajaxReturn(['status'=>0,'info'=>'存在从账户！']);
            //获取交易密码
            $uinfo = M('member')->field('user_name,pwdtrade,master')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->find();
            if (md5($post['pwdtrade']) != $uinfo['pwdtrade']) {
                $info['status'] = 0;
                $info['info']   = "交易密码不正确";
                $this->ajaxReturn($info);
            }
            if($uinfo['master']) $this->ajaxReturn(['status'=>0,'info'=>'已经是从账户！']);
            //获取主账户的member_id
            $id = M('member')->where(['user_name'=>$post['user_name']])->getField('member_id');
            if(empty($id)) $this->ajaxReturn(['status'=>0,'info'=>'用户不存在！']);
        }
        $re = M('member')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->setField('master',$id);
        if($re){
            $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
        }else $this->ajaxReturn(['status'=>0,'info'=>'操作失败！']);
     }
     //同意或拒绝成为从帐号
     function doReq(){//1为同意，2为拒绝
        $id = I('get.id',0,'intval');
        $status = I('get.status',2,'intval');
        if($status==1){
            $re = M('member')->where(['member_id'=>$_SESSION['USER_KEY_ID']])->setField('master',$id);
            if($re){
                M('master_req')->where(['status'=>0,'member_id'=>$id,'child_id'=>$_SESSION['USER_KEY_ID']])->delete();
                $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
            }else $this->ajaxReturn(['status'=>0,'info'=>'操作失败！']);
        }else{
            M('master_req')->where(['status'=>0,'member_id'=>$id,'child_id'=>$_SESSION['USER_KEY_ID']])->delete();
            $this->ajaxReturn(['status'=>1,'info'=>'操作成功！']);
        }

     }
     //转出alps币
     function exportAlps(){
        $member_id = $_SESSION['USER_KEY_ID'];
        $post = I('post.');
        $data['num'] = intval($post['money']);
        $data['member_id'] = $member_id;
        $data['type']=2;

        //获取交易密码
        $uinfo = M('member')->field('user_name,pwdtrade,master,alps_mt4')->where(['member_id'=>$member_id])->find();
        //获取可用余额
        $currency_u = M('currency_user')->where("member_id=$member_id")->find();
        if(!$uinfo['master']) $this->ajaxReturn(['status'=>0,'info'=>'未成为从账户！']);

        //验证密码
        if (md5($post['pwdtrade']) != $uinfo['pwdtrade']) {
            $info['status'] = 0;
            $info['info']   = "交易密码不正确";
            $this->ajaxReturn($info);
        }
        if ($data['num'] > $currency_u['num']+$uinfo['alps_mt4']) {
            $info['status'] = 0;
            $info['info']   = "交易数量大于账户余额";
            $this->ajaxReturn($info);
        }

        $data['addtime'] = time();
        $master_info = M('member')->where(['member_id'=>$uinfo['master']])->find();
        $data['title'] = '转到 ['.$master_info['user_name'].'] alps币';
        M('master_log')->add($data);
        $data2 = ['member_id'=>$master_info['member_id'],'type'=>1,'title'=>'收到 ['.$uinfo['user_name'].'] 转入alps币','num'=>$data['num'],'addtime'=>$data['addtime']];
        M('master_log')->add($data2);
        //帐号alps币扣减
        if($uinfo['alps_mt4']>=$data['num'])
            M('Member')->where(['member_id'=>$member_id])->setDec('alps_mt4',$data['num']);
        elseif($uinfo['alps_mt4']>0 && $uinfo['alps_mt4']<$data['num']){
            M('Member')->where(['member_id'=>$member_id])->setDec('alps_mt4',$uinfo['alps_mt4']);
            M('currency_user')->where("member_id=$member_id")->setDec('num',$data['num']-$uinfo['alps_mt4']);
        }
        else M('currency_user')->where("member_id=$member_id")->setDec('num',$data['num']);
        M('Member')->where(['member_id'=>$uinfo['master']])->setInc('alps_mt4',$data['num']);
        $info['status'] = 1;
        $info['info']   = "操作成功！";
        $this->ajaxReturn($info);

     }
     function addSlave(){
        $member_id = $_SESSION['USER_KEY_ID'];
        $post = I('post.');
        //用户名不能为空
        if (empty($post['user_name'])) {
            $info['status'] = 0;
            $info['info']   = "用户名不能为空";
            $this->ajaxReturn($info);
        }

        $uinfo = M('member')->field('pwdtrade,user_name,master')->where(['member_id'=>$member_id])->find();
        //不能添加自己为从用户
        if ($post['user_name']==$uinfo['user_name']) {
            $info['status'] = 0;
            $info['info']   = "不能添加自己为从用户";
            $this->ajaxReturn($info);
        }
        //验证交易密码
        if (md5($post['pwdtrade']) != $uinfo['pwdtrade']) {
            $info['status'] = 0;
            $info['info']   = "交易密码不正确";
            $this->ajaxReturn($info);
        }
        $slave_info = M('member')->where(['user_name'=>$post['user_name']])->find();
        //验证用户名
        if(!$slave_info['user_name']) {
            $info['status'] = 0;
            $info['info']   = "用户名不正确";
            $this->ajaxReturn($info);
        }
        //用户已成为从用户
        if($slave_info['master']){
            $info['status'] = 0;
            $info['info']   = "用户已成为从用户";
            $this->ajaxReturn($info);
        }
        //用户下有从用户
        $count = M('member')->where(['master'=>$slave_info['member_id']])->count();
        if($count){
            $info['status'] = 0;
            $info['info']   = "用户下有从用户";
            $this->ajaxReturn($info);
        }
        //限制10条
        $count = M('member')->where(['master'=>$member_id])->count();
        if($count>=10){
            $info['status'] = 0;
            $info['info']   = "从用户限制10个！";
            $this->ajaxReturn($info);
        }
        //发送绑定请求
        $data = ['member_id'=>$member_id,'user_name'=>$uinfo['user_name'],'type'=>1,'child_id'=>$slave_info['member_id'],'child_name'=>$slave_info['user_name'],'addtime'=>time()];
        M('master_req')->add($data);
        $this->ajaxReturn(['status'=>1,'info'=>'添加请求已发送成功！']);
     }
     //下载交易统计数据
     function tongji(){
        $member_id = $_SESSION['USER_KEY_ID'];
        $new_list = M('data')->where(['member_id'=>$member_id])->order('id asc')->select();
            $data = [];
            foreach ($new_list as $v) {
                $tmp = [];
                $tmp['riqi'] = date('Y-m-d',$v['datetime']);
                $tmp['czjf'] = $v['czjf'];
                $tmp['sfjf'] = $v['sfjf'];
                $tmp['hdjf'] = $v['hdjf'];
                $tmp['zrjf'] = $v['zrjf'];
                $tmp['ztjf'] = $v['ztjf'];
                $tmp['kyjf'] = $v['czjf']+$v['sfjf']+$v['zrjf']+$v['ztjf']-$v['hdjf'];
                $tmp['mbgd'] = $v['mbgd'];
                $tmp['mbjf'] = $v['mbjf'];
                $tmp['mbsl'] = $v['mbsl'];
                $tmp['jfye'] = $v['czjf']+$v['sfjf']+$v['zrjf']+$v['ztjf']-$v['hdjf']-$v['mbjf'];
                $tmp['zr_alps'] = $v['zr_alps'];
                $tmp['cz_alps'] = $v['cz_alps'];
                $tmp['km_alps'] = $v['km_alps'];
                $tmp['nbgd'] = $v['nbgd'];
                $tmp['nbsl'] = $v['nbsl'];
                $tmp['nbje'] = $v['nbje'];
                $tmp['ye_alps'] = $v['ye_alps'];
                $tmp['zr_rmb'] = $v['zr_rmb'];
                $tmp['cz_rmb'] = $v['cz_rmb'];
                $tmp['kt_rmb'] = $v['kt_rmb'];
                $tmp['tx_rmb'] = $v['tx_rmb'];
                $tmp['th_rmb'] = $v['th_rmb'];
                $data[] = $tmp;
            }
            $title = ['日期','充值积分','释放积分','还贷积分','转入积分','昨日积分','可用积分','买币挂单','买币总积分','买币总数量','积分余额','昨日ALPS','充值ALPS','可卖ALPS','卖币挂单','卖币总数量','卖币总金额','ALPS余额','昨日人民币','充值人民币','可用人民币','提现','提后余额'];
            exportexcel($data,$title,'数据统计');exit;
     }
}