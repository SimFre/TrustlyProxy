<?php
error_reporting(E_ALL);

class TrustlyProxy {
    private $privateKey = null;
    private $publicKey = null;
    public $apiURL = "https://trustly.com/api/1";
    public $uuid;
    public $method;
    public $requestData;
    public $requestSerial;
    // public $requestSignature;
    // public $responseInfo;
    // public $responseRaw;
    public $responseData;

    public function __construct($method) {
        $this->method = $method;
        $this->uuid = $this->createUUID();
    }

    public function loadPrivateKeyFile(string $cert) {
        $this->privateKey = openssl_get_privatekey($cert);
    }

    public function loadPublicKeyFile(string $cert) {
        $this->publicKey = openssl_get_publickey($cert);
    }

    public function serializeData($object) {
        $serialized = "";
        if (is_array($object)) {
            ksort($object); //Sort keys
            foreach($object as $key => $value) {
                if(is_numeric($key)) { //Array
                    $serialized .= $this->serializeData($value);
                } else { //Hash
                    $serialized .= $key . $this->serializeData($value);
                }
            }
        } else return "" . $object; //Scalar
        return $serialized;
    }
    
    public function sign($data) {
        $data = json_decode(json_encode($data, JSON_UNESCAPED_SLASHES), true);
        $this->requestSerial = $this->method . $this->uuid . $this->serializeData($data);
    
        openssl_sign(
            $this->requestSerial,
            $signature, // This is an output variable
            $this->privateKey
        );
        return base64_encode($signature);
    }
    
    public function verify($data, $signature, $pubkey = null) {
        if (is_null($pubkey)) { $pubkey = $this->publicKey; }
        $plaintext = $this->method . $this->uuid . $this->serializeData($data);
        return openssl_verify(
            $plaintext,
            base64_decode($signature),
            $pubkey
        );
    }

    public function prepare(array $data, ) {
        $re['method'] = $this->method;
        $re['version'] = "1.1";
        $re['params']['Data'] = $data;
        $re['params']['Data']['Attributes']['APIVersion'] = "1.2";
        $re['params']['UUID'] = $this->uuid;
        $re['params']['Signature'] = $this->sign(
            $re['params']['Data']
        );
        $this->requestData = $re;
        return $re;
       
    }

    public function post() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiURL);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Strictly off for debugging
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // Strictly off for debugging

        // Custom headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Accept: application/json",
            "Content-Type: application/json",
        ]);

        // Body
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            json_encode($this->requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $raw = curl_exec($ch);
        // $this->responseInfo = curl_getinfo($ch);
        $retval = false;
        if ($raw !== false) {
            $this->responseData = json_decode($raw, true);
            $retval = true;
        }
        curl_close($ch);
        return $retval;
    }

    public function write() {
        $j = json_encode($this, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $d = date('Ymd_His');
        $u = $this->uuid;
        if (!is_dir("log")) {
            mkdir("log");
        }
        file_put_contents("log/" . $d . "_" . $u . ".json", $j);
    }

    function createUUID() {
        // Generates a cryptographically secure
        // RFC4211 compliant UUID v4.
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int( 0, 0xffff ),
            random_int( 0, 0xffff ),
            random_int( 0, 0xffff ),
            random_int( 0, 0x0fff ) | 0x4000,
            random_int( 0, 0x3fff ) | 0x8000,
            random_int( 0, 0xffff ),
            random_int( 0, 0xffff ),
            random_int( 0, 0xffff )
        );
    }
}

// Get what's after .php:
$method = "UnspecifiedMethod";
if (!empty($_SERVER['PATH_INFO']) && strlen($_SERVER['PATH_INFO']) > 2) {
    $method = substr($_SERVER['PATH_INFO'], 1);
}

// Initialize
$tp = new TrustlyProxy($method);
$tp->loadPrivateKeyFile("file://./merchant_live_private.pem");
$tp->loadPublicKeyFile("file://./trustly_live_public.pem");

// Read raw POST data
$inputBody = file_get_contents("php://input");
if (strlen(trim($inputBody)) > 0) {
    $inputData = json_decode($inputBody, true);
    $tp->prepare($inputData);
    $tp->post();
    $tp->write();
}

// Determine return type
$ct = $_SERVER['CONTENT_TYPE'] ?? "";
switch ($ct) {
    case "application/json":
        header("Content-type: " . $ct . ";charset=utf-8");
        echo json_encode($tp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        break;
    
    // case "text/xml":
    // case "application/xml":
    //     header("Content-type: " . $ct . ";charset=utf-8");
    //     $xml = new SimpleXMLElement('<trustly/>');
    //     $arr = json_decode($outputJSON, true);
    //     array_walk_recursive($arr, array ($xml,'addChild'));
    //     echo $xml->asXML();
    // break;

    default:
        $b = print_r($tp, true);
        echo <<<EOT
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
         <head><meta charset="utf-8" /></head>
         <body><pre>$b</pre></body>
        </html>
        EOT;
    break;
}

?>