<?php

namespace App\Exports;

use App\Models\Crew;
use Illuminate\Support\Carbon;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The crew database as an Excel workbook: one sheet, one row per crew member, the
 * same columns whichever backend (ERP HPY or the local placeholder) supplied them.
 *
 * Rows are streamed straight to the browser as they are written, so the export of a
 * large fleet neither needs a temp file nor holds every record in memory.
 */
class CrewExport
{
    /** Column heading => how to read it off a Crew model. */
    private const COLUMNS = [
        'Employee ID' => 'id',
        'Name' => 'name',
        'Rank' => 'rank',
        'Department' => 'department',
        'Crew Status' => 'status',
        'Vessel' => 'vessel',
        'Sign On Date' => 'sign_on_date',
        'Contract End' => 'contract_end_date',
        'Nationality' => 'nationality',
        'Gender' => 'gender',
        'Date of Birth' => 'date_of_birth',
        'Seaman Book No' => 'seaman_book_no',
        'Seaman Book Expiry' => 'seaman_book_expiry',
        'Passport No' => 'passport_number',
        'Passport Expiry' => 'valid_upto',
        'Phone' => 'cell_number',
        'Email' => 'personal_email',
        'Employee Number' => 'employee_number',
        'Date of Joining' => 'date_of_joining',
        'Last Salary' => 'last_salary',
        'Currency' => 'last_salary_currency',
        'Employee Status' => 'employee_status',
        'Company' => 'company',
    ];

    /** Attributes written as real Excel dates rather than text. */
    private const DATES = [
        'sign_on_date', 'contract_end_date', 'date_of_birth', 'seaman_book_expiry',
        'valid_upto', 'date_of_joining',
    ];

    /**
     * @param  iterable<int, Crew>  $crews
     */
    public function download(iterable $crews, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($crews) {
            $this->write($crews, 'php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  iterable<int, Crew>  $crews
     */
    public function write(iterable $crews, string $path): void
    {
        $options = new Options;
        $options->setColumnWidth(14, ...range(1, count(self::COLUMNS)));
        $options->setColumnWidth(28, 2); // Name
        $options->setColumnWidth(22, 6); // Vessel

        $writer = new Writer($options);
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Crew');
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $writer->addRow(Row::fromValues(array_keys(self::COLUMNS), (new Style)->setFontBold()));

        $dateStyle = (new Style)->setFormat('yyyy-mm-dd');

        foreach ($crews as $crew) {
            $cells = [];

            foreach (self::COLUMNS as $attribute) {
                $cells[] = $this->cell($crew, $attribute, $dateStyle);
            }

            $writer->addRow(new Row($cells));
        }

        $writer->close();
    }

    private function cell(Crew $crew, string $attribute, Style $dateStyle): Cell
    {
        $value = $crew->{$attribute} ?? null;

        // The local placeholder links a Vessel row; ERP HPY gives the vessel's name.
        if ($attribute === 'vessel' && is_object($value)) {
            $value = $value->name ?? null;
        }

        if (in_array($attribute, self::DATES, true)) {
            $date = $this->date($value);

            return $date ? Cell::fromValue($date, $dateStyle) : Cell::fromValue('');
        }

        if (is_numeric($value) && $attribute === 'last_salary') {
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
