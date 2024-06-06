<?php
namespace MomentUtils\AliDDNS;

if(!defined('__MAIN__')) exit(1);

/**
 * AliDDNS
 * 使用阿里云云解析API + Moment异步能力实现域名动态解析
 * 
 * @license MIT
 * @copyright iz <2131601562@qq.com>
 * @version 1.0
 */

use function MomentAdaper\fetch;
use function MomentAdaper\sleep;
use function MomentCore\go;
use function MomentCore\log;

// ========================= 配置开始 ===========================
const akID	 = '                        ';       // 设置你的阿里云key,用于调用api
const akSec	 = '                              '; // 设置你的阿里云pw,用于调用api
const ttl    = 600;                              // ttl时长，一般免费版都是600
const dtype  = 'AAAA';                           // 解析类型，AAAA表示IPV6，A表示IPV4
const domain = 'imzlh.top';                      // 购买的域名，不包括前缀，如www.(bing.com)
const prefix = 'cloud';                          // 域名前缀，如(www).bing.com
const ipurl  = 'http://6.ipw.cn';                // 获取IP地址API，建议是http
// =============================================================

function encode(string $data){
    return preg_replace_callback('/[^A-Za-z0-9-_.~]/',fn($input) =>
        '%' . strtoupper(dechex(ord($input[0])))
    ,str_replace(' ','%20',$data));
}

function replace(string $data){
    return str_replace(['+', '*', '%7E'], ['%20', '%2A', '~'], urlencode($data));
}

function encode_query(array $param,string &$string){
    $data = '';
    foreach ($param as $key => $value) 
        $data .= '&' . encode($key) . '=' . encode($value);
    $string = substr($data,1);
    return base64_encode(hash_hmac('sha1', 'GET&%2F&' . replace($string),akSec . '&', true));
}

/**
 * @link https://help.aliyun.com/document_detail/166534.html
 */
function request(array $param){
    $param = array_merge([
        'AccessKeyId' => akID,
        'Format' => 'JSON',
        'SignatureMethod' => 'HMAC-SHA1',
        'SignatureNonce' => uniqid('aliddns_'),
        'SignatureVersion' => '1.0',
        'Timestamp' => gmdate('Y-m-d\\TH:i:s\\Z'),
        'Version' => '2015-01-09'
    ],$param);
    ksort($param);

    // 编码
    $str = '';
    $param['Signature'] = encode_query($param,$str);

    // 请求
    $txt = fetch(
        'http://alidns.aliyuncs.com/',
        [
            'query' => $param
        ]
    ) -> text();
    $response   = json_decode($txt,true);
    if(!is_array($response))
        throw new \Error("Request failed.Result:[$txt]");
    if(@$response['Message'])
        throw new \Error('Request failed with E_' . $response['Code'] . ': ' . $response['Message']);
    return $response;
}

function getRecId(){
    $res = request([
        'Action' => 'DescribeDomainRecords',
        'DomainName' => domain,
    ]);

    $recordList = @$res['DomainRecords']['Record'];
    $prefix = null;

    if(!$recordList)
        throw new \Error('Failed to get domain data!');

    foreach ($recordList as $key => $record)
        if ($record['Type'] == dtype && prefix == $record['RR'])
            $prefix = $record;

    if (!$prefix)
        throw new \Error('Domain prefix [' . prefix . '] not found.');

    return $prefix;
}

// 主程序
go(function(){
    
    $record = getRecId();
    $lastip = $record['Value'];
    $recID = $record['RecordId'];
    $domain = prefix . '.' . domain;
    unset($record);

    log('{color_green}I{/} AliDDNS is ready to update.');

    while(true){
		$ip = fetch(ipurl) -> text();
		if($ip == $lastip){
			log("{color_blue}I{/} IP not changed. IP: [$ip]");
		}else{
			$lastip = $ip;
            try{
                // 更新
                request([
                    'Action' => 'UpdateDomainRecord',
                    'RR' => prefix,
                    'RecordId' => $recID,
                    'TTL' => ttl,
                    'Type' => dtype,
                    'Value' => $ip, 
                    'Version' => '2015-01-09'
                ]);
                log("{color_green}S{/} {color_gray}$domain{/} REDIRECTED to {color_gray}$ip{/}' \n");
            }catch(\Throwable $e){
                log('{color_red}E{/} AliDDNS: '.$e->getMessage());
            }
		}
        // 延时继续
        sleep(ttl / 3);
    }
}) -> catch(fn(\Throwable $e) => 
    trigger_error('Thread [AliDDNS] exited: ' . $e -> getMessage())
);
?>
