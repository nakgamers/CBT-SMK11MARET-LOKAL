<?php

namespace App\Libraries;

final class ResultExport
{
    /** @param list<array<string, mixed>> $hasil @return list<string> */
    public static function classes(array $hasil): array
    {
        $kelas = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => trim((string) ($row['kelas'] ?? '')),
            $hasil
        ))));
        natcasesort($kelas);

        return array_values($kelas);
    }

    /**
     * @param list<array<string, mixed>> $hasil
     * @return list<array<string, mixed>>
     */
    public static function filter(array $hasil, string $kelas): array
    {
        $kelas = trim($kelas);
        if ($kelas === '') {
            return array_values($hasil);
        }

        return array_values(array_filter(
            $hasil,
            static fn (array $row): bool => trim((string) ($row['kelas'] ?? '')) === $kelas
        ));
    }

    public static function slug(string $value): string
    {
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($value));

        return strtolower(trim((string) $slug, '-'));
    }
}
