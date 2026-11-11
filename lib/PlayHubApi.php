<?php
declare(strict_types=1);

final class PlayHubApiException extends RuntimeException
{
    private int $httpStatus;
    private array $response;

    public function __construct(string $message, int $httpStatus, array $response = [])
    {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
        $this->response = $response;
    }

    public function httpStatus(): int { return $this->httpStatus; }
    public function response(): array { return $this->response; }
}

final class PlayHubApi
{
    private PDO $db;
    private string $base;
    private string $key;
    private string $secret;
    private array $override;
    private bool $useStoredToken;

    public function __construct(PDO $db, array $override = [])
    {
        $this->db = $db;
        $this->override = $override;
        $base = trim((string)($override['base_url'] ?? ''));
        $key = trim((string)($override['api_key'] ?? ''));
        $secret = trim((string)($override['api_secret'] ?? ''));
        $this->base = rtrim($base !== '' ? $base : ph_setting($db, 'base_url', self::sandboxBaseUrl()), '/');
        $this->key = $key !== '' ? $key : ph_decrypt(ph_setting($db, 'api_key'));
        $this->secret = $secret !== '' ? $secret : ph_decrypt(ph_setting($db, 'api_secret'));
        $this->useStoredToken = $key === '' && $secret === '';
    }

    public static function sandboxBaseUrl(): string
    {
        return 'https://sandbox-api.playhub.com.br/api/v3';
    }

    public static function defaults(): array
    {
        return [
            'token' => '/authentication/tokens',
            'customers' => '/customers',
            'customer' => '/customers/{username}',
            'subscriptions' => '/customers/{username}/subscriptions',
            'subscription' => '/customers/{username}/subscriptions/{product_id}',
        ];
    }

    public function configured(): bool
    {
        return $this->key !== '' && $this->secret !== '';
    }

    private function route(string $name, array $vars = []): string
    {
        $settingKey = 'route_'.$name;
        $custom = trim((string)($this->override[$settingKey] ?? ''));
        $path = $custom !== '' ? $custom : ph_setting($this->db, $settingKey, self::defaults()[$name] ?? '');
        foreach ($vars as $key => $value) {
            $path = str_replace('{'.$key.'}', rawurlencode((string)$value), $path);
        }
        return $path;
    }

    private function token(bool $forceRefresh = false): string
    {
        if ($this->useStoredToken && !$forceRefresh) {
            $cached = ph_setting($this->db, 'access_token');
            $until = ph_setting($this->db, 'token_until');
            if ($cached !== '' && $until !== '' && strtotime($until) > time() + 60) {
                return ph_decrypt($cached);
            }
        }
        $response = $this->raw('POST', $this->route('token'), [
            'ApiKey' => $this->key,
            'ApiSecret' => $this->secret,
        ], false);
        $token = (string)($response['AccessToken'] ?? $response['accessToken'] ?? $response['access_token'] ?? $response['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('A PlayHub não retornou um token de acesso.');
        }
        if ($this->useStoredToken) {
            ph_set_setting($this->db, 'access_token', ph_encrypt($token));
            ph_set_setting($this->db, 'token_until', date('Y-m-d H:i:s', time() + 1740));
        }
        return $token;
    }

    private function raw(string $method, string $path, ?array $body, bool $auth = true): array
    {
        if (!$this->configured()) {
            throw new RuntimeException('Configure a chave e o segredo da API.');
        }
        $handle = curl_init($this->base.'/'.ltrim($path, '/'));
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: Bearer '.$this->token();
        }
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Não foi possível preparar os dados para a PlayHub.');
            }
            curl_setopt($handle, CURLOPT_POSTFIELDS, $encoded);
        }
        $raw = curl_exec($handle);
        $http = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($raw === false) {
            throw new RuntimeException('Falha de conexão: '.$error);
        }
        $json = json_decode((string)$raw, true);
        $response = is_array($json) ? $json : [];
        if ($http < 200 || $http >= 300) {
            throw new PlayHubApiException(
                'PlayHub HTTP '.$http.': '.$this->errorMessage($response, (string)$raw),
                $http,
                $response
            );
        }
        return $response;
    }

    private function errorMessage(array $json, string $raw): string
    {
        $details = [];
        $state = $json['ModelState'] ?? $json['modelState'] ?? null;
        if (is_array($state)) {
            foreach ($state as $messages) {
                foreach ((array)$messages as $message) {
                    $message = trim((string)$message);
                    if ($message !== '') { $details[] = $message; }
                }
            }
        }
        if ($details) {
            return implode(' ', array_values(array_unique($details)));
        }
        foreach (['Message', 'message', 'error', 'Error'] as $key) {
            if (isset($json[$key]) && is_scalar($json[$key])) {
                return trim((string)$json[$key]);
            }
        }
        $fallback = trim(strip_tags($raw));
        return $fallback !== '' ? substr($fallback, 0, 300) : 'Resposta sem detalhes.';
    }

    public function test(): array
    {
        $token = $this->token(true);
        return ['authenticated' => $token !== '', 'tokenLength' => strlen($token)];
    }

    public function getClient(string $username): ?array
    {
        try {
            return $this->raw('GET', $this->route('customer', ['username' => $username]), null);
        } catch (PlayHubApiException $exception) {
            if ($exception->httpStatus() === 404) { return null; }
            throw $exception;
        }
    }

    public function createClient(array $payload): array
    {
        return $this->raw('POST', $this->route('customers'), $payload);
    }

    public function updateClient(string $username, array $payload): array
    {
        return $this->raw('PUT', $this->route('customer', ['username' => $username]), $payload);
    }

    public function subscriptions(string $username): array
    {
        return $this->raw('GET', $this->route('subscriptions', ['username' => $username]), null);
    }

    public function associate(string $username, string $productId): array
    {
        return $this->raw('POST', $this->route('subscriptions', ['username' => $username]), ['ProductId' => $productId]);
    }

    public function disassociate(string $username, string $productId): array
    {
        return $this->raw('DELETE', $this->route('subscription', [
            'username' => $username,
            'product_id' => $productId,
        ]), null);
    }
}
