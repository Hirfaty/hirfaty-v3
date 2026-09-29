<?php

namespace App\Services;

use Closure;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The CSV / Excel download behind every "Exporter" button in the admin.
 *
 * Each list only decides *what* goes in the file — the headings and one row
 * per record — and hands this service a closure yielding those rows. Keeping
 * the file mechanics in one place means the protections below (formula
 * neutralising, the BOM, streaming) cannot drift between exports.
 */
class SpreadsheetExportService
{
    public const FORMATS = ['csv', 'xlsx'];

    /**
     * @param  'csv'|'xlsx'  $format
     * @param  string  $name  Filename without date or extension, e.g. "utilisateurs".
     * @param  list<string>  $headings
     * @param  Closure(): iterable<list<string|int|float|null>>  $rows  Called once, inside
     *                                                                  the download, so the query runs
     *                                                                  while the file is written.
     */
    public function download(string $format, string $name, array $headings, Closure $rows): StreamedResponse|BinaryFileResponse
    {
        $filename = "{$name}-hirfaty-".now()->format('Y-m-d').".{$format}";

        return $format === 'xlsx'
            ? $this->xlsx($filename, $headings, $rows)
            : $this->csv($filename, $headings, $rows);
    }

    /**
     * Comma-separated, UTF-8 with a BOM so Excel and Numbers read the Arabic
     * and accented names correctly. Streamed, so a long list is never held in
     * memory at once.
     */
    private function csv(string $filename, array $headings, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headings, escape: '');

            foreach ($rows() as $row) {
                fputcsv(
                    $out,
                    array_map(fn ($value) => $this->neutraliseFormula((string) $value), $row),
                    escape: '',
                );
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * A real .xlsx: opens in Excel with the columns already split, whatever
     * the machine's list separator is (a French-locale Excel splits CSVs on
     * `;`, not `,`, and would put every column into one).
     */
    private function xlsx(string $filename, array $headings, Closure $rows): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export-');

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValuesWithStyle($headings, (new Style)->withFontBold(true)));

        foreach ($rows() as $row) {
            // Built cell by cell rather than Row::fromValues(): that guesses a
            // type from each value and turns a string beginning with `=` — a
            // name any visitor can type — into a live formula.
            $writer->addRow(new Row(array_map(
                fn ($value): Cell => is_int($value) || is_float($value)
                    ? new NumericCell($value)
                    : new StringCell((string) $value),
                array_values($row),
            )));
        }

        $writer->close();

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * CSV has no cell types, so a spreadsheet evaluates any cell that looks
     * like a formula — and names, emails and shop names are typed by the
     * public. Prefixing a quote disarms it (OWASP "CSV injection").
     *
     * Phone numbers and plain numbers are the exception: `+212 6…` starts
     * with `+` but is digits only, carries no formula, and would be mangled
     * by the quote.
     */
    private function neutraliseFormula(string $value): string
    {
        if ($value === '' || ! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        if (preg_match('/^[+-]?[\d\s().-]+$/', $value)) {
            return $value;
        }

        return "'".$value;
    }
}
