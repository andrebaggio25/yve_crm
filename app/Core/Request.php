<?php

namespace App\Core;

class Request
{
    private array $params = [];
    private array $queryParams = [];

    /**
     * Corpo JSON decodificado (uma unica leitura de php://input por pedido).
     *
     * @var array<string, mixed>|null
     */
    private ?array $jsonBodyCache = null;

    public function __construct()
    {
        $this->queryParams = $_GET;
    }

    public function getMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH);
        return $uri ?: '/';
    }

    public function getPath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $requestUri = $this->getUri();
        
        if (strpos($requestUri, $scriptName) === 0) {
            $requestUri = substr($requestUri, strlen($scriptName));
        }
        
        $requestUri = trim($requestUri, '/');
        return $requestUri ?: '';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $_POST);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->getMethod() === 'GET') {
            return $this->get($key, $default);
        }
        
        $input = $this->getJsonInput();
        return $input[$key] ?? $_POST[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function hasAny(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->has($key)) {
                return true;
            }
        }
        return false;
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function getJsonInput(): array
    {
        if ($this->jsonBodyCache !== null) {
            return $this->jsonBodyCache;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') === false) {
            $this->jsonBodyCache = [];

            return $this->jsonBodyCache;
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);
        $this->jsonBodyCache = is_array($decoded) ? $decoded : [];

        return $this->jsonBodyCache;
    }

    public function getFiles(): array
    {
        return $_FILES;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function isAjax(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function isJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return strpos($accept, 'application/json') !== false || $this->isAjax();
    }

    public function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strpos($auth, 'Bearer ') === 0) {
            return substr($auth, 7);
        }
        return null;
    }

    public function getIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? '0.0.0.0';
    }

    public function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function getParam(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        $data = [];

        foreach ($rules as $field => $ruleString) {
            $value = $this->input($field);
            $fieldRules = explode('|', $ruleString);
            
            foreach ($fieldRules as $rule) {
                $rule = trim($rule);
                
                if ($rule === 'required' && empty($value)) {
                    $errors[$field][] = "O campo {$field} é obrigatório.";
                    break;
                }
                
                if ($rule === 'email' && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "O campo {$field} deve ser um email válido.";
                }
                
                if (strpos($rule, 'min:') === 0 && !empty($value)) {
                    $min = (int) substr($rule, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "O campo {$field} deve ter pelo menos {$min} caracteres.";
                    }
                }
                
                if (strpos($rule, 'max:') === 0 && !empty($value)) {
                    $max = (int) substr($rule, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "O campo {$field} deve ter no máximo {$max} caracteres.";
                    }
                }
            }
            
            $data[$field] = $value;
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors));
        }

        return $data;
    }
}
