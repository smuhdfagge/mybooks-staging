<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Services\ExportService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportServicePdfTest extends TestCase
{
    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAuthenticatedUser();
        Storage::fake('exports');
        $this->service = app(ExportService::class);
    }

    public function test_export_to_pdf_creates_pdf_file(): void
    {
        $export = Export::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_PDF,
            'status' => Export::STATUS_PENDING,
        ]);

        $result = $this->service->processExport($export);

        $this->assertTrue($result);
        $export->refresh();
        $this->assertEquals(Export::STATUS_COMPLETED, $export->status);
        $this->assertStringEndsWith('.pdf', $export->filename);
    }

    public function test_export_to_pdf_contains_pdf_magic_bytes(): void
    {
        // Create some customer data so the export has content
        \App\Models\Customer::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $export = Export::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_PDF,
            'status' => Export::STATUS_PENDING,
        ]);

        $this->service->processExport($export);
        $export->refresh();

        $content = Storage::disk('exports')->get($export->file_path);

        // Real PDF files start with the %PDF- magic bytes
        $this->assertStringStartsWith('%PDF-', $content);
    }

    public function test_export_to_pdf_sets_file_size(): void
    {
        $export = Export::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => Export::TYPE_CUSTOMERS,
            'format' => Export::FORMAT_PDF,
            'status' => Export::STATUS_PENDING,
        ]);

        $this->service->processExport($export);
        $export->refresh();

        $this->assertGreaterThan(0, $export->file_size);
    }
}
