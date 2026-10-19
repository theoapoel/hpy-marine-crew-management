<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A list of records as a one-sheet Excel workbook: a bold, frozen header row and one
 * row per record. Subclasses say which columns to write and how to read them.
 *
 * Rows are streamed straight to the browser as they are written, so the export of a
 * large list neither needs a temp file nor holds every record in memory.
 */
abstract class XlsxExport
{
    /** @return array<string, string> Column heading => attribute read off each record. */
    abstract protected function columns(): array;

    /** @return array<int, string> Attributes written as real Excel dates rather than text. */
    protected function dates(): array
    {
        return [];
    }

    /** @return array<int, string> Attributes written as numbers rather than text. */
    protected function numbers(): array
    {
        return [];
    }

    /** @return array<int, float> Column widths by 1-based index, for the columns that need more than the default. */
    protected function widths(): array
    {
        return [];
    }

    protected function sheetName(): string
    {
        return 'Sheet1';
    }

    /** Read one attribute off a record; override to unwrap relations and the like. */
    protected function value(object $record, string $attribute): mixed
    {
        return $record->{$attribute} ?? null;
    }

    /**
     * @param  iterable<int, object>  $records
     */
    public function download(iterable $records, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($records) {
            $this->write($records, 'php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  iterable<int, object>  $records
     */
    public function write(iterable $records, string $path): void
    {
        $columns = $this->columns();

        $options = new Options;
        $options->setColumnWidth(14, ...range(1, count($columns)));
        foreach ($this->widths() as $index => $width) {
            $options->setColumnWidth($width, $index);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName());
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $writer->addRow(Row::fromValues(array_keys($columns), (new Style)->setFontBold()));

        $dateStyle = (new Style)->setFormat('yyyy-mm-dd');

        foreach ($records as $record) {
            $cells = [];

            foreach ($columns as $attribute) {
                $cells[] = $this->cell($this->value($record, $attribute), $attribute, $dateStyle);
            }

            $writer->addRow(new Row($cells));
        }

        $writer->close();
    }

    private function cell(mixed $value, string $attribute, Style $dateStyle): Cell
    {
        if (in_array($attribute, $this->dates(), true)) {
            $date = $this->date($value);

            return $date ? Cell::fromValue($date, $dateStyle) : Cell::fromValue('');
        }

        if (in_array($attribute, $this->numbers(), true) && is_numeric($value)) {
            return Cell::fromValue((float) $value);
        }

        return Cell::fromValue($value === null ? '' : (string) $value);
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value)->toDateTimeImmutable();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
