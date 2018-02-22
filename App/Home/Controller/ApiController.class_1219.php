<?php

namespace Home\Controller;

use Common\Controller\CommonController;

class ApiController extends CommonController {

    //空操作
    public function _empty() {
        header("HTTP/1.0 404 Not Found");
        $this->display('Public:404');
    }

    //同步注册
    public function test_001() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']        用户ID
        //$post['user_name']  用户名
        //$post['user_code']  用户编号
        //$post['levels']     等级
        //$post['pwd1']       登陆密码
        //$post['pwd2']       支付密码
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result['status'] = "-1";
            $result['info'] = '数据传输错误';
            return $this->ajaxReturn($result);
        }
        //验正用户名 用户编号 是否注册过
        //写入成功反回 1
        //写入失败反回 -2
        $member = M('Member');
        $re_email = $member->where(array('email' => $post['email']))->find();
        $re_user_name = $member->where(array('user_name' => $post['user_name']))->find();
        $re_user_code = $member->where(array('user_code' => $post['user_code']))->find();
        if ($re_email) {
            $result['status'] = "-2";
            $result['info'] = "该QQ邮箱已存在请换另个QQ注册";
            return $this->ajaxReturn($result);
        } elseif ($re_user_name) {
            $result['status'] = "-3";
            $result['info'] = "该用户名已存在";
        } elseif ($re_user_code) {
            $result['status'] = "-3";
            $result['info'] = "该用户编号已存在";
        } else {
            //写入数据库------》
            $data = [
                'email' => $post['email'],
                'user_id' => $post['uid'],
                'user_name' => $post['user_name'],
                'user_code' => $post['user_code'],
                'user_levels' => $post['levels'],
                'pwd' => md5($post['pwd1']),
                'pwdtrade' => md5($post['pwd2']),
                'reg_time' => time(),
            ];
            $re = $member->add($data);
            if ($re) {
                $result['status'] = "1";
                $result['info'] = '注册成功';
                return $this->ajaxReturn($result);
            } else {
                $result['status'] = "-4";
                $result['info'] = '注册失败';
                return $this->ajaxReturn($result);
            }
        }
    }

    //注释A
    /* 表设计
      create table `yang_baodan`(
      `oid` int(10) unsigned not null auto_increment,
      `user_id` int(10) unsigned not null default 0,
      `integral` int(10) unsigned not null default 0,
      `remain_days` int(10) unsigned not null default 0,
      `lastupdate` int(10) unsigned not null default 0,//暂时不用
      `nextupdate` int(10) unsigned not null default 0,
      `posttime` int(10) unsigned not null default 0,
      `status` tinyint(1) unsigned not null default 1,
      PRIMARY KEY(`oid`)
      )ENGINE=MyISAM DEFAULT CHARSET=UTF8;
     *
     */
    public function test_002() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']        用户ID
        //$post['user_name']  用户名
        //$post['user_code']  用户编号
        //$post['levels']     等级
        //$post['integral']   激活成功给的总积分 分200天分完
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result['status'] = "-1";
            $result['info'] = '数据传输错误';
            return $this->ajaxReturn($result);
        }
        //验正$post['times']时间 小于或等于上次转入金额时间不给通过
        $member = M('Member');
        $post_time = $member->where(array('user_id' => $post['uid']))->order('posttime desc')->getField('posttime');
        if ($post_time >= $post['times']) {
            $result['status'] = "-2";
            $result['info'] = '数据传输错误';
            return $this->ajaxReturn($result);
        }

        //写入数据库------》
        $data = [
            'user_id' => $post['uid'],
            'integral' => $post['integral'],
            'remain_days' => 200,
            'lastupdate' => strtotime(date('Y-m-d', time())),
            'nextupdate' => strtotime(date('Y-m-d', time() + 86400)),
            'posttime' => $post['times'],
        ];
        $re = M('Baodan')->add($data);
        if ($re) {
            $result['status'] = "1";
            $result['info'] = '数据写入成功';

            return $this->ajaxReturn($result);
        } else {
            $result['status'] = "-3";
            $result['info'] = '数据写入失败';
            return $this->ajaxReturn($result);
        }

        //写入成功反回 1
        //写入失败反回 -2
    }

    //注释B
    public function test_003() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']        用户ID
        //$post['user_name']  用户名
        //$post['money']     转入金额
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result['status'] = "-1";
            $result['info'] = '数据传输错误';
            return $this->ajaxReturn($result);
        }
        //验正$post['times']时间 小于或等于上次转入金额时间不给通过
        $member = M('Member');
        $info = $member->where(array('user_id' => $post['uid']))->field('member_id,posttime')->find();
        if ($info['posttime'] >= $post['times']) {
            $result['status'] = "-2";
            $result['info'] = '数据传输错误';
            return $this->ajaxReturn($result);
        }

        //写入数据库------》
        //转入金额成功记录post['times']时间
        //写入成功反回 1
        //写入失败反回 -2
        $re = $member->where(['user_id' => $post['uid']])->setInc('integrals', $post['money']);
        if ($re) {
            $this->inte_log($info['member_id'], $post['money'], 1, '积分转入'); //积分日志
            $res = $member->where(['user_id' => $post['uid']])->setField('posttime', $post['times']);
            if ($res) {
                $result['status'] = "1";
                $result['info'] = '数据写入成功';
                return $this->ajaxReturn($result);
            } else {
                $result['status'] = "-3";
                $result['info'] = '数据写入失败';
                return $this->ajaxReturn($result);
            }
        } else {
            $result['status'] = "-3";
            $result['info'] = '数据写入失败';
            return $this->ajaxReturn($result);
        }
    }

    //查询用户名
    public function test_004() {
        $post = I('post.');
        //正常的POST传输
        //$post['type']  1为验用户名，2为验email
        //$post['user_name']  用户名
        //$post['eail']  email
        //$post['times']      时间
        //$post['md5key']     加密串
        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result['status'] = '-1';
            $result['info'] = ' Token 不正确';
            return $this->ajaxReturn($result);
        }
        //用户是否注册过
        $member = M('Member');
        if ($post['type'] == 1) {
            $status = $member->where(array('user_name' => $post['user_name']))->find();
        } elseif ($post['type'] == 2) {
            $status = $member->where(array('email' => $post['email']))->find();
        } else {
            $result['status'] = "-2";
            $result['info'] = '你没有输入类型';
            return $this->ajaxReturn($result);
        }
        //没注册过反回 1
        //已注册过反回 -2
        if (!$status) {
            $result['status'] = "1";
            $result['info'] = '不存在';
            return $this->ajaxReturn($result);
        } else {
            $result['status'] = "-3";
            $result['info'] = '已存在';   
            return $this->ajaxReturn($result);
        }
    }

