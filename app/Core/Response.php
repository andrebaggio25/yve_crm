<?php

namespace App\Core;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function sendHeaders(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
    }

    public function json(array $data, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');
        $this->sendHeaders();
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function jsonSuccess(array $data = [], string $message = 'Success'): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
    }

    public function jsonError(string $message, int $statusCode = 400, array $errors = []): void
    {
        $response = [
            'success' => false,
            'message' => $message
        ];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        $this->json($response, $statusCode);
    }

    public function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->sendHeaders();
        
        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $viewPath) . '.php';
        
        if (!file_exists($viewFile)) {
            throw new \Exception("View not found: {$viewPath}");
        }
        
        extract($data);
        
        if ($layout) {
            ob_start();
            include $viewFile;
            $content = ob_get_clean();
            
            $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';
            
            if (file_exists($layoutFile)) {
                include $layoutFile;
            } else {
                echo $content;
            }
        } else {
            include $viewFile;
        }
        
        exit;
    }

    public function partial(string $viewPath, array $data = []): string
    {
        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $viewPath) . '.php';
        
        if (!file_exists($viewFile)) {
            throw new \Exception("Partial not found: {$viewPath}");
        }
        
        extract($data);
        ob_start();
        include $viewFile;
        return ob_get_clean();
    }

    public function redirect(string $url, int $statusCode = 302): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        $this->sendHeaders();
        exit;
    }

    public function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    public function with(string $key, mixed $value): self
    {
        Session::flash($key, $value);
        return $this;
    }

    public function withErrors(array $errors): self
    {
        Session::flash('errors', $errors);
        return $this;
    }

    public function withInput(array $input = []): self
    {
        Session::flash('old_input', $input ?: $_POST);
        return $this;
    }

    public function text(string $content, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'text/plain; charset=utf-8');
        $this->sendHeaders();
        
        echo $content;
        exit;
    }

    public function html(string $content, int $statusCode = 200): void
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'text/html; charset=utf-8');
        $this->sendHeaders();
        
        echo $content;
        exit;
    }

    public function download(string $filePath, ?string $fileName = null): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }
        
        $fileName = $fileName ?: basename($filePath);
        
        $this->setHeader('Content-Type', 'application/octet-stream');
        $this->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $this->setHeader('Content-Length', filesize($filePath));
        $this->sendHeaders();
        
        readfile($filePath);
        exit;
    }
}
