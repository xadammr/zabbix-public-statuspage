<?php

namespace App\Services;

use Illuminate\Support\Collection;

class StatusPageHostDiscovery
{
    public function __construct(protected ZabbixClient $zabbix) {}

    public function fetch(): array
    {
        return $this->zabbix->request('host.get', [
            'tags' => [['tag' => 'statuspage', 'operator' => 4]],
            'output' => ['hostid', 'host', 'name', 'description'],
            'selectTags' => ['tag', 'value'],
            'sortfield' => 'name',
        ]);
    }

    public function forSections(Collection $hosts, Collection $sections): Collection
    {
        return $hosts
            ->map(function (array $host) use ($sections): ?array {
                $hostSections = collect($host['tags'] ?? [])->where('tag', 'statuspage')->pluck('value');
                $section = $sections->first(fn (string $section) => $hostSections->contains($section));

                return $section ? [...$host, 'statuspage_section' => $section] : null;
            })
            ->filter()
            ->unique('hostid')
            ->values();
    }
}
