<?php

use Psr\Container\ContainerInterface;
use Mu\CherryPicker\Config\ConfigLoader;

// Create a test ConfigLoader instance with mock configuration
class TestConfigLoader extends ConfigLoader
{
    public function __construct()
    {
        // Initialize with test config data instead of loading from files
        $this->setTestConfig([
            'cherry-picker' => [
                'jira' => [
                    'api_url' => 'https://jira.example.com/rest/api/3',
                    'email' => 'test@example.com',
                    'personal_access_token' => 'test-token-123',
                ],
                'gitlab' => [
                    'api_url' => 'https://gitlab.example.com/api/v4',
                    'personal_access_token' => 'test-gitlab-token-123',
                ],
            ],
        ]);
    }

    public function setTestConfig(array $config): void
    {
        $reflection = new ReflectionClass(ConfigLoader::class);
        $property = $reflection->getProperty('config');
        $property->setValue($this, $config);
    }
}

// Create a mock container for testing
class TestContainer implements ContainerInterface
{
    private $services = [];

    public function __construct()
    {
        $this->services[ConfigLoader::class] = new TestConfigLoader();
    }

    public function get(string $id)
    {
        return $this->services[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

// Set the global container for testing BEFORE any tests use config()
$GLOBALS['container'] = new TestContainer();
