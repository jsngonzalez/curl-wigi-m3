<?php

namespace wigilabs\curlWigiM3;

/**
 * Autor: Jeisson Gonzalez
 */
class curlWigi
{

    public $URL = "";
    public $CONNECTTIMEOUT = 30;
    public $TIMEOUT = 30;
    public $RETURNTRANSFER = true;
    public $SSL_VERIFYPEER = false;
    public $SSL_VERIFYHOST = false;
    public $POST = true;
    public $POSTFIELDS = "";
    public $HTTPHEADER = array();
    public $HEADERS = array();


    private $txt_error="En este momento no podemos atender esta solicitud, intenta nuevamente.";

    function __construct() {

    }


    public function soap($headerRequest=array(),$debug=false,$isSoap=true)
    {

       

        if($isSoap){
            $contentType = "text/xml;charset=\"utf-8\"";
            $accept = "text/xml";
            $params = $this->POSTFIELDS;
        }else{
            $params = $this->POSTFIELDS;
            $contentType = "application/json;charset=\"utf-8\"";
            $accept = "application/json";
        }

        $this->HEADERS = array(
            "Content-type: ".$contentType,
            "Accept: ".$accept,
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: ".strlen($this->POSTFIELDS)
        );

        if (count($headerRequest)>0){
            foreach ($headerRequest as $key) {
                $this->HEADERS[]=$key;
            }
        }else{
            $this->HEADERS[]="SOAPAction: \"run\"";
        }
        

        if ($this->URL=="") {
            $response["response"] = "No se encontró el EndPoint de este servicio.";
            $response["error"] = 1;
            return $response;
        }


        //return $this->HEADERS;

        $starttime = microtime(true);

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL,$this->URL);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, $this->CONNECTTIMEOUT);
        curl_setopt($soap_do, CURLOPT_TIMEOUT,        $this->TIMEOUT);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, $this->RETURNTRANSFER );
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $this->SSL_VERIFYPEER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $this->SSL_VERIFYHOST);
        curl_setopt($soap_do, CURLOPT_POST,           $this->POST );
        curl_setopt($soap_do, CURLOPT_POSTFIELDS,     $params);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER,     $this->HEADERS);
        $res = curl_exec($soap_do);


        $resServer = $res;

        //return $res;
        
        $diff = microtime(true) - $starttime;
        $sec = intval($diff);
        $micro = $diff - $sec;
        $final = strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.4f', $micro));
        $secs = $final;
        $tiempo=$this->timeToSeconds($secs);
        $response["secs"]=$secs;
        $response["tiempo"]=$tiempo;

        if(!$res) {
            if ($debug) {
                $res = 'Error: ' . curl_error($soap_do);
                $response["Exception"]=$res;
            }
            
            curl_close($soap_do);
            $response["response"] = $this->txt_error." - RED";
            $response["error"] = 1;
            return $response;
        } else {
            curl_close($soap_do);
            $res= str_replace("soap-env", "soapenv", $res);
            $res= str_replace("SOAP-ENV", "soapenv", $res);
            $res = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $res);

            $res = str_replace(" xmlns=\"http://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"https://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Entities.Contracts\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Exception.Contracts\"", "", $res);
        }


        if(!$isSoap){

            $r = json_decode($res);
            if (json_last_error() == JSON_ERROR_NONE){
                $response["response"] = json_decode($res);
                $response["error"] = 0;
                return $response;
            }else{                
                $res_data = array("error"=>1,"response"=>$this->txt_error,"secs"=>$secs,"dataE"=>json_last_error());
                return $res_data;
            }
            
        }


        try {
            libxml_use_internal_errors(true);
            $xml = new \SimpleXMLElement($res);
        } catch (Exception $e) {
            
            $response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;
        }

        //funciona para ver el error del xml
        /*$response["response"] = $xml;
        $response["error"] = 1;
        return $response;*/


        if(isset($xml->SBody->ns0Fault)){
            
            if (isset($xml->SBody->ns0Fault->faultcode) && $xml->SBody->ns0Fault->faultcode=="ERROR") {

                $response["response"] = (isset($xml->SBody->ns0Fault->detail))?$xml->SBody->ns0Fault->detail:$this->txt_error;
                $response["error"] = 1;
                return $response;
            }
        }else if(isset($xml->sBody->sFault->detail->InnerFault->amessage)){

            $temp=json_encode($xml->sBody->sFault->detail->InnerFault);
            $temp=json_decode($temp, true);
            $response["response"] = $temp["amessage"];
            $response["error"] = 1;
            return $response;
            
            
        }else if(isset($xml->sBody->sFault)){

            $temp=json_encode($xml->sBody->sFault);
            $temp=json_decode($temp, true);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        }else if(isset($xml->sBody->SFault)){

            $temp=json_encode($xml->sBody->SFault);
            $temp=json_decode($temp, true);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        }else if(isset($xml->SBody->SFault)){

            $temp=json_encode($xml->SBody->SFault);
            $temp=json_decode($temp, true);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        }else if(isset($xml->soapenvBody->SFault)){

            $temp=json_encode($xml->soapenvBody->SFault);
            $temp=json_decode($temp, true);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        }


        if(isset($xml->soapenvBody)){
            $body = $xml->soapenvBody;
        }else if(isset($xml->sBody)){
            $body = $xml->sBody;
        }else if(isset($xml->SBody)){
            $body = $xml->SBody;
        }else if(isset($xml->Body)){
            $body = $xml->Body;
        }else if(isset($xml->soapBody)){
            $body = $xml->soapBody;
        }else if(isset($xml->soapenv1Body)){
            $body = $xml->soapenv1Body;
        }

        if(isset($body)){

            $response["responseServer"] = $resServer;
            $response["response"] = $body;
            $response["error"] = 0;
            return $response;
        }else{
            
            $response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;

        }
    }

    function timeToSeconds($time)
    {
         $timeExploded = explode(':', $time);
         if (isset($timeExploded[2])) {
             return $timeExploded[0] * 3600 + $timeExploded[1] * 60 + $timeExploded[2];
         }
         return $timeExploded[0] * 3600 + $timeExploded[1] * 60;
    }

    function arrayToString($val){

        if (isset($val)){
            $temp=json_encode($val, true);
            
            if(json_last_error()==JSON_ERROR_NONE){
                $temp=json_decode($temp);
            
                if( is_array($temp)){
                    return "";
                }else{
                    return trim($val);
                }
            }else{
                return trim($val);
            }
        }else{
            return "";
        }
    }

    function getArray($val){

        $list=array();
        $temp=json_encode($val, true);
        $temp=json_decode($temp);

        if( is_array($temp)){
            return $temp;
        }else{
            array_push($list,$temp);
            return $list;
        }
    }

     function esArray($val){
        $temp=json_encode($val, true);
        if(json_last_error()==JSON_ERROR_NONE){
            $temp=json_decode($temp);
        
            if( is_array($temp)){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }


    function simple_post($url,$data){
        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL,$url);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, $this->CONNECTTIMEOUT);
        curl_setopt($soap_do, CURLOPT_TIMEOUT,        $this->TIMEOUT);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, $this->RETURNTRANSFER );
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $this->SSL_VERIFYPEER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $this->SSL_VERIFYHOST);
        curl_setopt($soap_do, CURLOPT_POST,           $this->POST );
        curl_setopt($soap_do, CURLOPT_POSTFIELDS,     json_encode($data));
        //curl_setopt($soap_do, CURLOPT_HTTPHEADER,     $this->HEADERS);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $res = curl_exec($soap_do);

        return $res;
    }

      function getFijoDocType($tipoDoc){
        if(isset($tipoDoc) && $tipoDoc != null){
            $tipo=array(
                "tipo1"=>"CC",
                "tipo2"=>"CE",
                "tipo3"=>"PP",
                "tipo4"=>"CD",
                "tipo5"=>"NI"
            );

            return $tipo["tipo".$tipoDoc];
        }else{
            return "1";
        }
    }

    function getMovilDocType($tipoDoc){

        if(isset($tipoDoc) && $tipoDoc != null){
            $tipo=array(
                "tipo1"=>"1",
                "tipo2"=>"4",
                "tipo3"=>"3",
                "tipo4"=>"-1",
                "tipo5"=>"2"
            );
            return $tipo["tipo".$tipoDoc];
        }else{
            return "1";
        }

    }

       function return_data($data){

        //return $resJSON;
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}