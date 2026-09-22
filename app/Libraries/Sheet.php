<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Baca xlsx/xls/csv jadi array baris, plus tulis file template.
 * Satu kelas untuk import siswa & import soal — formatnya sama-sama
 * "baris pertama = header".
 */
class Sheet
{
    /**
     * @return list<list<string>> baris data (header sudah dibuang), sel di-trim
     */
    public static function rows(string $path, string $clientName = ''): array
    {
        $ext = strtolower(pathinfo($clientName !== '' ? $clientName : $path, PATHINFO_EXTENSION));

        // CSV dibaca manual: lebih cepat dan tidak rewel soal delimiter/BOM
        if ($ext === 'csv' || $ext === 'txt') {
            return self::csvRows($path);
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();

        $rows = $sheet->rangeToArray(
            'A1:' . $sheet->getHighestDataColumn() . $sheet->getHighestDataRow(),
            null,
            true,  // formula dihitung
            false, // tanpa format tanggal/angka
        );

        return self::bersihkan($rows);
    }

    /** @return list<list<string>> */
    private static function csvRows(string $path): array
    {
        $rows = [];
        $fh   = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }

        $first = true;
        while (($line = fgetcsv($fh, 0, self::delimiter($path))) !== false) {
            if ($first) {
                // buang BOM UTF-8 pada sel pertama
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($line[0] ?? ''));
                $first   = false;
            }
            $rows[] = $line;
        }
        fclose($fh);

        return self::bersihkan($rows);
    }

    private static function delimiter(string $path): string
    {
        $head = (string) file_get_contents($path, false, null, 0, 4096);

        return substr_count($head, ';') > substr_count($head, ',') ? ';' : ',';
    }

    /**
     * Buang header + baris kosong, ubah semua sel jadi string ter-trim.
     *
     * @param array<int, array<int, mixed>> $rows
     *
     * @return list<list<string>>
     */
    private static function bersihkan(array $rows): array
    {
        array_shift($rows); // header

        $out = [];
        foreach ($rows as $row) {
            $row = array_map(
                static fn ($v) => trim((string) ($v ?? '')),
                array_values($row)
            );
            if (implode('', $row) === '') {
                continue; // baris kosong
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Baca format soal topik: satu baris SOAL diikuti lima baris JAWABAN.
     * Kolom: No, Jenis, Kode, Isi, Status Jawaban, Tingkat kesulitan Soal.
     * Status Jawaban = 1 menandai kunci yang benar.
     *
     * @return list<array{baris:int, teks:string, opsi:array<string,string>, kunci:list<string>}>
     */
    public static function soalTopik(string $path, string $clientName = ''): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getActiveSheet();
        $out = [];
        $current = null;

        $flush = static function () use (&$current, &$out): void {
            if ($current !== null) {
                $out[] = $current;
                $current = null;
            }
        };

        for ($row = 1; $row <= $sheet->getHighestDataRow(); $row++) {
            $jenis = strtoupper(trim((string) $sheet->getCell([2, $row])->getValue()));
            $kode  = strtoupper(trim((string) $sheet->getCell([3, $row])->getValue()));
            $isi   = trim((string) $sheet->getCell([4, $row])->getValue());

            if ($jenis === 'SOAL' && $kode === 'Q') {
                $flush();
                $current = [
                    'baris' => $row,
                    'teks'  => $isi,
                    'opsi'  => [],
                    'kunci' => [],
                ];
                continue;
            }

            if ($current === null || $jenis !== 'JAWABAN') {
                continue;
            }

            $jumlahOpsi = count($current['opsi']);
            if ($jumlahOpsi >= 5) {
                continue;
            }

            $huruf = chr(65 + $jumlahOpsi);
            $current['opsi'][$huruf] = $isi;
            $status = trim((string) $sheet->getCell([5, $row])->getValue());
            if (in_array(strtoupper($status), ['1', 'BENAR', 'TRUE', 'YA'], true)) {
                $current['kunci'][] = $huruf;
            }
        }

        $flush();

        return $out;
    }

    /**
     * Kirim file xlsx template ke browser lalu exit.
     *
     * @param list<string>       $header
     * @param list<list<string>> $contoh
     */
    public static function unduhTemplate(string $namaFile, array $header, array $contoh = []): void
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->fromArray($header, null, 'A1');
        if ($contoh !== []) {
            $sheet->fromArray($contoh, null, 'A2');
        }

        $lastCol = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setWidth(22);
        }

        // header HTTP diset manual: writer menulis langsung ke php://output
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $namaFile . '"');
        header('Cache-Control: max-age=0');
        (new XlsxWriter($ss))->save('php://output');
        exit;
    }

    /**
     * Kirim data sebagai xlsx (dipakai export nilai).
     *
     * @param list<string>            $header
     * @param list<list<int|string>>  $baris
     */
    public static function unduhData(string $namaFile, array $header, array $baris): void
    {
        self::unduhTemplate($namaFile, $header, $baris);
    }
}
