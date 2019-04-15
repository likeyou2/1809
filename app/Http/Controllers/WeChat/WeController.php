<?php

namespace App\Http\Controllers\WeChat;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Model\UserModel;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp;
class WeController extends Controller
{
    public function valid(){
        echo $_GET['echostr'];
    }

    public function wxPostEvent(){

        //接受微信服务器推送
        $content = file_get_contents("php://input");

        $time = date("Y-m-d H:i:s") . $content . "\n";

        file_put_contents('logs/wx_event.log',$time,FILE_APPEND);

        $objxml = simplexml_load_string($content);  //吧XML格式转为成对象格式
        $ToUserName=$objxml->ToUserName;     //开发者微信号
        $FromUserName=$objxml->FromUserName;   //用户的微信号
        $CreateTime=$objxml->CreateTime;     //时间
        $MsgType=$objxml->MsgType;        //消息类型
        $Event=$objxml->Event;          //事件


        if($MsgType=="event"){ //判断数据类型
            if($Event=="subscribe"){ //判断事件类型

                $userInfo=$this->userInfo($FromUserName);//获取用户昵称

                $one=UserModel::where(['openid'=>$FromUserName])->first();//查询数据库
                if($one){//判断用户是否是第一次关注
                    $xml="<xml>
                      <ToUserName><![CDATA[$FromUserName]]></ToUserName>
                      <FromUserName><![CDATA[$ToUserName]]></FromUserName>
                      <CreateTime>time()</CreateTime>
                      <MsgType><![CDATA[text]]></MsgType>
                      <Content><![CDATA[你好,欢迎".$userInfo['nickname']."回归]]></Content>
                    </xml>";//设置发送的xml格式
                    echo $xml;//返回结果
                }else{//如果是第一次关注
                    $array=[
                        "openid"=>$userInfo['openid'],
                        "nickname"=>$userInfo['nickname'],
                        "city"=>$userInfo['city'],
                        "province"=>$userInfo['province'],
                        "country"=>$userInfo['country'],
                        "headimgurl"=>$userInfo['headimgurl'],
                        "subscribe_time"=>$userInfo['subscribe_time'],
                        "sex"=>$userInfo['sex'],
                    ];//设置数组形式的数据类型
                    $res=UserModel::insertGetId($array);//存入数据库
                    if($res){//判断是否入库成功
                        $xml="<xml>
                      <ToUserName><![CDATA[$FromUserName]]></ToUserName>
                      <FromUserName><![CDATA[$ToUserName]]></FromUserName>
                      <CreateTime>time()</CreateTime>
                      <MsgType><![CDATA[text]]></MsgType>
                      <Content><![CDATA[你好,欢迎".$userInfo['nickname']."]]></Content>
                    </xml>";//设置xml格式的数据
                        echo $xml;//返回结果
                    }
                }
            }
        }

        echo "SUCCESS";
    }


    //获取用户的基本信息
    public function userInfo($FromUserName){
        $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$this->getAccessToken().'&openid='.$FromUserName.'&lang=zh_CN';
        $count = file_get_contents($url); //调用URL接口
        $info = json_decode($count,true); //XML格式转换成数组
        return $info;
    }



    //获取Access_token
    public function getAccessToken(){
        //是否有缓存
        $key = 'wx_access_token';
        $token = Redis::get($key);

        if($token){
            return $token;
        }else{
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.env('WX_APPID').'&secret='.env('WX_SECRET');

            $response = file_get_contents($url);

            $arr = json_decode($response,true);
            //缓存 access_token
            Redis::set($key,$arr['access_token']);
            Redis::expire($key,3600); //缓存一小时
            $token = $arr['access_token'];
        }
        return $token;
    }


    /**
     * 创建自定义菜单
     */
    public function CustomMenu(){
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.$this->getAccessToken();
        $client = new GuzzleHttp\Client(['base_uri' => $url]);
        $data = [
            "button"    => [
                [
                    "name" => "相册拍照",
                    "sub_button" => [
                        [
                            "type"  => "pic_sysphoto",      // view类型 跳转指定 URL
                            "name"  => "系统拍照发图",
                            "key"   => "rselfmenu_1_0",
                            "sub_button"=> [ ]
                        ],
                        [
                            "type" =>  "pic_photo_or_album",
                            "name" => "拍照或者相册发图",
                            "key" => "rselfmenu_1_1",
                            "sub_button" => [ ]
                        ],
                        [
                            "type" => "pic_weixin",
                            "name" => "微信相册发图",
                            "key" => "rselfmenu_1_2",
                            "sub_button" => [ ]
                        ]
                    ]
                ],
                [
                    "name" => "点击跳转",
                    "sub_button" => [
                        [
                            "name" => "发送位置",
                            "type" => "location_select",
                            "key" => "rselfmenu_2_0"
                        ],
                        [
                            "name" => "哔哩哔哩",
                            'type' => "view",
                            "url" => "https://www.bilibili.com/"
                        ]
                    ]
                ],
                [
                    "name" => "扫码功能",
                    "sub_button" => [
                        [
                            "name" => "扫码带提示",
                            "type" => "scancode_waitmsg",
                            "key" => "rselfmenu_0_0",
                            "sub_button"=>[
                                'text'=>"text"
                            ]
                        ],
                        [
                            "name" => "扫码推事件",
                            "type" => "scancode_push",
                            "key" => "rselfmenu_0_1",
                            "sub_button"=>[]
                        ],
                    ]
                ]
            ]
        ];
        $r = $client->request('POST', $url, [
            'body' => json_encode($data,JSON_UNESCAPED_UNICODE)
        ]);
        // 3 解析微信接口返回信息
        $response_arr = json_decode($r->getBody(),true);
        if($response_arr['errcode'] == 0){
            echo "菜单创建成功";
        }else{
            echo "菜单创建失败，请重试";echo '</br>';
            echo $response_arr['errmsg'];
        }
    }


}
