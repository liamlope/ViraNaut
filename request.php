<?php
require_once 'config.php';

class CurlRequest {
    private $url;
    private $headers = [];
    private $timeout = null;
    private $connectTimeout = null;
    private $authToken = null;
    private $api_key = null;
    private $cookie = null;
    private $csrfToken = null;

    public function __construct($url) {
        global $request_exec_timeout;
        $this->url = $url;
        $this->timeout = $request_exec_timeout;
    }

    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }

    public function setConnectTimeout($milliseconds) {
        $this->connectTimeout = $milliseconds;
    }

    public function setHeaders(array $headers) {
        $this->headers = array_merge($this->headers, $headers);
    }

    public function setBearerToken($token) {
        $this->authToken = $token;
    }
    
    public function api_key($token) {
        $this->api_key = $token;
    }

    public function setCookie($cookieStr) {
        $this->cookie = $cookieStr;
    }

    /** 3x-ui v3: session POSTs need X-CSRF-Token (Bearer API token skips CSRF). */
    public function setCsrfToken($token) {
        $this->csrfToken = $token;
    }

    private function prepareHeaders() {
        $headers = $this->headers;

        if ($this->authToken) {
            $headers[] = "Authorization: Bearer {$this->authToken}";
        }
        if ($this->api_key) {
            $headers[] = $this->authToken;
        }
        if ($this->csrfToken !== null && $this->csrfToken !== '') {
            $headers[] = 'X-CSRF-Token: ' . $this->csrfToken;
        }

        return $headers;
    }

    private function execute($method, $data = null) {
        global $request_exec_timeout;
        if (!$this->timeout) {
            $this->timeout = ($request_exec_timeout !== null && (int) $request_exec_timeout > 0)
                ? (int) $request_exec_timeout
                : 120000;
        }
        $this->timeout = max(120000, (int) $this->timeout);
        if (!$this->connectTimeout) {
            $this->connectTimeout = min(45000, (int) max(15000, $this->timeout / 3));
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');

        $finalHeaders = $this->prepareHeaders();
        if (!empty($finalHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $finalHeaders);
        }
        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);   
        }
        if ($data) {
            if (is_array($data)) {
                $data = http_build_query($data);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return [
                'status' => null,
                'body' => null,
                'error' => $error,
            ];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'body' => $response
        ];
    }

    public function get() {
        return $this->execute("GET");
    }

    public function post($data) {
        return $this->execute("POST", $data);
    }

    public function put($data) {
        return $this->execute("PUT", $data);
    }

    public function delete($data = null) {
        return $this->execute("DELETE", $data);
    }
    public function PATCH($data = null){
        return $this->execute('PATCH',$data);
    }
}