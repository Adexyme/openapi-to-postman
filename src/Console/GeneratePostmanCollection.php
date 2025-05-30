<?php
namespace Adexyme\OpenApiToPostman\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratePostmanCollection extends Command
{
    protected $signature = 'postman:generate
                            {input : Path to OpenAPI JSON file}
                            {output? : Path to write Postman collection JSON}
                            {--folder= : Optional Postman folder name to group endpoints}';

    protected $description = 'Generate a Postman Collection from an OpenAPI v3 JSON spec';

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
                // Base request skeleton
                $request = [
                    'method'      => strtoupper($httpMethod),
                    'header'      => [],
                    'url'         => [
                        'raw'   => '{{baseUrl}}' . $this->formatPath($path),
                        'host'  => ['{{baseUrl}}'],
                        'path'  => array_values(array_filter(explode('/', trim($path, '/')))),
                    ],
                    'description' => $operation['description'] ?? '',
                ];

                // === PARAMETERS ===
                $pathVars  = [];
                $queryVars = [];
                foreach ($operation['parameters'] ?? [] as $param) {
                    switch ($param['in']) {
                        case 'path':
                            $pathVars[] = [
                                'key'         => $param['name'],
                                'value'       => (string) ($param['schema']['default']
                                                       ?? $param['example']
                                                       ?? ''),
                                'description' => $param['description'] ?? '',
                            ];
                            break;

                        case 'query':
                            $queryVars[] = [
                                'key'         => $param['name'],
                                'value'       => (string) ($param['schema']['default']
                                                       ?? $param['example']
                                                       ?? ''),
                                'description' => $param['description'] ?? '',
                            ];
                            break;

                        case 'header':
                            $request['header'][] = [
                                'key'         => $param['name'],
                                'value'       => (string) ($param['schema']['default']
                                                       ?? $param['example']
                                                       ?? ''),
                                'type'        => 'text',
                                'description' => $param['description'] ?? '',
                            ];
                            break;

                        // you can add 'cookie' or others here...
                    }
                }
                if ($pathVars)  { $request['url']['variable'] = $pathVars; }
                if ($queryVars) { $request['url']['query']    = $queryVars; }

                // === AUTH HEADER ===
                if (! empty($spec['security']) || isset($operation['security'])) {
                    $request['header'][] = [
                        'key'   => 'Authorization',
                        'value' => 'Bearer {{token}}',
                        'type'  => 'string',
                    ];
                }

                // === REQUEST BODY ===
                if (! empty($operation['requestBody']['content'])) {
                    foreach ($operation['requestBody']['content'] as $contentType => $media) {
                        if (isset($media['schema']['properties'])) {
                            switch ($contentType) {
                                case 'application/json':
                                    // form-data for JSON
                                    $request['header'][] = [
                                        'key'   => 'Content-Type',
                                        'value' => 'multipart/form-data',
                                        'type'  => 'text',
                                    ];
                                    $request['body'] = [
                                        'mode'     => 'formdata',
                                        'formdata' => $this->generateFormDataWithExamples($media['schema']),
                                    ];
                                    break;

                                case 'application/x-www-form-urlencoded':
                                    // urlencoded for form data
                                    $request['header'][] = [
                                        'key'   => 'Content-Type',
                                        'value' => 'application/x-www-form-urlencoded',
                                        'type'  => 'text',
                                    ];
                                    $request['body'] = [
                                        'mode'       => 'urlencoded',
                                        'urlencoded' => $this->generateFormDataWithExamples($media['schema']),
                                    ];
                                    break;

                                // add other content-types (e.g. form-data file) here...
                            }
                        }
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

        Storage::disk('local')->put(
            $outputPath,
            json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->info("Postman collection generated at: {$outputPath}");
        return 0;
    }

    protected function formatPath(string $path): string
    {
        return preg_replace('/\{(.+?)\}/', '{{$1}}', $path);
    }

    protected function generateFormDataWithExamples(array $schema): array
    {
        $formData   = [];
        $properties = $schema['properties'] ?? [];

        foreach ($properties as $key => $prop) {
            $value = $prop['example']
                   ?? $prop['default']
                   ?? ($schema['example'][$key] ?? '')
                   ?? '';

            $formData[] = [
                'key'         => $key,
                'value'       => (string) $value,
                'type'        => 'text',
                'description' => $prop['description'] ?? '',
            ];
        }

        return $formData;
    }
}
