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

    private $txt_error = "En este momento no podemos atender esta solicitud, intenta nuevamente.";

    public function __construct()
    {

    }

    public function soap($headerRequest = array(), $debug = false, $isSoap = true)
    {

        if ($isSoap) {
            $contentType = "text/xml;charset=\"utf-8\"";
            $accept = "text/xml";
            $params = $this->POSTFIELDS;
        } else {
            $params = $this->POSTFIELDS;
            $contentType = "application/json;charset=\"utf-8\"";
            $accept = "application/json";
        }
        $log = array("request" => $params, "canal" => "N/A", "metodo" => "testM3", "httpVerb" => "POST", "tipoServicio" => "SOAP");

        $this->HEADERS = array(
            "Content-type: " . $contentType,
            "Accept: " . $accept,
            "Cache-Control: no-cache",
            "Pragma: no-cache",
            "Content-length: " . strlen($this->POSTFIELDS),
        );

        if (count($headerRequest) > 0) {
            foreach ($headerRequest as $key) {
                $this->HEADERS[] = $key;
            }
        } else {
            $this->HEADERS[] = "SOAPAction: \"run\"";
        }

        if ($this->URL == "") {
            $response["response"] = "No se encontró el EndPoint de este servicio.";
            $response["error"] = 1;
            return $response;
        }

        $log["reqXML"] = $params;
        //return $this->HEADERS;
        $log["url"] = $this->URL;
        $starttime = microtime(true);

        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $this->URL);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, $this->CONNECTTIMEOUT);
        curl_setopt($soap_do, CURLOPT_TIMEOUT, $this->TIMEOUT);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, $this->RETURNTRANSFER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $this->SSL_VERIFYPEER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $this->SSL_VERIFYHOST);
        curl_setopt($soap_do, CURLOPT_POST, $this->POST);
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, $params);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, $this->HEADERS);
        $res = curl_exec($soap_do);

        $resServer = $res;

        //return $res;

        $diff = microtime(true) - $starttime;
        $sec = intval($diff);
        $micro = $diff - $sec;
        $final = strftime('%T', mktime(0, 0, $sec)) . str_replace('0.', '.', sprintf('%.4f', $micro));
        $secs = $final;
        $tiempo = $this->timeToSeconds($secs);
        $log["tiempo"] = $secs;
        $response["secs"] = $secs;
        $response["tiempo"] = $tiempo;

        if (!$res) {
            if ($debug) {
                $res = 'Error: ' . curl_error($soap_do);
                $response["Exception"] = $res;
            }
            $log["response"] = $res;
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            curl_close($soap_do);
            $response["response"] = $this->txt_error . " - RED";
            $response["error"] = 1;
            return $response;
        } else {
            curl_close($soap_do);
            $res = str_replace("soap-env", "soapenv", $res);
            $res = str_replace("SOAP-ENV", "soapenv", $res);
            $res = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $res);

            $res = str_replace(" xmlns=\"http://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"https://services.cmPoller.sisges.telmex.com.co\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Entities.Contracts\"", "", $res);
            $res = str_replace(" xmlns=\"Claro.SelfCareManagement.Services.Exception.Contracts\"", "", $res);
            $log["resXML"] = $res;
        }

        if (!$isSoap) {

            $r = json_decode($res);
            if (json_last_error() == JSON_ERROR_NONE) {
                $response["response"] = json_decode($res);
                $response["error"] = 0;
                return $response;
            } else {
                $res_data = array("error" => 1, "response" => $this->txt_error, "secs" => $secs, "dataE" => json_last_error());
                return $res_data;
            }

        }

        try {
            libxml_use_internal_errors(true);
            $xml = new \SimpleXMLElement($res);
        } catch (Exception $e) {
            $log["response"] = "En este momento no podemos atender esta solicitud, intenta nuevamente (2)." . $e->getMessage() . ", " . $res . " URL:" . $this->URL . "- IP:" . $_SERVER["SERVER_ADDR"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;
        }

        //funciona para ver el error del xml
        /*$response["response"] = $xml;
        $response["error"] = 1;
        return $response;*/

        if (isset($xml->SBody->ns0Fault)) {

            if (isset($xml->SBody->ns0Fault->faultcode) && $xml->SBody->ns0Fault->faultcode == "ERROR") {

                $response["response"] = (isset($xml->SBody->ns0Fault->detail)) ? $xml->SBody->ns0Fault->detail : $this->txt_error;
                $response["error"] = 1;
                $log["response"] = (isset($xml->SBody->ns0Fault->detail)) ? $xml->SBody->ns0Fault->detail : $this->txt_error;
                $log["isError"] = 1;
                $this->save_in_db($log);
                $this->save_to_file($log);
                return $response;
            }
        } else if (isset($xml->sBody->sFault->detail->InnerFault->amessage)) {

            $temp = json_encode($xml->sBody->sFault->detail->InnerFault);
            $temp = json_decode($temp, true);
            $response["response"] = $temp["amessage"];
            $response["error"] = 1;
            $log["response"] = $temp["amessage"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            return $response;

        } else if (isset($xml->sBody->sFault)) {

            $temp = json_encode($xml->sBody->sFault);
            $temp = json_decode($temp, true);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            $log["response"] = $temp["faultstring"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            return $response;

        } else if (isset($xml->sBody->SFault)) {

            $temp = json_encode($xml->sBody->SFault);
            $temp = json_decode($temp, true);
            $log["response"] = $temp["faultstring"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        } else if (isset($xml->SBody->SFault)) {

            $temp = json_encode($xml->SBody->SFault);
            $temp = json_decode($temp, true);
            $log["response"] = $temp["faultstring"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        } else if (isset($xml->soapenvBody->SFault)) {

            $temp = json_encode($xml->soapenvBody->SFault);
            $temp = json_decode($temp, true);

            $log["response"] = $temp["faultstring"];
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["response"] = $temp["faultstring"];
            $response["error"] = 1;
            return $response;

        }

        if (isset($xml->soapenvBody)) {
            $body = $xml->soapenvBody;
        } else if (isset($xml->sBody)) {
            $body = $xml->sBody;
        } else if (isset($xml->SBody)) {
            $body = $xml->SBody;
        } else if (isset($xml->Body)) {
            $body = $xml->Body;
        } else if (isset($xml->soapBody)) {
            $body = $xml->soapBody;
        }

        if (isset($body)) {
            $log["response"] = $body;
            $log["isError"] = 0;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["responseServer"] = $resServer;
            $response["response"] = $body;
            $response["error"] = 0;
            return $response;
        } else {
            $log["response"] = $this->txt_error;
            $log["isError"] = 1;
            $this->save_in_db($log);
            $this->save_to_file($log);
            $response["response"] = $this->txt_error;
            $response["error"] = 1;
            return $response;

        }
    }

    public function timeToSeconds($time)
    {
        $timeExploded = explode(':', $time);
        if (isset($timeExploded[2])) {
            return $timeExploded[0] * 3600 + $timeExploded[1] * 60 + $timeExploded[2];
        }
        return $timeExploded[0] * 3600 + $timeExploded[1] * 60;
    }

    public function arrayToString($val)
    {

        if (isset($val)) {
            $temp = json_encode($val, true);

            if (json_last_error() == JSON_ERROR_NONE) {
                $temp = json_decode($temp);

                if (is_array($temp)) {
                    return "";
                } else {
                    return trim($val);
                }
            } else {
                return trim($val);
            }
        } else {
            return "";
        }
    }

    public function getArray($val)
    {

        $list = array();
        $temp = json_encode($val, true);
        $temp = json_decode($temp);

        if (is_array($temp)) {
            return $temp;
        } else {
            array_push($list, $temp);
            return $list;
        }
    }

    public function esArray($val)
    {
        $temp = json_encode($val, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $temp = json_decode($temp);

            if (is_array($temp)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function simple_post($url, $data)
    {
        $soap_do = curl_init();
        curl_setopt($soap_do, CURLOPT_URL, $url);
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, $this->CONNECTTIMEOUT);
        curl_setopt($soap_do, CURLOPT_TIMEOUT, $this->TIMEOUT);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, $this->RETURNTRANSFER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, $this->SSL_VERIFYPEER);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, $this->SSL_VERIFYHOST);
        curl_setopt($soap_do, CURLOPT_POST, $this->POST);
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, json_encode($data));
        //curl_setopt($soap_do, CURLOPT_HTTPHEADER,     $this->HEADERS);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $res = curl_exec($soap_do);

        return $res;
    }

    public function getFijoDocType($tipoDoc)
    {
        if (isset($tipoDoc) && $tipoDoc != null) {
            $tipo = array(
                "tipo1" => "CC",
                "tipo2" => "CE",
                "tipo3" => "PP",
                "tipo4" => "CD",
                "tipo5" => "NI",
            );

            return $tipo["tipo" . $tipoDoc];
        } else {
            return "1";
        }
    }

    public function getMovilDocType($tipoDoc)
    {

        if (isset($tipoDoc) && $tipoDoc != null) {
            $tipo = array(
                "tipo1" => "1",
                "tipo2" => "4",
                "tipo3" => "3",
                "tipo4" => "-1",
                "tipo5" => "2",
            );
            return $tipo["tipo" . $tipoDoc];
        } else {
            return "1";
        }

    }

    public function return_data($data)
    {

        //return $resJSON;
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function ConexDB($query)
    {

        $conn = new PDO('mysql:host=10.2.0.11;dbname=ClaroTest', 'clarotestusr', 'pQxg58*7');
        $conn->exec("SET CHARACTER SET utf8mb4");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        $sql = $conn->query($query)->fetchAll();

        return $sql;
    }

    public function save_in_db($log)
    {

        $log = $this->workAroundLogs($log, true);

        $col = "(metodo,httpVerb,tipoServicio,canal,request,response,url,tiempo,isError,reqXML,resXML,Cuenta,Correo,Seccion)";
        $colErr = "(metodo,httpVerb,tipoServicio,canal,request,response,url,tiempo,isError,Cuenta,Correo,Seccion)";

        $val = "('" . $log["metodo"] . "','" . $log["httpVerb"] . "','" . $log["tipoServicio"] . "','" . $log["canal"] . "','" . json_encode($log["request"]) . "','" . json_encode($log["response"]) . "','" . $log["url"] . "','" . $log["tiempo"] . "'," . $log["isError"] . ",'" . $log["reqXMLDB"] . "','" . $log["resXMLDB"] . "','" . $log["linea"] . "','" . $log["correo"] . "','" . $log["segmento"] . "')";
        $valErr = "('" . $log["metodo"] . "','ERR','" . $log["tipoServicio"] . "','" . $log["canal"] . "','" . json_encode($log["request"]) . "','Error al intentar guardar el metodo.','" . $log["url"] . "','" . $log["tiempo"] . "'," . $log["isError"] . ",'" . $log["linea"] . "','" . $log["correo"] . "','" . $log["segmento"] . "')";

        $colSmall = "(linea,segmento,correo,metodo,isError,dispositivo,appVersion)";
        $valSmall = "('" . $log["linea"] . "','" . $log["segmento"] . "','" . $log["correo"] . "','" . $log["metodo"] . "'," . $log["isError"] . ",'" . $log["dispositivo"] . "','" . $log["appVersion"] . "')";

        $colPass = "(correo,pass)";
        $valPass = "('" . $log["correo"] . "','" . json_encode($log["request"]) . "')";

        if ($log["metodo"] == "LoginUsuario" && intval($log["isError"] == 0)) {
            //$q="insert into Logs ".$col." values ".$val;
            //$qError="insert into Logs ".$colErr." values".$valErr;
            $qPass = "insert into app_data_login " . $colPass . " values " . $valPass;
        }
        $qSmall = "insert into app_data_small_log " . $colSmall . " values " . $valSmall;

        $qs = "";
        try {
            if (!$this->ConexDB($qSmall)) {
                $qs = "1";
            }
        } catch (Exception $e) {
            $qs = "1";
        }

        try {
            if (!$this->ConexDB($qPass)) {
                $qs = "1";
            }
        } catch (Exception $e) {
            $qs = "1";
        }
    }

    public function save_to_file($log)
    {

        $log = $this->workAroundLogs($log, false);

        $dataCruda = "FECHA: " . $log["reg"] . " \n ";
        $dataCruda .= "MÉTODO: " . $log["metodo"] . " \n ";
        $dataCruda .= "HOMOLOGACIÓN: " . "!#$%&/" . " \n ";
        $dataCruda .= "NOMENCLATURA: " . "!#$%&/" . " \n ";
        $dataCruda .= "OS: " . $log["dispositivo"] . " \n ";
        $dataCruda .= "TIPO_CUENTA: " . $log["segmento"] . " \n ";
        $dataCruda .= "CUENTA: " . $log["linea"] . " <br> ";
        $dataCruda .= "correoElectronico: " . $log["correo"] . " \n ";

        try {
            $anio = date("Y", strtotime("-5 hour"));
            $mes = date("m", strtotime("-5 hour"));
            $dia = date("d", strtotime("-5 hour"));
            $hora = date("H_i_s", strtotime("-5 hour"));
            $hh = date("H", strtotime("-5 hour"));
            $mm = date("i", strtotime("-5 hour"));
            list($usec, $sec) = explode(" ", microtime());

            $path = '/logs/' . $anio . '/' . $mes . '/' . $dia;
            $path2 = '/logs/dataCruda/' . $anio . '/' . $mes . '/' . $dia . '/' . $hh . '/' . $mm;
            $fileName = $hora . "." . $usec . "xx" . $log["metodo"] . "xx" . $log["srv_req_id"] . "xx" . $log["linea"] . 'xx' . $log["dispositivo"] . '.json';
            $fileName2 = $hora . "." . $usec . "xx" . $log["metodo"] . "xx" . $log["srv_req_id"] . "xx" . $log["linea"] . 'xx' . $log["dispositivo"] . $sec . 'dataCruda.json';

            $datos = array(
                "path" => $path, "fileName" => $fileName, "data" => json_encode($log),
            );

            $datos2 = array(
                "path" => $path2, "fileName" => $fileName2, "data" => json_encode($dataCruda),
            );

            $data_string = json_encode(array("data" => $datos));
            $data_string2 = json_encode(array("data" => $datos2));

            $ch = curl_init('http://10.2.0.8/SLM/Archivos/');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string))
            );

            $Information = curl_exec($ch);
            $Information = json_encode($Information);

            $ch2 = curl_init('http://10.2.0.8/SLM/Archivos/');
            curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $data_string2);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data_string2))
            );

            $Information = curl_exec($ch2);

        } catch (Exception $e) {
            $r = "";
        }

    }

    public function workAroundLogs($log, $toDB)
    {

        try {
            $index = array("metodo", "httpVerb", "tipoServicio", "canal", "request", "response", "url", "tiempo", "isError", "reqXML", "resXML", "linea", "segmento", "correo");

            $log["headers"] = getallheaders();

            $log["srv_nodo"] = isset($_SERVER["SERVER_ADDR"]) ? $_SERVER["SERVER_ADDR"] : '';
            $log["srv_req_id"] = isset($_SERVER["UNIQUE_ID"]) ? $_SERVER["UNIQUE_ID"] : '999_999';
            $log["http_origin"] = isset($_SERVER["HTTP_ORIGIN"]) ? $_SERVER["HTTP_ORIGIN"] : '';
            $log["http_user_agent"] = isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : '';

            $linea = isset($log["request"]["AccountId"]) ? $log["request"]["AccountId"] : (isset($log["request"]["numeroCuenta"]) ? $log["request"]["numeroCuenta"] : null);
            $segmento = isset($log["request"]["LineOfBusiness"]) ? $log["request"]["LineOfBusiness"] : null;
            $correo = isset($log["request"]["UserProfileID"]) ? $log["request"]["UserProfileID"] : (isset($log["request"]["nombreUsuario"]) ? $log["request"]["nombreUsuario"] : null);

            $tagIphone = "ios";
            $tagAndroid = "android";
            $tagWeb = "web";

            $dispositivo = isset($log["headers"]["X-MC-SO"]) ? $log["headers"]["X-MC-SO"] : null;

            $log["linea"] = isset($linea) ? $linea : (isset($log["headers"]["X-MC-LINE"]) ? $log["headers"]["X-MC-LINE"] : null);
            $log["segmento"] = isset($segmento) ? $segmento : (isset($log["headers"]["X-MC-LOB"]) ? $log["headers"]["X-MC-LOB"] : null);
            $log["correo"] = isset($correo) ? $correo : (isset($log["headers"]["X-MC-MAIL"]) ? $log["headers"]["X-MC-MAIL"] : null);
            $log["dispositivo"] = isset($dispositivo) ? $dispositivo : ((strpos($log["http_origin"], 'iPhone') || strpos($log["http_user_agent"], 'iPhone')) ? $tagIphone : ((strpos($log["http_origin"], 'khttp') || strpos($log["http_user_agent"], 'khttp')) ? $tagAndroid : null));
            $log["appVersion"] = isset($log["headers"]["X-MC-APP-V"]) ? $log["headers"]["X-MC-APP-V"] : null;

            foreach ($index as $k) {
                $log[$k] = isset($log[$k]) ? $log[$k] : "N_A";
            }

            if ($log["metodo"] == 'registerIMEI' || $log["metodo"] == 'codificacionContrato' || $log["metodo"] == 'retrieveContractDocument') {
                $log["reqXML"] = "Ignored";
                $log["resXML"] = "Ignored";
            }

            if ($toDB) {
                if (intval($log["isError"]) == 0) {
                    $log["resXMLDB"] = "";
                    $log["reqXMLDB"] = "";
                    $log["url"] = "";
                    $log["canal"] = "";
                } else {
                    $log["resXMLDB"] = $log["resXML"];
                    $log["reqXMLDB"] = $log["reqXML"];
                }
            } else {
                $reg = date('Y/m/d H:i:s', strtotime("-5 hour", $_SERVER["REQUEST_TIME"]));
                $log["reg"] = $reg;
            }

        } catch (Exception $e) {
            $r = "";
        }

        return $log;
    }
}
