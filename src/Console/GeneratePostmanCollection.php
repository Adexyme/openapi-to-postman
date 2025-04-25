<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratePostmanCollection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'postman:generate
                            {input : Path to OpenAPI JSON file}
                            {output? : Path to write Postman collection JSON}
                            {--folder= : Optional Postman folder name to group endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a Postman Collection from an OpenAPI v3 JSON spec';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $inputPath  = $this->argument('input');
        $outputPath = $this->argument('output') ?? 'postman_collection.json';
        $folderName = $this->option('folder');

        if (! file_exists($inputPath)) {
            $this->error("Input file not found: {$inputPath}");
            return 1;
        }

        $spec = json_decode(file_get_contents($inputPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return 1;
        }

        $collection = [
            'info'     => [
                'name'        => $spec['info']['title'] ?? 'API Collection',
                '_postman_id' => (string) Str::uuid(),
                'description' => $spec['info']['description'] ?? '',
                'schema'      => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                'version'     => $spec['info']['version'] ?? '1.0.0',
            ],
            'variable' => [
                [
                    'key'         => 'baseUrl',
                    'value'       => rtrim($spec['servers'][0]['url'] ?? '{{baseUrl}}', '/'),
                    'description' => 'Base URL',
                ],
                [
                    'key'         => 'token',
                    'value'       => '',
                    'description' => 'Bearer token (public or secret key)',
                ],
            ],
            'auth'     => [
                'type'   => 'bearer',
                'bearer' => [
                    [
                        'key'   => 'token',
                        'value' => '{{token}}',
                        'type'  => 'string',
                    ],
                ],
            ],
            'item'     => [],
        ];

        $items = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $httpMethod => $operation) {
                $request = [
                    'method'      => strtoupper($httpMethod),
                    'header'      => [],
                    'url'         => [
                        'raw'  => '{{baseUrl}}' . $this->formatPath($path),
                        'host' => ['{{baseUrl}}'],
                        'path' => array_values(array_filter(explode('/', trim($path, '/')))),
                    ],
                    'description' => $operation['description'] ?? '',
                ];

                // Authorization header if needed
                if (! empty($spec['security']) || isset($operation['security'])) {
                    $request['header'][] = [
                        'key'   => 'Authorization',
                        'value' => 'Bearer {{token}}',
                        'type'  => 'string',
                    ];
                }

                // JSON body
                if (! empty($operation['requestBody']['content']['application/json']['schema']['properties'])) {
                    $request['header'][] = [
                        'key'   => 'Content-Type',
                        'value' => 'application/json',
                        'type'  => 'text',
                    ];

                    $payload         = $this->generateExamplePayload($operation['requestBody']['content']['application/json']['schema']);
                    $request['body'] = [
                        'mode' => 'raw',
                        'raw'  => json_encode($payload, JSON_PRETTY_PRINT),
                    ];
                }

                // Path parameters
                if (! empty($operation['parameters'])) {
                    $vars = [];
                    foreach ($operation['parameters'] as $param) {
                        if ($param['in'] === 'path') {
                            $default = null;
                            if (isset($param['schema']['default'])) {
                                $default = $param['schema']['default'];
                            } elseif (isset($param['example'])) {
                                $default = $param['example'];
                            }
                            $vars[] = [
                                'key'         => $param['name'],
                                'value'       => $default ?? '',
                                'description' => $param['description'] ?? '',
                            ];
                        }
                    }
                    if ($vars) {
                        $request['url']['variable'] = $vars;
                    }
                }

                $items[] = [
                    'name'    => $operation['summary'] ?? ucfirst($httpMethod) . ' ' . $path,
                    'request' => $request,
                ];
            }
        }

        // Group into folder if requested
        if ($folderName) {
            $collection['item'][] = [
                'name' => $folderName,
                'item' => $items,
            ];
        } else {
            $collection['item'] = $items;
        }

        Storage::disk('local')->put($outputPath, json_encode($collection, JSON_PRETTY_PRINT));
        $this->info("Postman collection generated at: {$outputPath}");

        return 0;
    }

    /**
     * Convert OpenAPI path to Postman style.
     */
    protected function formatPath(string $path): string
    {
        return preg_replace('/\{(.+?)\}/', '{{$1}}', $path);
    }

    /**
     * Build example payload from schema, preserving defaults and examples.
     */
    protected function generateExamplePayload(array $schema): array
    {
        $example    = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $key => $prop) {
            if (array_key_exists('default', $prop)) {
                $example[$key] = $prop['default'];
            } elseif (array_key_exists('example', $prop)) {
                $example[$key] = $prop['example'];
            } else {
                $type = $prop['type'] ?? 'string';
                switch ($type) {
                    case 'integer':
                    case 'number':
                        $example[$key] = 0;
                        break;
                    case 'boolean':
                        $example[$key] = false;
                        break;
                    default:
                        $example[$key] = '';
                }
            }
        }

        return $example;
    }
}