//清空会员表数据后，写入条会员信息
    public function test_005() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']        用户ID
        //$post['user_name']  用户名
        //$post['user_code']  用户编号
        //$post['levels']     等级
        //$post['pwd1']       登陆密码
        //$post['pwd2']       支付密码
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result[0] = '-1';
            $result[1] = ' Token 不正确';
            return $this->ajaxReturn($result);
        }
        //清空所有会员
        $member = M('Member');
        $db_pre = C('DB_PREFIX');
        $sql = 'TRUNCATE ' . $db_pre . 'baodan;TRUNCATE ' . $db_pre . 'integrals_log;';
        $member->execute($sql);
        $member_id = $member->where(['user_id' => ['neq', 0]])->getField('member_id', true);
        //删除member表记录
        $member->where(['user_id' => ['neq', 0]])->delete();
        //删除orders、currency_user、finance、trade、withdraw、pay
        $order = M('Orders');
        $currency_user = M('currency_user');
        $finance = M('Finance');
        $trade = M('Trade');
        $withdraw = M('Withdraw');
        $pay = M('Pay');
        foreach ($member_id as $v) {
            $order->where('member_id=' . $v)->delete();
            $currency_user->where('member_id=' . $v)->delete();
            $finance->where('member_id=' . $v)->delete();
            $trade->where('member_id=' . $v)->delete();
            $withdraw->where('uid=' . $v)->delete();
            $pay->where('member_id=' . $v)->delete();
        }
        $uid = $member->where(['user_id' => $post['uid']])->getField('user_id');
        //验正用户名 用户编号 是否注册过
        $data = [
            'email' => $post['email'],
            'user_id' => $post['uid'],
            'user_name' => $post['user_name'],
            'user_code' => $post['user_code'],
            'user_levels' => $post['levels'],
            'pwd' => md5($post['pwd1']),
            'pwdtrade' => md5($post['pwd2']),
            'reg_time' => time(),
        ];
        $data = array_filter($data);
        if (!$uid)
            $re = $member->add($data);
        //写入数据库------》
        if ($re) {
            $result['status'] = "1";
            $result['info'] = '注册成功';
            return $this->ajaxReturn($result);
        } else {
            $result['status'] = "-4";
            $result['info'] = '注册失败';
            return $this->ajaxReturn($result);
        }
    }

    //删除用户
    public function test_006() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']   UID
        //$post['user_name']  用户名
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result[0] = '-1';
            $result[1] = ' Token 不正确';
            return $this->ajaxReturn($result);
        }
        $member = M('Member');
        $member_id = $member->where('user_id=' . $post['uid'])->getField('member_id');
        $where['member_id'] = $member_id;
        $r[] = $member->delete($member_id);
        $r[] = M('Currency_user')->where($where)->delete();
        $r[] = M('Finance')->where($where)->delete();
        $r[] = M('Orders')->where($where)->delete();
        $r[] = M('Trade')->where($where)->delete();
        $r[] = M('Withdraw')->where('uid=' . $member_id)->delete();
        $r[] = M('Pay')->where($where)->delete();
        if ($r) {
            $result['status'] = "1";
            $result['info'] = '删除成功';
            return $this->ajaxReturn($result);
        } else {
            $result['status'] = "-4";
            $result['info'] = '删除失败';
            return $this->ajaxReturn($result);
        }
    }

    //修改密码
    public function test_007() {
        $post = I('post.');
        //正常的POST传输
        //$post['uid']   UID
        //$post['user_name']  用户名
        //$post['pwd1']       登陆密码
        //$post['pwd2']       支付密码
        //$post['times']      时间
        //$post['md5key']     加密串

        $key = 'GDSL28GSJGJ2G5YH6JSGS03S';
        $token_this = md5($post["times"] . md5($key));
        //验正数据是否被修改
        if ($post["md5key"] != $token_this) {
            $result[0] = '-1';
            $result[1] = ' Token 不正确';
            return $this->ajaxReturn($result);
        }
        //如果$post['pwd1'] 为空就不修改 pwd1登陆密码
        //如果$post['pwd2'] 为空就不修改 pwd2支付密码
        $data = array();
        $post['pwd1'] && $data['pwd'] = md5($post['pwd1']);
        $post['pwd2'] && $data['pwdtrade'] = md5($post['pwd2']);
        $re = M('Member')->where('user_id=' . $post['uid'])->save($data);
        if ($re) {
            $result['status'] = "1";
            $result['info'] = '修改成功';
            return $this->ajaxReturn($result);
        } else {
            $result['status'] = "-4";
            $result['info'] = '修改失败';
            return $this->ajaxReturn($result);
        }
    }

}
