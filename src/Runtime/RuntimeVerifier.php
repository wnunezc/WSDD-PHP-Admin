<?php

namespace Wsdd\PhpAdmin\Runtime;

use Wsdd\PhpAdmin\Extensions\ExtensionName;
final class RuntimeVerifier
{
    /**
     * @var \Wsdd\PhpAdmin\Runtime\ApacheRuntimeProbe
     */
    private $apache;
    public function __construct(ApacheRuntimeProbe $apache)
    {
        $this->apache = $apache;
    }
    /** @param array<string, bool> $expected  * @param float $timeoutSeconds
     * @return array
     */
    public function verify(array $expected, $timeoutSeconds = 30)
    {
        $timeoutSeconds = \Wsdd\PhpAdmin\Support\TypeGuard::check($timeoutSeconds, 'float');
        $deadline = microtime(true) + $timeoutSeconds;
        do {
            $errors = [];
            $observed = [];
            foreach (['cli', 'apache'] as $source) {
                try {
                    $snapshot = ($source === 'cli') ? RuntimeSnapshot::freshCli() : $this->apache->observe();
                    $loaded = array_fill_keys(array_map([ExtensionName::class, 'canonical'], $snapshot['extensions']), true);
                    $observed[$source] = ['version' => $snapshot['version'], 'sapi' => $snapshot['sapi'], 'extensions' => []];
                    foreach ($expected as $extension => $enabled) {
                        $isLoaded = isset($loaded[strtolower($extension)]);
                        $observed[$source]['extensions'][$extension] = $isLoaded;
                        if ($isLoaded !== $enabled) {
                            $errors[] = "{$source}: extension {$extension} does not match expected state.";
                        }
                    }
                } catch (\Exception $exception) {
                    $errors[] = $source . ': ' . $exception->getMessage();
                } catch (\Throwable $exception) {
                    $errors[] = $source . ': ' . $exception->getMessage();
                }
            }
            if ($errors === []) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'success', 'observed' => $observed, 'errors' => []], 'array');
            }
            if (microtime(true) >= $deadline) {
                return \Wsdd\PhpAdmin\Support\TypeGuard::check(['status' => 'failed', 'observed' => $observed, 'errors' => $errors], 'array');
            }
            usleep(250000);
        } while (true);
    }
}
