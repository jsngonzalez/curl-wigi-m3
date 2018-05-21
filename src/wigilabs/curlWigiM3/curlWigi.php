<?php

namespace wigilabs\curlWigiM3;

/**
 * Autor: Jeisson Gonzalez
 */
class curlWigi
{

	public $CURLOPT_URL = "";
	public $CURLOPT_CONNECTTIMEOUT = 30;
	public $CURLOPT_TIMEOUT = 30;
	public $CURLOPT_RETURNTRANSFER = true;
	public $CURLOPT_SSL_VERIFYPEER = false;
	public $CURLOPT_SSL_VERIFYHOST = false;
	public $CURLOPT_POST = true;
	public $CURLOPT_POSTFIELDS = "";
	public $CURLOPT_HTTPHEADER = array();
	public $headers = array();


	private $txt_error="En este momento no podemos atender esta solicitud, intenta nuevamente.";

	function __construct() {

	}


    public function soap($headerRequest=array())
    {
		$this->headers = array(
	        "Content-type: text/xml;charset=\"utf-8\"",
	        "Accept: text/xml",
	        "Cache-Control: no-cache",
	        "Pragma: no-cache",
            "Content-length: ".strlen($reqXML)
	    );

		if (count($headerRequest)>0){
			foreach ($headerRequest as $key) {
				$this->headers[]=$key;
			}
        }else{
            $this->headers[]="SOAPAction: \"run\"";
        }


        if ($this->CURLOPT_URL=="") {
        	$response["response"] = "No se encontró el EndPoint de este servicio.";
            $response["error"] = 1;
            return $response;
        }


        $starttime = microtime(true);

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL,$this->CURLOPT_URL);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, $this->CURLOPT_CONNECTTIMEOUT);
        curl_setopt($soap_do, CURLOPT_TIMEOUT,        $this->CURLOPT_TIMEOUT);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, $this->CURLOPT_RETURNTRANSFER );
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $this->CURLOPT_SSL_VERIFYPEER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $this->CURLOPT_SSL_VERIFYHOST);
        curl_setopt($soap_do, CURLOPT_POST,           $this->CURLOPT_POST );
        curl_setopt($soap_do, CURLOPT_POSTFIELDS,     $this->CURLOPT_POSTFIELDS);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER,     $this->headers);
        $res = curl_exec($soap_do);

        $now = new DateTime();
        $diff = microtime(true) - $starttime;
        $sec = intval($diff);
        $final = strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.4f', $micro));
        $secs = $final;
        $tiempo=$this->timeToSeconds($secs);
		$response["secs"]=$secs;
		$response["tiempo"]=$tiempo;

        if(!$res) {
        	$response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;
        } else {
        	curl_close($soap_do);
            $res = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $res);

            $res = str_replace(" xmlns=\"http://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"https://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Entities.Contracts\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Exception.Contracts\"", "", $res);
		}


        try {
            libxml_use_internal_errors(true);
            $xml = new SimpleXMLElement($res);
        } catch (Exception $e) {
            
        	$response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;
        }

        if(isset($xml->soapenvBody)){
            $body = $xml->soapenvBody;
        }else if(isset($xml->sBody)){
            $body = $xml->sBody;
        }else if(isset($xml->SBody)){
            $body = $xml->SBody;
        }

        if(isset($body)){

        	$response["response"] = $body;
            $response["error"] = 0;
            return $response;
        }

        if(isset($xml->SBody->ns0Fault)){
                $tagFaultNS1='ns1'.$nMetodo.'Fault';

            if(isset($xml->SBody->ns0Fault->detail->$tagFaultNS1->Message)){

                $temp=json_encode($xml->SBody->ns0Fault->detail->$tagFaultNS1);
                $temp=json_decode($temp, true);

	        	$response["response"] = $temp["Message"];
	            $response["error"] = 1;
	            return $response;

            }else{

                $temp=json_encode($xml->SBody->ns0Fault);
	        	$response["response"] = json_encode($xml->SBody->ns0Fault);
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
}