<?php
namespace Joomla\Component\Dockerhost\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

class ApiHelper
{
    private string $apiUrl;
    private string $apiToken;
    private $http;

    public function __construct()
    {
        $params           = ComponentHelper::getParams('com_dockerhost');
        $this->apiUrl     = rtrim($params->get('api_url', 'http://127.0.0.1:7001'), '/');
        $this->apiToken   = $params->get('api_token', 'dockerhost-secret-token-2026');
        $this->http       = HttpFactory::getHttp();
    }

    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];
    }

    public function get(string $endpoint): array
    {
        try {
            $response = $this->http->get($this->apiUrl . $endpoint, $this->headers(), 15);
            $body     = trim($response->body ?? '');
            if ($body === '') {
                return [];
            }
            $data = json_decode($body, true);
            if ($data === null) {
                return ['error' => 'Invalid JSON response: ' . substr($body, 0, 200)];
            }
            return $data;
        } catch (\Exception $e) {
            Log::add('DockerHost GET ' . $endpoint . ': ' . $e->getMessage(), Log::ERROR, 'com_dockerhost');
            return ['error' => $e->getMessage()];
        }
    }

    public function getText(string $endpoint): string
    {
        try {
            $response = $this->http->get($this->apiUrl . $endpoint, $this->headers(), 30);
            return $response->body ?? '';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }

    public function post(string $endpoint, array $data = []): array
    {
        try {
            $response = $this->http->post(
                $this->apiUrl . $endpoint,
                json_encode($data),
                $this->headers(),
                30
            );
            $body = trim($response->body ?? '');
            if ($body === '') {
                return ['success' => true];
            }
            $decoded = json_decode($body, true);
            return $decoded ?? ['success' => true];
        } catch (\Exception $e) {
            Log::add('DockerHost POST ' . $endpoint . ': ' . $e->getMessage(), Log::ERROR, 'com_dockerhost');
            return ['error' => $e->getMessage()];
        }
    }

    public function delete(string $endpoint): array
    {
        try {
            $response = $this->http->delete($this->apiUrl . $endpoint, $this->headers(), 15);
            $body     = trim($response->body ?? '');
            if ($body === '') {
                return ['success' => true];
            }
            $decoded = json_decode($body, true);
            return $decoded ?? ['success' => true];
        } catch (\Exception $e) {
            Log::add('DockerHost DELETE ' . $endpoint . ': ' . $e->getMessage(), Log::ERROR, 'com_dockerhost');
            return ['error' => $e->getMessage()];
        }
    }
}
