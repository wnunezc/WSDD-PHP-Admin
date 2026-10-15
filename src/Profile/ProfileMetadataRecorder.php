<?php

namespace Wsdd\PhpAdmin\Profile;

use Wsdd\PhpAdmin\Extensions\ExtensionCatalog;
use Wsdd\PhpAdmin\Extensions\ExtensionName;

final class ProfileMetadataRecorder
{
    private $profiles;
    private $catalog;

    public function __construct(ProfileRepository $profiles, ExtensionCatalog $catalog)
    {
        $this->profiles = $profiles;
        $this->catalog = $catalog;
    }

    public function record(array $report, array $installations = [], $reconciliation = false, $extension = null)
    {
        $loaded = $this->profiles->load();
        if (!$loaded->isLoaded() || $loaded->profile === null) {
            throw new \RuntimeException('Cannot record metadata without a valid persistent profile.');
        }
        $verification = isset($report['verification']) ? $report['verification'] : [];
        $observed = isset($verification['observed']) ? $verification['observed'] : [];
        $catalog = $this->catalog->status([]);
        $now = gmdate('c');
        $entries = [];
        $summary = ['status' => isset($report['status']) ? $report['status'] : 'failed',
            'recorded_at' => $now, 'message' => isset($report['message']) ? $report['message'] : '',
            'verification' => $verification, 'errors' => isset($report['errors']) ? $report['errors'] : [],
            'reload' => isset($report['reload']) ? $report['reload'] : []];
        foreach ($loaded->profile->extensions() as $name => $previous) {
            if (!$reconciliation && $extension !== $name) continue;
            if (!ExtensionName::isValid($name)) continue;
            $entry = isset($catalog[$name]) ? $catalog[$name] : [];
            $sources = [];
            $versions = [];
            foreach (['cli', 'apache'] as $source) {
                $snapshot = isset($observed[$source]) ? $observed[$source] : [];
                $sources[$source] = [
                    'loaded' => isset($snapshot['extensions'][$name]) ? $snapshot['extensions'][$name] : null,
                    'version' => isset($snapshot['extension_versions'][$name]) ? $snapshot['extension_versions'][$name] : null,
                    'php_version' => isset($snapshot['version']) ? $snapshot['version'] : null,
                    'observed_at' => isset($snapshot['observed_at']) ? $snapshot['observed_at'] : null];
                if ($sources[$source]['loaded'] === true) $versions[] = $sources[$source]['version'];
            }
            $cli = $sources['cli']['loaded'];
            $apache = $sources['apache']['loaded'];
            $state = 'unknown';
            if (is_bool($cli) && is_bool($apache)) {
                $state = $cli !== $apache ? 'inconsistent' : ($cli ? 'enabled' : (!empty($entry['available']) ? 'disabled' : 'missing'));
            }
            $metadata = ['observed_state' => $state, 'observed_at' => $now, 'observations' => $sources,
                'installed_version' => count($versions) === 2 && $versions[0] === $versions[1] ? $versions[0] : null,
                'system_dependencies' => isset($entry['recipe_available']) && $entry['recipe_available'] ? $entry['packages'] : null,
                'origin' => isset($entry['recipe_available']) && $entry['recipe_available'] ? $entry['origin'] : null,
                'metadata_source' => 'runtime-verification-and-reviewed-catalog'];
            foreach (['install_method', 'installed_at', 'incompatible_with', 'conflicts_with'] as $field) {
                $metadata[$field] = array_key_exists($field, $previous) ? $previous[$field] : null;
            }
            if (isset($installations[$name]['method'])) {
                $metadata['install_method'] = $installations[$name]['method'];
                $metadata['installed_at'] = $now;
                $metadata['installation'] = $installations[$name];
            }
            $metadata['updated_at'] = $now;
            if ($reconciliation) $metadata['last_reconciliation'] = $summary;
            if ($extension === $name) $metadata['last_operation'] = $summary;
            $entries[$name] = $metadata;
        }
        $this->profiles->save($loaded->profile->withMetadata($entries, $reconciliation ? $summary : null));
    }
}
