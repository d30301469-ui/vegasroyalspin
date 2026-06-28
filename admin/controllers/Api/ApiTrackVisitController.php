<?php

require_once SERVICE_PATH . '/BackendApiClient.php';

class ApiTrackVisitController
{
    public function index(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $ip        = $this->getUserIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $referer   = $_SERVER['HTTP_REFERER'] ?? '';

        if (empty($ip) || $ip === '0.0.0.0') {
            http_response_code(400);
            echo json_encode(['success' => false, 'msg' => 'No IP available']);
            return;
        }

        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,regionName,city,lat,lon,message";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        $country     = null;
        $countryCode = null;
        $region      = null;
        $city        = null;
        $lat         = null;
        $lon         = null;
        $apiMessage  = null;

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['status']) && $data['status'] === 'success') {
                $country     = $data['country'] ?? null;
                $countryCode = $data['countryCode'] ?? null;
                $region      = $data['regionName'] ?? null;
                $city        = $data['city'] ?? null;
                $lat         = isset($data['lat']) ? (float) $data['lat'] : null;
                $lon         = isset($data['lon']) ? (float) $data['lon'] : null;
            } else {
                $apiMessage = $data['message'] ?? null;
            }
        } else {
            $apiMessage = $curlErr ?: 'empty_response';
        }

        $logged = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/analytics/visit', [], [
            'ip'            => $ip,
            'country_code'  => $countryCode,
            'country_name'  => $country,
            'region'        => $region,
            'city'          => $city,
            'lat'           => $lat,
            'lon'           => $lon,
            'user_agent'    => $userAgent,
            'referer'       => $referer,
        ]);

        if ($logged !== null && (($logged['success'] ?? false) === true || ($logged['ok'] ?? false) === true)) {
            echo json_encode([
                'success'     => true,
                'country'     => $country,
                'countryCode' => $countryCode,
            ]);
            return;
        }

        echo json_encode([
            'success'     => $logged === null ? true : (bool) ($logged['success'] ?? true),
            'country'     => $country,
            'countryCode' => $countryCode,
            'note'        => $logged === null ? 'visit_log_api_skipped' : null,
        ]);
    }

    private function getUserIp(): string
    {
        if (function_exists('metropol_cloudflare_client_ip')) {
            $ip = metropol_cloudflare_client_ip();
            if ($ip !== '') {
                return $ip;
            }
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $candidate = trim(explode(',', $value)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return '0.0.0.0';
    }
}
