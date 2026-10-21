<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\BusinessIdentifier;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Support\Import\Normalizer;
use App\Support\Import\XlsxReader;
use App\Support\Permissions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ImportTest extends TestCase
{
    private const HEADER = ['Company Name', 'Category', 'Phone', 'Mobile', 'Email', 'Website', 'Address', 'City', 'Latitude', 'Longitude', 'Description'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.imports_disk' => 'local']);
        Storage::fake('local');
    }

    /** Writes a minimal but standard .xlsx (shared strings) — the format Excel and Google Sheets export. */
    private function xlsx(array $rows, string $sheet = 'Directory'): string
    {
        $strings = [];
        $index = function (string $value) use (&$strings): int {
            if (! array_key_exists($value, $strings)) {
                $strings[$value] = count($strings);
            }

            return $strings[$value];
        };
        $xmlRows = '';
        foreach (array_values($rows) as $r => $cells) {
            $xmlRows .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $ref = chr(65 + $c).($r + 1);
                $xmlRows .= is_int($value) || is_float($value)
                    ? '<c r="'.$ref.'"><v>'.$value.'</v></c>'
                    : '<c r="'.$ref.'" t="s"><v>'.$index((string) $value).'</v></c>';
            }
            $xmlRows .= '</row>';
        }
        $sst = '';
        foreach (array_keys($strings) as $s) {
            $sst .= '<si><t xml:space="preserve">'.htmlspecialchars((string) $s, ENT_XML1).'</t></si>';
        }

        $path = tempnam(sys_get_temp_dir(), 'dq').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$sheet.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.count($strings).'" uniqueCount="'.count($strings).'">'.$sst.'</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xmlRows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function sampleRows(): array
    {
        return [
            ['Qatar Business Listings — exported 2026'],
            [],
            self::HEADER,
            ['Pearl Coffee House W.L.L', 'Cafes', '4444 1234', '5555 6789', 'INFO@PEARLCOFFEE.QA', 'www.pearlcoffee.qa', 'Porto Arabia, The Pearl', 'Doha', 25.3712, 51.5503, 'Specialty coffee roastery.'],
            // Same company again with formatting differences: must merge, not duplicate.
            ['Pearl Coffee House', 'Coffee Shop', '+974 4444-1234', 'N/A', 'info@pearlcoffee.qa', 'https://pearlcoffee.qa/', 'N/A', 'Doha', null, null, ''],
            ['Al Wakra Auto Garage', 'Car Repair', '00974 4466 7788', '', '', 'https://facebook.com/wakragarage', 'Industrial Area', 'Al Wakrah', 'N/A', 'N/A', '-'],
            ['', '', '', '', '', '', '', '', '', '', ''],
            ['Incomplete Trading', '', '', '', '', '', '', '', '', '', ''],
        ];
    }

    public function test_normalizer_canonicalises_qatar_contacts(): void
    {
        $this->assertSame('+97444441234', Normalizer::phone('4444 1234'));
        $this->assertSame('+97444441234', Normalizer::phone('00974-4444-1234'));
        $this->assertSame('+97455556789', Normalizer::phone('+974 5555 6789'));
        $this->assertNull(Normalizer::phone('N/A'));
        $this->assertSame('info@pearl.qa', Normalizer::email(' INFO@Pearl.qa '));
        $this->assertSame('pearl.qa', Normalizer::domain('https://www.pearl.qa/menu'));
        $this->assertNull(Normalizer::domain('https://facebook.com/wakragarage'));
        $this->assertSame(Normalizer::nameKey('Pearl Coffee House W.L.L'), Normalizer::nameKey('pearl coffee house'));
        $this->assertNull(Normalizer::text('N/A'));
    }

    public function test_xlsx_reader_streams_rows(): void
    {
        $rows = iterator_to_array((new XlsxReader($this->xlsx($this->sampleRows())))->rows());

        $this->assertSame('Company Name', $rows[3][0]);
        $this->assertSame('Pearl Coffee House W.L.L', $rows[4][0]);
    }

    public function test_command_imports_detects_header_and_merges_duplicates(): void
    {
        $file = $this->xlsx($this->sampleRows());

        $this->artisan('daleel:import', ['file' => $file, '--source' => 'Sample Directory'])->assertSuccessful();

        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(3, $batch->header_row);

        $pearl = Business::query()->where('name_key', Normalizer::nameKey('Pearl Coffee House'))->get();
        $this->assertCount(1, $pearl, 'The second Pearl Coffee row must merge into the first.');
        $pearl = $pearl->first();
        $this->assertSame('+974 4444 1234', $pearl->phone);
        $this->assertSame('info@pearlcoffee.qa', $pearl->email);
        $this->assertTrue($pearl->isPublished());
        $this->assertSame('exact', $pearl->geo_precision);
        $this->assertTrue(BusinessIdentifier::query()->where(['type' => 'domain', 'value' => 'pearlcoffee.qa', 'business_id' => $pearl->id])->exists());

        $garage = Business::query()->where('name_en', 'Al Wakra Auto Garage')->firstOrFail();
        $this->assertNull($garage->latitude, 'N/A coordinates must be ignored.');
        $this->assertNull($garage->website, 'Social links are not stored as the website.');
        $this->assertSame('al-wakrah', $garage->municipality?->slug);

        // The repeated Pearl Coffee row adds nothing new, so it is reported as a skipped duplicate.
        $this->assertSame(['created' => 3, 'skipped' => 1], ImportRow::query()->where('import_batch_id', $batch->id)->selectRaw('status, count(*) as n')->groupBy('status')->orderBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all());
        $this->assertTrue(Business::query()->where('name_en', 'Incomplete Trading')->exists(), 'A name alone is enough; missing details show as N/A.');
    }

    public function test_reimporting_the_same_file_creates_nothing_new(): void
    {
        $file = $this->xlsx($this->sampleRows());
        $this->artisan('daleel:import', ['file' => $file, '--source' => 'Sample Directory'])->assertSuccessful();
        $count = Business::query()->count();

        $this->artisan('daleel:import', ['file' => $file, '--source' => 'Sample Directory'])->assertSuccessful();

        $this->assertSame($count, Business::query()->count());
        $this->assertSame(0, ImportBatch::query()->latest('id')->firstOrFail()->created_count);
    }

    public function test_csv_from_another_directory_is_deduplicated_by_phone_and_email(): void
    {
        $this->artisan('daleel:import', ['file' => $this->xlsx($this->sampleRows()), '--source' => 'Sample Directory'])->assertSuccessful();

        $csv = tempnam(sys_get_temp_dir(), 'dq').'.csv';
        file_put_contents($csv, "\u{FEFF}Business Name;Tel;E-mail\nPearl Coffee House Co.;44441234;info@pearlcoffee.qa\nNew Bakery Doha;44990011;\n");

        $this->artisan('daleel:import', ['file' => $csv, '--source' => 'Other Directory'])->assertSuccessful();

        $this->assertSame(1, Business::query()->where('name_key', Normalizer::nameKey('Pearl Coffee House'))->count());
        $this->assertTrue(Business::query()->where('name_en', 'New Bakery Doha')->exists());
    }

    public function test_admin_can_upload_map_and_run_an_import(): void
    {
        $admin = $this->makeUser(Permissions::ADMIN);
        $file = new UploadedFile($this->xlsx($this->sampleRows()), 'listings.xlsx', null, null, true);

        $this->actingAs($admin)->get(route('admin.imports.index'))->assertOk();
        $response = $this->actingAs($admin)->post(route('admin.imports.store'), ['file' => $file, 'source_name' => 'Upload test']);
        $batch = ImportBatch::query()->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.imports.show', $batch));
        $this->assertSame('ready', $batch->status);

        $this->actingAs($admin)->get(route('admin.imports.show', $batch))->assertOk()->assertSee('Company Name');

        $this->actingAs($admin)->postJson(route('admin.imports.start', $batch), [
            'mapping' => $batch->mapping, 'source_name' => 'Upload test', 'publish' => true, 'update_existing' => true,
        ])->assertOk();
        $progress = $this->actingAs($admin)->postJson(route('admin.imports.process', $batch))->assertOk()->json();
        $this->assertSame('completed', $progress['status'] ?? $batch->refresh()->status);

        $this->actingAs($admin)->get(route('admin.imports.show', $batch))->assertOk();
        $this->actingAs($admin)->get(route('admin.imports.report', $batch))->assertOk();
    }

    public function test_regular_users_cannot_reach_imports(): void
    {
        $this->actingAs($this->makeUser())->get(route('admin.imports.index'))->assertForbidden();
    }
}
