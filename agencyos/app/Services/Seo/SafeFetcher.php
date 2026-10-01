<?php
namespace App\Services\Seo;
use RuntimeException;

class SafeFetcher
{
    public function __construct(private PublicUrl $urls) {}
    public function fetch(string $url,?callable $beforeRequest=null): array
    {
        $settings=ToolRegistry::settings();$visited=[];$chain=[];$elapsed=0;
        for ($hop=0;$hop<=$settings['max_redirects'];$hop++) {
            $target=$this->urls->resolve($url);
            if(isset($visited[$target['url']])) { throw new RuntimeException('Redirect loop detected.'); }
            $visited[$target['url']]=true;
            if($beforeRequest) { $beforeRequest($target['url']); }
            $response=$this->request($target,$settings);$elapsed+=$response['response_ms'];
            $chain[]=['url'=>$target['url'],'status'=>$response['status']];
            if(in_array($response['status'],[301,302,303,307,308],true) && isset($response['headers']['location'])) {
                $url=$this->urls->relative($target['url'],$response['headers']['location']) ?? throw new RuntimeException('Invalid redirect target.');continue;
            }
            return $response+['url'=>$target['url'],'redirects'=>$chain,'total_response_ms'=>$elapsed];
        }
        throw new RuntimeException('Redirect limit exceeded.');
    }
    protected function request(array $target,array $settings): array
    {
        $curl=curl_init($target['url']);$body='';$headers=[];$tooLarge=false;
        $ip=str_contains($target['ip'],':') ? '['.$target['ip'].']' : $target['ip'];
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>min(10,$settings['timeout']),CURLOPT_TIMEOUT=>$settings['timeout'],CURLOPT_USERAGENT=>'SEOAgencyOS/1.0 (+internal diagnostic crawler)',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_RESOLVE=>[$target['host'].':'.$target['port'].':'.$ip],CURLOPT_ENCODING=>'',CURLOPT_HEADERFUNCTION=>function ($handle,string $line) use (&$headers): int {
            if(str_starts_with($line,'HTTP/')) { $headers=[]; }
            elseif(str_contains($line,':')) { [$key,$value]=explode(':',$line,2);$headers[strtolower(trim($key))]=trim($value); }
            return strlen($line);
        },CURLOPT_WRITEFUNCTION=>function ($handle,string $chunk) use (&$body,&$tooLarge,$settings): int {
            if(strlen($body)+strlen($chunk)>$settings['max_bytes']) { $tooLarge=true;return 0; }
            $body.=$chunk;return strlen($chunk);
        }]);
        try {
            $ok=curl_exec($curl);$code=curl_errno($curl);$info=curl_getinfo($curl);
            if($tooLarge) { throw new RuntimeException('Response exceeds the configured download size limit.'); }
            if($ok===false) { throw new RuntimeException(match($code) { CURLE_OPERATION_TIMEDOUT=>'Website request timed out.',CURLE_COULDNT_RESOLVE_HOST=>'Hostname resolution failed.',CURLE_SSL_CACERT=>'TLS certificate verification failed.',default=>'Website request failed (network error '.$code.').' }); }
            return ['status'=>(int)$info['http_code'],'headers'=>$headers,'body'=>$body,'response_ms'=>(int)round($info['total_time']*1000),'bytes'=>strlen($body)];
        } finally { curl_close($curl); }
    }
}
