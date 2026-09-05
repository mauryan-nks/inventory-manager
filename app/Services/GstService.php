<?php
namespace App\Services;

use App\Models\SettingModel;
use RuntimeException;

class GstService
{
    private const BASE='https://api.sinhcoms.com';

    protected function key(): string
    {
        $key=trim((string)(new SettingModel())->value('gst_api_key',''));
        if($key==='') throw new RuntimeException('GST API key is not configured. Add it in Settings.');
        return $key;
    }

    protected function request(string $method,string $url,array $form=[]): array
    {
        $ch=curl_init();
        $headers=['Authorization: Bearer '.$this->key(),'Accept: application/json'];
        if($method==='POST') $headers[]='Content-Type: application/x-www-form-urlencoded';
        curl_setopt_array($ch,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
        if($method==='POST') curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($form));
        $body=curl_exec($ch); $errno=curl_errno($ch); $error=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if($errno) throw new RuntimeException('GST API connection failed: '.$error);
        $json=json_decode((string)$body,true);
        if($status<200||$status>=300){
            $message=is_array($json)?($json['message']??$json['error']??'GST API request failed.'):'GST API request failed.';
            if($status===401)$message='GST API authentication failed. Check the API key in Settings.';
            if($status===429)$message='GST API daily request limit has been exceeded.';
            throw new RuntimeException($message);
        }
        if(!is_array($json)) throw new RuntimeException('GST API returned an unexpected response.');
        return $json;
    }

    public function start(): array
    {
        $r=$this->request('GET',self::BASE.'/gst/start');
        $sessionId=(string)($r['sessionId']??$r['session_id']??'');
        $image=(string)($r['image']??$r['captcha']??'');
        if($sessionId==='') throw new RuntimeException('GST API did not return a session ID.');
        return ['sessionId'=>$sessionId,'image'=>$image];
    }

    public function refresh(string $sessionId): array
    {
        if($sessionId==='') throw new RuntimeException('GST validation session is missing.');
        $r=$this->request('GET',self::BASE.'/gst/refresh_captcha?sessionId='.rawurlencode($sessionId));
        return ['sessionId'=>(string)($r['sessionId']??$sessionId),'image'=>(string)($r['image']??$r['captcha']??'')];
    }

    public function validate(string $sessionId,string $gstin,string $captcha): array
    {
        if($sessionId===''||$captcha==='') throw new RuntimeException('Captcha is required.');
        return $this->request('POST',self::BASE.'/gst/data',['sessionId'=>$sessionId,'gst'=>$gstin,'captcha'=>$captcha]);
    }

    public function wasValidated(string $gstin): bool
    {
        $row=session()->get('gst_validation_'.hash('sha256',strtoupper(trim($gstin))));
        return is_array($row) && !empty($row['valid']) && (int)($row['validated_at']??0)>=time()-900;
    }
}
